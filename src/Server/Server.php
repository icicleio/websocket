<?php
namespace Icicle\WebSocket\Server;

use Icicle\Http\Server\Server as HttpServer;
use Icicle\Http\Server\RequestHandler;
use Icicle\Log\Log;
use Icicle\Socket\Server\ServerFactory;
use Icicle\WebSocket\Driver\Driver;
use Icicle\WebSocket\Driver\WebSocketDriver;
use Icicle\WebSocket\Protocol\DefaultProtocolMatcher;
use Icicle\WebSocket\Protocol\ProtocolMatcher;

class Server extends HttpServer
{
    /**
     * @param \Icicle\Http\Server\RequestHandler $handler
     * @param \Icicle\Log\Log|null $log
     * @param \Icicle\WebSocket\Driver\Driver|null $driver
     * @param \Icicle\Socket\Server\ServerFactory|null $factory
     * @param \Icicle\WebSocket\Protocol\ProtocolMatcher|null $matcher
     */
    public function __construct(
        RequestHandler $handler,
        Log $log = null,
        Driver $driver = null,
        ServerFactory $factory = null,
        ProtocolMatcher $matcher = null
    ) {
        $handler = new Internal\WebSocketRequestHandler($matcher ?: new DefaultProtocolMatcher(), $handler);
        $driver = $driver ?: new WebSocketDriver();

        parent::__construct($handler, $log, $driver, $factory);
    }
}
