<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Socket\Socket;
use Icicle\Stream;
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
    const CHUNK_SIZE =          0x1000;

    /**
     * {@inheritdoc}
     */
    public function read(Socket $socket, $maxSize, $timeout = 0)
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

        if ($size > $maxSize) {
            throw new PolicyException('Frame size exceeded max allowed size.');
        }

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

        yield new Frame($opcode, $buffer, (bool) $mask, $final);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Frame $frame, Socket $socket, $timeout = 0)
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

        if ($frame->isMasked()) {
            $byte |= self::MASK_FLAG_MASK;
        }

        $buffer .= chr($byte);

        if (self::TWO_BYTE_LENGTH_FLAG === $length) {
            $buffer .= pack('n', $size);
        } elseif (self::EIGHT_BYTE_LENGTH_FLAG === $length) {
            $buffer .= pack('NN', $size >> 32, $size);
        }

        if ($frame->isMasked()) {
            $mask = $this->generateMask();

            foreach ($mask as $value) {
                $buffer .= chr($value);
            }

            $written = (yield $socket->write($buffer, $timeout));

            $remaining = $size;
            $position = 0;

            $data = $frame->getData();

            while (0 < $remaining) {
                $buffer = str_repeat("\0", min($remaining, self::CHUNK_SIZE));

                for ($i = 0; $position < $remaining && $i < self::CHUNK_SIZE; ++$i, ++$position) {
                    $buffer[$i] = chr(ord($data[$position]) ^ $mask[$position & 0x3]); // $position % 4
                }

                $remaining -= $i;

                $written += (yield $socket->write($buffer, $timeout));
            }

            yield $written;
            return;
        }

        $written = (yield $socket->write($buffer, $timeout));
        yield $written + (yield $socket->write($frame->getData(), $timeout));
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
