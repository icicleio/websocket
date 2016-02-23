<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\{Request, Response};
use Icicle\Socket\Socket;
use Icicle\WebSocket\{Application, Connection};

interface Protocol
{
    /**
     * @return string
     */
    public function getVersionNumber(): string;

    /**
     * Determines if the request supports this protocol.
     *
     * @param \Icicle\Http\Message\Request $request
     *
     * @return bool
     */
    public function isProtocol(Request $request): bool;

    /**
     * @param Application $application
     * @param Request $request
     * @param Socket $socket
     *
     * @return \Icicle\Http\Message\Response
     */
    public function createResponse(Application $application, Request $request, Socket $socket): Response;

    /**
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Http\Message\Response $response
     *
     * @return bool
     */
    public function validateResponse(Request $request, Response $response): bool;

    /**
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Socket\Socket $socket
     * @param bool $mask
     *
     * @return \Icicle\WebSocket\Connection
     */
    public function createConnection(Response $response, Socket $socket, bool $mask): Connection;
}
