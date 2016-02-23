<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Awaitable\Exception\TimeoutException;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Observable\{Emitter, Observable};
use Icicle\Socket\Exception\Exception as SocketException;
use Icicle\Stream\Exception\Exception as StreamException;
use Icicle\WebSocket\{Close, Connection, Message};
use Icicle\WebSocket\Exception\{ConnectionException, DataException, PolicyException, ProtocolException};
use Icicle\WebSocket\Protocol\{Rfc6455Frame as Frame, Rfc6455Transporter as Transporter};

class Rfc6455Connection implements Connection
{
    const DEFAULT_TIMEOUT = 5;
    const DEFAULT_INACTIVITY_TIMEOUT = 60;
    const DEFAULT_MAX_MESSAGE_SIZE = 0x100000; // 1 MB
    const DEFAULT_MAX_FRAME_COUNT = 8;
    const PING_DATA_LENGTH = 3;

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
        string $subProtocol,
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
    public function isOpen(): bool
    {
        return $this->transporter->isOpen() && !$this->closed;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubProtocol(): string
    {
        return $this->subProtocol;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * {@inheritdoc}
     */
    public function read(): Observable
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
    private function createObservable(float $interval, int $maxSize, int $maxFrames): Observable
    {
        return new Emitter(function (callable $emit) use ($interval, $maxSize, $maxFrames): \Generator {
            /** @var \Icicle\WebSocket\Protocol\Rfc6455Frame[] $frames */
            $frames = [];
            $size = 0;

            $ping = Loop\periodic($interval, Coroutine\wrap(function () use (&$pong, &$expected) {
                try {
                    yield from $this->ping($expected = base64_encode(random_bytes(self::PING_DATA_LENGTH)));

                    if (null === $pong) {
                        $pong = Loop\timer($this->timeout, Coroutine\wrap(function () {
                            yield from $this->close(Close::VIOLATION);
                            $this->transporter->close();
                        }));
                        $pong->unreference();
                    } else {
                        $pong->again();
                    }
                } catch (\Throwable $exception) {
                    $this->transporter->close();
                }
            }));
            $ping->unreference();

            try {
                while ($this->transporter->isOpen()) {
                    /** @var \Icicle\WebSocket\Protocol\Rfc6455Frame $frame */
                    $frame = yield from $this->transporter->read($maxSize - $size);

                    $ping->again();

                    switch ($type = $frame->getType()) {
                        case Frame::CLOSE: // Close connection.
                            if (!$this->closed) { // Respond with close frame if one has not been sent.
                                yield from $this->close(Close::NORMAL);
                            }

                            $data = $frame->getData();

                            if (2 > strlen($data)) {
                                return new Close(Close::NO_STATUS);
                            }

                            $bytes = unpack('ncode', substr($data, 0, 2));

                            $data = (string) substr($data, 2);

                            if (!preg_match('//u', $data)) {
                                throw new DataException('Invalid UTF-8 data received.');
                            }

                            return new Close($bytes['code'], $data);

                        case Frame::PING: // Respond with pong frame.
                            yield from $this->pong($frame->getData());
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

                            yield from $emit(new Message($data, $type === Frame::BINARY));
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

                            yield from $emit(new Message($data, $type === Frame::BINARY));
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
            } catch (TimeoutException $exception) {
                $close = new Close(Close::GOING_AWAY, $exception->getMessage());
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
                yield from $this->close($close->getCode());
            }

            return $close;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function send($message, bool $binary = false): \Generator
    {
        if ($message instanceof Message) {
            $binary = $message->isBinary();
        }

        $frame = new Frame($binary ? Frame::BINARY : Frame::TEXT, (string) $message);

        try {
            return yield from $this->transporter->send($frame, $this->timeout);
        } catch (\Exception $exception) {
            return 0;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(int $code = Close::NORMAL, string $data = ''): \Generator
    {
        $this->closed = true;

        $frame = new Frame(Frame::CLOSE, pack('n', $code) . $data);

        try {
            return yield from $this->transporter->send($frame, $this->timeout);
        } catch (\Exception $exception) {
            return 0;
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
    protected function ping(string $data = ''): \Generator
    {
        $frame = new Frame(Frame::PING, $data);
        return yield from $this->transporter->send($frame, $this->timeout);
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
    protected function pong(string $data = ''): \Generator
    {
        $frame = new Frame(Frame::PONG, $data);
        return yield from $this->transporter->send($frame, $this->timeout);
    }

    /**
     * @return string
     */
    public function getLocalAddress(): string
    {
        return $this->transporter->getLocalAddress();
    }

    /**
     * @return int
     */
    public function getLocalPort(): int
    {
        return $this->transporter->getLocalPort();
    }

    /**
     * @return string
     */
    public function getRemoteAddress(): string
    {
        return $this->transporter->getRemoteAddress();
    }

    /**
     * @return int
     */
    public function getRemotePort(): int
    {
        return $this->transporter->getRemotePort();
    }

    /**
     * @return bool
     */
    public function isCryptoEnabled(): bool
    {
        return $this->transporter->isCryptoEnabled();
    }
}
