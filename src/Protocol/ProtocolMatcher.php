<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Http\Message\Uri;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application;

interface ProtocolMatcher
{
    /**
     * @coroutine
     *
     * @param \Icicle\WebSocket\Application $application
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function createResponse(Application $application, Request $request, Socket $socket);

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Uri $uri
     * @param string[] $protocols
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Request
     */
    public function createRequest(Uri $uri, array $protocols = []);

    /**
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Http\Message\Response $response
     *
     * @return bool
     */
    public function validateResponse(Request $request, Response $response);

    /**
     * @return string[]
     */
    public function getSupportedVersions();
}
