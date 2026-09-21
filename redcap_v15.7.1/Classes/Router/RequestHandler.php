<?php
namespace Vanderbilt\REDCap\Classes\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    /**
     *
     * @var mixed
     */
    private $handler;
    /**
     *
     * @var array
     */
    private $params;

    /**
     *
     * @var ResponseInterface
     */
    private $response;

    public function __construct($handler, $response, $params=[])
    {
        $this->handler = $handler;
        $this->params = $params;
        $this->response = $response;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = $this->handler;
        $params = $this->params;
        
        $response = $this->response;

        if (is_array($handler)) {
            list($controller, $action) = $handler;
            $controllerInstance = new $controller();
            $result = call_user_func_array([$controllerInstance, $action], [$request, $response, $params]);
        } elseif (is_callable($handler)) {
            $result = call_user_func_array($handler, [$request, $response, $params]);
        }

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        // Assuming the result is a string or other type of response content
        $response->getBody()->write($result);

        return $response;
    }

}
