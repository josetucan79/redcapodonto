<?php
namespace Vanderbilt\REDCap\Classes\Router;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

class MiddlewareRunner implements RequestHandlerInterface
{
    private $middlewares;
    private $finalHandler;
    private $response;

    public function __construct(array $middlewares, callable $finalHandler, ResponseInterface $response)
    {
        $this->middlewares = $middlewares;
        $this->finalHandler = $finalHandler;
        $this->response = $response;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (empty($this->middlewares)) {
            return ($this->finalHandler)($request);
        }

        $middleware = array_shift($this->middlewares);
        return $middleware($request, $this);
    }
}
