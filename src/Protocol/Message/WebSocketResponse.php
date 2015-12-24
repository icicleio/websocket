<?php
namespace Icicle\WebSocket\Protocol\Message;

use Icicle\Http\Message\BasicResponse;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Protocol\Protocol;

class WebSocketResponse extends BasicResponse
{
    /**
     * @var \Icicle\WebSocket\Application
     */
    private $application;

    /**
     * @var \Icicle\WebSocket\Protocol\Protocol
     */
    private $protocol;

    /**
     * @var string
     */
    private $subProtocol;

    /**
     * @var string[]
     */
    private $extensions;

    /**
     * @param string[][] $headers
     * @param \Icicle\WebSocket\Application $application
     * @param \Icicle\WebSocket\Protocol\Protocol $protocol
     * @param string $subProtocol
     * @param string[] $extensions
     */
    public function __construct(
        array $headers,
        Application $application,
        Protocol $protocol,
        $subProtocol,
        array $extensions
    ) {
        parent::__construct(101, $headers);

        $this->application = $application;
        $this->protocol = $protocol;
        $this->subProtocol = (string) $subProtocol;
        $this->extensions = $extensions;
    }

    /**
     * @return \Icicle\WebSocket\Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @return \Icicle\WebSocket\Protocol\Protocol
     */
    public function getProtocol()
    {
        return $this->protocol;
    }

    /**
     * @return string
     */
    public function getSubProtocol()
    {
        return $this->subProtocol;
    }

    /**
     * @return \string[]
     */
    public function getExtensions()
    {
        return $this->extensions;
    }
}
