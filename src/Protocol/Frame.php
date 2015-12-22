<?php
namespace Icicle\WebSocket\Protocol;

interface Frame
{
    const CONTINUATION = 0x0;
    const TEXT =         0x1;
    const BINARY =       0x2;
    const CLOSE =        0x8;
    const PING =         0x9;
    const PONG =         0xa;

    /**
     * @return \Icicle\Stream\ReadableStream
     */
    public function getData();

    /**
     * @return int Returns an integer corresponding to the frame type constants.
     */
    public function getType();

    /**
     * @return bool True if the frame is final, false if not.
     */
    public function isFinal();

    /**
     * @return bool True if the frame message was masked, false if not.
     */
    public function isMasked();

    /**
     * @return string Frame encoded as a string.
     */
    public function encode();
}
