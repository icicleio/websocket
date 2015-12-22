<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Socket\Socket;
use Icicle\Stream;
use Icicle\WebSocket\Exception\FrameException;

class Rfc6455Frame implements Frame
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
     * Integer value corresponding to one of the type constants.
     *
     * @var int
     */
    protected $opcode = self::TEXT;

    /**
     * @var string
     */
    protected $data;

    /**
     * @var bool
     */
    protected $final = true;

    /**
     * @var bool
     */
    protected $mask = false;

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\WebSocket\Protocol\Frame
     *
     * @throws \Icicle\WebSocket\Exception\FrameException
     */
    public static function read(Socket $socket, $timeout = 0)
    {
        $buffer = (yield Stream\readTo($socket, 2, $timeout));

        $bytes = unpack('Cflags/Clength', $buffer);

        // This will need to be changed to support compression.
        if (($bytes['flags'] & self::RSV_MASK) !== 0) {
            throw new FrameException('Unsupported extension.');
        }

        $opcode = $bytes['flags'] & self::OPCODE_MASK;
        $final = (bool) ($bytes['flags'] & self::FIN_MASK);

        $mask = (bool) ($bytes['length'] & self::MASK_FLAG_MASK);

        $size = $bytes['length'] & self::LENGTH_MASK;

        if ($size === self::TWO_BYTE_LENGTH_FLAG) {
            $buffer = (yield Stream\readTo($socket, 2, $timeout));

            $bytes = unpack('nlength', $buffer);
            $size = $bytes['length'];

            if ($size < self::TWO_BYTE_LENGTH_FLAG) {
                throw new FrameException('Frame format error.');
            }
        } elseif ($size === self::EIGHT_BYTE_LENGTH_FLAG) {
            $buffer = (yield Stream\readTo($socket, 8, $timeout));

            $bytes = unpack('Nhigh/Nlow', $buffer);
            $size = ($bytes['high'] << 32) | $bytes['low'];

            if ($size < self::TWO_BYTE_MAX_LENGTH) {
                throw new FrameException('Frame format error.');
            }
        }

        // @todo Enforce a max frame size (should frame data be a stream?)

        if ($mask) {
            $buffer = (yield Stream\readTo($socket, 4, $timeout));

            $mask = [];
            for ($i = 0; $i < 4; ++$i) {
                $mask[] = ord($buffer[$i]);
            }
        }

        $buffer = (yield Stream\readTo($socket, $size, $timeout));

        if ($mask) {
            for ($i = 0; $i < $size; ++$i) {
                $buffer[$i] = chr(ord($buffer[$i]) ^ $mask[$i & 0x3]); // $i % 4
            }
        }

        if (($opcode === self::OPCODE_TEXT || $opcode === self::OPCODE_BINARY) && !$final) {
            throw new FrameException('Non-text or non-binary frame must be final.');
        }

        // Text frames received must contain only valid UTF-8.
        if ($opcode === self::OPCODE_TEXT && !preg_match('//u', $buffer)) {
            throw new FrameException('Invalid UTF-8 data received.');
        }

        yield new self($opcode, $buffer, (bool) $mask, $final);
    }

    /**
     * @param int $opcode
     * @param string $data
     * @param bool $mask
     * @param bool $final
     *
     * @throws \Icicle\WebSocket\Exception\FrameException
     */
    public function __construct($opcode, $data = '', $mask = false, $final = true)
    {
        switch ($opcode) {
            case self::CONTINUATION:
            case self::TEXT:
            case self::BINARY:
            case self::CLOSE:
            case self::PING:
            case self::PONG:
                $this->opcode = $opcode;
                break;

            default:
                throw new FrameException('Invalid opcode.');
        }

        $this->data = (string) $data;
        $this->mask = (bool) $mask;
        $this->final = (bool) $final;
    }

    /**
     * Returns the raw string of bytes of the encoded frame.
     *
     * @return  string
     */
    public function encode()
    {
        $byte = $this->opcode;

        if ($this->final) {
            $byte = self::BIT_FIN | $byte;
        }

        $buffer = chr($byte);

        $size = strlen($this->data);

        if ($size < self::TWO_BYTE_LENGTH_FLAG) {
            $length = $size;
        } elseif ($size < self::TWO_BYTE_MAX_LENGTH) {
            $length = self::TWO_BYTE_LENGTH_FLAG;
        } else {
            $length = self::EIGHT_BYTE_LENGTH_FLAG;
        }

        $byte = ($this->mask ? self::MASK_FLAG_MASK : 0) | ($length & self::LENGTH_MASK);

        $buffer .= chr($byte);

        if (self::TWO_BYTE_LENGTH_FLAG === $length) {
            $buffer .= pack('n', $size);
        } elseif (self::EIGHT_BYTE_LENGTH_FLAG === $length) {
            $buffer .= pack('NN', $size >> 32, $size);
        }

        if ($this->mask) {
            $mask = $this->generateMask();
            $position = strlen($buffer);
            $buffer = str_pad($buffer, $position + $size + count($mask));

            foreach ($mask as $value) {
                $buffer[$position++] = chr($value);
            }

            for ($i = 0; $i < $size; ++$i) {
                $buffer[$position++] = chr(ord($this->data[$i]) ^ $mask[$i & 0x3]); // $i % 4
            }

            return $buffer;
        }

        $buffer .= $this->data;

        return $buffer;
    }

    /**
     * @return  string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return  bool
     */
    public function isMasked()
    {
        return $this->mask;
    }

    /**
     * @return  int
     */
    public function getType()
    {
        return $this->opcode;
    }

    /**
     * @return  bool
     */
    public function isFinal()
    {
        return $this->final;
    }

    /**
     * Array of pseudo-random bytes to use for masking data.
     *
     * @return  int[]
     */
    private function generateMask()
    {
        $bytes = random_bytes(self::MASK_LENGTH);

        $mask = [];

        for ($i = 0; $i < self::MASK_LENGTH; ++$i) {
            $mask[$i] = ord($bytes[$i]);
        }

        return $mask;
    }
}
