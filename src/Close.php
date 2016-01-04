<?php
namespace Icicle\WebSocket;

class Close
{
    const NORMAL =        1000;
    const GOING_AWAY =    1001;
    const PROTOCOL =      1002;
    const BAD_DATA =      1003;
    const NO_STATUS =     1005;
    const ABNORMAL =      1006;
    const INVALID_DATA =  1007;
    const VIOLATION =     1008;
    const TOO_BIG =       1009;
    const EXTENSION =     1010;
    const SERVER_ERROR =  1011;
    const TLS_ERROR =     1015;

    /**
     * @var int
     */
    private $code;

    /**
     * @var string
     */
    private $data;

    /**
     * @param int $code
     * @param string $data
     */
    public function __construct($code = self::NORMAL, $data = '')
    {
        $this->code = (int) $code;
        $this->data = (string) $data;
    }

    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->data;
    }
}
