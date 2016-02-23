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
    public function __construct(string $data, bool $binary = false)
    {
        $this->data = $data;
        $this->binary = $binary;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @return bool
     */
    public function isBinary(): bool
    {
        return $this->binary;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->data;
    }
}
