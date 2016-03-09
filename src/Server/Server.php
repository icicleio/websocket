<?php
namespace Icicle\WebSocket\Server;

use Icicle\Http\Server\{RequestHandler, Server as HttpServer};
use Icicle\Log\Log;
use Icicle\Socket\Server\ServerFactory;
use Icicle\WebSocket\Driver\{Driver, WebSocketDriver};
use Icicle\WebSocket\Protocol\{DefaultProtocolMatcher, ProtocolMatcher};

class Server extends HttpServer
{
    /**
     * @param \Icicle\Http\Server\RequestHandler $handler
     * @param \Icicle\Log\Log|null $log
     * @param \Icicle\WebSocket\Protocol\ProtocolMatcher|null $matcher
     * @param \Icicle\WebSocket\Driver\Driver|null $driver
     * @param \Icicle\Socket\Server\ServerFactory|null $factory
     */
    public function __construct(
        RequestHandler $handler,
        Log $log = null,
        ProtocolMatcher $matcher = null,
        Driver $driver = null,
        ServerFactory $factory = null
    ) {
        $handler = new Internal\WebSocketRequestHandler($matcher ?: new DefaultProtocolMatcher(), $handler);
        $driver = $driver ?: new WebSocketDriver();

        parent::__construct($handler, $log, $driver, $factory);
    }
}
