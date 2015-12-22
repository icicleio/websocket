<?php
namespace Icicle\WebSocket;

class Message
{
    /**
     * @var string
     */
    private $data;

    /**
     * @var bool
     */
    private $binary = false;

    /**
     * @param string $data
     * @param bool $binary
     */
    public function __construct($data, $binary = false)
    {
        $this->data = (string) $data;
        $this->binary = (bool) $binary;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return bool
     */
    public function isBinary()
    {
        return $this->binary;
    }
}
