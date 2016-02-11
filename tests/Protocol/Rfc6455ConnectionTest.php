<?php
namespace Icicle\Tests\WebSocket\Protocol;

use Icicle\Coroutine\Coroutine;
use Icicle\Socket\Socket;
use Icicle\Tests\WebSocket\TestCase;
use Icicle\WebSocket\Message;
use Icicle\WebSocket\Protocol\Rfc6455Connection;
use Icicle\WebSocket\Protocol\Rfc6455Transporter;
use Symfony\Component\Yaml\Yaml;

class Rfc6455ConnectionTest extends TestCase
{
    /**
     * @param string $data
     *
     * @return \Icicle\Socket\Socket|\PHPUnit_Framework_MockObject_MockObject
     */
    public function createSocket($data)
    {
        $socket = $this->getMock(Socket::class);

        $socket->method('isOpen')
            ->will($this->returnCallback(function () use (&$data) {
                return (bool) strlen($data);
            }));

        $socket->method('isReadable')
            ->will($this->returnCallback([$socket, 'isOpen']));

        $socket->method('isWritable')
            ->will($this->returnCallback([$socket, 'isOpen']));

        $socket->method('read')
            ->will($this->returnCallback(function () use (&$data) {
                $temp = $data;
                $data = '';
                yield $temp;
            }));

        $socket->method('unshift')
            ->will($this->returnCallback(function ($string) use (&$data) {
                $data = $string;
            }));

        $socket->method('write')
            ->will($this->returnCallback(function ($string) {
                yield strlen($string);
            }));

        return $socket;
    }

    /**
     * @param \Icicle\Socket\Socket $socket
     * @param bool $masked
     * @param string $subProtocol
     * @param string[] $extensions
     * @param mixed[] $options
     *
     * @return \Icicle\WebSocket\Protocol\Rfc6455Connection
     */
    public function createConnection(Socket $socket, $masked = false, $subProtocol = '', array $extensions = [], array $options = [])
    {
        return new Rfc6455Connection(
            new Rfc6455Transporter($socket, $masked), $subProtocol, $extensions, $options
        );
    }

    /**
     * @return array
     */
    public function getValidConnections()
    {
        return Yaml::parse(file_get_contents(dirname(__DIR__) . '/data/valid.yml'));
    }

    /**
     * @dataProvider getValidConnections
     *
     * @param array $data
     * @param bool $masked
     * @param array $messages
     * @param int $close
     */
    public function testRead(array $data, $masked, array $messages, $close)
    {
        $data = hex2bin(implode('', $data)); // Put data segments together and convert to binary.

        $connection = $this->createConnection($this->createSocket($data), $masked);

        $this->assertTrue($connection->isOpen());

        $coroutine = new Coroutine($connection->read()->each(function (Message $message) use ($messages) {
            static $i = 0;
            $this->assertSame($messages[$i]['data'], $message->getData());
            $this->assertSame($messages[$i]['binary'], $message->isBinary());
            ++$i;
        }));

        $result = $coroutine->wait();

        $this->assertSame($close, $result->getCode());

        $this->assertFalse($connection->isOpen());
    }

    public function testSend()
    {
        $socket = $this->createSocket('');
        $connection = $this->createConnection($socket, false);

        $encoded = hex2bin('81074d657373616765');
        $coroutine = new Coroutine($connection->send('Message'));
        $this->assertSame(strlen($encoded), $coroutine->wait());

        $encoded = hex2bin('82074d657373616765');
        $coroutine = new Coroutine($connection->send(new Message('Message', true)));
        $this->assertSame(strlen($encoded), $coroutine->wait());
    }
}
