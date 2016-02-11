<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Coroutine;
use Icicle\Observable\Emitter;
use Icicle\Loop;
use Icicle\Socket\Exception\Exception as SocketException;
use Icicle\Stream\Exception\Exception as StreamException;
use Icicle\WebSocket\Close;
use Icicle\WebSocket\Connection;
use Icicle\WebSocket\Exception\ConnectionException;
use Icicle\WebSocket\Exception\DataException;
use Icicle\WebSocket\Exception\PolicyException;
use Icicle\WebSocket\Exception\ProtocolException;
use Icicle\WebSocket\Message;
use Icicle\WebSocket\Protocol\Rfc6455Frame as Frame;
use Icicle\WebSocket\Protocol\Rfc6455Transporter as Transporter;

class Rfc6455Connection implements Connection
{
    const DEFAULT_TIMEOUT = 5;
    const DEFAULT_INACTIVITY_TIMEOUT = 60;
    const DEFAULT_MAX_MESSAGE_SIZE = 0x100000; // 1 MB
    const DEFAULT_MAX_FRAME_COUNT = 128;

    /**
     * @var \Icicle\WebSocket\Protocol\Rfc6455Transporter
     */
    private $transporter;

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
     * @param \Icicle\WebSocket\Protocol\Rfc6455Transporter $transporter
     * @param string $subProtocol
     * @param string[] $extensions
     * @param mixed[] $options
     */
    public function __construct(
        Transporter $transporter,
        $subProtocol,
        array $extensions,
        array $options = []
    ) {
        $this->timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;

        $interval = isset($options['inactivity_timeout'])
            ? (float) $options['inactivity_timeout']
            : self::DEFAULT_INACTIVITY_TIMEOUT;

        $maxMessageSize = isset($options['max_message_size'])
            ? (int) $options['max_frame_size']
            : self::DEFAULT_MAX_MESSAGE_SIZE;

        $maxFrameCount = isset($options['max_frame_count'])
            ? (int) $options['max_frame_count']
            : self::DEFAULT_MAX_FRAME_COUNT;

        $this->transporter = $transporter;
        $this->subProtocol = $subProtocol;
        $this->extensions = $extensions;

        $this->observable = $this->createObservable($interval, $maxMessageSize, $maxFrameCount);
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen()
    {
        return $this->transporter->isOpen() && !$this->closed;
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
     * @param float $interval
     * @param int $maxSize
     * @param int $maxFrames
     *
     * @return \Icicle\Observable\Observable
     */
    private function createObservable($interval, $maxSize, $maxFrames)
    {
        return new Emitter(function (callable $emit) use ($interval, $maxSize, $maxFrames) {
            /** @var \Icicle\WebSocket\Protocol\Rfc6455Frame[] $frames */
            $frames = [];
            $size = 0;

            $ping = Loop\periodic($interval, Coroutine\wrap(function () use (&$pong, &$expected) {
                try {
                    yield $this->ping($expected = base64_encode(random_bytes(3)));

                    if (null === $pong) {
                        $pong = Loop\timer($this->timeout, Coroutine\wrap(function () {
                            yield $this->close(Close::VIOLATION);
                            $this->transporter->close();
                        }));
                        $pong->unreference();
                    } else {
                        $pong->again();
                    }
                } catch (\Exception $exception) {
                    $this->transporter->close();
                }
            }));
            $ping->unreference();

            try {
                while ($this->transporter->isOpen()) {
                    /** @var \Icicle\WebSocket\Protocol\Rfc6455Frame $frame */
                    $frame = (yield $this->transporter->read($maxSize - $size));

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

                            $bytes = unpack('ncode', substr($data, 0, 2));

                            $data = (string) substr($data, 2);

                            if (!preg_match('//u', $data)) {
                                throw new DataException('Invalid UTF-8 data received.');
                            }

                            yield new Close($bytes['code'], $data);
                            return;

                        case Frame::PING: // Respond with pong frame.
                            yield $this->pong($frame->getData());
                            continue;

                        case Frame::PONG: // Cancel timeout set by sending ping frame.
                            // Only stop timer if ping sent and pong data matches ping data.
                            if (null !== $pong && $frame->getData() === $expected) {
                                $pong->stop();
                            }
                            continue;

                        case Frame::CONTINUATION:
                            $count = count($frames);

                            if (0 === $count) {
                                throw new ProtocolException('Received orphan continuation frame.');
                            }

                            $frames[] = $frame;
                            $size += $frame->getSize();

                            if (!$frame->isFinal()) {
                                if ($count + 1 >= $maxFrames) {
                                    throw new PolicyException('Too many frames in message.');
                                }
                                continue;
                            }

                            $data = '';

                            for ($i = $count; $i >= 0; --$i) {
                                $frame = $frames[$i];
                                $data = $frame->getData() . $data;
                            }

                            $frames = [];
                            $size = 0;

                            $type = $frame->getType();

                            if ($type === Frame::TEXT && !preg_match('//u', $data)) {
                                throw new DataException('Invalid UTF-8 data received.');
                            }

                            yield $emit(new Message($data, $type === Frame::BINARY));
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

                            $data = $frame->getData();

                            if ($type === Frame::TEXT && !preg_match('//u', $data)) {
                                throw new DataException('Invalid UTF-8 data received.');
                            }

                            yield $emit(new Message($data, $type === Frame::BINARY));
                            continue;

                        default:
                            throw new ProtocolException('Received unrecognized frame type.');
                    }
                }
            } catch (ConnectionException $exception) {
                $close = new Close($exception->getReasonCode(), $exception->getMessage());
            } catch (SocketException $exception) {
                $close = new Close(Close::ABNORMAL, $exception->getMessage());
            } catch (StreamException $exception) {
                $close = new Close(Close::ABNORMAL, $exception->getMessage());
            } finally {
                $ping->stop();
                if (null !== $pong) {
                    $pong->stop();
                }
            }

            if (!isset($close)) {
                $close = new Close(Close::ABNORMAL, 'Peer unexpectedly disconnected.');
            }

            if ($this->isOpen()) {
                yield $this->close($close->getCode());
            }

            yield $close;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function send($message, $binary = false)
    {
        if ($message instanceof Message) {
            $binary = $message->isBinary();
        }

        $frame = new Frame($binary ? Frame::BINARY : Frame::TEXT, (string) $message);

        try {
            yield $this->transporter->send($frame, $this->timeout);
        } catch (\Exception $exception) {
            yield 0;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close($code = Close::NORMAL, $data = '')
    {
        $this->closed = true;

        $frame = new Frame(Frame::CLOSE, pack('n', (int) $code) . $data);

        try {
            yield $this->transporter->send($frame, $this->timeout);
        } catch (\Exception $exception) {
            yield 0;
        }
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
        $frame = new Frame(Frame::PING, $data);
        yield $this->transporter->send($frame, $this->timeout);
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
        $frame = new Frame(Frame::PONG, $data);
        yield $this->transporter->send($frame, $this->timeout);
    }

    /**
     * @return string
     */
    public function getLocalAddress()
    {
        return $this->transporter->getLocalAddress();
    }

    /**
     * @return int
     */
    public function getLocalPort()
    {
        return $this->transporter->getLocalPort();
    }

    /**
     * @return string
     */
    public function getRemoteAddress()
    {
        return $this->transporter->getRemoteAddress();
    }

    /**
     * @return int
     */
    public function getRemotePort()
    {
        return $this->transporter->getRemotePort();
    }

    /**
     * @return bool
     */
    public function isCryptoEnabled()
    {
        return $this->transporter->isCryptoEnabled();
    }
}
