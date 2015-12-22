<?php
namespace Icicle\WebSocket;

use Icicle\Coroutine\Coroutine;
use Icicle\Http\Message\Uri;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Exception\ProtocolException;
use Icicle\WebSocket\Protocol\Frame;
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
     * @coroutine
     *
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\WebSocket\Message|null
     *
     * @throws \Icicle\WebSocket\Exception\ProtocolException
     */
    public function read($timeout = 0)
    {
        while ($this->socket->isOpen()) {
            /** @var \Icicle\WebSocket\Protocol\Frame $frame */
            $frame = (yield $this->socketProtocol->readFrame($this->socket, $timeout));

            if ($this->mask === $frame->isMasked()) {
                throw new ProtocolException(sprintf('Received %s frame.', $this->mask ? 'masked' : 'unmasked'));
            }

            switch ($type = $frame->getType()) {
                case Frame::CLOSE: // Close connection.
                    // @todo Do not send close frame if close frame was already sent.
                    $frame = $this->socketProtocol->createFrame(Frame::CLOSE, '', $this->mask);
                    yield $this->socketProtocol->sendFrame($frame, $this->socket, $timeout);
                    $this->socket->close();
                    yield null; // @todo Resolve with close message.
                    return;

                case Frame::PING: // Respond with pong frame.
                    $frame = $this->socketProtocol->createFrame(Frame::PONG, '', $this->mask);
                    yield $this->socketProtocol->sendFrame($frame, $this->socket, $timeout);
                    continue;

                case Frame::PONG: // Cancel timeout set by sending ping frame.
                    // @todo Handle pong received after sending ping.
                    continue;

                default: // Emit message.
                    // @todo Collect multiple frames into messages.
                    yield new Message($frame->getData(), $type === Frame::BINARY);
                    return;
            }
        }
    }

    /**
     * @param \Icicle\WebSocket\Message $message
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve int Number of bytes sent.
     */
    public function send(Message $message, $timeout = 0)
    {
        $frame = $this->socketProtocol->createFrame(
            $message->isBinary() ? Frame::BINARY : Frame::TEXT,
            $message->getData(),
            $this->mask,
            true
        );

        yield $this->socketProtocol->sendFrame($frame, $this->socket, $timeout);
    }

    /**
     * @param string $data
     * @param int $timeout
     *
     * @return \Generator
     *
     * @resolve null
     */
    public function close($data = '', $timeout = 0)
    {
        try {
            if ($this->socket->isWritable()) {
                $frame = $this->socketProtocol->createFrame(Frame::CLOSE, $data, $this->mask);
                yield $this->socketProtocol->sendFrame($frame, $this->socket, $timeout);

                /** @var \Icicle\WebSocket\Protocol\Frame $frame */
                $frame = (yield $this->socketProtocol->readFrame($this->socket, $timeout));
                yield $frame->getData();
            }
        } finally {
            $this->socket->close();
        }
    }
}
