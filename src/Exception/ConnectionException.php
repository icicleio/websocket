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
    public function __construct(int $reasonCode, string $message)
    {
        parent::__construct($message);
        $this->reasonCode = $reasonCode;
    }

    /**
     * @return int
     */
    public function getReasonCode(): int
    {
        return $this->reasonCode;
    }
}
