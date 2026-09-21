<?php

/**
 * Language class
 * Contains methods used for dealing with multiple languages in REDCap
 */
class Language
{
	const DEFAULT_LANGUAGE = 'English';

	// Language: Obtain list of all language files (.ini files) in the languages folder. Return as array with language name as both key and value.
	public static function getLanguageList()
	{
		$languages = array(self::DEFAULT_LANGUAGE=>self::DEFAULT_LANGUAGE); // English is always included by default
		foreach (getDirFiles(dirname(dirname(dirname(__FILE__))) . DS . 'languages') as $this_language)
		{
			if (strtolower(substr($this_language, -4)) == '.ini')
			{
				$lang_name = substr($this_language, 0, -4);
				// Set name as both key and value
				$languages[$lang_name] = $lang_name;
			}
		}
		ksort($languages);
		return $languages;
	}

	// Language: Call the correct language file for this project (default to English)
	public static function callLanguageFile($language = self::DEFAULT_LANGUAGE, $show_error = true)
	{
		global $lang;
		// Get directory: English is kept in version sub-folder, while others are kept above version folder
		$dir = ($language == self::DEFAULT_LANGUAGE) ? (dirname(dirname(__FILE__)) . DS . 'LanguageUpdater') : (dirname(dirname(dirname(__FILE__))) . DS . 'languages');
		// Get path of language file
		$language_file = $dir . DS . "$language.ini";
		// Parse ini file into an array
		$this_lang = parse_ini_file($language_file);
		// If fails, give error message
		if ($show_error && (!$this_lang || !is_dir($dir))) exit($lang['config_functions_63'] . "<br>" . RCView::escape($language_file));
		// In case of English, integrate the EM framework strings. We assume that this file will always load ok
		if ($language == self::DEFAULT_LANGUAGE && defined("APP_PATH_EXTMOD")) {
			$em_file = APP_PATH_EXTMOD . "classes/English.ini";
			$em_strings = parse_ini_file($em_file);
			$this_lang = array_merge($this_lang, $em_strings);
			// Indicate that EM framework strings have been integrated
			defined("EM_STRINGS_LOADED") || define("EM_STRINGS_LOADED", 1);
		}
		// Return array of language text
		return $this_lang;
	}

	// Language: Create and return array of all abstracted language text
	public static function getLanguage($set_language = self::DEFAULT_LANGUAGE)
	{
		global $lang;
		// Always call English text first, in case the other language file used is not up to date (prevents empty text on the page)
		$lang = self::callLanguageFile(self::DEFAULT_LANGUAGE);
		// If set language is not English, then now call that other language file and override all English strings with it
		if ($set_language != self::DEFAULT_LANGUAGE)
		{
			$lang2 = self::callLanguageFile($set_language, false);
			// Merge language file with English language, unless returns False
			if ($lang2 !== false)
			{
				$lang = array_merge($lang, $lang2);
			}
		}
		// Return array of language
		return $lang;
	}

	/**
	 * Replaces placeholders in a language-specific string with provided values.
	 *
	 * This function takes a language array, a key, and an associative array of replacements.
	 * It finds the language-specific string based on the key, and then replaces all
	 * occurrences of placeholders within this string. Placeholders are expected to be in 
	 * the format {{placeholder}}.
	 *
	 * @param array $lang The array containing language-specific strings. 
	 *                    This is typically loaded from a language file.
	 * @param string $key The key used to locate the specific string within the $lang array.
	 * @param array $replacements An associative array where the key is the placeholder name 
	 *                            (without curly braces) and the value is the replacement string.
	 *
	 * @return string|null The modified language string with placeholders replaced by their 
	 *                     respective values. If the key is not found in $lang, null is returned.
	 */
	public static function replacePlaceHolders($lang, $key, $replacements=[]) {
		$entry = $lang[$key] ?? false;
		if(!$entry) return;
		foreach ($replacements as $placeholder => $value) {
            $entry = str_replace("{{" . $placeholder . "}}", $value, $entry);
        }
        return $entry;
	}

	/**
	 * Translate function to retrieve a language string by key, or provide a default value.
	 *
	 * This method takes a key, looks up the corresponding value in the language array,
	 * and returns it. If the key is not found, it returns the provided default value,
	 * or a "KEY NOT FOUND" message if no default is provided.
	 * 
	 * You can pass an options array to specify the language and replacements for placeholders.
	 *
	 * @param string $key The key used to locate the specific string in the language array.
	 * @param string|array|null $optionsOrDefault The default value to return if the key is not found. If not provided, a "KEY NOT FOUND" message will be returned. When provided as an array, this should contain the 'default','language' and 'replacements' keys:
	 *                       - 'default': Same as when providing a string argument.
	 *                       - 'language': The language to use (defaults to 'self::DEFAULT_LANGUAGE').
	 *                       - 'replacements': An associative array of placeholders and their values.
	 * 
	 * @return string The translated string with replacements, or the default value (or a "KEY NOT FOUND" message) if the key is not found.
	 */
    public static function tt($key, string|array|null $optionsOrDefault = [])
    {
        // Set default options for language and replacements
        $defaultOptions = [
			'default' => null,
            'language' => self::DEFAULT_LANGUAGE,
            'replacements' => [],
        ];

		if(is_string($optionsOrDefault)) {
			$optionsOrDefault = ["default" => $optionsOrDefault];
		}

        // Merge provided options with the defaults
		if (is_array($optionsOrDefault)) {
			$optionsOrDefault = array_merge($defaultOptions, $optionsOrDefault);
		}

        // Load the specified language or default to 'English'
        $lang = self::getLanguage($optionsOrDefault['language']);

		// default fallback
		$default = $optionsOrDefault['default'];

        // Check if the key exists in the language array, otherwise return the default value.
        $entry = $lang[$key] ?? $default ?? "KEY NOT FOUND: $key";

        // If replacements are provided, use the replacePlaceHolders method to replace them.
        if (!empty($optionsOrDefault['replacements'])) {
            $entry = self::replacePlaceHolders($lang, $key, $optionsOrDefault['replacements']);
        }

        return $entry;
    }
}
