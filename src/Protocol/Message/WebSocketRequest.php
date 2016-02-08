<?php
namespace Icicle\WebSocket\Protocol\Message;

use Icicle\Http\Message\BasicRequest;
use Icicle\Http\Message\Uri;
use Icicle\Stream\ReadableStream;

class WebSocketRequest extends BasicRequest
{
    /**
     * @param \Icicle\Http\Message\Uri $uri
     * @param string[][] $headers
     * @param \Icicle\Stream\ReadableStream|null $stream
     * @param string|null $target
     */
    public function __construct(Uri $uri, array $headers, ReadableStream $stream = null, $target = null)
    {
        parent::__construct('GET', $uri, $headers, $stream, $target, '1.1');
    }
}
