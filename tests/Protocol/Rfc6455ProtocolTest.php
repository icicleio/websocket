<?php
namespace Icicle\Tests\WebSocket\Protocol;

use Icicle\Coroutine\Coroutine;
use Icicle\Http\Message\BasicRequest;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Response;
use Icicle\Socket\Socket;
use Icicle\Tests\WebSocket\TestCase;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Protocol\Message\WebSocketResponse;
use Icicle\WebSocket\Protocol\Rfc6455Protocol;
use Icicle\WebSocket\SubProtocol;

class Rfc6455ProtocolTest extends TestCase
{
    /**
     * @var \Icicle\WebSocket\Protocol\Rfc6455Protocol
     */
    protected $protocol;

    public function setUp()
    {
        $this->protocol = new Rfc6455Protocol();
    }

    public function testGetVersionNumber()
    {
        $this->assertSame('13', $this->protocol->getVersionNumber());
    }

    public function testIsProtocol()
    {
        $request = new BasicRequest('GET', 'http://example.com', [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Version' => '13',
            'Sec-WebSocket-Key' => '1234567890',
        ]);

        $this->assertTrue($this->protocol->isProtocol($request));

        $request = new BasicRequest('GET', 'http://example.com', [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Version' => '8',
            'Sec-WebSocket-Key' => '1234567890',
        ]);

        $this->assertFalse($this->protocol->isProtocol($request));
    }

    public function testCreateResponse()
    {
        $request = new BasicRequest('GET', 'http://example.com', [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Version' => '13',
            'Sec-WebSocket-Key' => '1234567890',
        ]);

        $application = $this->getMock(Application::class);
        $socket = $this->getMock(Socket::class);

        $response = $this->protocol->createResponse($application, $request, $socket);

        $this->assertInstanceOf(WebSocketResponse::class, $response);
    }

    /**
     * @depends testCreateResponse
     */
    public function testCreateResponseWithSubProtocol()
    {
        $subProtocols = ['chat.example.com', 'irc.example.com'];

        $request = new BasicRequest('GET', 'http://example.com', [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Version' => '13',
            'Sec-WebSocket-Key' => '1234567890',
            'Sec-WebSocket-Protocol' => implode(',', $subProtocols),
        ]);

        $application = $this->getMock(SubProtocol::class);
        $application->expects($this->once())
            ->method('selectSubProtocol')
            ->with($subProtocols)
            ->will($this->returnValue($subProtocols[0]));

        $socket = $this->getMock(Socket::class);

        $response = $this->protocol->createResponse($application, $request, $socket);

        $this->assertInstanceOf(WebSocketResponse::class, $response);

        $connection = $response->getConnection();
        $this->assertSame($subProtocols[0], $connection->getSubProtocol());
    }

    /**
     * @depends testCreateResponse
     */
    public function testCreateResponseWithInvalidRequest()
    {
        $request = new BasicRequest('GET', 'http://example.com', [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Version' => '13',
            // No key provided.
        ]);

        $application = $this->getMock(Application::class);
        $socket = $this->getMock(Socket::class);

        $response = $this->protocol->createResponse($application, $request, $socket);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::BAD_REQUEST, $response->getStatusCode());
    }

    public function testValidateResponse()
    {
        $request = new BasicRequest('GET', 'http://example.com', [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Version' => '13',
            'Sec-WebSocket-Key' => '1234567890',
        ]);

        $response = new BasicResponse(Response::SWITCHING_PROTOCOLS, [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Accept' => 'qrAsbG+EeIo8ooFLgckbiuFt1YE=',
        ]);

        $this->assertTrue($this->protocol->validateResponse($request, $response));
    }

    /**
     * @return array
     */
    public function getInvalidResponses()
    {
        return [
            [new BasicResponse(Response::OK)],
            [new BasicResponse(Response::SWITCHING_PROTOCOLS, [
                'Connection' => 'close'
            ])],
            [new BasicResponse(Response::SWITCHING_PROTOCOLS, [
                'Connection' => 'upgrade',
                'Upgrade' => 'invalid',
            ])],
            [new BasicResponse(Response::SWITCHING_PROTOCOLS, [
                'Connection' => 'upgrade',
                'Upgrade' => 'websocket',
                // No key provided.
            ])],
            [new BasicResponse(Response::SWITCHING_PROTOCOLS, [
                'Connection' => 'upgrade',
                'Upgrade' => 'websocket',
                'Sec-WebSocket-Accept' => 'invalid-accept',
            ])]
        ];
    }

    /**
     * @dataProvider getInvalidResponses
     *
     * @param \Icicle\Http\Message\Response $response
     */
    public function testValidateResponseWithInvalidResponse(Response $response)
    {
        $request = new BasicRequest('GET', 'http://example.com', [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Version' => '13',
            'Sec-WebSocket-Key' => '1234567890',
        ]);

        $this->assertFalse($this->protocol->validateResponse($request, $response));
    }
}
