<?php

declare(strict_types=1);

use Chiron\Core\Exception\ScopeException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

// TODO : réfléchir si on conserve ces 2 fonctions car elles ne servent à rien !!!!

if (! function_exists('get_current_request')) {
    /**
     * Get current request (fresh instance from the container).
     *
     * @throws ScopeException If the request is not binded in the container.
     *
     * @return ServerRequestInterface
     */
    function get_current_request(): ServerRequestInterface
    {
        return container(ServerRequestInterface::class);
    }
}

if (! function_exists('get_current_uri')) {
    /**
     * Get current request uri.
     *
     * @throws ScopeException If the request is not binded in the container.
     *
     * @return UriInterface
     */
    function get_current_uri(): UriInterface
    {
        return get_current_request()->getUri();
    }
}
