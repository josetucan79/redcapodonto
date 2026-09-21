<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Vanderbilt\REDCap\Classes\Router\Router;
use Vanderbilt\REDCap\Classes\Router\Middlewares\ExtractRouteMiddleware;

require_once dirname(__DIR__,3).'/redcap_connect.php';

$baseURL = APP_PATH_WEBROOT.'Rewards/api';

// Example usage
$app = new Router();
$app->add(new ExtractRouteMiddleware($baseURL));

$app->get($baseURL.'/', function(ServerRequestInterface $request, ResponseInterface $response, $params) {
    $payload =  'Lambda function executed from root';
    $response->getBody()->write($payload);
    return $response;
});

$app->get($baseURL.'/products/{id:[0-9]+}[/{test}]', function(ServerRequestInterface $request, ResponseInterface $response, array $params) {
    $id = $params['id'];
    $data = [
        'productId' => $id,
        'name' => 'Sample Product',
        'description' => 'This is a sample product description with a test part.',
    ];
    $payload = json_encode($data);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json'); // The response will be created by the router
});

// Run the application
$app->run();