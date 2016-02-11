# WebSockets for Icicle

**Asynchronous, non-blocking WebSocket server.**

This library is a component for [Icicle](https://github.com/icicleio/icicle) that provides an asynchronous WebSocket server that can handle normal HTTP requests and WebSocket requests on the same port. Like other Icicle components, this library uses [Promises](https://github.com/icicleio/icicle/wiki/Promises) and [Generators](http://www.php.net/manual/en/language.generators.overview.php) for asynchronous operations that may be used to build [Coroutines](https://github.com/icicleio/icicle/wiki/Coroutines) to make writing asynchronous code more like writing synchronous code.

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

- PHP 5.5+

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
