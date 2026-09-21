<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

abstract class AbstractRewardProvider implements RewardProviderInterface
{

    /**
     *
     * @var Client
     */
    protected  $client;

    /**
     *
     * @var string
     */
    protected  $baseURL;

    public function __construct($baseURL, $clientOptions=[], $middlewares=[]) {
        $this->baseURL = $baseURL;
        $handlerStack = HandlerStack::create();
        $clientOptions = array_merge([
            'base_uri' => $baseURL,
            'handler'=>$handlerStack,
        ], $clientOptions);
        $this->client = new Client($clientOptions);

        foreach ($middlewares as $middleware) {
            $handlerStack->push($middleware);
        }
    }

    public function executeOperation($method, $params = [])
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$params);
        }
        throw new Exception("Operation not supported: $method");
    }

}