<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\Request;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Message;

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
     * @param \Icicle\Socket\Socket $socket
     * @param bool $mask
     * @param float|int $timeout
     *
     * @return \Icicle\Observable\Observable
     */
    public function read(Socket $socket, $mask, $timeout = 0);

    /**
     * @coroutine
     *
     * @param \Icicle\WebSocket\Message $message
     * @param \Icicle\Socket\Socket $socket
     * @param bool $mask
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve int
     */
    public function send(Message $message, Socket $socket, $mask, $timeout = 0);

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param bool $mask
     * @param string $data
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve string
     */
    public function close(Socket $socket, $mask, $data = '', $timeout = 0);
}
