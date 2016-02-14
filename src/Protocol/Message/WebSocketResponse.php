<?php
namespace Icicle\WebSocket\Protocol\Message;

use Icicle\Http\Message\Response;
use Icicle\Stream\ReadableStream;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Connection;

class WebSocketResponse implements Response
{
    /**
     * @var \Icicle\Http\Message\Response
     */
    private $message;

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
     * @param \Icicle\Http\Message\Response $response
     */
    public function __construct(
        Application $application,
        Connection $connection,
        Response $response
    ) {
        $this->application = $application;
        $this->connection = $connection;
        $this->message = $response;
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

    /**
     * @return \Icicle\Http\Message\Response
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion()
    {
        return $this->message->getProtocolVersion();
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->message->getHeaders();
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader($name)
    {
        return $this->message->hasHeader($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderAsArray($name)
    {
        return $this->message->getHeaderAsArray($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($name)
    {
        return $this->message->getHeader($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getBody()
    {
        return $this->message->getBody();
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion($version)
    {
        $new = clone $this;
        $new->message = $new->message->withProtocolVersion($version);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader($name, $value)
    {
        $new = clone $this;
        $new->message = $new->message->withHeader($name, $value);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader($name, $value)
    {
        $new = clone $this;
        $new->message = $new->message->withAddedHeader($name, $value);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader($name)
    {
        $new = clone $this;
        $new->message = $new->message->withoutHeader($name);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(ReadableStream $stream)
    {
        $new = clone $this;
        $new->message = $new->message->withBody($stream);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return $this->message->getStatusCode();
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase()
    {
        return $this->message->getReasonPhrase();
    }

    /**
     * @return \Icicle\Http\Message\Cookie\MetaCookie[]
     */
    public function getCookies()
    {
        return $this->message->getCookies();
    }

    /**
     * {@inheritdoc}
     */
    public function hasCookie($name)
    {
        return $this->message->hasCookie($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getCookie($name)
    {
        return $this->message->getCookie($name);
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus($code, $reason = null)
    {
        $new = clone $this;
        $new->message = $new->message->withStatus($code, $reason);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withCookie(
        $name,
        $value = '',
        $expires = 0,
        $path = '',
        $domain = '',
        $secure = false,
        $httpOnly = false
    )
    {
        $new = clone $this;
        $new->message = $new->message->withCookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutCookie($name)
    {
        $new = clone $this;
        $new->message = $new->message->withoutCookie($name);
        return $new;
    }
}
