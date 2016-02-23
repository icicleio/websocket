<?php
namespace Icicle\WebSocket\Protocol\Message;

use Icicle\Http\Message\{Cookie\Cookie, Message, Response};
use Icicle\Stream\ReadableStream;
use Icicle\WebSocket\{Application, Connection};

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
    public function getApplication(): Application
    {
        return $this->application;
    }

    /**
     * @return \Icicle\WebSocket\Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @return \Icicle\Http\Message\Response
     */
    public function getMessage(): Response
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion(): string
    {
        return $this->message->getProtocolVersion();
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(): array
    {
        return $this->message->getHeaders();
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader(string $name): bool
    {
        return $this->message->hasHeader($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderAsArray(string $name): array
    {
        return $this->message->getHeaderAsArray($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader(string $name): string
    {
        return $this->message->getHeader($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(): ReadableStream
    {
        return $this->message->getBody();
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion(string $version): Message
    {
        $new = clone $this;
        $new->message = $new->message->withProtocolVersion($version);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader(string $name, $value): Message
    {
        $new = clone $this;
        $new->message = $new->message->withHeader($name, $value);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader(string $name, $value): Message
    {
        $new = clone $this;
        $new->message = $new->message->withAddedHeader($name, $value);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader(string $name): Message
    {
        $new = clone $this;
        $new->message = $new->message->withoutHeader($name);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(ReadableStream $stream): Message
    {
        $new = clone $this;
        $new->message = $new->message->withBody($stream);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return $this->message->getStatusCode();
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase(): string
    {
        return $this->message->getReasonPhrase();
    }

    /**
     * @return \Icicle\Http\Message\Cookie\MetaCookie[]
     */
    public function getCookies(): array
    {
        return $this->message->getCookies();
    }

    /**
     * {@inheritdoc}
     */
    public function hasCookie(string $name): bool
    {
        return $this->message->hasCookie($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getCookie(string $name): Cookie
    {
        return $this->message->getCookie($name);
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus(int $code, string $reason = null): Response
    {
        $new = clone $this;
        $new->message = $new->message->withStatus($code, $reason);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withCookie(
        string $name,
        $value = '',
        int $expires = 0,
        string $path = '',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = false
    ): Response {
        $new = clone $this;
        $new->message = $new->message->withCookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutCookie(string $name): Response
    {
        $new = clone $this;
        $new->message = $new->message->withoutCookie($name);
        return $new;
    }
}
