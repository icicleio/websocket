<?php
namespace Icicle\WebSocket\Server\Internal;

use Icicle\Http\Driver\Http1Driver;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Protocol\Message\WebSocketResponse;

class WebSocketDriver extends Http1Driver
{
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

            yield $application->onConnection($connection, $response, $request);
        }

        yield $written;
    }
}
