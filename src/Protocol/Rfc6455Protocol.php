<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Response;
use Icicle\Observable\Emitter;
use Icicle\Socket\Socket;
use Icicle\Stream\MemorySink;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Connection;
use Icicle\WebSocket\Exception\ProtocolException;
use Icicle\WebSocket\Message;
use Icicle\WebSocket\Message\WebSocketResponse;

class Rfc6455Protocol implements Protocol
{
    const VERSION = '13';
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    const DEFAULT_KEY_LENGTH = 12;

    /**
     * @var \Icicle\WebSocket\Protocol\Transporter
     */
    private $transporter;

    /**
     * @param \Icicle\WebSocket\Protocol\Transporter|null $transporter
     */
    public function __construct(Transporter $transporter = null)
    {
        $this->transporter = $transporter ?: new Rfc6455Transporter();
    }

    /**
     * {@inheritdoc}
     */
    public function getVersionNumber()
    {
        return self::VERSION;
    }

    /**
     * {@inheritdoc}
     */
    public function isProtocol(Request $request)
    {
        $versions = array_map('trim', explode(',', $request->getHeaderLine('Sec-WebSocket-Version')));

        return in_array(self::VERSION, $versions);
    }

    /**
     * {@inheritdoc}
     */
    public function createResponse(Application $application, Request $request, Socket $socket)
    {
        if (!$request->hasHeader('Sec-WebSocket-Key')) {
            $sink = new MemorySink('No WebSocket key header provided.');
            yield new BasicResponse(Response::BAD_REQUEST, [
                'Connection' => 'close',
                'Content-Length' => $sink->getLength(),
            ], $sink);
            return;
        }

        $headers = [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Accept' => $this->responseKey($request->getHeaderLine('Sec-WebSocket-Key')),
        ];

        $protocol = $application->selectProtocol(
            array_map('trim', explode(',', $request->getHeaderLine('Sec-WebSocket-Protocol')))
        );

        if (strlen($protocol)) {
            $headers['Sec-WebSocket-Protocol'] = $protocol;
        }

        /*
        $extensions = $application->selectExtensions(
            array_map('trim', explode(',', $request->getHeaderLine('Sec-WebSocket-Extensions')))
        );

        if (!empty($extensions)) {
            $headers['Sec-WebSocket-Extensions'] = $extensions;
        }
        */

        $connection = new Connection($socket, $this, $request->getUri(), $protocol, [], false);

        yield new WebSocketResponse($application, $connection, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function validateResponse(Request $request, Response $response)
    {
        $key = $request->getHeaderLine('Sec-WebSocket-Key');

        if (!$response->hasHeader('Sec-WebSocket-Accept')) {
            return false;
        }

        return $this->responseKey($key) === $response->getHeaderLine('Sec-WebSocket-Accept');
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function responseKey($key)
    {
        return base64_encode(sha1($key . self::GUID, true));
    }

    /**
     * @param int $length Number of random bytes to generate for the encoded key.
     *
     * @return string
     */
    public function generateKey($length = self::DEFAULT_KEY_LENGTH)
    {
        return base64_encode(random_bytes($length));
    }

    /**
     * {@inheritdoc}
     */
    public function read(Socket $socket, $mask, $timeout = 0)
    {
        return new Emitter(function (callable $emit) use ($socket, $mask, $timeout) {
            /** @var \Icicle\WebSocket\Protocol\Frame[] $frames */
            $size = 0;
            $frames = [];

            try {
                while ($socket->isReadable()) {
                    /** @var \Icicle\WebSocket\Protocol\Frame $frame */
                    $frame = (yield $this->transporter->read($socket, $timeout));

                    if ($frame->isMasked() === $mask) {
                        throw new ProtocolException(sprintf('Received %s frame.', $mask ? 'masked' : 'unmasked'));
                    }

                    switch ($type = $frame->getType()) {
                        case Frame::CLOSE: // Close connection.
                            $data = $frame->getData();
                            if ($socket->isWritable()) {
                                $frame = new Frame(Frame::CLOSE, '', $mask);
                                yield $this->transporter->send($frame, $socket, $timeout);
                            }
                            yield $data; // @todo Parse close status from data.
                            return;

                        case Frame::PING: // Respond with pong frame.
                            $frame = new Frame(Frame::PONG, $frame->getData(), $mask);
                            yield $this->transporter->send($frame, $socket, $timeout);
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
            } finally {
                $socket->close();
            }

            yield ''; // @todo Return successful or failure status.
        });
    }

    /**
     * {@inheritdoc}
     */
    public function send(Message $message, Socket $socket, $mask, $timeout = 0)
    {
        $frame = new Frame($message->isBinary() ? Frame::BINARY : Frame::TEXT, $message->getData(), $mask);

        yield $this->transporter->send($frame, $socket, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function close(Socket $socket, $mask, $data = '', $timeout = 0)
    {
        $frame = new Frame(Frame::CLOSE, $data, $mask);

        $written = (yield $this->transporter->send($frame, $socket, $timeout)) + (yield $socket->end('', $timeout));

        yield $written;
    }
}
