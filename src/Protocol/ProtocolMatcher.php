<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\Request;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application;

interface ProtocolMatcher
{
    /**
     * @param \Icicle\WebSocket\Application $application
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function createResponse(
        Application $application,
        Request $request,
        Socket $socket
    );

    /**
     * @return string[]
     */
    public function getSupportedVersions();
}
