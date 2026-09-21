<?php
namespace Vanderbilt\REDCap\Classes\Router\Middlewares;


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Vanderbilt\REDCap\Classes\Router\MiddlewareInterface;
use Vanderbilt\REDCap\Classes\Router\RequestHandlerInterface;

/**
 * middlewqare that extracts the route matching the schema needed
 * in REDC
 */
class ExtractRouteMiddleware implements MiddlewareInterface
{
    private $baseURL;
    public function __construct($baseURL='')
    {
        $this->baseURL = $baseURL;
    }

    private function joinPaths($part1, $part2) {
        // Trim slashes from the right side of the first part and from the left side of the second part
        $part1 = rtrim($part1, '/');
        $part2 = ltrim($part2, '/');
        
        // Join the two parts with a single slash
        return $part1 . '/' . $part2;
    }
    

    public function __invoke(Request $request, RequestHandlerInterface $handler): Response {
        $queryParams = $request->getQueryParams();
    
        // Check if the 'route' parameter is present in the query string
        if (isset($queryParams['route'])) {
            // Extract the route
            $route = $queryParams['route'];
            $fullRoute = $this->joinPaths($this->baseURL, $route);
    
            // Rewrite the URI with the extracted route
            $uri = $request->getUri()->withPath($fullRoute);
    
            // Replace the URI in the request
            $request = $request->withUri($uri);
        }
    
        // Process the request with the next middleware or route
        return $handler->handle($request);
    }
}