<?php
namespace Icicle\WebSocket\Exception;

class ConnectionException extends \Exception implements Exception
{
    /**
     * @var int
     */
    private $reasonCode;

    /**
     * @param int $reasonCode
     * @param string $message
     */
    public function __construct($reasonCode, $message)
    {
        parent::__construct($message);
        $this->reasonCode = (int) $reasonCode;
    }

    /**
     * @return int
     */
    public function getReasonCode()
    {
        return $this->reasonCode;
    }
}
