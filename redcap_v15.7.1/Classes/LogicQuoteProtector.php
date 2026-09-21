<?php
/**
 * Takes a string and replaces any "quoted" or 'quoted' strings with placeholders
 * like {123123123}.  You can then replace the strings back in.
 * https://regex101.com/r/lhmjed/1
 */
class LogicQuoteProtector
{
    const PATTERN = '/(["\'])(.*?)\1/s';
  
    private $count;
    private $original;
    private $substitute;
    private $placeholders    = array();
    private $strings         = array();
	private $placeholdersNaN = array();
	private $stringsNaN      = array();
 
    public function sub($string, $ignoreNaN=true) {
        $this->original = $string;
        // Search for matches
        $this->substitute = preg_replace_callback (self::PATTERN, array($this, "substitute")  ,  $string, -1, $this->count);
		if (!$ignoreNaN && !empty($this->placeholdersNaN)) {
			$this->substitute = str_replace($this->placeholdersNaN, $this->stringsNaN, $this->substitute);
		}
        return $this->substitute;
    }
 
    public function unsub($string, $handleJavascriptLineBreaks=false) {
        // Replace all line breaks with space to prevent JS errors on the page
        if ($handleJavascriptLineBreaks) {
            foreach ($this->strings as &$thisString) {
                $thisString = str_replace(["\r\n", "\r", "\n"], " ", $thisString);
            }
        }
        return str_replace($this->placeholders, $this->strings, $string);
    }
 
    private function substitute($matches) {
		// Create a unique string to be used for substitution
		global $RCLogicQuoteProtectorPlaceholder, $RCLogicQuoteProtectorPlaceholderIncrement;
		if (!isset($RCLogicQuoteProtectorPlaceholder)) {
			$RCLogicQuoteProtectorPlaceholder = sha1(rand());
			$RCLogicQuoteProtectorPlaceholderIncrement = 1;
		} else {
			$RCLogicQuoteProtectorPlaceholderIncrement++;
		}
		$placeholder = "{" . $RCLogicQuoteProtectorPlaceholder . "-" . sprintf('%07d', $RCLogicQuoteProtectorPlaceholderIncrement) . "}";
		// If the matched value is NaN, then add to special array to possible replacement later
		if (substr($matches[0], 1, -1) === 'NaN') {
			$this->placeholdersNaN[] = $placeholder;
			$this->stringsNaN[]      = $matches[0];
		}
		// Add to arrays
        $this->placeholders[]   = $placeholder;
        $this->strings[]        = $matches[0];
        return $placeholder;
    }
}