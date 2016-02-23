<?php
namespace Icicle\WebSocket\Server\Internal;

use Icicle\Http\{Exception\InvalidResultError, Server\RequestHandler};
use Icicle\Http\Message\{Request, Response};
use Icicle\Awaitable\Awaitable;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Protocol\{Message\WebSocketResponse, ProtocolMatcher};

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
     *
     * @throws \Icicle\Http\Exception\InvalidResultError If a Response object is not returned from
     *     Application::onHandshake().
     */
    public function onRequest(Request $request, Socket $socket)
    {
        $application = $this->handler->onRequest($request, $socket);

        if ($application instanceof \Generator) {
            $application = yield from $application;
        } elseif ($application instanceof Awaitable) {
            $application = yield $application;
        }

        if (!$application instanceof Application) {
            return $application; // Other response returned, let HTTP server handle it.
        }

        $response = $this->matcher->createResponse($application, $request, $socket);

        if (!$response instanceof WebSocketResponse) {
            return $response;
        }

        $message = $response->getMessage();

        $result = $application->onHandshake($message, $request, $socket);

        if ($result instanceof \Generator) {
            $result = yield from $result;
        } elseif ($result instanceof Awaitable) {
            $result = yield $result;
        }

        if (!$result instanceof Response) {
            throw new InvalidResultError(
                sprintf('A %s object was not returned from %s::onHandshake().', Response::class, Application::class),
                $result
            );
        }

        if ($result !== $message) {
            $response = new WebSocketResponse($response->getApplication(), $response->getConnection(), $result);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function onError(int $code, Socket $socket)
    {
        return $this->handler->onError($code, $socket);
    }
}
