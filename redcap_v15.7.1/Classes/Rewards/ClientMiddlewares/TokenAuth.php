<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ClientMiddlewares;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\ClientException;

class TokenAuth  {
    /**
     *
     * @var string
     */
    private $tokenURL;

    /**
     *
     * @var string
     */
    private $clientID;

    /**
     *
     * @var string
     */
    private $clientSecret;

    /**
     *
     * @var TokenProviderInterface
     */
    private $tokenProvider;

    /**
     *
     * @var array
     */
    private $options;

    

    /**
     *
     * @param string $tokenURL
     * @param string $clientID
     * @param string $clientSecret
     * @param TokenProviderInterface $tokenProvider
     * @param array $options
     */
    public function __construct($tokenURL, $clientID, $clientSecret, $tokenProvider, $options=[])
    {
        $this->tokenURL = $tokenURL;
        $this->clientID = $clientID;
        $this->clientSecret = $clientSecret;
        $this->options = $options;
        $this->tokenProvider = $tokenProvider;
    }

    private function fetchToken() {
        try {
            $client = new Client([
                'base_uri' => $this->tokenURL,
                // 'auth'=>[$username, $password],
            ]);
            $params = [
                'client_id'=> $this->clientID,
                'client_secret'=> $this->clientSecret,
                'scope'=> 'raas.all',
                // 'audience'=> $this->baseURL, // 'https://api.tangocard.com/'
                'audience'=> 'https://api.tangocard.com/',
                'grant_type'=> 'client_credentials',
            ];
            $response = $client->post('',[
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $params,
            ]);
            return json_decode($response->getBody(), true);
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            if($th instanceof ClientException) {
                $response = $th->getResponse();
                $responseBodyAsString = $response->getBody()->getContents();
                $message .= " $responseBodyAsString";
            }
            throw new Exception($message, $th->getCode(), $th);
        }
    }

    /**
     * try to get an access token from the provider.
     * if not available, fetch it first, then return it
     *
     * @return string|null the token or null if no token available
     */
    private function getOrFetchToken() {
        $token = $this->tokenProvider->getToken();
        if($token) return $token;
        
        $data = $this->fetchToken();
        $this->tokenProvider->storeToken($data);
        return $this->tokenProvider->getToken();
    }

    function __invoke() {
        return Middleware::mapRequest(function ($request)  {
            $token = $this->getOrFetchToken();
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
            return $request;
        });
    }
}