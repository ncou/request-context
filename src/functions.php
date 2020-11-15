<?php

declare(strict_types=1);

use Chiron\Core\Exception\ScopeException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

if (! function_exists('get_current_request')) {
    /**
     * Get current request (fresh instance from the container).
     *
     * @throws ScopeException If the request is not binded in the container.
     *
     * @return string
     */
    function get_current_request(): string
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
     * @return string
     */
    function get_current_uri(): UriInterface
    {
        return get_current_request()->getUri();
    }
}
