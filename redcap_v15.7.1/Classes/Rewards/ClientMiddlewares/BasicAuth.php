<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ClientMiddlewares;

use GuzzleHttp\Middleware;


class BasicAuth  {
    private $username;
    private $password;

    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    function __invoke() {
        return $basicAuthMiddleware = Middleware::mapRequest(function ($request)  {
            $auth = base64_encode($this->username . ':' . $this->password);
            $request = $request->withHeader('Authorization', 'Basic ' . $auth);
            
            return $request;
        });
    }
}