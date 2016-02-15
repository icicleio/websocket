<?php
namespace Icicle\Tests\WebSocket\Protocol;

use Icicle\Http\Message\BasicRequest;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Http\Message\Uri;
use Icicle\Socket\Socket;
use Icicle\Tests\WebSocket\TestCase;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Protocol\DefaultProtocolMatcher;

class DefaultProtocolMatcherTest extends TestCase
{
    /**
     * @var \Icicle\WebSocket\Protocol\DefaultProtocolMatcher
     */
    protected $matcher;

    public function setUp()
    {
        $this->matcher = new DefaultProtocolMatcher();
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

        $response = $this->matcher->createResponse($application, $request, $socket);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function getInvalidRequests()
    {
        return [
            [new BasicRequest('POST', 'http://example.com'), Response::METHOD_NOT_ALLOWED],
            [new BasicRequest('GET', 'http://example.com', [
                'Connection' => 'upgrade',
                'Upgrade' => 'websocket',
                'Sec-WebSocket-Version' => '8',
                'Sec-WebSocket-Key' => '1234567890',
            ]), Response::UPGRADE_REQUIRED],
            [new BasicRequest('GET', 'http://example.com', [
                'Connection' => 'close',
            ]), Response::UPGRADE_REQUIRED],
            [new BasicRequest('GET', 'http://example.com', [
                'Connection' => 'upgrade',
                'Upgrade' => 'invalid'
            ]), Response::UPGRADE_REQUIRED],
        ];
    }

    /**
     * @dataProvider getInvalidRequests
     * @depends testCreateResponse
     *
     * @param \Icicle\Http\Message\Request $request
     * @param int $code
     */
    public function testCreateResponseInvalidRequest(Request $request, $code)
    {
        $application = $this->getMock(Application::class);
        $socket = $this->getMock(Socket::class);

        $response = $this->matcher->createResponse($application, $request, $socket);

        $this->assertSame($code, $response->getStatusCode());
    }

    public function testCreateRequest()
    {
        $uri = $this->getMock(Uri::class);
        $protocols = ['chat.example.com', 'chat.example.org'];

        $request = $this->matcher->createRequest($uri, $protocols);

        $this->assertInstanceOf(Request::class, $request);

        $this->assertSame('upgrade', strtolower($request->getHeader('Connection')));
        $this->assertSame('websocket', strtolower($request->getHeader('Upgrade')));
        $this->assertSame($protocols, array_map('trim', explode(',', $request->getHeader('Sec-WebSocket-Protocol'))));
    }
}
