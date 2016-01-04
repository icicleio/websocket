<?php
namespace Icicle\Examples\WebSocket;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Connection;
use Icicle\WebSocket\Message;

class EchoApplication implements Application
{
    public function selectSubProtocol(array $protocols)
    {
        return '';
    }

    public function onHandshake(Response $response, Request $request, Socket $socket)
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

        /** @var \Icicle\WebSocket\Close $close */
        $close = $iterator->getReturn();

        printf("Close code: %d\n", $close->getCode());
    }
}
