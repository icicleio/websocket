<?php
namespace Icicle\WebSocket\Protocol;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Response;
use Icicle\Socket\Socket;
use Icicle\Stream\MemorySink;
use Icicle\WebSocket\Application;
use Icicle\WebSocket\Protocol\Message\WebSocketResponse;
use Icicle\WebSocket\SubProtocol;

class Rfc6455Protocol implements Protocol
{
    const VERSION = '13';
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    const DEFAULT_KEY_LENGTH = 12;

    /**
     * @var mixed[]
     */
    private $options;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersionNumber()
    {
        return self::VERSION;
    }

    /**
     * {@inheritdoc}
     */
    public function isProtocol(Request $request)
    {
        $versions = array_map('trim', explode(',', $request->getHeader('Sec-WebSocket-Version')));

        return in_array(self::VERSION, $versions);
    }

    /**
     * {@inheritdoc}
     */
    public function createResponse(Application $application, Request $request, Socket $socket)
    {
        if (!$request->hasHeader('Sec-WebSocket-Key')) {
            $sink = new MemorySink('No WebSocket key header provided.');
            yield new BasicResponse(Response::BAD_REQUEST, [
                'Connection' => 'close',
                'Content-Length' => $sink->getLength(),
            ], $sink);
            return;
        }

        $headers = [
            'Connection' => 'upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Accept' => $this->responseKey(trim($request->getHeader('Sec-WebSocket-Key'))),
        ];

        if ($application instanceof SubProtocol) {
            $protocol = $application->selectSubProtocol(
                array_map('trim', explode(',', $request->getHeader('Sec-WebSocket-Protocol')))
            );

            if (strlen($protocol)) {
                $headers['Sec-WebSocket-Protocol'] = $protocol;
            }
        } else {
            $protocol = '';
        }

        /*
        $extensions = $application->selectExtensions(
            array_map('trim', explode(',', $request->getHeader('Sec-WebSocket-Extensions')))
        );

        if (!empty($extensions)) {
            $headers['Sec-WebSocket-Extensions'] = $extensions;
        }
        */

        $connection = new Rfc6455Connection(
            new Rfc6455Transporter($socket, false),
            $protocol,
            [],
            $this->options
        );

        $response = new BasicResponse(Response::SWITCHING_PROTOCOLS, $headers);

        yield new WebSocketResponse($application, $connection, $response);
    }

    /**
     * {@inheritdoc}
     */
    public function validateResponse(Request $request, Response $response)
    {
        if (Response::SWITCHING_PROTOCOLS !== $response->getStatusCode()) {
            return false;
        }

        if ('upgrade' !== strtolower($response->getHeader('Connection'))) {
            return false;
        }

        if ('websocket' !== strtolower($response->getHeader('Upgrade'))) {
            return false;
        }

        $key = $request->getHeader('Sec-WebSocket-Key');

        if (!$response->hasHeader('Sec-WebSocket-Accept')) {
            return false;
        }

        return $this->responseKey($key) === $response->getHeader('Sec-WebSocket-Accept');
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function responseKey($key)
    {
        return base64_encode(sha1($key . self::GUID, true));
    }

    /**
     * @param int $length Number of random bytes to generate for the encoded key.
     *
     * @return string
     */
    public function generateKey($length = self::DEFAULT_KEY_LENGTH)
    {
        return base64_encode(random_bytes($length));
    }
}
