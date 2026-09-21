<?php
namespace Vanderbilt\REDCap\Classes\GeminiAI;
class GeminiAi
{
    private $headers;
    private $contentTypes;
    public $curlInfo = [];
    private
    const END_POINT_URL = "https://generativelanguage.googleapis.com/";

    public function __construct($apiKey, $apiModel, $apiVersion)
    {
        $this->contentTypes = [
            "application/json"    => "Content-Type: application/json",
            "multipart/form-data" => "Content-Type: multipart/form-data",
        ];

        $this->headers = [
            $this->contentTypes["application/json"],
            "api-key: $apiKey",
        ];

        if($apiKey != "") {
            $this->apiKey = $apiKey;
            $this->apiModel = $apiModel;
            $this->apiVersion = $apiVersion;
        }
    }

    public function chat($options)
    {
        // Build endpoint URL, exa.- https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$YOUR_API_KEY
        $url = self::END_POINT_URL . $this->apiVersion ."/models/".$this->apiModel.":generateContent?key=".urlencode($this->apiKey);

        return http_post($url, $options, null, 'application/json', "", $this->headers);
    }

    public function tokenCount($options)
    {
        // Build endpoint URL, exa.- https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$YOUR_API_KEY
        $url = self::END_POINT_URL . $this->apiVersion ."/models/".$this->apiModel.":countTokens?key=".urlencode($this->apiKey);

        return http_post($url, $options, null, 'application/json', "", $this->headers);
    }
}