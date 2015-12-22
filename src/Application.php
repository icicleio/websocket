<?php
namespace Icicle\WebSocket;

use Icicle\Http\Message\Response;

interface Application
{
    /**
     * @param string $origin
     *
     * @return bool
     */
    public function allowOrigin($origin);

    /**
     * @param array $protocols
     *
     * @return string
     */
    public function selectProtocol(array $protocols);

    /**
     * @param array $extensions
     *
     * @return array
     */
    public function selectExtensions(array $extensions);

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Response $response
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function createResponse(Response $response);

    /**
     * @coroutine
     *
     * @param \Icicle\WebSocket\Connection $connection
     *
     * @return \Generator
     *
     * @resolve null
     */
    public function onConnection(Connection $connection);
}
