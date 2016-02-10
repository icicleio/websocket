<?php
namespace Icicle\WebSocket;

interface SubProtocol extends Application
{
    /**
     * This method should select a sub protocol to use from an array of protocols provided in the request.
     *
     * @param string[] $protocols
     *
     * @return string
     */
    public function selectSubProtocol(array $protocols);
}
