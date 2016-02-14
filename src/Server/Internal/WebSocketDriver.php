<?php
namespace Icicle\WebSocket\Server\Internal;

use Icicle\Http\Driver\Http1Driver;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Log\Log;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Protocol\Message\WebSocketResponse;

class WebSocketDriver extends Http1Driver
{
    private $log;

    /**
     * @param \Icicle\Log\Log $log
     *
     * @param array $options
     */
    public function __construct(Log $log, array $options = [])
    {
        parent::__construct($options);

        $this->log = $log;
    }

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

            assert(yield $this->log->log(
                Log::DEBUG,
                'Accepted WebSocket connection from %s:%d on %s:%d',
                $connection->getRemoteAddress(),
                $connection->getRemotePort(),
                $connection->getLocalAddress(),
                $connection->getLocalPort()
            ));

            yield $application->onConnection($connection, $response, $request);
        }

        yield $written;
    }
}
