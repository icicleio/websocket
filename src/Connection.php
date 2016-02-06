<?php
namespace Icicle\WebSocket;

interface Connection
{
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
     * Returns an observable that emits \Icicle\WebSocket\Message instances when a message is received and resolves
     * with an instance of \Icicle\WebSocket\Close when the connection in closed.
     *
     * @return \Icicle\Observable\Observable
     *
     * @emit \Icicle\WebSocket\Message
     *
     * @resolve \Icicle\WebSocket\Close
     */
    public function read();

    /**
     * @coroutine
     *
     * @param string|\Icicle\WebSocket\Message $message Can provide a Message object, a string, or anything that can
     *     successfully be cast to a string.
     * @param bool $binary True if the message should be treated as binary data, false if it should be treated as an
     *     UTF-8 encoded string. This parameter is ignored if the first argument is a Message object.
     *
     * @return \Generator
     *
     * @resolve int
     */
    public function send($message, $binary = false);

    /**
     * @coroutine
     *
     * @param int $code
     * @param string $data
     *
     * @return \Generator
     *
     * @resolve int
     */
    public function close($code = Close::NORMAL, $data = '');

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
