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
    public function __construct($opcode, $data = '', $rsv = 0, $final = true)
    {
        $this->data = (string) $data;
        $this->final = (bool) $final;
        $this->rsv = (int) $rsv;

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
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return strlen($this->data);
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
     * @return int
     */
    public function getRsv()
    {
        return $this->rsv;
    }

    /**
     * @return bool
     */
    public function getRsv1()
    {
        return (bool) $this->rsv & 4;
    }

    /**
     * @return bool
     */
    public function getRsv2()
    {
        return (bool) $this->rsv & 2;
    }

    /**
     * @return bool
     */
    public function getRsv3()
    {
        return (bool) $this->rsv & 1;
    }
}
