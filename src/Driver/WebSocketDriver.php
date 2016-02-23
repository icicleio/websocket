<?php
namespace Icicle\WebSocket\Driver;

use Icicle\Http\Driver\Http1Driver;
use Icicle\Http\Message\{Request, Response};
use Icicle\Socket\Socket;
use Icicle\WebSocket\{Application, Connection, Protocol\Message\WebSocketResponse};

class WebSocketDriver extends Http1Driver implements Driver
{
    /**
     * {@inheritdoc}
     */
    public function writeResponse(
        Socket $socket,
        Response $response,
        Request $request = null,
        float $timeout = 0
    ): \Generator {
        $written = yield from parent::writeResponse($socket, $response, $request, $timeout);

        if ($response instanceof WebSocketResponse) {
            $application = $response->getApplication();
            $connection = $response->getConnection();
            $response = $response->getMessage();

            yield from $this->onConnection($application, $connection, $response, $request);
        }

        return $written;
    }

    /**
     * {@inheritdoc}
     */
    public function onConnection(
        Application $application,
        Connection $connection,
        Response $response,
        Request $request
    ): \Generator {
        return yield $application->onConnection($connection, $response, $request);
    }
}
