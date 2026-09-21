<?php
namespace Vanderbilt\REDCap\Classes\Utility;

/**
 * Simple template engine for replacing placeholders with actual values.
 */
class TemplateEngine {
    /**
     * Replaces placeholders in a template string with actual values from data array.
     *
     * @param string $template The template string with placeholders.
     * @param array $data Associative array of data to replace placeholders.
     * @return string The processed string with placeholders replaced.
     */
    public static function render($template, $data) {
        // Find all occurrences of the placeholder with parameters
        preg_match_all("/\[(?<key>\w+)(?::(?<params>[^\]]+))?\]/", $template, $matches, PREG_SET_ORDER); 
        foreach ($matches as $match) {
            // $match[0] is the full placeholder, $match[1] is the key, and $match[2] are the parameters
            $placeholder = $match[0]; // The entire parameter string after the colon
            $key = $match['key']; // The entire parameter string after the colon
            if(!array_key_exists($key, $data)) continue;
            $valueOrCallback = $data[$key];
            $value = $valueOrCallback; // initially set to $valueOrCallback. if is not a callable, then we are done 
            // Check if the value is callable and is not a string or if it is a valid object method
            if (
                (is_callable($valueOrCallback) && !is_string($valueOrCallback)) || 
                (is_object($valueOrCallback) && is_callable([$valueOrCallback, '__invoke']))
            ) {
                $paramString = $match['params'] ?? null; // The entire parameter string after the colon
                $params = !is_null($paramString) ? static::extractParams($paramString) : [];
                $value = call_user_func_array($valueOrCallback, $params);
            }
            $template = str_replace($placeholder, $value, $template);
        }
        return $template;
    }

    protected static function extractParams($paramString) {
        // Split the parameters by comma, ignoring commas preceded by a backslash
        $params = preg_split('/(?<!\\\\),/', $paramString);

        // Remove backslashes before commas in the parameters
        $params = array_map(function($param) {
            return str_replace('\\,', ',', $param);
        }, $params);
        return $params;
    }
    
}
