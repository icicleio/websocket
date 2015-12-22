<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Uri;
use Icicle\Socket\Socket;
use Icicle\Stream\MemorySink;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Connection;
use Icicle\WebSocket\Message\WebSocketResponse;

class Rfc6455Protocol implements Protocol
{
    const VERSION = '13';
    const KEY = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

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
    public function createResponse(Application $application, Request $request, Socket $socket)
    {
        if (!$request->hasHeader('Sec-WebSocket-Key')) {
            $sink = new MemorySink('No WebSocket key header provided.');
            yield new BasicResponse(400, [
                'Connection' => 'close',
                'Content-Length' => $sink->getLength(),
            ], $sink);
            return;
        }

        $headers = [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Accept' => $this->createKey($request->getHeaderLine('Sec-WebSocket-Key')),
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
    public function createRequest(Application $application, Socket $socket, Uri $uri, $protocol)
    {
        $connection = new Connection($socket, $this, $uri, $protocol, [], true);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function createKey($key)
    {
        return base64_encode(sha1($key . self::KEY, true));
    }

    /**
     * {@inheritdoc}
     */
    public function createFrame($type, $data = '', $mask, $final = true)
    {
        return new Rfc6455Frame($type, $data, $mask, $final);
    }

    /**
     * {@inheritdoc}
     */
    public function readFrame(Socket $socket, $timeout = 0)
    {
        return Rfc6455Frame::read($socket, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function sendFrame(Frame $frame, Socket $socket, $timeout = 0)
    {
        return $socket->write($frame->encode(), $timeout);
    }
}
