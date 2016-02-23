<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\{Request, Response, Uri};
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application;

interface ProtocolMatcher
{
    /**
     * @param \Icicle\WebSocket\Application $application
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Icicle\Http\Message\Response
     */
    public function createResponse(Application $application, Request $request, Socket $socket): Response;

    /**
     * @param \Icicle\Http\Message\Uri $uri
     * @param string[] $protocols
     *
     * @return \Icicle\Http\Message\Request
     */
    public function createRequest(Uri $uri, array $protocols = []): Request;

    /**
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Http\Message\Response $response
     *
     * @return bool
     */
    public function validateResponse(Request $request, Response $response): bool;

    /**
     * @return string[]
     */
    public function getSupportedVersions(): array;
}
