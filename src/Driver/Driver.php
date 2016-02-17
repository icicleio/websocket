<?php
namespace Icicle\WebSocket\Driver;

use Icicle\Http\Driver\Driver as HttpDriver;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Connection;

interface Driver extends HttpDriver
{
    /**
     * @coroutine
     *
     * @param \Icicle\WebSocket\Application $application
     * @param \Icicle\WebSocket\Connection $connection
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Http\Message\Request $request
     *
     * @return \Generator
     *
     * @resolve null
     */
    public function onConnection(
        Application $application,
        Connection $connection,
        Response $response,
        Request $request
    );
}
