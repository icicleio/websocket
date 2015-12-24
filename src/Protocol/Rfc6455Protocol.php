<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Response;
use Icicle\Observable\Emitter;
use Icicle\Socket\Socket;
use Icicle\Stream\MemorySink;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Exception\ProtocolException;
use Icicle\WebSocket\Message;
use Icicle\WebSocket\Protocol\Message\WebSocketResponse;

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

        if ($request->hasHeader('Sec-WebSocket-Protocol')) {
            $protocol = $application->selectSubProtocol(
                array_map('trim', explode(',', $request->getHeaderLine('Sec-WebSocket-Protocol')))
            );

            if (strlen($protocol)) {
                $headers['Sec-WebSocket-Protocol'] = $protocol;
            }
        } else {
            $protocol = '';
        }

        /*
        $extensions = $application->selectExtensions(
            array_map('trim', explode(',', $request->getHeaderLine('Sec-WebSocket-Extensions')))
        );

        if (!empty($extensions)) {
            $headers['Sec-WebSocket-Extensions'] = $extensions;
        }
        */

        yield new WebSocketResponse($headers, $application, $this, $protocol, []);
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

            while ($socket->isReadable()) {
                /** @var \Icicle\WebSocket\Protocol\Frame $frame */
                $frame = (yield $this->transporter->read($socket, $timeout));

                if ($frame->isMasked() === $mask) {
                    throw new ProtocolException(sprintf('Received %s frame.', $mask ? 'masked' : 'unmasked'));
                }

                switch ($type = $frame->getType()) {
                    case Frame::CLOSE: // Close connection.
                        $data = $frame->getData();

                        $frame = new Frame(Frame::CLOSE, pack('S', self::CLOSE_NORMAL), $mask);
                        yield $this->transporter->send($frame, $socket, $timeout);

                        if (2 > strlen($data)) {
                            yield self::CLOSE_NO_STATUS;
                            return;
                        }

                        $bytes = unpack('Scode', substr($data, 0, 2));
                        $data = (string) substr($data, 2);

                        yield $bytes['code']; // @todo Return object with data section.
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

            yield self::CLOSE_NO_STATUS;
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
    public function close(Socket $socket, $mask, $code = self::CLOSE_NORMAL, $timeout = 0)
    {
        $frame = new Frame(Frame::CLOSE, pack('S', (int) $code), $mask);
        yield $this->transporter->send($frame, $socket, $timeout);

        /** @var \Icicle\WebSocket\Protocol\Frame $frame */
        $frame = (yield $this->transporter->read($socket, $timeout));

        $socket->close();

        if ($frame->isMasked() === $mask) {
            throw new ProtocolException(sprintf('Received %s frame.', $mask ? 'masked' : 'unmasked'));
        }

        $data = $frame->getData();

        if (2 > strlen($data)) {
            yield self::CLOSE_NO_STATUS;
            return;
        }

        $bytes = unpack('Scode', substr($data, 0, 2));
        $data = (string) substr($data, 2);

        yield $bytes['code']; // @todo Return object with data section.
    }
}
