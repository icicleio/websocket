<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\Message as HttpMessage;
use Icicle\Observable\Emitter;
use Icicle\Socket\Socket as NetworkSocket;
use Icicle\WebSocket\Connection;
use Icicle\WebSocket\Exception\ProtocolException;
use Icicle\WebSocket\Message;

class Rfc6455Connection implements Connection
{
    /**
     * @var \Icicle\Socket\Socket
     */
    private $socket;

    /**
     * @var bool
     */
    private $mask;

    /**
     * @var \Icicle\WebSocket\Protocol\Transporter
     */
    private $transporter;

    /**
     * @var \Icicle\Http\Message\Message
     */
    private $message;

    /**
     * @var string
     */
    private $subProtocol;

    /**
     * @var string[]
     */
    private $extensions;

    /**
     * @var bool
     */
    private $closed = false;

    /**
     * @param \Icicle\WebSocket\Protocol\Transporter $transporter
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Message
     * @param bool $mask
     * @param string $subProtocol
     * @param string[] $extensions
     */
    public function __construct(
        Transporter $transporter,
        NetworkSocket $socket,
        HttpMessage $message,
        $mask,
        $subProtocol,
        array $extensions
    ) {
        $this->transporter = $transporter;
        $this->socket = $socket;
        $this->message = $message;
        $this->subProtocol = $subProtocol;
        $this->extensions = $extensions;
        $this->mask = (bool) $mask;
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen()
    {
        return $this->socket->isOpen() && !$this->closed;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubProtocol()
    {
        return $this->subProtocol;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * {@inheritdoc}
     */
    public function read($timeout = 0)
    {
        return new Emitter(function (callable $emit) use ($timeout) {
            /** @var \Icicle\WebSocket\Protocol\Frame[] $frames */
            $frames = [];
            $size = 0;

            while ($this->socket->isReadable()) {
                /** @var \Icicle\WebSocket\Protocol\Frame $frame */
                $frame = (yield $this->transporter->read($this->socket, $timeout));

                if ($frame->isMasked() === $this->mask) {
                    throw new ProtocolException(sprintf('Received %s frame.', $mask ? 'masked' : 'unmasked'));
                }

                switch ($type = $frame->getType()) {
                    case Frame::CLOSE: // Close connection.
                        $data = $frame->getData();

                        if (!$this->closed) {
                            $this->closed = true;
                            $frame = new Frame(Frame::CLOSE, pack('S', self::CLOSE_NORMAL), $this->mask);
                            yield $this->transporter->send($frame, $this->socket, $timeout);
                        }

                        if (2 > strlen($data)) {
                            yield self::CLOSE_NO_STATUS;
                            return;
                        }

                        $bytes = unpack('Scode', substr($data, 0, 2));
                        $data = (string) substr($data, 2);

                        yield $bytes['code']; // @todo Return object with data section.
                        return;

                    case Frame::PING: // Respond with pong frame.
                        $frame = new Frame(Frame::PONG, $frame->getData(), $this->mask);
                        yield $this->transporter->send($frame, $this->socket, $timeout);
                        continue;

                    case Frame::PONG: // Cancel timeout set by sending ping frame.
                        // @todo Handle pong received after sending ping.
                        continue;

                    case Frame::CONTINUATION:
                        $count = count($frames);

                        if (0 === $count) {
                            throw new ProtocolException('Received orphan continuation frame.');
                        }

                        // @todo Enforce max $size and frame $count.

                        $size += $frame->getSize();
                        $frames[] = $frame;

                        if (!$frame->isFinal()) {
                            continue;
                        }

                        $data = $frame->getData();

                        while (!empty($frames)) {
                            $frame = array_pop($frames);
                            $data = $frame->getData() . $data;
                        }

                        yield $emit(new Message($data, $frame->getType() === Frame::BINARY));
                        continue;

                    case Frame::TEXT:
                    case Frame::BINARY:
                        if (!empty($frames)) {
                            throw new ProtocolException('Expected continuation data frame.');
                        }

                        if (!$frame->isFinal()) {
                            $size = $frame->getSize();
                            $frames[] = $frame;
                            continue;
                        }

                        yield $emit(new Message($frame->getData(), $type === Frame::BINARY));
                        continue;

                    default:
                        throw new ProtocolException('Received unrecognized frame type.');
                }
            }

            yield self::CLOSE_ABNORMAL;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function send(Message $message, $timeout = 0)
    {
        $frame = new Frame($message->isBinary() ? Frame::BINARY : Frame::TEXT, $message->getData(), $this->mask);
        yield $this->transporter->send($frame, $this->socket, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function close($code = self::CLOSE_NORMAL, $timeout = 0)
    {
        $this->closed = true;

        $frame = new Frame(Frame::CLOSE, pack('S', (int) $code), $this->mask);
        yield $this->transporter->send($frame, $this->socket, $timeout);
    }

    /**
     * @return string
     */
    public function getLocalAddress()
    {
        return $this->socket->getLocalAddress();
    }

    /**
     * @return int
     */
    public function getLocalPort()
    {
        return $this->socket->getLocalPort();
    }

    /**
     * @return string
     */
    public function getRemoteAddress()
    {
        return $this->socket->getRemoteAddress();
    }

    /**
     * @return int
     */
    public function getRemotePort()
    {
        return $this->socket->getRemotePort();
    }

    /**
     * @return bool
     */
    public function isCryptoEnabled()
    {
        return $this->socket->isCryptoEnabled();
    }
}
