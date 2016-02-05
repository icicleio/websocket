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
     * @param string[][] $headers
     * @param \Icicle\WebSocket\Application $application
     * @param \Icicle\WebSocket\Connection $connection
     */
    public function __construct(
        array $headers,
        Application $application,
        Connection $connection
    ) {
        parent::__construct(101, $headers, null, 'Switching Protocols', '1.1');

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
