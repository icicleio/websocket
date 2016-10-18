# WebSockets for Icicle

**Asynchronous, non-blocking WebSocket server.**

This library is a component for [Icicle](https://github.com/icicleio/icicle) that provides an asynchronous WebSocket server that can handle normal HTTP requests and WebSocket requests on the same port. Like other Icicle components, this library uses [Coroutines](https://icicle.io/docs/manual/coroutines/) built from [Awaitables](https://icicle.io/docs/manual/awaitables/) and [Generators](http://www.php.net/manual/en/language.generators.overview.php) to make writing asynchronous code more like writing synchronous code.

[![Build Status](https://img.shields.io/travis/icicleio/websocket/v1.x.svg?style=flat-square)](https://travis-ci.org/icicleio/websocket)
[![Coverage Status](https://img.shields.io/coveralls/icicleio/websocket/v1.x.svg?style=flat-square)](https://coveralls.io/r/icicleio/websocket)
[![Semantic Version](https://img.shields.io/github/release/icicleio/websocket.svg?style=flat-square)](http://semver.org)
[![MIT License](https://img.shields.io/packagist/l/icicleio/websocket.svg?style=flat-square)](LICENSE)
[![@icicleio on Twitter](https://img.shields.io/badge/twitter-%40icicleio-5189c7.svg?style=flat-square)](https://twitter.com/icicleio)

#### Documentation and Support

- [Full API Documentation](https://icicle.io/docs)
- [Official Twitter](https://twitter.com/icicleio)
- [Gitter Chat](https://gitter.im/icicleio/icicle)

##### Requirements

- PHP 5.5+ for v0.1.x branch (current stable) and v1.x branch (mirrors current stable)
- PHP 7 for v2.0 (master) branch supporting generator delegation and return expressions

##### Suggested

- [openssl extension](http://php.net/manual/en/book.openssl.php): Required to create secure WebSocket servers.

##### Installation

The recommended way to install is with the [Composer](http://getcomposer.org/) package manager. (See the [Composer installation guide](https://getcomposer.org/doc/00-intro.md) for information on installing and using Composer.)

Run the following command to use this library in your project: 

```bash
composer require icicleio/websocket
```

You can also manually edit `composer.json` to add this library as a project requirement.

```js
// composer.json
{
    "require": {
        "icicleio/websocket": "^0.1"
    }
}
```

#### Example

The example below creates a simple HTTP server that accepts WebSocket connections on the path `/echo`, otherwise responding with a 404 status code on other paths.

```php
#!/usr/bin/env php
<?php

require '/vendor/autoload.php';

use Icicle\Http\Message\{BasicResponse, Request, Response};
use Icicle\Http\Server\RequestHandler;
use Icicle\Loop;
use Icicle\Socket\Socket;
use Icicle\WebSocket\{Application, Connection, Server\Server};

$echo = new class implements Application {
    /**
     * {@inheritdoc}
     */
    public function onHandshake(Response $response, Request $request, Socket $socket): Response
    {
        // This method provides an opportunity to inspect the Request and Response before a connection is accepted.
        // Cookies may be set and returned on a new Response object, e.g.: return $response->withCookie(...);

        return $response; // No modification needed to the response, so the passed Response object is simply returned.
    }

    /**
     * {@inheritdoc}
     */
    public function onConnection(Connection $connection, Response $response, Request $request): Generator
    {
        // The Response and Request objects used to initiate the connection are provided for informational purposes.
        // This method will primarily interact with the Connection object.

        yield from $connection->send('Connected to echo WebSocket server powered by Icicle.');

        // Messages are read through an Observable that represents an asynchronous set. There are a variety of ways
        // to use this asynchronous set, including an asynchronous iterator as shown in the example below.

        $observable = $connection->read(); // Connection::read() returns an observable of received Message objects.
        $iterator = $observable->getIterator();

        while (yield from $iterator->isValid()) {
            /** @var \Icicle\WebSocket\Message $message */
            $message = $iterator->getCurrent();

            if ($message->getData() === 'close') {
                yield from $connection->close(); // Close the connection if the message contains only 'close'
            } else {
                yield from $connection->send($message); // Echo the message back to the client.
            }
        }

        /** @var \Icicle\WebSocket\Close $close */
        $close = $iterator->getReturn(); // Only needs to be called if the close reason is needed.
    }
};

$server = new Server(new class ($echo) implements RequestHandler {
    /** @var \Icicle\WebSocket\Application */
    private $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    public function onRequest(Request $request, Socket $socket): Generator
    {
        if ($request->getUri()->getPath() === '/echo') {
            return $this->application; // Return a WebSocket Application to create a WebSocket connection.
        }

        $response = new BasicResponse(Response::NOT_FOUND, [
            'Content-Type' => 'text/plain',
        ]);

        yield from $response->getBody()->end('Resource not found.');

        return $response; // Return a regular HTTP response for other request paths.
    }

    public function onError(int $code, Socket $socket): Response
    {
        return new BasicResponse($code);
    }
});

$server->listen(8080);

Loop\run();
```
