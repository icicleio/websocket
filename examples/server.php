#!/usr/bin/env php
<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Examples\WebSocket\EchoApplication;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Server\RequestHandler;
use Icicle\Loop;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Server\Server;

class ExampleRequestHandler implements RequestHandler
{
    private $application;

    public function __construct()
    {
        $this->application = new EchoApplication();
    }

    public function onRequest(Request $request, Socket $socket)
    {
        yield new $this->application;
    }

    public function onError($code, Socket $socket)
    {
        yield new BasicResponse($code);
    }
}

$server = new Server(new ExampleRequestHandler());

$server->listen(8080);

Loop\run();
