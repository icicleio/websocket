<?php
namespace Icicle\WebSocket;

interface Connection
{
    const CLOSE_NORMAL =        1000;
    const CLOSE_GOING_AWAY =    1001;
    const CLOSE_PROTOCOL =      1002;
    const CLOSE_BAD_DATA =      1003;
    const CLOSE_NO_STATUS =     1005;
    const CLOSE_ABNORMAL =      1006;
    const CLOSE_INVALID_DATA =  1007;
    const CLOSE_VIOLATION =     1008;
    const CLOSE_TOO_BIG =       1009;
    const CLOSE_EXTENSION =     1010;
    const CLOSE_SERVER_ERROR =  1011;
    const CLOSE_TLS_ERROR =     1015;

    /**
     * @return bool
     */
    public function isOpen();

    /**
     * HTTP request (server) or response (client) object received to create the connection.
     *
     * @return \Icicle\Http\Message\Message
     */
    public function getMessage();

    /**
     * Returns the name of any sub protocol to be used on the connection.
     *
     * @return string
     */
    public function getSubProtocol();

    /**
     * Returns an array of extension names active on this connection.
     *
     * @return string[]
     */
    public function getExtensions();

    /**
     * @return \Icicle\Observable\Observable
     */
    public function read();

    /**
     * @coroutine
     *
     * @param \Icicle\WebSocket\Message $message
     *
     * @return \Generator
     *
     * @resolve int
     */
    public function send(Message $message);

    /**
     * @coroutine
     *
     * @param int $code
     *
     * @return \Generator
     *
     * @resolve int
     */
    public function close($code = self::CLOSE_NORMAL);

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
