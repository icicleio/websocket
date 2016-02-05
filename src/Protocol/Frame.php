<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\WebSocket\Exception\FrameException;

class Frame
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
    private $final = true;

    /**
     * @param int $opcode
     * @param string $data
     * @param bool $mask
     * @param bool $final
     *
     * @throws \Icicle\WebSocket\Exception\FrameException
     */
    public function __construct($opcode, $data = '', $final = true)
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
        $this->final = (bool) $final;
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
}
