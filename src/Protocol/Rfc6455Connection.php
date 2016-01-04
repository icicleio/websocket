<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Coroutine;
use Icicle\Http\Message\Message as HttpMessage;
use Icicle\Observable\Emitter;
use Icicle\Loop;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Close;
use Icicle\WebSocket\Connection;
use Icicle\WebSocket\Exception\ProtocolException;
use Icicle\WebSocket\Message;

class Rfc6455Connection implements Connection
{
    const DEFAULT_TIMEOUT = 5;
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
     * @var \Icicle\Observable\Observable|null
     */
    private $observable;

    /**
     * @var bool
     */
    private $closed = false;

    /**
     * @var float|int
     */
    private $timeout = self::DEFAULT_TIMEOUT;

    /**
     * @var float|int
     */
    private $interval = self::DEFAULT_INACTIVITY_TIMEOUT;

    /**
     * @param \Icicle\WebSocket\Protocol\Transporter $transporter
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Message $message
     * @param bool $mask
     * @param string $subProtocol
     * @param string[] $extensions
     * @param mixed[] $options
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

        $this->interval = isset($options['inactivity_timeout'])
            ? (float) $options['inactivity_timeout']
            : self::DEFAULT_INACTIVITY_TIMEOUT;

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
    public function read()
    {
        return $this->observable = $this->observable ?: new Emitter(function (callable $emit) {
            /** @var \Icicle\WebSocket\Protocol\Frame[] $frames */
            $frames = [];
            $size = 0;

            $ping = Loop\periodic($this->interval, Coroutine\wrap(function () use (&$pong, &$expected) {
                try {
                    yield $this->ping($expected = base64_encode(random_bytes(3)));

                    $pong = Loop\timer($this->timeout, Coroutine\wrap(function () {
                        try {
                            yield $this->close(Close::VIOLATION);
                        } catch (\Exception $exception) {
                            $this->observable->dispose(
                                new ProtocolException('Could not write close frame after ping timeout.')
                            );
                        }
                    }));
                    $pong->unreference();
                } catch (\Exception $exception) {
                    $this->observable->dispose(new ProtocolException('Could not send ping frame.'));
                }
            }));
            $ping->unreference();

            try {
                while ($this->socket->isReadable()) {
                    /** @var \Icicle\WebSocket\Protocol\Frame $frame */
                    $frame = (yield $this->transporter->read($this->socket));

                    if ($frame->isMasked() === $this->mask) {
                        throw new ProtocolException(
                            sprintf('Received %s frame.', $frame->isMasked() ? 'masked' : 'unmasked')
                        );
                    }

                    $ping->again();

                    switch ($type = $frame->getType()) {
                        case Frame::CLOSE: // Close connection.
                            if (!$this->closed) { // Respond with close frame if one has not been sent.
                                yield $this->close(Close::NORMAL);
                            }

                            $data = $frame->getData();

                            if (2 > strlen($data)) {
                                yield new Close(Close::NO_STATUS);
                                return;
                            }

                            $bytes = unpack('Scode', substr($data, 0, 2));
                            yield new Close($bytes['code'], (string) substr($data, 2));
                            return;

                        case Frame::PING: // Respond with pong frame.
                            yield $this->pong($frame->getData());
                            continue;

                        case Frame::PONG: // Cancel timeout set by sending ping frame.
                            if (null === $pong) {
                                continue; // Unsolicited pong frame, just continue.
                            }

                            $pong->stop();
                            $pong = null;

                            if ($frame->getData() !== $expected) {
                                throw new ProtocolException('Pong frame data did not match ping frame data.');
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

                            $data = '';

                            for ($i = $count; $i >= 0; --$i) {
                                $frame = $frames[$i];
                                $data = $frame->getData() . $data;
                            }

                            $frames = [];

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
            } catch (ProtocolException $exception) {
                $code = Close::PROTOCOL;
                yield $this->close($code);
                yield new Close($code);
                return;
            } catch (\Exception $exception) {
                if ($this->socket->isWritable()) {
                    yield $this->close(Close::SERVER_ERROR);
                }
                throw $exception;
            } finally {
                $ping->stop();
                $ping = null;

                if (null !== $pong) {
                    $pong->stop();
                    $pong = null;
                }
            }

            yield new Close(Close::ABNORMAL);
        });
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
    public function close($code = Close::NORMAL, $data = '')
    {
        $this->closed = true;

        $frame = new Frame(Frame::CLOSE, pack('S', (int) $code) . $data, $this->mask);
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
        yield $this->transporter->send($frame, $this->socket, $this->timeout);
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
        yield $this->transporter->send($frame, $this->socket, $this->timeout);
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
