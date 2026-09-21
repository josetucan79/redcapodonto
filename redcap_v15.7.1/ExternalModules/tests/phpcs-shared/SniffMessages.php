<?php namespace ExternalModules;

class SniffMessages{
	const CONFIG_FILENAME = 'config.json';

    const JS_EVAL = "Please avoid the javascript eval() function. It is not currently possible to ensure its safety via automated scanning.";
    const MISSING_NAMESPACE = "Function, class, and const definitions are only allowed in namespaced PHP files to avoid conflicts between modules and/or REDCap core.  Please add use statements or backslashes for all references to classes in the global space (search for 'new' and '::'), then add a namespace at the top of this file (perhaps the same namespace as your module class).";

	static private $configsByPath = [];

    static function doesLineContainJSEval($line){
        // Also match basic window['eval'] reverences like eslint does
        if(preg_match('/window\[[\'"]eval[\'"]\]/', $line)){
            return true;
        }

        /**
         * A false positive edge case in pdf.js
         */
        $line = str_replace('eval("require")', '', $line);

        /**
         * A false positive edge case in https://github.com/almende/vis/blob/master/dist/vis.js
         */
        $line = str_replace('eval)', '', $line);

        /**
         * A false positive edge case in https://github.com/dr01d3r/redcap-em-biospecimen-tracking/releases/download/v0.9.2-beta/biospecimen_tracking_v0.9.2.zip
         */
        $line = str_replace("'%eval%': eval", '', $line);
        $line = str_replace('"%eval%": eval', '', $line); // And a variant of it
        $line = str_replace('"%eval%":eval', '', $line); // Variant in https://github.com/OCTRI/REDCap-Vizr/releases/download/v3.0.1/vizr_v3.0.1.zip
        $line = str_replace("'%eval%':eval", '', $line); // Might exist out there somewhere.  It would be simpler to use regex to cover all these cases instead...

        $line = trim(static::clearQuotedSubStrings($line));
        if(preg_match('/\beval\b/', $line, $matches, PREG_OFFSET_CAPTURE)){
            if(str_starts_with($line, '*')){
                // This is most likely a multiline comment.
                return false;
            }

            $slashesIndex = strpos($line, '//');
            $allowedAdjacentChars = ['.', '-', '|'];
            foreach($matches as $match){
                $i = $match[1];
                if($slashesIndex !== false && $i > $slashesIndex){
                    // This is mostly likely a comment.
                    return false;
                }

                $previousChar = $line[$i-1] ?? '';
                $nextChar = $line[$i+4] ?? '';
                if(
                    !in_array($previousChar, $allowedAdjacentChars)
                    &&
                    !in_array($nextChar, $allowedAdjacentChars)
                ){
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Copied from REDCap's System::clearQuotedSubStrings()
     */
    private static function clearQuotedSubStrings($s){
		$newS = '';
	
		$lastC = null;
		$quoteType = null;
		for($i=0; $i<strlen($s); $i++){
			$c = $s[$i];
			
			if($quoteType === null){
				if(in_array($c, ['"', "'"])){
					$quoteType = $c;
				}

				$newS .= $c;
			}
			else if($c === $quoteType && $lastC !== '\\'){
				$quoteType = null;
				$newS .= $c;
			}
	
			$lastC = $c;
		}

		return $newS;
	}

	static function getConfig($path){
		$config = static::$configsByPath[$path] ?? false;
		if(!$config){
			if(!static::isModule($path)){
				// This is a plugin or hook.
				$config = [];
			}
			else{
				$config = json_decode(file_get_contents(static::CONFIG_FILENAME), true);
				if(!isset($config['namespace'])){
					throw new Exception('Error parsing config.json');
				}
			}

			static::setConfig($path, $config);
		}

		return $config;
	}

	static function setConfig($path, $config){
		static::$configsByPath[$path] = $config;
	}

	static function isModule($path){
		return file_exists($path . '/'. static::CONFIG_FILENAME);
	}

	static function formatMessage($message){
		$lines = explode("\n", $message);

		$newMessage = '';
		$codeIndentation = null;
		foreach($lines as $line){
			if($codeIndentation === null && !empty(trim($line))){
				preg_match('/\s*/', $line, $matches);
				$codeIndentation = $matches[0];
			}

			$newMessage .= str_replace($codeIndentation ?? '', '', $line) . "\n";
		}

		// Trim leading & trailing newlines AFTER handling $codeIndentation
		$newMessage = trim($newMessage);

		$newMessage .= "\n\n";

		return $newMessage;
	}

	static function getHardcodedTableName($s){
        /**
         * Prefix & suffix chosen per 'Permitted characters in unquoted identifiers' from https://dev.mysql.com/doc/refman/8.0/en/identifiers.html
         * This use case is similar to System::checkQuery().
         */
		if(preg_match('/(^|[^[0-9a-z$_^])(redcap_data|redcap_log_event)[0-9]*($|[^[0-9a-z$_])/i', $s, $matches) === 1){
			if(
				/**
				 * If the entire string is just a table name, rather than a larger string containing one,
				 * it is likely a fallback for older REDCap versions where getDataTable() didn't exist yet,
				 * like the following:
				 * 		return method_exists('\REDCap', 'getDataTable') ? \REDCap::getDataTable($project_id) : "redcap_data";
				 * 
				 * Do NOT consider this a hardcoded table name.
				 */
				in_array($s, ['"redcap_data"', "'redcap_data'", '"redcap_log_event"', "'redcap_log_event'"])
				||
				/**
				 * Is this a Logging::logEvent() call? See TestModule.php for an example.
				 */
				str_contains($s, 'object_type')
				&&
				(
					str_contains($s, '"redcap_data"')
					||
					str_contains($s, "'redcap_data'")
				)
			){
				return null;
			}

			return $matches[2];
		}

		return null;
	}
}