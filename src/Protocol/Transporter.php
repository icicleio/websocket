<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Socket\Socket;

interface Transporter
{
    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\WebSocket\Protocol\Frame
     *
     * @throws \Icicle\WebSocket\Exception\FrameException
     */
    public function read(Socket $socket, $timeout = 0);

    /**
     * @coroutine
     *
     * @param \Icicle\WebSocket\Protocol\Frame $frame
     * @param \Icicle\Socket\Socket $socket
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve int Number of bytes sent on the socket.
     */
    public function send(Frame $frame, Socket $socket, $timeout = 0);
}
