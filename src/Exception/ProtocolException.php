<?php
namespace Icicle\WebSocket\Exception;

use Icicle\WebSocket\Close;

class ProtocolException extends ConnectionException
{
    public function __construct(string $message)
    {
        parent::__construct(Close::PROTOCOL, $message);
    }
}
