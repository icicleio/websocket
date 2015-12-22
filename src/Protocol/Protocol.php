<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Uri;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application;

interface Protocol
{
    /**
     * @return string
     */
    public function getVersionNumber();

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
     * @param \Icicle\WebSocket\Application $application
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Uri $uri
     * @param string $protocol
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Request
     */
    public function createRequest(Application $application, Socket $socket, Uri $uri, $protocol);

    /**
     * @param int $type
     * @param string $data
     * @param bool $mask
     * @param bool $final
     *
     * @return \Icicle\WebSocket\Protocol\Frame
     */
    public function createFrame($type, $data = '', $mask, $final = true);

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\WebSocket\Protocol\Frame
     */
    public function readFrame(Socket $socket, $timeout = 0);

    /**
     * @coroutine
     *
     * @param \Icicle\WebSocket\Protocol\Frame $frame
     * @param \Icicle\Socket\Socket $socket
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve int Number of bytes sent.
     */
    public function sendFrame(Frame $frame, Socket $socket, $timeout = 0);
}
