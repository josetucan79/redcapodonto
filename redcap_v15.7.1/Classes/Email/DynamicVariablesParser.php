<?php
namespace Vanderbilt\REDCap\Classes\Email;

use Message;
use Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers\PlaceholderReplacerFactory;
use Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers\PlaceholderReplacerInterface;

class DynamicVariablesParser {
    private $placeholders = [];

    public function addPlaceHolder(PlaceholderReplacerInterface $placeholder) {
        $this->placeholders[] = $placeholder;
    }

    /**
     * extract all placeholders in a string
     * placeholders are strings surrounded by square brackets
     *
     * @param string $string
     * @return array list of unique placehodlers
     */
    public function extract($string) {
        $pattern = '/\[([a-zA-Z0-9_-]+)\]/';
        preg_match_all($pattern, $string, $matches);
        return array_unique($matches[1]);
    }

    /**
     *
     * @param string $text
     * @param string $useremail
     * @return string
     */
    public function parse($text, $useremail) {
        $placeholders = $this->extract($text);
        foreach ($placeholders as $placeholder) {
            $replacer = PlaceholderReplacerFactory::make($placeholder, $useremail);
            if(!($replacer instanceof PlaceholderReplacerInterface)) continue;
            $text = $replacer->replace($text);
        }
        return $text;
    }
}