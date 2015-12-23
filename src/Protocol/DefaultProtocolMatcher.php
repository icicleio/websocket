<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Http\Message\Uri;
use Icicle\Socket\Socket;
use Icicle\Stream\MemorySink;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Message\WebSocketRequest;

class DefaultProtocolMatcher implements ProtocolMatcher
{
    /**
     * @var \Icicle\WebSocket\Protocol\Protocol
     */
    private $protocol;

    public function __construct()
    {
        $this->protocol = new Rfc6455Protocol(); // Only RFC6455 supported at the moment, so keeping this simple.
    }

    /**
     * {@inheritdoc}
     */
    public function createResponse(Application $application, Request $request, Socket $socket)
    {
        if (strtolower($request->getHeaderLine('Connection')) !== 'upgrade'
            || strtolower($request->getHeaderLine('Upgrade')) !== 'websocket'
        ) {
            $sink = new MemorySink('Must upgrade to WebSocket connection for requested resource.');
            yield new BasicResponse(Response::UPGRADE_REQUIRED, [
                'Connection' => 'close',
                'Upgrade' => 'websocket',
                'Content-Length' => $sink->getLength(),
                'Content-Type' => 'text/plain',
            ], $sink);
            return;
        }

        if (!$application->allowOrigin($request->getHeaderLine('Origin'))) {
            $sink = new MemorySink('Origin forbidden.');
            yield new BasicResponse(Response::FORBIDDEN, [
                'Connection' => 'close',
                'Content-Length' => $sink->getLength(),
            ], $sink);
            return;
        }

        if (!$request->hasHeader('Sec-WebSocket-Version')) {
            $sink = new MemorySink('No WebSocket version header provided.');
            yield new BasicResponse(Response::BAD_REQUEST, [
                'Connection' => 'close',
                'Content-Length' => $sink->getLength(),
                'Upgrade' => 'websocket',
                'Sec-WebSocket-Version' => $this->getSupportedVersions()
            ], $sink);
            return;
        }

        if (!$this->protocol->isProtocol($request)) {
            $sink = new MemorySink('Unsupported protocol version.');
            yield new BasicResponse(Response::UPGRADE_REQUIRED, [
                'Connection' => 'close',
                'Content-Length' => $sink->getLength(),
                'Upgrade' => 'websocket',
                'Sec-WebSocket-Version' => $this->getSupportedVersions()
            ], $sink);
            return;
        }

        yield $this->protocol->createResponse($application, $request, $socket);
    }

    /**
     * {@inheritdoc}
     */
    public function createRequest(Uri $uri, array $protocols = [])
    {
        $headers = [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Version' => $this->getSupportedVersions(),
            'Sec-WebSocket-Key' => $this->protocol->generateKey(),
        ];

        if (!empty($protocols)) {
            $headers['Sec-WebSocket-Protocol'] = $protocols;
        }

        yield new WebSocketRequest($uri, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function validateResponse(Request $request, Response $response)
    {
        return $this->protocol->validateResponse($request, $response);
    }

    /**
     * @return string[]
     */
    public function getSupportedVersions()
    {
        return [$this->protocol->getVersionNumber()];
    }
}