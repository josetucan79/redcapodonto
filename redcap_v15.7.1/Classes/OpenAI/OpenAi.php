<?php
namespace Vanderbilt\REDCap\Classes\OpenAI;
class OpenAi
{
    private $headers;
    private $contentTypes;
    public $curlInfo = [];
    public $baseUrl = '';

    public function __construct($apiKey, $endpoint = "")
    {
        $this->contentTypes = [
            "application/json"    => "Content-Type: application/json",
            "multipart/form-data" => "Content-Type: multipart/form-data",
        ];

        if($endpoint != "") {
            $this->baseUrl = $endpoint;
        }

        $this->headers = [
            $this->contentTypes["application/json"],
        ];

        if ($apiKey != '') { // apiKey may be empty, in case user entered details of locally hosted OpenAI compatible AI service
            // Set this header for "Microsoft Azure OpenAI" service
            $this->headers[] = "api-key: $apiKey";
            // Set this header for OpenAI compatible AI services if entered at settings
            $this->headers[] = "Authorization: Bearer $apiKey";
        }
    }

    /* Determine if service details entered are of Azure OpenAI (It will return false for OpenAI compatible AI services) */
    public function isAzureOpenAISeriviceSet() {
        // Check if URL is of format "https://{your-resource-name}.openai.azure.com/..."
        return strpos($this->baseUrl, '.openai.azure.com') > 0;
    }
    public function chat($options, $openai_api_version)
    {
        // Build endpoint URL
        $url = $this->baseUrl . "chat/completions";
        if ($this->isAzureOpenAISeriviceSet()) {
            $url .= "?api-version=".urlencode($openai_api_version);
        }

        return http_post($url, $options, null, 'application/json', "", $this->headers);
    }
}