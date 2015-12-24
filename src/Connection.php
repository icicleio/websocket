<?php
namespace Icicle\WebSocket;

use Icicle\Http\Message\Message as HttpMessage;
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
    private $protocol;

    /**
     * @var string
     */
    private $subProtocol;

    /**
     * @var \Icicle\Http\Message\Message
     */
    private $message;

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
     * @param \Icicle\WebSocket\Protocol\Protocol $protocol
     * @param \Icicle\Http\Message\Message $message
     * @param string $subProtocol
     * @param string[] $extensions
     * @param bool $mask
     */
    public function __construct(
        Socket $socket,
        Protocol $protocol,
        HttpMessage $message,
        $subProtocol,
        array $extensions,
        $mask = false
    ) {
        $this->socket = $socket;
        $this->protocol = $protocol;
        $this->message = $message;
        $this->subProtocol = (string) $subProtocol;
        $this->extensions = $extensions;
        $this->mask = (bool) $mask;

        $this->observable = $this->protocol->read($this->socket, $this->mask);
    }

    /**
     * @return string
     */
    public function getSubProtocol()
    {
        return $this->subProtocol;
    }

    /**
     * @return string[]
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * @return \Icicle\Http\Message\Message
     */
    public function getMessage()
    {
        return $this->message;
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
        return $this->protocol->send($message, $this->socket, $this->mask, $timeout);
    }

    /**
     * @coroutine
     *
     * @param float|int $timeout
     * @param int $code
     *
     * @return \Generator
     *
     * @resolve int Close status received from the endpoint.
     */
    public function close($timeout = 0, $code = Protocol::CLOSE_NORMAL)
    {
        return $this->protocol->close($this->socket, $this->mask, $code, $timeout);
    }
}
