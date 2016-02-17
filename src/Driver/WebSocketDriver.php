<?php
namespace Icicle\WebSocket\Driver;

use Icicle\Http\Driver\Http1Driver;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Connection;
use Icicle\WebSocket\Protocol\Message\WebSocketResponse;

class WebSocketDriver extends Http1Driver implements Driver
{
    /**
     * {@inheritdoc}
     */
    public function writeResponse(
        Socket $socket,
        Response $response,
        Request $request = null,
        $timeout = 0
    ) {
        $written = (yield parent::writeResponse($socket, $response, $request, $timeout));

        if ($response instanceof WebSocketResponse) {
            $application = $response->getApplication();
            $connection = $response->getConnection();
            $response = $response->getMessage();

            yield $this->onConnection($application, $connection, $response, $request);
        }

        yield $written;
    }

    /**
     * {@inheritdoc}
     */
    public function onConnection(
        Application $application,
        Connection $connection,
        Response $response,
        Request $request
    ) {
        yield $application->onConnection($connection, $response, $request);
    }
}
