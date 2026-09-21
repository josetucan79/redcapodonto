<?php

/**
 * Class with utility functions for action tags
 */

class ActionTags {

    /** @var string The action tag regex assumes that a valid action tag consists of @ followed by captial letters only (with optional embedded hyphens), and is preceded by a space or a line break (or at the start of the string) and followed by a space or a line break (or end of string)  
     * See https://regex101.com/r/rK1eOs/1
     */
    const ANY_ACTION_TAG_REGEX = '/(?<=^|\s)@[A-Z]+(-[A-Z]+)*(?=\s|=|\(|$)/m';

    /** @var string This regex is used to check for a specific action tag ({AT} will be replaced with the specific action tag, e.g., @DEFAULT) */
    const SPECIFIC_ACTION_TAG_REGEX = '/(?<=^|\s){AT}(?=\s|=|\(|$)/m';

    /** @var string This regex is used to check for any deactivated action tags */
    const DEACTIVATED_ACTION_TAG_REGEX = '/(?<=^|\s)@\.OFF\.[A-Z]+(-[A-Z]+)*(?=\s|=|\(|$)/m';

    /** @var string The @DEACTIVATED-ACTION-TAGS marker */
    const DEACTIVATED_ACTION_TAG = "@DEACTIVATED-ACTION-TAGS";

    /**
     * Determine if text contains any action tags or a specific action tag
     * @param string $text The text to check that might contain an action tag
     * @param string|null $action_tag The specific action tag to check for
     * @return bool
     */
    public static function containsActionTags($text, $action_tag = null) {
        if (empty($text)) return false;
        // Add leading and trailing spaces to the text to ensure the regex will match the beginning/end of the string
        $text = " ".trim($text)." "; 
        if (empty($action_tag)) {
            return (preg_match_all(static::ANY_ACTION_TAG_REGEX, $text, $matches) > 0);
        }
        else {
            $regex = str_replace("{AT}", $action_tag, static::SPECIFIC_ACTION_TAG_REGEX);
            return (preg_match($regex, $text) > 0);
        }
    }

    /**
     * Get a list of all action tags in the text
     * @param string $text 
     * @return string[] 
     */
    public static function getActionTags($text) {
        preg_match_all(static::ANY_ACTION_TAG_REGEX, $text, $matches);
        return $matches[0];
    }

    /**
     * Get a list of all deactivated action tags in the text
     * @param string $text 
     * @return string[] 
     */
    public static function getDeactivatedActionTags($text) {
        preg_match_all(static::DEACTIVATED_ACTION_TAG_REGEX, $text, $matches);
        return $matches[0];
    }

    /**
     * Deactivate all (or certain) action tags in the text
     * @param string $text 
     * @param string[] $limit Limit deactivation to the tags listed here (optional; include full tag names, e.g., @HIDDEN)
     * @return string 
     */
    public static function deactivateActionTags($text, $limit = []) {
        $action_tags = static::getActionTags($text);
        foreach ($action_tags as $tag) {
            if (count($limit) && !in_array($tag, $limit)) continue;
            $pattern = str_replace("{AT}", $tag, static::SPECIFIC_ACTION_TAG_REGEX); 
            $replacement = str_replace("@", "@.OFF.", $tag);
            $text = preg_replace($pattern, $replacement, $text);
        }
        return $text;
    }

    /**
     * Reactivate all (or certain) deactivated action tags in the text
     * @param string $text 
     * @param string[] $limit Limit reactivation to the tags listed here (optional; include full tag names, e.g., @HIDDEN)
     * @return string 
     */
    public static function reactivateActionTags($text, $limit = []) {
        if (count($limit) == 0) $limit = static::getDeactivatedActionTags($text);
        foreach ($limit as $tag) {
            $tag_name = str_replace(".OFF.", "", substr($tag, 1));
            $pattern = str_replace("{AT}", "@\.OFF\.".$tag_name, static::SPECIFIC_ACTION_TAG_REGEX);
            $text = preg_replace($pattern, "@".$tag_name, $text);
        }
        return $text;
    }

    /**
     * Determine if the text contains any deactivated action tags
     * @param string $text 
     * @return bool 
     */
    public static function hasDeactivatedActionTags($text) {
        return (preg_match(static::DEACTIVATED_ACTION_TAG_REGEX, $text) > 0);
    }

    /**
     * Add the @DEACTIVATED-ACTION-TAGS marker to the text if it doesn't already contain it
     * @param string $text 
     * @return string 
     */
    public static function addDeactivatedActionTagsMarker($text) {
        if (!static::containsActionTags($text, static::DEACTIVATED_ACTION_TAG)) {
            $text = static::DEACTIVATED_ACTION_TAG." ".$text;
        }
        return $text;
    }

    /**
     * Remove the @DEACTIVATED-ACTION-TAGS marker from the text if it contains it
     * @param string $text 
     * @return string 
     */
    public static function removeDeactivatedActionTagsMarker($text) {
        if (static::containsActionTags($text, static::DEACTIVATED_ACTION_TAG)) {
            $pattern = str_replace("{AT}", static::DEACTIVATED_ACTION_TAG, static::SPECIFIC_ACTION_TAG_REGEX); 
            $text = trim(preg_replace($pattern, "", $text));
        }
        return $text;
    }
}