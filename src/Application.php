<?php
namespace Icicle\WebSocket;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;

interface Application
{
    /**
     * This method should select a sub protocol to use from an array of protocols provided in the request. This method
     * is only invoked if a list of sub protocols is provided in the request.
     *
     * @param array $protocols
     *
     * @return string
     */
    public function selectSubProtocol(array $protocols);

    /**
     * This method is called before responding to a handshake request when the request has been verified to be a valid
     * WebSocket request. This method can simply resolve with the response object given to it if no headers need to be
     * set or no other validation is needed. This method can also reject the request by resolving with another response
     * object entirely.
     *
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\WebSocket\Connection $connection
     *
     * @return \Generator|\Icicle\Awaitable\Awaitable|\Icicle\Http\Message\Response
     */
    public function onHandshake(Response $response, Request $request, Connection $connection);

    /**
     * This method is called when a WebSocket connection is established to the WebSocket server. This method should
     * not resolve until the connection should be closed.
     *
     * @coroutine
     *
     * @param \Icicle\WebSocket\Connection $connection
     *
     * @return \Generator|\Icicle\Awaitable\Awaitable|null
     */
    public function onConnection(Connection $connection);
}
