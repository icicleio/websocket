<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Coroutine;
use Icicle\Http\Message\Message as HttpMessage;
use Icicle\Observable\Emitter;
use Icicle\Loop;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Connection;
use Icicle\WebSocket\Exception\ProtocolException;
use Icicle\WebSocket\Message;

class Rfc6455Connection implements Connection
{
    const DEFAULT_TIMEOUT = 10;
    const DEFAULT_INACTIVITY_TIMEOUT = 60;

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
     * @var \Icicle\Observable\Observable
     */
    private $observable;

    /**
     * @var \Icicle\Loop\Watcher\Timer
     */
    private $ping;

    /**
     * @var \Icicle\Loop\Watcher\Timer|null
     */
    private $pong;

    /**
     * @var string|null
     */
    private $expected;

    /**
     * @var bool
     */
    private $closed = false;

    /**
     * @var float|int
     */
    private $timeout = self::DEFAULT_TIMEOUT;

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
        Socket $socket,
        HttpMessage $message,
        $mask,
        $subProtocol,
        array $extensions,
        array $options = []
    ) {
        $this->timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;

        $interval = isset($options['inactivity_timeout'])
            ? (float) $options['inactivity_timeout']
            : self::DEFAULT_INACTIVITY_TIMEOUT;

        $callback = Coroutine\wrap(function () {
            try {
                yield $this->ping($this->expected = base64_encode(random_bytes(3)));

                $callback = Coroutine\wrap(function () {
                    try {
                        yield $this->close(self::CLOSE_VIOLATION);
                    } catch (\Exception $exception) {
                        $this->socket->close();
                    }
                });

                $this->pong = Loop\timer($this->timeout, $callback);
                $this->pong->unreference();
            } catch (\Exception $exception) {
                $this->socket->close();
            }
        });

        $this->ping = Loop\periodic($interval, $callback);
        $this->ping->unreference();

        $this->transporter = $transporter;
        $this->socket = $socket;
        $this->message = $message;
        $this->subProtocol = $subProtocol;
        $this->extensions = $extensions;
        $this->mask = (bool) $mask;

        $this->observable = new Emitter(function (callable $emit) {
            /** @var \Icicle\WebSocket\Protocol\Frame[] $frames */
            $frames = [];
            $size = 0;

            while ($this->socket->isReadable()) {
                /** @var \Icicle\WebSocket\Protocol\Frame $frame */
                $frame = (yield $this->transporter->read($this->socket));

                if ($frame->isMasked() === $this->mask) {
                    throw new ProtocolException(
                        sprintf('Received %s frame.', $frame->isMasked() ? 'masked' : 'unmasked')
                    );
                }

                $this->ping->again();

                switch ($type = $frame->getType()) {
                    case Frame::CLOSE: // Close connection.
                        if (!$this->closed) { // Respond with close frame if one has not been sent.
                            yield $this->close(self::CLOSE_NORMAL);
                        }

                        $data = $frame->getData();

                        if (2 > strlen($data)) {
                            yield self::CLOSE_NO_STATUS;
                            return;
                        }

                        $bytes = unpack('Scode', substr($data, 0, 2));
                        $data = (string) substr($data, 2);

                        yield $bytes['code']; // @todo Return object with data section.
                        return;

                    case Frame::PING: // Respond with pong frame.
                        yield $this->pong($frame->getData());
                        continue;

                    case Frame::PONG: // Cancel timeout set by sending ping frame.
                        if (null === $this->pong) {
                            yield $this->close(self::CLOSE_PROTOCOL);
                            continue;
                        }

                        $this->pong->stop();
                        $this->pong = null;

                        if ($frame->getData() !== $this->expected) {
                            yield $this->close(self::CLOSE_VIOLATION);
                        }

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
                        yield $this->close(self::CLOSE_PROTOCOL);
                        throw new ProtocolException('Received unrecognized frame type.');
                }
            }

            yield self::CLOSE_ABNORMAL;
        });
    }

    public function __destruct()
    {
        $this->ping->stop();

        if (null !== $this->pong) {
            $this->pong->stop();
        }
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
    public function read()
    {
        return $this->observable;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Message $message)
    {
        $frame = new Frame($message->isBinary() ? Frame::BINARY : Frame::TEXT, $message->getData(), $this->mask);
        yield $this->transporter->send($frame, $this->socket, $this->timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function close($code = self::CLOSE_NORMAL)
    {
        $this->closed = true;

        $frame = new Frame(Frame::CLOSE, pack('S', (int) $code), $this->mask);
        yield $this->transporter->send($frame, $this->socket, $this->timeout);
    }

    /**
     * Sends a ping frame on the connection with the given data.
     *
     * @coroutine
     *
     * @param string $data
     *
     * @return \Generator
     *
     * @resolve int
     */
    protected function ping($data = '')
    {
        $frame = new Frame(Frame::PING, $data, $this->mask);
        return $this->transporter->send($frame, $this->socket, $this->timeout);
    }

    /**
     * Sends a pong frame on the connection with the given data.
     *
     * @coroutine
     *
     * @param string $data
     *
     * @return \Generator
     *
     * @resolve int
     */
    protected function pong($data = '')
    {
        $frame = new Frame(Frame::PONG, $data, $this->mask);
        return $this->transporter->send($frame, $this->socket, $this->timeout);
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
