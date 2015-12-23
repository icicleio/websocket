<?php
namespace Icicle\Examples\WebSocket;

use Icicle\Http\Message\Response;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Connection;
use Icicle\WebSocket\Message;

class EchoApplication implements Application
{
    public function allowOrigin($origin)
    {
        return true;
    }

    public function selectProtocol(array $protocols)
    {
        return '';
    }

    public function selectExtensions(array $extensions)
    {
        return [];
    }

    public function createResponse(Response $response)
    {
        yield $response;
    }

    public function onConnection(Connection $connection)
    {
        yield $connection->send(new Message('Connected to echo WebSocket server powered by Icicle.'));

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
    }
}
