<?php
namespace Icicle\WebSocket;

use Icicle\Http\Message\Uri;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Protocol\Protocol;

class Connection
{
    /**
     * @var \Icicle\Socket\Socket
     */
    private $socket;

    /**
     * @var \Icicle\WebSocket\Protocol\Protocol
     */
    private $socketProtocol;

    /**
     * @var string
     */
    private $connectionProtocol;

    /**
     * @var \Icicle\Http\Message\Uri
     */
    private $uri;

    /**
     * @var string[]
     */
    private $extensions;

    /**
     * @var bool
     */
    private $mask;

    /**
     * @var \Icicle\Observable\Observable
     */
    private $observable;

    /**
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\WebSocket\Protocol\Protocol $socketProtocol
     * @param \Icicle\Http\Message\Uri $uri
     * @param string $connectionProtocol
     * @param string[] $extensions
     * @param bool $mask
     */
    public function __construct(
        Socket $socket,
        Protocol $socketProtocol,
        Uri $uri,
        $connectionProtocol,
        array $extensions,
        $mask = false
    ) {
        $this->socket = $socket;
        $this->socketProtocol = $socketProtocol;
        $this->uri = $uri;
        $this->connectionProtocol = (string) $connectionProtocol;
        $this->extensions = $extensions;
        $this->mask = (bool) $mask;

        $this->observable = $this->socketProtocol->read($this->socket, $this->mask);
    }

    /**
     * @return string
     */
    public function getProtocol()
    {
        return $this->connectionProtocol;
    }

    /**
     * @return string[]
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * @return \Icicle\Http\Message\Uri
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @return bool
     */
    public function isOpen()
    {
        return $this->socket->isWritable();
    }

    /**
     * @return \Icicle\Observable\Observable
     */
    public function read()
    {
        return $this->observable;
    }

    /**
     * @coroutine
     *
     * @param \Icicle\WebSocket\Message $message
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve int Number of bytes sent.
     */
    public function send(Message $message, $timeout = 0)
    {
        return $this->socketProtocol->send($message, $this->socket, $this->mask, $timeout);
    }

    /**
     * @coroutine
     *
     * @param string $data
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve int Number of bytes sent.
     */
    public function close($data = '', $timeout = 0)
    {
        return $this->socketProtocol->close($this->socket, $this->mask, $data, $timeout);
    }
}
