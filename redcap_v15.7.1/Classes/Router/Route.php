<?php
namespace Vanderbilt\REDCap\Classes\Router;

class Route {
    private $method;
    private $path;

    /**
     *
     * @var callable
     */
    private $handler;
    private $pattern;
    private $middlewares = [];

    public function __construct($method, $path, $handler) {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
        $this->pattern = $this->createPattern($path);
    }

    /**
     *
     * @param MiddlewareInterface|callable $middleware
     * @return Route
     */
    public function add($middleware) {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function getMiddlewares() {
        return $this->middlewares;
    }

    private function createPattern($path) {
        // Escape forward slashes
        $path = str_replace('/', '\/', $path);

        // Handle optional segments first
        $path = $this->convertOptionalSegments($path);

        // Convert placeholders to named capture groups
        $path = preg_replace_callback('/\{(\w+)(?::([^}]+))?\}/', function ($matches) {
            $name = $matches[1];
            $regex = isset($matches[2]) ? $matches[2] : '[^\/]+';
            return '(?P<' . $name . '>' . $regex . ')';
        }, $path);

        // Add start and end delimiters
        return '/^' . $path . '$/';
    }

    private function convertOptionalSegments($path) {
        $pattern = '/\[(\/?[^\[\]]+)\]/';
        while (preg_match($pattern, $path, $matches, PREG_OFFSET_CAPTURE)) {
            $segment = $matches[1][0];
            $offset = $matches[0][1];
            
            // Ensure the segment is not contained within curly braces
            if (substr_count(substr($path, 0, $offset), '{') === substr_count(substr($path, 0, $offset), '}')) {
                $path = substr_replace($path, '(?:' . $segment . ')?', $offset, strlen($matches[0][0]));
            } else {
                // Skip this segment as it's within curly braces
                $pattern = '/\[(\/?[^\[\]]+)\](?![^{}]*\})/';
            }
        }
        return $path;
    }

    public function matches($method, $path) {
        if ($this->method !== $method) {
            return false;
        }
        return preg_match($this->pattern, $path);
    }

    /**
     *
     * @return RouteCallback
     */
    public function getHandler() {
        return new RouteCallback($this->handler);
    }

    public function getParams($path) {
        $matches = [];
        preg_match($this->pattern, $path, $matches);
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

}
