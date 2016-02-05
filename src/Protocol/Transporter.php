<?php
namespace Icicle\WebSocket\Protocol;

interface Transporter
{
    /**
     * @coroutine
     *
     * @param int $maxSize Max frame size.
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\WebSocket\Protocol\Frame
     *
     * @throws \Icicle\WebSocket\Exception\FrameException
     */
    public function read($maxSize, $timeout = 0);

    /**
     * @coroutine
     *
     * @param \Icicle\WebSocket\Protocol\Frame $frame
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve int Number of bytes sent on the socket.
     */
    public function send(Frame $frame, $timeout = 0);

    /**
     * Returns true if the underlying socket is open, false if it has been closed.
     *
     * @return bool
     */
    public function isOpen();

    /**
     * Closes the underlying transport socket.
     */
    public function close();

    /**
     * @return string
     */
    public function getLocalAddress();

    /**
     * @return int
     */
    public function getLocalPort();

    /**
     * @return string
     */
    public function getRemoteAddress();

    /**
     * @return int
     */
    public function getRemotePort();

    /**
     * @return bool
     */
    public function isCryptoEnabled();
}
