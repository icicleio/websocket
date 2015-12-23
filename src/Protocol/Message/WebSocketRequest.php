<?php
namespace Icicle\WebSocket\Protocol\Message;

use Icicle\Http\Message\BasicRequest;
use Icicle\Http\Message\Uri;

class WebSocketRequest extends BasicRequest
{
    /**
     * @param \Icicle\Http\Message\Uri $uri
     * @param string[][] $headers
     * @param string|null $target
     */
    public function __construct(Uri $uri, array $headers, $target = null)
    {
        parent::__construct('GET', $uri, $headers, null, $target, '1.1');
    }
}
