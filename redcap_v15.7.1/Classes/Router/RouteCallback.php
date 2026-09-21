<?php

namespace Vanderbilt\REDCap\Classes\Router;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class RouteCallback
{
    private $callback;

    /**
     * Constructor to accept any callable.
     *
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Handle method to apply the callback with the given parameters.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        return call_user_func($this->callback, $request, $response, $args);
    }
}
