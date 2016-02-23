<?php
namespace Icicle\WebSocket\Exception;

use Icicle\WebSocket\Close;

class DataException extends ConnectionException
{
    public function __construct(string $message)
    {
        parent::__construct(Close::INVALID_DATA, $message);
    }
}
