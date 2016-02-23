<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\WebSocket\Exception\FrameException;

class Rfc6455Frame
{
    const CONTINUATION = 0x0;
    const TEXT =         0x1;
    const BINARY =       0x2;
    const CLOSE =        0x8;
    const PING =         0x9;
    const PONG =         0xa;

    const RSV1 =         0x4;
    const RSV2 =         0x2;
    const RSV3 =         0x1;

    /**
     * Integer value corresponding to one of the type constants.
     *
     * @var int
     */
    private $opcode = self::TEXT;

    /**
     * @var string
     */
    private $data;

    /**
     * @var bool
     */
    private $final;

    /**
     * @var int
     */
    private $rsv;

    /**
     * @param int $opcode
     * @param string $data
     * @param int $rsv
     * @param bool $final
     *
     * @throws \Icicle\WebSocket\Exception\FrameException
     */
    public function __construct(int $opcode, string $data = '', int $rsv = 0, bool $final = true)
    {
        $this->data = $data;
        $this->final = $final;
        $this->rsv = $rsv;

        switch ($opcode) {
            case self::BINARY:
            case self::CLOSE:
            case self::PING:
            case self::PONG:
                if (!$this->final) {
                    throw new FrameException('Non-text or non-binary frame must be final.');
                }
                // No break.

            case self::CONTINUATION:
            case self::TEXT:
                $this->opcode = $opcode;
                break;

            default:
                throw new FrameException('Invalid opcode.');
        }
    }

    /**
     * @return  string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return strlen($this->data);
    }

    /**
     * @return  int
     */
    public function getType(): int
    {
        return $this->opcode;
    }

    /**
     * @return  bool
     */
    public function isFinal(): bool
    {
        return $this->final;
    }

    /**
     * @return int
     */
    public function getRsv(): int
    {
        return $this->rsv;
    }

    /**
     * @return bool
     */
    public function getRsv1(): bool
    {
        return (bool) $this->rsv & self::RSV1;
    }

    /**
     * @return bool
     */
    public function getRsv2(): bool
    {
        return (bool) $this->rsv & self::RSV2;
    }

    /**
     * @return bool
     */
    public function getRsv3(): bool
    {
        return (bool) $this->rsv & self::RSV3;
    }
}
