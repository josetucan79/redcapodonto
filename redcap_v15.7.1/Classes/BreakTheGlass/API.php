<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass;

use HttpClient;
use Vanderbilt\REDCap\Classes\BreakTheGlass\DTOs\AcceptDTO;

class API
{

    // endpoints URL templates
    const ENDPOINT_ACCEPT_2013 = 'api/epic/2013/Security/BreakTheGlass/AcceptBreakTheGlass/Security/BreakTheGlass/Accept';
    const ENDPOINT_ACCEPT = 'api/epic/2021/Security/BreakTheGlass/AcceptBreakTheGlass/Security/BreakTheGlass/Accept';
    const ENDPOINT_CANCEL = 'api/epic/2013/Security/BreakTheGlass/CancelBreakTheGlass/Security/BreakTheGlass/Cancel';
    const ENDPOINT_CHECK = 'api/epic/2013/Security/BreakTheGlass/CheckBreakTheGlass/Security/BreakTheGlass/AccessCheck';
    const ENDPOINT_INITIALIZE = 'api/epic/2013/Security/BreakTheGlass/InitializeBreakTheGlass/Security/BreakTheGlass/Initialize';

    private static $timeout = 30.0;
    private static $connect_timeout = 30.0;

    /**
     * Break the glass settings
     *
     * @var base URL for endpoints
     */
    private $base_url = '';

    /**
     * authorization used in post calls (bearer or basic)
     *
     * @var string
     */
    private $authorization = '';
    /**
     * cleint ID of the APP on the epic app orchard
     *
     * @var string
     */
    private $epic_client_ID = '';

    public function __construct($fhirBaseURL, $clientID)
    {
        $this->base_url = $this->makeBaseUrl($fhirBaseURL);
        $this->epic_client_ID = $clientID;
    }


    /**
     * set the base URL for the endpoints
     *
     * @return string
     */
    private function makeBaseUrl($fhirBaseURL)
    {
        $reg_exp = '/(?<base>.+?)api\/FHIR\/(?:DSTU2|STU3|R4)\/?$/i';
        $base_url = preg_replace($reg_exp, '\1', $fhirBaseURL ?? '');
        return $base_url;
    }

    /**
     * get the full URL of the break the glass endpoint
     *
     * @param string $endpoint
     * @return string
     */
    private function getEndpointUrl($endpoint)
    {
        $break_the_glass_endpoints = [
            self::ENDPOINT_ACCEPT,
            self::ENDPOINT_CANCEL,
            self::ENDPOINT_CHECK,
            self::ENDPOINT_INITIALIZE,
        ];
        if(!in_array($endpoint,$break_the_glass_endpoints)) throw new \Exception("The requested endpoint is not available", 1);
        
        // web service at Vanderbilt used fot testing purposes
        // $base_url = 'https://ic1-dev.service.vumc.org/Interconnect-DEV-WEBSVC/';
        return $this->base_url.$endpoint;
    }
 
    /**
     * This service logs an accepted Break-the-Glass form
     * to run through the action lists set up in Epic
     * 
     * to use the MRN as PatientID a PatientIDType of MRN must be specified
     * to use a login enabled user as UserID a UserIDType of SystemLogin must be specified
     *
     * @param AcceptDTO $params
     * @return mixed
     */
    function accept($accessToken, $params)
    {
        $url = $this->getEndpointUrl(self::ENDPOINT_ACCEPT);

        $authorization = $this->getAuthorization($accessToken);
        $response = $this->postData($url, $authorization, $params->getData());
        return $response;
    }

    /**
     * retrieve the authorization.
     * could be Bearer (FHIR) or Basic (non-OAuth2)
     * @throws Exception if no authorization method is available
     * @return string
     */
    private function getAuthorization($accessToken)
    {
        return "Bearer ".$accessToken;
    }



    /**
     * This service returns Break-the-Glass initialization information required for client
     * development to implement Break-the-Glass outside of Epic. The information includes
     * the data requirements for the reason and explanation fields, the legal message,
     * a list of possible reasons, the message to display for inappropriate access,
     * the default Hyperspace timeout in minutes, and any reason-specific overrides
     * for the explanation field's data requirement
     *
     * @return array
     */
    function initialize($accessToken)
    {
        $url = $this->getEndpointUrl(self::ENDPOINT_INITIALIZE);
        // log to database
        /* \Logging::logEvent($sql="",
            $object_type="redcap_glass_breaker",
            $event="MANAGE",
            $record="",
            $data_values="",
            $change_reason= "Initialize break the glass"
        ); */
        $authorization = $this->getAuthorization($accessToken);
        return $this->postData($url, $authorization);
    }

    /**
     * post data to remote endpoint
     * all Break the glass endpoints use the POST method
     *
     * @param string $url
     * @param array $data
     * @param array $settings HTTP request settings (headers, options)
     * 
     * @throws Exception if the HttpClient request fails
     * 
     * @return mixed json decoded data
     */
    private function postData($url, $authorization, $data=[], $settings=[])
    {
        $default_settings = [
            'options' => [
                'timeout' => self::$timeout,
                'connect_timeout' => self::$connect_timeout,
            ],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => $authorization,
                'Epic-Client-ID' => $this->epic_client_ID,
                // 'Epic-User-IDType' => $this->epic_user_type
            ],
            'form_params' => $data,
        ];
        $request_settings = array_replace_recursive($default_settings, $settings);
        $response = HttpClient::request('POST', $url, $request_settings);
        return json_decode($response->getBody(), true);
    }

}
