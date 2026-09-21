<?php
namespace Vanderbilt\REDCap\Classes\Router;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

interface MiddlewareInterface
{
    public function __invoke(Request $request, RequestHandlerInterface $handler): Response;
}