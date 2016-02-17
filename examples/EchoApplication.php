<?php
namespace Icicle\Examples\WebSocket;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Log as LogNS;
use Icicle\Log\Log;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Connection;

class EchoApplication implements Application
{
    /**
     * @var \Icicle\Log\Log
     */
    private $log;

    /**
     * @param \Icicle\Log\Log|null $log
     */
    public function __construct(Log $log = null)
    {
        $this->log = $log ?: LogNS\log();
    }

    /**
     * {@inheritdoc}
     */
    public function onHandshake(Response $response, Request $request, Socket $socket)
    {
        // This method provides an opportunity to inspect the Request and Response before a connection is accepted.
        // Cookies may be set and returned on a new Response object, e.g.: return $response->withCookie(...);

        return $response; // No modification needed to the response, so the passed Response object is simply returned.
    }

    /**
     * {@inheritdoc}
     */
    public function onConnection(Connection $connection, Response $response, Request $request)
    {
        yield $this->log->log(
            Log::INFO,
            'Accepted WebSocket connection from %s:%d on %s:%d',
            $connection->getRemoteAddress(),
            $connection->getRemotePort(),
            $connection->getLocalAddress(),
            $connection->getLocalPort()
        );

        // The Response and Request objects used to initiate the connection are provided for informational purposes.
        // This method will primarily interact with the Connection object.

        yield $connection->send('Connected to echo WebSocket server powered by Icicle.');

        // Messages are read through an Observable that represents an asynchronous set. There are a variety of ways
        // to use this asynchronous set, including an asynchronous iterator as shown in the example below.

        $iterator = $connection->read()->getIterator();

        while (yield $iterator->isValid()) {
            /** @var \Icicle\WebSocket\Message $message */
            $message = $iterator->getCurrent();

            if ($message->getData() === 'close') {
                yield $connection->close();
            } else {
                yield $connection->send($message);
            }
        }

        /** @var \Icicle\WebSocket\Close $close */
        $close = $iterator->getReturn(); // Only needs to be called if the close reason is needed.

        yield $this->log->log(
            Log::INFO,
            'WebSocket connection from %s:%d closed; Code %d; Data: %s',
            $connection->getRemoteAddress(),
            $connection->getRemotePort(),
            $close->getCode(),
            $close->getData()
        );
    }
}
