<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application;

interface Protocol
{
    /**
     * @return string
     */
    public function getVersionNumber();

    /**
     * Determines if the request supports this protocol.
     *
     * @param \Icicle\Http\Message\Request $request
     *
     * @return bool
     */
    public function isProtocol(Request $request);

    /**
     * @coroutine
     *
     * @param Application $application
     * @param Request $request
     * @param Socket $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function createResponse(Application $application, Request $request, Socket $socket);

    /**
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Http\Message\Response $response
     *
     * @return bool
     */
    public function validateResponse(Request $request, Response $response);

    /**
     * @param \Icicle\Socket\Socket $socket
     * @param bool $mask
     *
     * @return \Icicle\WebSocket\Protocol\Protocol
     */
    //public function createConnection(Socket $socket, $mask);
}
