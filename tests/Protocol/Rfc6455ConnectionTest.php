<?php
namespace Icicle\Tests\WebSocket\Protocol;

use Icicle\Coroutine\Coroutine;
use Icicle\Socket\Socket;
use Icicle\Tests\WebSocket\TestCase;
use Icicle\WebSocket\Close;
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
                return yield $temp;
            }));

        $socket->method('unshift')
            ->will($this->returnCallback(function ($string) use (&$data) {
                $data = $string;
            }));

        $socket->method('write')
            ->will($this->returnCallback(function ($string) {
                return yield strlen($string);
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

    /**
     * @return array
     */
    public function getInvalidConnections()
    {
        return Yaml::parse(file_get_contents(dirname(__DIR__) . '/data/invalid.yml'));
    }

    /**
     * @dataProvider getInvalidConnections
     *
     * @param array $data
     * @param bool $masked
     * @param int $close
     */
    public function testInvalidData(array $data, $masked, $close)
    {
        $data = hex2bin(implode('', $data)); // Put data segments together and convert to binary.

        $connection = $this->createConnection($this->createSocket($data), $masked);

        $this->assertTrue($connection->isOpen());

        $coroutine = new Coroutine($connection->read()->each());

        $result = $coroutine->wait();

        $this->assertSame($close, $result->getCode());
        $this->assertFalse($connection->isOpen());
    }

    /**
     * @depends testInvalidData
     */
    public function testMaxFrameCount()
    {
        $data = hex2bin(implode('', [
            '010131', // Initial text frame.
            str_repeat('000131', Rfc6455Connection::DEFAULT_MAX_FRAME_COUNT + 1), // Repeat continuation frame.
            '800131', // Final frame.
            '8800', // Close frame.
        ]));

        $connection = $this->createConnection($this->createSocket($data), true);

        $this->assertTrue($connection->isOpen());

        $coroutine = new Coroutine($connection->read()->each());

        $result = $coroutine->wait();

        $this->assertSame(Close::VIOLATION, $result->getCode());
        $this->assertFalse($connection->isOpen());
    }

    /**
     * @depends testInvalidData
     */
    public function testMaxMessageSize()
    {
        $data  = hex2bin('817f');
        $data .= pack('NN', 0, Rfc6455Connection::DEFAULT_MAX_MESSAGE_SIZE + 1);
        $data .= str_repeat(chr(0), Rfc6455Connection::DEFAULT_MAX_MESSAGE_SIZE + 1);

        $connection = $this->createConnection($this->createSocket($data), true);

        $this->assertTrue($connection->isOpen());

        $coroutine = new Coroutine($connection->read()->each());

        $result = $coroutine->wait();

        $this->assertSame(Close::VIOLATION, $result->getCode());
        $this->assertFalse($connection->isOpen());
    }

    public function testSend()
    {
        $socket = $this->createSocket('');
        $connection = $this->createConnection($socket, false);

        $encoded = hex2bin('81074d657373616765');
        $socket->method('write')
            ->with($this->identicalTo($encoded));

        $coroutine = new Coroutine($connection->send('Message'));
        $coroutine->wait();

        $encoded = hex2bin('82074d657373616765');
        $socket->method('write')
            ->with($this->identicalTo($encoded));

        $coroutine = new Coroutine($connection->send(new Message('Message', true)));
        $coroutine->wait();
    }
}
