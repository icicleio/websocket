<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Socket\Socket;
use Icicle\Stream;
use Icicle\Stream\Structures\Buffer;
use Icicle\WebSocket\Exception\FrameException;
use Icicle\WebSocket\Exception\PolicyException;

class Rfc6455Transporter implements Transporter
{
    const BIT_FIN =             0x80;
    const BIT_RSV1 =            0x40;
    const BIT_RSV2 =            0x20;
    const BIT_RSV3 =            0x10;

    const OPCODE_MASK =         0x0f;

    /* Frame opcodes */
    const OPCODE_CONTINUATION = 0x0;
    const OPCODE_TEXT =         0x1;
    const OPCODE_BINARY =       0x2;
    const OPCODE_CLOSE =        0x8;
    const OPCODE_PING =         0x9;
    const OPCODE_PONG =         0xa;

    const FIN_MASK =            0x80;
    const RSV_MASK =            0x70;
    const MASK_FLAG_MASK =      0x80;
    const LENGTH_MASK =         0x7f;

    const TWO_BYTE_LENGTH_FLAG =    0x7e;
    const EIGHT_BYTE_LENGTH_FLAG =  0x7f;
    const TWO_BYTE_MAX_LENGTH =     0xffff;

    const MASK_LENGTH =         4;

    /**
     * @var \Icicle\Socket\Socket
     */
    private $socket;

    /**
     * @var bool
     */
    private $masked;

    /**
     * @param \Icicle\Socket\Socket $socket
     * @param bool $masked True if received frames should be masked
     */
    public function __construct(Socket $socket, $masked)
    {
        $this->socket = $socket;
        $this->masked = (bool) $masked;
    }

    /**
     * {@inheritdoc}
     */
    public function read($maxSize, $timeout = 0)
    {
        $buffer = new Buffer();

        try {
            do {
                $buffer->push(yield $this->socket->read(0, null, $timeout));
            } while ($buffer->getLength() < 2);

            $bytes = unpack('Cflags/Clength', $buffer->shift(2));

            // @todo This will need to be changed to support compression.
            if (($bytes['flags'] & self::RSV_MASK) !== 0) {
                throw new FrameException('Unsupported extension.');
            }

            $opcode = $bytes['flags'] & self::OPCODE_MASK;
            $final = (bool) ($bytes['flags'] & self::FIN_MASK);

            $masked = (bool) ($bytes['length'] & self::MASK_FLAG_MASK);
            $size = $bytes['length'] & self::LENGTH_MASK;

            if ($masked === $this->masked) {
                throw new FrameException(
                    sprintf('Received %s frame.', $masked ? 'masked' : 'unmasked')
                );
            }

            if ($size === self::TWO_BYTE_LENGTH_FLAG) {
                while ($buffer->getLength() < 2) {
                    $buffer->push(yield $this->socket->read(0, null, $timeout));
                }

                $bytes = unpack('nlength', $buffer->shift(2));
                $size = $bytes['length'];

                if ($size < self::TWO_BYTE_LENGTH_FLAG) {
                    throw new FrameException('Frame format error.');
                }
            } elseif ($size === self::EIGHT_BYTE_LENGTH_FLAG) {
                while ($buffer->getLength() < 8) {
                    $buffer .= (yield $this->socket->read(0, null, $timeout));
                }

                $bytes = unpack('Nhigh/Nlow', $buffer->shift(8));
                $size = ($bytes['high'] << 32) | $bytes['low'];

                if ($size < self::TWO_BYTE_MAX_LENGTH) {
                    throw new FrameException('Frame format error.');
                }
            }

            if ($size > $maxSize) {
                throw new PolicyException('Frame size exceeded max allowed size.');
            }

            if ($masked) {
                while ($buffer->getLength() < self::MASK_LENGTH) {
                    $buffer->push(yield $this->socket->read(0, null, $timeout));
                }

                $mask = $buffer->shift(self::MASK_LENGTH);
            }

            while ($buffer->getLength() < $size) {
                $buffer->push(yield $this->socket->read(0, null, $timeout));
            }

            $data = $buffer->shift($size);

            if ($masked) {
                $data ^= str_repeat($mask, (int) (($size + self::MASK_LENGTH - 1) / self::MASK_LENGTH));
            }

            if (($opcode === self::OPCODE_TEXT || $opcode === self::OPCODE_BINARY) && !$final) {
                throw new FrameException('Non-text or non-binary frame must be final.');
            }

            yield new Frame($opcode, $data, $masked, $final);
        } finally {
            if (!$buffer->isEmpty()) {
                $this->socket->unshift((string) $buffer);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send(Frame $frame, $timeout = 0)
    {
        $byte = $frame->getType();

        if ($frame->isFinal()) {
            $byte = self::BIT_FIN | $byte;
        }

        $buffer = chr($byte);

        $size = $frame->getSize();

        if ($size < self::TWO_BYTE_LENGTH_FLAG) {
            $length = $size;
        } elseif ($size < self::TWO_BYTE_MAX_LENGTH) {
            $length = self::TWO_BYTE_LENGTH_FLAG;
        } else {
            $length = self::EIGHT_BYTE_LENGTH_FLAG;
        }

        $byte = $length;

        if ($this->masked) {
            $byte |= self::MASK_FLAG_MASK;
        }

        $buffer .= chr($byte);

        if (self::TWO_BYTE_LENGTH_FLAG === $length) {
            $buffer .= pack('n', $size);
        } elseif (self::EIGHT_BYTE_LENGTH_FLAG === $length) {
            $buffer .= pack('NN', $size >> 32, $size);
        }

        $data = $frame->getData();

        if ($this->masked) {
            $mask = random_bytes(self::MASK_LENGTH);
            $buffer .= $mask;
            $data ^= str_repeat($mask, (int) (($size + self::MASK_LENGTH - 1) / self::MASK_LENGTH));
        }

        $written = (yield $this->socket->write($buffer, $timeout));
        yield $written + (yield $this->socket->write($data, $timeout));
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen()
    {
        return $this->socket->isOpen();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return $this->socket->close();
    }

    /**
     * {@inheritdoc}
     */
    public function getLocalAddress()
    {
        return $this->socket->getLocalAddress();
    }

    /**
     * {@inheritdoc}
     */
    public function getLocalPort()
    {
        return $this->socket->getLocalPort();
    }

    /**
     * {@inheritdoc}
     */
    public function getRemoteAddress()
    {
        return $this->socket->getRemoteAddress();
    }

    /**
     * {@inheritdoc}
     */
    public function getRemotePort()
    {
        return $this->socket->getRemotePort();
    }

    /**
     * {@inheritdoc}
     */
    public function isCryptoEnabled()
    {
        return $this->socket->isCryptoEnabled();
    }
}
