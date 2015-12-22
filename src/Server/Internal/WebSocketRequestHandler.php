<?php
namespace Icicle\WebSocket\Server\Internal;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Server\RequestHandler;
use Icicle\Socket\Socket;
use Icicle\Stream\MemorySink;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Protocol\ProtocolMatcher;

class WebSocketRequestHandler implements RequestHandler
{
    /**
     * @var \Icicle\Http\Server\RequestHandler
     */
    private $handler;

    /**
     * @var \Icicle\WebSocket\Protocol\ProtocolMatcher
     */
    private $matcher;

    /**
     * @param \Icicle\WebSocket\Protocol\ProtocolMatcher $matcher
     * @param \Icicle\Http\Server\RequestHandler $handler
     */
    public function __construct(ProtocolMatcher $matcher, RequestHandler $handler)
    {
        $this->matcher = $matcher;
        $this->handler = $handler;
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response $response
     */
    public function onRequest(Request $request, Socket $socket)
    {
        $application = (yield $this->handler->onRequest($request, $socket));

        if (!$application instanceof Application) {
            yield $application; // Other response returned, let HTTP server handle it.
            return;
        }

        if (strtolower($request->getHeaderLine('Connection')) !== 'upgrade'
            || strtolower($request->getHeaderLine('Upgrade')) !== 'websocket'
        ) {
            $sink = new MemorySink('Must upgrade to WebSocket connection for requested resource.');
            yield new BasicResponse(426, [
                'Connection' => 'close',
                'Upgrade' => 'websocket',
                'Content-Length' => $sink->getLength(),
            ], $sink);
            return;
        }

        if (!$application->allowOrigin($request->getHeaderLine('Origin'))) {
            $sink = new MemorySink('Origin forbidden.');
            yield new BasicResponse(403, [
                'Connection' => 'close',
                'Content-Length' => $sink->getLength(),
            ], $sink);
            return;
        }

        $response = (yield $this->matcher->createResponse($application, $request, $socket));

        yield $application->createResponse($response);
    }

    /**
     * @coroutine
     *
     * @param int $code
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function onError($code, Socket $socket)
    {
        return $this->handler->onError($code, $socket);
    }
}
