<?php
namespace Icicle\WebSocket\Exception;

use Icicle\WebSocket\Close;

class PolicyException extends ConnectionException
{
    public function __construct(string $message)
    {
        parent::__construct(Close::VIOLATION, $message);
    }
}
