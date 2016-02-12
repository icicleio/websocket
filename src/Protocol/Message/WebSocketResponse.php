<?php
namespace Icicle\WebSocket\Protocol\Message;

use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Response;
use Icicle\Stream\ReadableStream;
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
     * @param \Icicle\Stream\ReadableStream $stream
     */
    public function __construct(
        array $headers,
        Application $application,
        Connection $connection,
        ReadableStream $stream = null
    ) {
        parent::__construct(Response::SWITCHING_PROTOCOLS, $headers, $stream);

        $this->application = $application;
        $this->connection = $connection;
    }

    /**
     * @internal
     *
     * @return \Icicle\WebSocket\Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @internal
     *
     * @return \Icicle\WebSocket\Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
