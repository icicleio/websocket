<?php
namespace Icicle\WebSocket\Protocol\Message;

use Icicle\Http\Message\BasicResponse;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Connection;

class WebSocketResponse extends BasicResponse
{
    /**
     * @var \Icicle\WebSocket\Application
     */
    private $application;

    /**
     * @var \Icicle\WebSocket\Connection
     */
    private $connection;

    /**
     * @param \Icicle\WebSocket\Application $application
     * @param \Icicle\WebSocket\Connection $connection
     * @param string[][] $headers
     */
    public function __construct(Application $application, Connection $connection, array $headers)
    {
        parent::__construct(101, $headers);

        $this->application = $application;
        $this->connection = $connection;
    }

    /**
     * @return \Icicle\WebSocket\Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @return \Icicle\WebSocket\Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
