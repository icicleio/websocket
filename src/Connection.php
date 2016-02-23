<?php
namespace Icicle\WebSocket;

use Icicle\Observable\Observable;

interface Connection
{
    /**
     * @return bool
     */
    public function isOpen(): bool;

    /**
     * Returns the name of any sub protocol to be used on the connection.
     *
     * @return string
     */
    public function getSubProtocol(): string;

    /**
     * Returns an array of extension names active on this connection.
     *
     * @return string[]
     */
    public function getExtensions(): array;

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
    public function read(): Observable;

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
    public function send($message, bool $binary = false): \Generator;

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
    public function close(int $code = Close::NORMAL, string $data = ''): \Generator;

    /**
     * @return string
     */
    public function getLocalAddress(): string;

    /**
     * @return int
     */
    public function getLocalPort(): int;

    /**
     * @return string
     */
    public function getRemoteAddress(): string;

    /**
     * @return int
     */
    public function getRemotePort(): int;

    /**
     * @return bool
     */
    public function isCryptoEnabled(): bool;
}
