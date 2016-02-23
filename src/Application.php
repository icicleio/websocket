<?php
namespace Icicle\WebSocket;

use Icicle\Http\Message\{Request, Response};
use Icicle\Socket\Socket;

interface Application
{
    /**
     * This method is called before responding to a handshake request when the request has been verified to be a valid
     * WebSocket request. This method can simply resolve with the response object given to it if no headers need to be
     * set or no other validation is needed. This method can also reject the request by resolving with another response
     * object entirely.
     *
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator|\Icicle\Awaitable\Awaitable|\Icicle\Http\Message\Response
     */
    public function onHandshake(Response $response, Request $request, Socket $socket);

    /**
     * This method is called when a WebSocket connection is established to the WebSocket server. This method should
     * not resolve until the connection should be closed.
     *
     * @coroutine
     *
     * @param \Icicle\WebSocket\Connection $connection
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Http\Message\Request $request
     *
     * @return \Generator|\Icicle\Awaitable\Awaitable|null
     */
    public function onConnection(Connection $connection, Response $response, Request $request);
}
