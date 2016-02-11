<?php

/*
 * This file is part of the WebSocket package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015-2016 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\WebSocket;

use Icicle\Tests\WebSocket\Stub\CallbackStub;

/**
 * Abstract test class with methods for creating callbacks and asserting runtimes.
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Creates a callback that must be called $count times or the test will fail.
     *
     * @param int $count Number of times the callback should be called.
     *
     * @return callable|\PHPUnit_Framework_MockObject_MockObject Object that is callable and expects to be called the
     * given number of times.
     */
    public function createCallback($count)
    {
        $mock = $this->getMock(CallbackStub::class);
        
        $mock->expects($this->exactly($count))
            ->method('__invoke');

        return $mock;
    }
}
