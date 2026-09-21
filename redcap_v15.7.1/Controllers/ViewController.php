<?php

/**
 * Class ViewController
 *
 * This controller is responsible for fetching content from either a remote URL or a local file.
 * It can handle both absolute URLs (using cURL) and relative URLs (using the local file system).
 * If the URL is remote, it retrieves the content using cURL.
 * If the URL is local, it uses PHP's `require` function to execute any PHP within the file and capture the output.
 */
class ViewController extends Controller
{

    /**
     * Fetches content from a URL, either remote or local.
     *
     * This method checks if the provided URL is absolute (starting with http/https) or relative.
     * If the URL is absolute, it fetches the content using cURL.
     * If the URL is relative, it fetches the content from the local file system and executes any PHP code within the file.
     *
     * @return void
     */
    public function fetchContent()
    {
        // Get the URL from the GET request
        $url = $_GET['url'] ?? false;
        if ($url) {
            // Check if the URL is an absolute URL (starts with http or https)
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                // It's an absolute URL, use cURL to fetch the content
                $content = $this->fetchRemoteContent($url);
            } else {
                // It's a relative URL, adjust the url to full
                $baseURL = $this->getBaseUrl(APP_PATH_WEBROOT_FULL);
                $content = $this->fetchRemoteContent($baseURL.$url);
            }

            // Check if content was successfully fetched
            if ($content === false) {
                http_response_code(500);
                echo "Error fetching content";
            } else {
                echo $content; // Return the fetched HTML content
            }
        } else {
            http_response_code(400);
            echo "No URL provided";
        }
    }

    /**
     * Extracts the root URL (protocol, host, and port if present) from a given URL.
     *
     * @param string $url The full URL to process.
     * @return string The root URL containing the protocol, host, and port (if any).
     */
    private function getBaseUrl($url) {
        // Parse the URL
        $parsedUrl = parse_url($url);

        // Extract components
        $scheme = $parsedUrl['scheme'] ?? ''; // Protocol (e.g., https)
        $host = $parsedUrl['host'] ?? '';     // Host (e.g., redcap.test)
        $port = $parsedUrl['port'] ?? '';     // Port (e.g., 8080, if specified)

        // Reconstruct the base URL
        $base = $scheme . '://' . $host;
        if (!empty($port)) {
            $base .= ':' . $port; // Add port if it exists
        }

        return $base;
    }

    /**
     * Fetches content from a remote URL using cURL.
     *
     * This method sends an HTTP GET request to the provided remote URL and returns the response content.
     * It follows any redirects and returns the content as a string, or false if there was an error.
     *
     * @param string $url The absolute URL to fetch content from.
     * @return string|false The content of the remote URL, or false on failure.
     */
    private function fetchRemoteContent($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $content = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return $content;
    }

}