<?php
namespace Vanderbilt\REDCap\Classes\Router;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Router {
    /**
     *
     * @var Route[]
     */
    private $routes = [];

    /**
     *
     * @var MiddlewareInterface
     */
    private $middlewares = [];
    
    /**
     *
     * @var Response
     */
    private $response;

    public function get($path, $handler) {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post($path, $handler) {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put($path, $handler) {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete($path, $handler) {
        return $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute($method, $path, $handler) {
        $route = new Route($method, $path, $handler);
        $this->routes[] = $route;
        return $route;
    }

    /**
     *
     * @return []]
     */
    public function getRoutes() {
        return $this->routes;
    }

    /**
     *
     * @param MiddlewareInterface|callable $middleware
     * @return Router
     */
    public function add($middleware) {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function run(ServerRequestInterface $request = null) {
        if ($request === null) {
            $request = $this->createRequestFromGlobals();
        }

        $this->dispatch($request);
    }

    private function createRequestFromGlobals() {
        return ServerRequest::fromGlobals();
    }

    public function dispatch(ServerRequestInterface $request) {
        $response = $this->createResponse();

        // Execute application-level middlewares
        $response = $this->processMiddlewares($request, $response, $this->middlewares, function ($request) use($response) {
            return $this->handleRoute($request, $response);
        });

        $this->sendResponse($response);
    }

    protected function handleRoute(ServerRequestInterface $request, $response)
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                $handler = $route->getHandler();
                $params = $route->getParams($path);
                $routeMiddlewares = $route->getMiddlewares();

                // Execute route-specific middlewares
                return $this->processMiddlewares($request, $response, $routeMiddlewares, function ($request) use ($handler, $params, $response) {
                    return $handler->handle($request, $response, $params);
                });
            }
        }

        return $this->createResponse(404, [], '404 Not Found');
    }

    /**

     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param MiddlewareInterface[] $middlewares
     * @param callable $finalHandler
     * @return ResponseInterface
     */
    protected function processMiddlewares($request, $response, $middlewares, $finalHandler)
    {
        $runner = new MiddlewareRunner($middlewares, $finalHandler, $response);
        return $runner->handle($request);
    }


    public function setResponse(Response $response) {
        $this->response = $response;
    }
    public function getResponse() {
        if(!$this->response instanceof Response) {
            $this->response = $this->createResponse();
        }
        return $this->response;
    }

    /**
     *
     * @param integer $status
     * @param array $headers
     * @param mixed $body
     * @param string $version
     * @param string|null $reason
     * @return Response
     */
    public function createResponse(int $status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null) {
        return new Response(...func_get_args());
    }

    private function createRequestHandler($handler, $params) {
        return new RequestHandler($handler, $params, $this);
    }

    private function sendResponse(ResponseInterface $response) {
        // Set HTTP response headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
        // Set the HTTP status code
        http_response_code($response->getStatusCode());

        // Output the response body
        echo $response->getBody();
    }
}
