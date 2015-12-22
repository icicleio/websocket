<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Request;
use Icicle\Socket\Socket;
use Icicle\Stream\MemorySink;
use Icicle\WebSocket\Application;

class DefaultProtocolMatcher implements ProtocolMatcher
{
    /**
     * @var \Icicle\WebSocket\Protocol\Protocol
     */
    private $protocol;

    public function __construct()
    {
        $this->protocol = new Rfc6455Protocol(); // Only RFC6455 supported at the moment, so keeping this simple.
    }

    /**
     * @param \Icicle\WebSocket\Application $application
     * @param Request $request
     * @param Socket $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function createResponse(Application $application, Request $request, Socket $socket)
    {
        if (!$request->hasHeader('Sec-WebSocket-Version')) {
            $sink = new MemorySink('No WebSocket version header provided.');
            yield new BasicResponse(400, [
                'Connection' => 'close',
                'Content-Length' => $sink->getLength(),
                'Upgrade' => 'websocket',
                'Sec-WebSocket-Version' => $this->getSupportedVersions()
            ], $sink);
            return;
        }

        $versions = array_map('trim', explode(',', $request->getHeaderLine('Sec-WebSocket-Version')));

        if (!in_array($this->protocol->getVersionNumber(), $versions)) {
            $sink = new MemorySink('Unsupported protocol version.');
            yield new BasicResponse(426, [
                'Connection' => 'close',
                'Content-Length' => $sink->getLength(),
                'Upgrade' => 'websocket',
                'Sec-WebSocket-Version' => $this->getSupportedVersions()
            ], $sink);
            return;
        }

        yield $this->protocol->createResponse($application, $request, $socket);
    }

    /**
     * @return string[]
     */
    public function getSupportedVersions()
    {
        return [$this->protocol->getVersionNumber()];
    }
}