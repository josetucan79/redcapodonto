<?php
namespace Vanderbilt\REDCap\Classes\Utility;

use Exception;

class UrlChecker
{
    private $url;
    private $errors = [];

    public function __construct($url)
    {
        $this->url = $url;
    }

    // Check if there are errors
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    // Get the errors
    public function getErrors(): array
    {
        return $this->errors;
    }

    // Reset the errors
    public function resetErrors(): void
    {
        $this->errors = [];
    }

    // Method to check URL using cURL
    public function checkWithCurl(): bool
    {
        $this->resetErrors();

        if (!function_exists('curl_init')) {
            $this->errors[] = "cURL is not available.";
            return false;
        }

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            $this->errors[] = $error;
            return false;
        }

        return $httpCode >= 200 && $httpCode < 400;
    }

    // Method to check URL using file_get_contents
    public function checkWithFileGetContents(): bool
    {
        $this->resetErrors();

        if (!ini_get('allow_url_fopen')) {
            $this->errors[] = "allow_url_fopen is disabled.";
            return false;
        }

        $options = [
            "http" => [
                "method" => "HEAD",
                "timeout" => 10,
            ],
        ];
        $context = stream_context_create($options);

        try {
            $headers = @file_get_contents($this->url, false, $context);
            return $headers !== false;
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return false;
        }
    }

    // Method to check URL using fsockopen
    public function checkWithFsockopen(): bool
    {
        $this->resetErrors();

        $parsedUrl = parse_url($this->url);
        $host = $parsedUrl['host'] ?? '';
        $port = ($parsedUrl['scheme'] ?? '') === 'https' ? 443 : 80;

        $connection = @fsockopen($host, $port, $errno, $errstr, 10);

        if ($connection) {
            fclose($connection);
            return true;
        }

        $this->errors[] = $errstr;
        return false;
    }

    // Comprehensive check combining all methods
    public function checkComprehensive(): bool
    {
        $this->resetErrors();

        if ($this->checkWithCurl()) {
            return true;
        }

        if ($this->checkWithFileGetContents()) {
            return true;
        }

        return $this->checkWithFsockopen();
    }
}
