<?php
namespace Vanderbilt\REDCap\Classes\Utility\SpecialTag;

/**
 * Class SpecialTagParser
 *
 * This class parses an input string for one or more special tags.
 */
class SpecialTagParser {
    /** @var string */
    private $text;

    /**
     * Constructor.
     *
     * @param string $text The text to parse.
     */
    public function __construct($text) {
        $this->text = $text;
    }

    /**
     * Factory method to create a new instance from a string.
     *
     * @param string $text
     * @return SpecialTagParser
     */
    public static function fromString($text) {
        return new self($text);
    }

    /**
     * Extracts a single tag's parts and returns a SpecialTag instance.
     * If the tag does not match, returns null.
     *
     * @param string $tag The tag string to parse.
     * @return SpecialTag|null
     */
    public function extractTag($tag) {
        $pattern = '/
            (                           # Full match of the tag
              (                         # Optional event block
                \[
                  (?P<event_name>[^\]]*)
                \]
              )?
              \[                        # Start of the command block
                (?P<command>[A-Za-z0-9\(\)\._-]*)
                :?                      # Optional colon after the command
                (?P<param1>[^\]:]*)     # Optional param1 (up to a colon or closing bracket)
                :?                      # Optional colon before param2
                (?P<param2>[^\]:]*)     # Optional param2
                :?                      # Optional colon before param3
                (?P<param3>[^\]:]*)     # Optional param3
              (?:                       # Begin optional instance specifier group
                \]\[                   # Closes command block and opens instance block
                (?P<instance>
                    \d+|              # A number, or...
                    first-instance|
                    last-instance|
                    previous-instance|
                    next-instance|
                    current-instance|
                    new-instance
                )
              )?
              \]                       # Closing bracket for command or instance block
            )
        /mx';

        if (preg_match($pattern, $tag, $matches)) {
            $event = isset($matches['event_name']) && $matches['event_name'] !== '' ? $matches['event_name'] : null;
            $command = isset($matches['command']) ? $matches['command'] : null;
            $params = [];
            // Collect parameters in order. They may be empty strings.
            $params[] = (isset($matches['param1']) && $matches['param1'] !== '') ? $matches['param1'] : null;
            $params[] = (isset($matches['param2']) && $matches['param2'] !== '') ? $matches['param2'] : null;
            $params[] = (isset($matches['param3']) && $matches['param3'] !== '') ? $matches['param3'] : null;
            $instance = isset($matches['instance']) && $matches['instance'] !== '' ? $matches['instance'] : null;

            return new SpecialTag($event, $command, $params, $instance);
        }
        return null;
    }

    /**
     * Extracts all special tags found in the instance text.
     *
     * @return SpecialTag[] An array of SpecialTag objects.
     */
    public function extractAllTags() {
        $pattern = '/
            (                           # Full match of the tag
              (                         # Optional event block
                \[
                  (?P<event_name>[^\]]*)
                \]
              )?
              \[                        # Start of the command block
                (?P<command>[A-Za-z0-9\(\)\._-]*)
                :?                      # Optional colon after the command
                (?P<param1>[^\]:]*)     # Optional param1 (up to a colon or closing bracket)
                :?                      # Optional colon before param2
                (?P<param2>[^\]:]*)     # Optional param2
                :?                      # Optional colon before param3
                (?P<param3>[^\]:]*)     # Optional param3
              (?:                       # Begin optional instance specifier group
                \]\[                   # Closes command block and opens instance block
                (?P<instance>
                    \d+|              # A number, or...
                    first-instance|
                    last-instance|
                    previous-instance|
                    next-instance|
                    current-instance|
                    new-instance
                )
              )?
              \]                       # Closing bracket for command or instance block
            )
        /mx';

        $tags = [];
        if (preg_match_all($pattern, $this->text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $event = isset($match['event_name']) && $match['event_name'] !== '' ? $match['event_name'] : null;
                $command = isset($match['command']) ? $match['command'] : null;
                $params = [];
                $params[] = (isset($match['param1']) && $match['param1'] !== '') ? $match['param1'] : null;
                $params[] = (isset($match['param2']) && $match['param2'] !== '') ? $match['param2'] : null;
                $params[] = (isset($match['param3']) && $match['param3'] !== '') ? $match['param3'] : null;
                $instance = isset($match['instance']) && $match['instance'] !== '' ? $match['instance'] : null;

                $tags[] = new SpecialTag($event, $command, $params, $instance);
            }
        }
        return $tags;
    }
}


