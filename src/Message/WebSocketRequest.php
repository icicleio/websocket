<?php
namespace Icicle\WebSocket\Message;

use Icicle\Http\Message\BasicRequest;
use Icicle\Http\Message\Uri;
use Icicle\WebSocket\Application;

class WebSocketRequest extends BasicRequest
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
    public function __construct(Application $application, Uri $uri, array $headers)
    {
        parent::__construct('GET', $uri, $headers);

        $this->application = $application;
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
