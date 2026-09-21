<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Utilities;

class TextNormalizer
{
    public static function normalizeText($text)
    {
        if ($text === null) {
            return '';
        }
        
        // Convert to string if not already
        $text = (string) $text;
        
        // Decode HTML entities that might be present in one source but not the other
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove common invisible/problematic characters
        $text = str_replace([
            "\u{FEFF}",     // BOM
            "\u{200B}",     // Zero width space
            "\u{00A0}",     // Non-breaking space
        ], '', $text);
        
        // Normalize line endings to Unix-style
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Trim whitespace from beginning and end
        $text = trim($text);
        
        // Normalize internal whitespace - collapse multiple spaces/tabs to single space
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Normalize multiple consecutive newlines
        $text = preg_replace('/\n[ \t]*\n/', "\n\n", $text);
        
        // Convert to lowercase for case-insensitive comparison
        $text = mb_strtolower($text, 'UTF-8');
        
        return $text;
    }
}