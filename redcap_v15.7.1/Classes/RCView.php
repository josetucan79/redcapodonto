<?php



/**
 * REDCap View is a class of static functions that build HTML elements.
 */
class RCView
{
	/** The amount to indent each level of HTML. */
	const INDENT = "\t";

	/** HTML for non-breaking space. */
	const SP = '&nbsp;';

	/** Used to generate unique IDs for HTML elements. */
	private static $jsId = 0;

	/** Returns a unique HTML element ID. */
	static function getId() {
		self::$jsId++;
		return 'redcapJSAutoId_' . self::$jsId;
	}

	/**
	 * List of valid characters for a language key.
	 */
	const LANGUAGE_ALLOWED_KEY_CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_";

	/**
	 * Replaces placeholders in a language string with the supplied values.
	 * @param string $string The template string.
	 * @param array $values The values to be used for interpolation. 
	 * @param bool $escapeHTML Determines whether to escape HTML in interpolation values.
	 * @return string The result of the string interpolation.
	 */
	public static function interpolateLanguageString($string, $values, $escapeHTML = true) {

        if (is_string($values)) $values = [$values];
		if (count($values) == 0) return $string;

		// Placeholders are in curly braces, e.g. {0}. Optionally, a type hint can be present after a colon (e.g. {0:Date}), 
		// which is ignored however. Hints must not contain any curly braces.
		// To not replace a placeholder, the first curly can be escaped with a %-sign like so: '%{1}' (this will leave '{1}' in the text).
		// To include '%' as a literal before a curly opening brace, a double-% ('%%') must be used, i.e. '%%{0}' with value x this will result in '%x'.
		// Placeholder names can be strings (a-Z0-9_), too (need associative array then). 
		// First, parse the string.
		$matches = array();
		$mode = "scan";
		$escapes = 0;
		$start = 0;
		$key = "";
		$hint = "";
		for ($i = 0; $i < strlen($string); $i++) {
			$c = $string[$i];
			if ($mode == "scan" && $c == "{") {
				$start = $i;
				$key = "";
				$hint = "";
				if ($escapes % 2 == 0) {
					$mode = "key";
				}
				else {
					$mode = "store";
				}
			}
			if ($mode == "scan" && $c == "%") {
				$escapes++;
			}
			else if ($mode == "scan") {
				$escapes = 0;
			}
			if ($mode == "hint") {
				if ($c == "}") {
					$mode = "store";
				}
				else {
					$hint .= $c;
				}
			}
			if ($mode == "key") {
				if (strpos(self::LANGUAGE_ALLOWED_KEY_CHARS, $c)) {
					$key .= $c;
				}
				else if ($c == ":") {
					$mode = "hint";
				}
				else if ($c == "}") {
					$mode = "store";
				}
			}
			if ($mode == "store") {
				$match = array(
					"key" => $key,
					"hint" => $hint,
					"escapes" => $escapes,
					"start" => $start,
					"end" => $i
				);
				$matches[] = $match;
				$key = "";
				$hint = "";
				$escapes = 0;
				$mode = "scan";
			}
		}
		// Then, build the result.
		$result = "";
		if (count($matches) == 0) {
			$result = $string;
		} else {
			$prevEnd = 0;
			for ($i = 0; $i < count($matches); $i++) {
				$match = $matches[$i];
				$len = $match["start"] - $prevEnd - ($match["escapes"] > 0 ? max(1, $match["escapes"] - 1) : 0);
				$result .= substr($string, $prevEnd, $len);
				$prevEnd = $match["end"];
				if ($match["key"] != "" && array_key_exists($match["key"], $values)) {
					$result .= $escapeHTML ? htmlspecialchars($values[$match["key"]]) : $values[$match["key"]];
					$prevEnd++;
				}
			}
			$result .= substr($string, $prevEnd);
		}
		return $result;
	}

	/** 
	 * Returns a string from the language file or "KEY NOT FOUND: KEY".
	 * @param string $lang_key The language key
	 * @return string 
	 */
	static function getLangStringByKey($lang_key) {
		if (empty($GLOBALS["lang"])) {
			$project_language = isset($GLOBALS["Proj"]) ? $GLOBALS["Proj"]->project["project_language"] : "English";
			$GLOBALS["lang"] = Language::getLanguage($project_language);
		}
		$s = $GLOBALS["lang"][$lang_key] ?? "KEY NOT FOUND: {$lang_key}";
		return $s;
	}

	/** 
	 * Returns a string from the language file, wrapped in a span (unless otherwise specified).
	 * @param string $lang_key The language key
	 * @param string $wrap The element type to wrap the string in (default: span)
	 * @param Array $attrs Attribute key-value pairs
	 * @return string 
	 */
	static function tt($lang_key, $wrap = "span", $attrs = array()) {
		$s = self::getLangStringByKey($lang_key);
		if (empty($wrap)) return $s;
		$attrs["data-rc-lang"] = $lang_key;
		return self::toHtml($wrap, $attrs, $s, true);
	}

	/**
	 * Returns a language string that is passed through strip_tags().
	 * @param mixed $lang_key 
	 * @return string 
	 */
	static function tt_strip_tags($lang_key) {
		return strip_tags(self::getLangStringByKey($lang_key));
	}

	/**
	 * Returns a language string that is passed through htmlentities(). 
	 * This is suitable for inserting text into HTML attributes.
	 * @param mixed $lang_key 
	 * @return string 
	 */
	static function tt_attr($lang_key) {
		return htmlentities(self::getLangStringByKey($lang_key), ENT_QUOTES, "UTF-8", false);
	}

	/**
	 * Returns an interpolated language string that is passed through strip_tags().
	 * @param mixed $lang_key 
	 * @param Array $values
	 * @return string 
	 */
	static function tt_i_strip_tags($lang_key, $values) {
		return strip_tags(self::tt_i($lang_key, $values, false, null));
	}

	/** 
	 * Returns a string from the language file, passed through js_escape().
	 * @param string $lang_key The language key
	 * @return string 
	 */
	static function tt_js($lang_key) {
		$s = self::getLangStringByKey($lang_key);
		return js_escape($s);
	}

	/** 
	 * Returns a string from the language file, passed thorugh js_escape2().
	 * @param string $lang_key The language key
	 * @return string 
	 */
	static function tt_js2($lang_key) {
		$s = self::getLangStringByKey($lang_key);
		return js_escape2($s);
	}

	/** 
	 * Returns a string from the language file, interpolated with the given values,
	 * and wrapped in a span (unless otherwise specified). Use lang_i() if on-the-fly 
	 * translation is not needed.
	 * @param string $lang_key The language key
	 * @param Array $values
	 * @param bool $escapeHTML Determines whether to escape HTML in interpolation values.
	 * @param string $wrap The element type to wrap the string in (default: span).
	 * @param Array $attr Any attributes to add to the wrapping element (default: null).
	 * @return string 
	 */
	static function tt_i($lang_key, $values = array(), $escapeHTML = true, $wrap = "span", $attrs = []) {
		$s = self::getLangStringByKey($lang_key);
		$s = self::interpolateLanguageString($s, $values, $escapeHTML);
		if (empty($wrap)) return $s;
		// Wrap, add values as data attribute to the wrapping element.
		// The values are base64-encoded to ensure integrity when being passed through escape() in toHtml().
		$attrs["data-rc-lang"] = $lang_key;
		if (!empty($values)) {
			$attrs["data-rc-lang-values"] = base64_encode(json_encode($values, JSON_FORCE_OBJECT));
		}
		return self::toHtml($wrap, $attrs, $s, true);
	}

	/** 
	 * Returns a string from the language file, interpolated with the given values,
	 * and wrapped in a span (unless otherwise specified). Use tt_i() for strings that need to
	 * support on-the-fly translation.
	 * @param string $lang_key The language key
	 * @param Array $values
	 * @param bool $escapeHTML Determines whether to escape HTML in interpolation values.
	 * @param string $wrap The element type to wrap the string in (default: span).
	 * @param Array $attr Any attributes to add to the wrapping element.
	 * @return string 
	 */
	static function lang_i($lang_key, $values = array(), $escapeHTML = true, $wrap = "span", $attrs = []) {
		$s = self::getLangStringByKey($lang_key);
		$s = self::interpolateLanguageString($s, $values, $escapeHTML);
		if (empty($wrap)) return $s;
		$attrs["data-rc-lang"] = $lang_key;
		return self::toHtml($wrap, $attrs, $s, true);
	}

	/** 
	 * Returns the string passed as an argument, wrapped in a span (unless otherwise specified).
	 * This is a marker for text that yet needs to be made translatable (i.e. added to English.ini).
	 * @param string $s The string that eventually should be made translatable
	 * @param string $wrap The element type to wrap the string in (default: span)
	 * @return string
	 */
	static function ttfy($s, $wrap = "span") {
		return empty($wrap) ? $s : "<$wrap class=\"ttfy\">$s</$wrap>";
	}

	/** 
	 * Returns the string passed as an argument, interpolated with the given values,
	 * and wrapped in a span (unless otherwise specified).
	 * This is a marker for text that yet needs to be made translatable (i.e. added to English.ini).
	 * @param string $s The string that eventually should be made translatable
	 * @param Array $values
	 * @param bool $escapeHTML Determines whether to escape HTML in interpolation values.
	 * @param string $wrap The element type to wrap the string in (default: span)
	 * @return string
	 */
	static function ttfy_i($s, $values = array(), $escapeHTML = true, $wrap = "span") {
		$s = self::interpolateLanguageString($s, $values, $escapeHTML);
		return empty($wrap) ? $s : "<$wrap class=\"ttfy\">$s</$wrap>";
	}

	/** Returns a Font Awesome icon */
	static function fa($classes, $style="") {
		$h = "<i";
		if ($style != "") $h .= " style=\"$style\"";
		$h .= " class=\"$classes\"></i>";
		return $h;
	}

	/**#@+ Convenience functions for various HTML elements. */
	static function i($attrs, $html='') { 
		if (!is_array($attrs)) {
			$html = $attrs;
			$attrs = array();
		}
		return self::toHtml('i', $attrs, $html, true); 
	}
	static function u($attrs, $html='') { 
		if (!is_array($attrs)) {
			$html = $attrs;
			$attrs = array();
		}
		return self::toHtml('u', $attrs, $html, true); 
	}
	static function b($attrs, $html='') { 
		if (!is_array($attrs)) {
			$html = $attrs;
			$attrs = array();
		}
		return self::toHtml('b', $attrs, $html, true); 
	}
	static function br() { return self::toHtml('br', array(), false, true); }
	static function input($attrs=array()) { return self::toHtml('input', $attrs, false); }
	static function hidden($attrs=array()) {
		$attrs['type'] = 'hidden';
		return self::toHtml('input', $attrs, false);
	}
	static function submit($attrs=array()) {
		$attrs['type'] = 'submit';
		return self::toHtml('input', $attrs, false);
	}
	static function file($attrs=array()) {
		$attrs['type'] = 'file';
		return self::toHtml('input', $attrs, false);
	}
	static function checkbox($attrs=array()) {
		$attrs['type'] = 'checkbox';
		return self::toHtml('input', $attrs, false);
	}

    static function toggle($attrs = array(), $label = '') {
        $defaultWidth = '48';
        $defaultHeight = '24';

        $style = $attrs['style'] ?? '';
        preg_match_all('/(\w+)\s*:\s*(\d+)/', $style, $matches);

        $width = $matches[2][array_search('width', $matches[1])] ?? $defaultWidth;
        $height = $matches[2][array_search('height', $matches[1])] ?? $defaultHeight;

        $margin = floor((0.33 * $height) / 2) . 'px'; // take a percentage for top and bottom margin of toggle nob
        $toggleNobDiameter = ($height - floor(0.33 * $height)) . 'px';
        $xTranslate = ($width - $height) . 'px';

        $toggleBehaviorCss = self::style(".toggle-slider{background-color:#ccc;}.toggle-switch input:checked + .toggle-slider{background-color:#0d6efd;}.toggle-switch input:checked + .toggle-slider .toggle-switch-nob{transform:translateX($xTranslate);}");

        $innerSpanElmt = self::span(array('class' => "toggle-switch-nob", 'style' => "position:absolute;content:'';height:$toggleNobDiameter;width:$toggleNobDiameter;left:$margin;bottom:$margin;background-color:white;border-radius:50%;transition:.4s;"));
        $innerElmts = self::checkbox(array_merge(array('style' => "opacity:0;width:0;height:0;"), isset($attrs['id']) ? ['id' => $attrs['id']] : [] )) . self::span(array('class' => "toggle-slider", 'style' => "position:absolute;top:0px;left:0px;right:0px;bottom:0px;border-radius:{$height}px;"), $innerSpanElmt);
        $toggleElmt = self::label(array('class' => "toggle-switch", 'style' => "position:relative;display:inline-block;$style;width:{$width}px;height:{$height}px;"), $innerElmts);

        return $toggleBehaviorCss . $label . self::br() . $toggleElmt;
    }

	static function text($attrs=array()) {
		$attrs['type'] = 'text';
		return self::toHtml('input', $attrs, false);
	}
	static function radio($attrs=array()) {
		$attrs['type'] = 'radio';
		return self::toHtml('input', $attrs, false);
	}
	static function font($attrs, $html, $suppressNewlines=true) {
		return self::toHtml('font', $attrs, $html, $suppressNewlines);
	}
	static function label($attrs, $html, $suppressNewlines=true) {
		return self::toHtml('label', $attrs, $html, $suppressNewlines);
	}
	static function button($attrs=array(), $html='') {
		return self::toHtml('button', $attrs, $html);
	}
	static function iframe($attrs=array(), $html='') {
		return self::toHtml('iframe', $attrs, $html);
	}
	static function div($attrs=array(), $html='') {
		return self::toHtml('div', $attrs, $html);
	}
	static function span($attrs, $html='', $suppressNewlines=true) {
		return self::toHtml('span', $attrs, $html, $suppressNewlines);
	}
	static function form($attrs=array(), $html='') {
		return self::toHtml('form', $attrs, $html);
	}
	static function a($attrs=array(), $html='') {
		return self::toHtml('a', $attrs, $html, true, true);
	}
	static function h1($attrs=array(), $html='') {
		return self::toHtml('h1', $attrs, $html);
	}
	static function h2($attrs=array(), $html='') {
		return self::toHtml('h2', $attrs, $html);
	}
	static function h3($attrs=array(), $html='') {
		return self::toHtml('h3', $attrs, $html);
	}
	static function h4($attrs=array(), $html='') {
		return self::toHtml('h4', $attrs, $html);
	}
	static function h5($attrs=array(), $html='') {
		return self::toHtml('h5', $attrs, $html);
	}
	static function h6($attrs=array(), $html='') {
		return self::toHtml('h6', $attrs, $html);
	}
	static function table($attrs=array(), $html='') {
		return self::toHtml('table', $attrs, $html);
	}
	static function tbody($attrs=array(), $html='') {
		return self::toHtml('tbody', $attrs, $html);
	}
	static function tr($attrs=array(), $html='') {
		return self::toHtml('tr', $attrs, $html);
	}
	static function td($attrs=array(), $html='') {
		return self::toHtml('td', $attrs, $html);
	}
	static function thead($attrs=array(), $html='') {
		return self::toHtml('thead', $attrs, $html);
	}
	static function th($attrs=array(), $html='') {
		return self::toHtml('th', $attrs, $html);
	}
	static function fieldset($attrs=array(), $html='') {
		return self::toHtml('fieldset', $attrs, $html);
	}
	static function legend($attrs=array(), $html='') {
		return self::toHtml('legend', $attrs, $html);
	}
	static function img($attrs=array()) {
		// Does not require that APP_PATH_IMAGES be used in SRC attr, but if added, then don't add a second time.
		if (isset($attrs['src']) && substr($attrs['src'], 0, strlen(APP_PATH_IMAGES)) != APP_PATH_IMAGES) {
			$attrs['src'] = APP_PATH_IMAGES . $attrs['src'];
		}
		return self::toHtml('img', $attrs, false);
	}
	static function p($attrs=array(), $html='') {
		return self::toHtml('p', $attrs, $html);
	}
	static function li($attrs=array(), $html='') {
		return self::toHtml('li', $attrs, $html);
	}
	static function ul($attrs=array(), $html='') {
		return self::toHtml('ul', $attrs, $html);
	}
	static function ol($attrs=array(), $html='') {
		return self::toHtml('ol', $attrs, $html);
	}
	static function textarea($attrs=array(), $html='') {
		return self::toHtml('textarea', $attrs, $html, false, true);
	}
	static function pre($attrs=array(), $html='') {
		return self::toHtml('pre', $attrs, $html, false, true);
	}
	static function code($attrs=array(), $html='') {
		return self::toHtml('code', $attrs, $html, false, true);
	}
	/**#@-*/

	/** Makes a link using an icon.
	 * @param string $id the ID attribute of the link.
	 * @param string $icon the image src.
	 * @param string $title the title and alt for the image.
	 * @param string $url the href for the link (minus query string).
	 * @param array $qvars the variables used to make the query string. The values will be automatically
	 * encoded for URL.
	 */
	static function iconLink($id, $icon, $title, $url, $qvars, $onclick = null) {
		$imgAttrs = array('src' => $icon, 'title' => $title, 'alt' => $title);
		$imgHtml = self::toHtml('img', $imgAttrs, false, true);
		if (is_array($qvars) && count($qvars) > 0) {
			$pairs = array();
			foreach ($qvars as $key => $val) $pairs[] = "$key=" . urlencode($val);
			$url .= '?' . implode('&', $pairs);
		}
		$aAttrs = array_merge(array('id' => $id, 'href' => $url), !is_null($onclick) ? ['onclick' => $onclick] : []);
		return self::toHtml('a', $aAttrs, $imgHtml);
	}

	/** Makes a very simple unordered list. */
	static function simpleList($items, $escape=false, $listAttrs=array()) {
		$html = "";
		foreach ($items as $i) {
			$html .= self::toHtml('li', array(), $escape ? self::escape($i) : $i);
		}
		return self::toHtml('ul', $listAttrs, $html);
	}

	/**
	 * Makes a select box.
	 * @param array $attrs see self::toHTML() $attrs param.
	 * @param array $opts the select box options. Values will be automatically escaped for HTML and
	 * trimmed to a reasonable size.
	 * @param mixed $selKey the option to select by default. Is an array if multiple options selected on multi-select dropdown
	 */
	static function select($attrs, $opts, $selKey=null, $maxOptChars=75)
	{
		$o = '';
		// Set values for multiple selection drop-downs
		$isMultiSelect = isset($attrs['multiple']);
		if (!$isMultiSelect && is_array($selKey)) $selKey = array_pop($selKey);
		if (is_array($selKey) && empty($selKey)) $selKey = null;
		$hasMultiplePreselected = is_array($selKey);
		// Make sure all values in preselection array are strings (so we can do a proper strict in_array later)
		if ($hasMultiplePreselected) {
			foreach ($selKey as $key=>$val) {
				$selKey[$key] = $val."";
			}
		}
		// Loop through all choices
        if (is_array($opts)) {
			foreach ($opts as $key => $val) {
				// HTML for this loop
				// If $val is an array, then assume it's an OPTGROUP
				if (is_array($val)) {
					// Optgroup
					$this_o = '';
					foreach ($val as $key2 => $val2) {
						// Normal option inside optgroup
						$oAttrs = array('value' => $key2);
						// Determine if we should pre-select this option
						if ((!$hasMultiplePreselected && $selKey . "" === $key2 . "")
							|| ($hasMultiplePreselected && in_array($key2 . "", $selKey, true))) {
							$oAttrs['selected'] = 'selected';
						}
						// Truncate if option length too long
						$val2 = RCView::TrimForDropdownDisplay($val2, $maxOptChars);
						// Output choice
						$this_o .= self::toHtml('option', $oAttrs, self::escape($val2));
					}
					// Wrap the options with the optgroup tag
					$o .= self::toHtml('optgroup', array('label' => $key), $this_o);
				} else {
					// Normal option
					$oAttrs = array('value' => $key);
					// Determine if we should pre-select this option
					if ((!$hasMultiplePreselected && $selKey . "" === $key . "")
						|| ($hasMultiplePreselected && in_array($key . "", $selKey, true))) {
						$oAttrs['selected'] = 'selected';
					}
					// Truncate if option length too long
					$val = RCView::TrimForDropdownDisplay($val, $maxOptChars);
					// Output choice
					$o .= self::toHtml('option', $oAttrs, self::escape($val));
				}
			}
		}
		return self::toHtml('select', $attrs, $o);
	}

	/**
	 * Creates an Enabled/Disabled select box.
	 * @param array $attrs see self::toHTML() $attrs param.
	 * @param boolean $enabled true if the select box should default to Enabled;
	 * false to default to Disabled.
	 */
	static function selectEnabledDisabled($attrs, $enabled) {
		$opts = array(0 => RCL::disabled(), 1 => RCL::enabled());
		return self::select($attrs, $opts, ($enabled ? 1 : 0));
	}

	/** Displays a box with an error message inside, prepended with error icon/text. */
	static function errorBox($html, $id='') {
		global $lang;
		$h = '';
		$h .= self::toHtml('img', array('src' => APP_PATH_IMAGES . 'exclamation.png'));
		$h .= $lang['global_01'] . $lang['colon'] . ' ';
		$attrs = array('class' => 'red', 'style' => 'margin-bottom: 20px;', 'id' => $id, 'title' => $lang['global_01']);
		return self::toHtml('div', $attrs, $h . $html);
	}

	/** Displays a box with a success message inside, prepended with check icon. */
	static function successBox($html, $id='') {
		global $lang;
		$h = '';
		$h .= self::toHtml('img', array('src' => APP_PATH_IMAGES . 'tick.png'));
		$h .= $lang['setup_08'] . ' ';
		$attrs = array('class' => 'darkgreen', 'style' => 'margin-bottom: 20px;', 'id' => $id, 'title' => $lang['setup_08']);
		return self::toHtml('div', $attrs, $h . $html);
	}

	/** Displays a box with a warning message inside, prepended with warning icon. */
	static function warnBox($html, $id='') {
		global $lang;
		$h = '';
		$h .= self::toHtml('img', array('src' => APP_PATH_IMAGES . 'error.png'));
		$h .= $lang['global_03'] . $lang['colon'] . ' ';
		$attrs = array('class' => 'yellow', 'style' => 'margin-bottom: 20px;', 'id' => $id, 'title' => $lang['global_03']);
		return self::toHtml('div', $attrs, $h . $html);
	}

	/** Displays a box with a confirmation message inside, prepended with confirmation icon. */
	static function confBox($html, $id='') {
		global $lang;
		$h = '';
		$h .= self::toHtml('img', array('src' => APP_PATH_IMAGES . 'exclamation_orange.png'));
		$attrs = array('class' => 'yellow', 'style' => 'margin-bottom: 20px;', 'id' => $id, 'title' => $lang['global_02']);
		return self::toHtml('div', $attrs, $h . $html);
	}

	/**
	 * Uses flexigrid to build a very simple table.
	 * @param array $rows the first element is the table title (string), the
	 * second element is an array of column headers, and the subsequent elements
	 * are arrays of column data.
	 * @param $widths the width in pixels of each column.
	 */
	static function simpleGrid($rows, $widths) {
		$r = '';
		// build the title row
		$title = array_shift($rows);
		if (!empty($title))
			$r .= self::div(array('class' => 'mDiv'), self::div(array('class' => 'ftitle'), $title));
		// build the header row
		$hdr = array_shift($rows);
		if ($hdr !== null) {
			$h = '';
			for ($i = 0; $i < count($widths); $i++) {
				$h .= self::th(array(), self::div(array('style' => 'width: ' . $widths[$i] . 'px;'), $hdr[$i]));
			}
			$r .= self::div(array('class' => 'hDiv'), self::div(array('class' => 'hDivBox'),
							self::table(array('cellspacing' => '0'), self::tr(array(), $h))));
		}
		// build the data rows
		$h = ''; $rowCnt = 1;
		foreach ($rows as $row) {
			$cells = '';
			for ($i = 0; $i < count($widths); $i++) {
				$cells .= self::td(array(), self::div(array('style' => (isset($widths[$i]) ? 'width: ' . $widths[$i] . 'px;' : '')), (isset($row[$i]) ? $row[$i] : '')));
			}
			$rowAttrs = $rowCnt % 2 == 0 ? array('class' => 'erow') : array();
			$h .= self::tr($rowAttrs, $cells);
			$rowCnt++;
		}
		$r .= self::div(array('class' => 'bDiv'), self::table(array('cellspacing' => '0'), $h));
		$totalWidth = array_sum($widths) + count($widths);
		return self::div(array('class' => 'flexigrid', 'style' => 'width: ' . $totalWidth . 'px;'), $r);
	}

	/**
	 * Builds a simple 2-column table intended for user input.
	 * @param string $title a title displayed at the top of the table.
	 * @param array $rowArr each element is an array representing a row:
	 * $arr = current($rowArr);
	 * $arr['label'] = HTML explanation of the required input
	 * $arr['input'] = HTML representing the input field(s)
	 * $arr['info'] = HTML additional instructions to appear under the input.
	 */
	static function simpleInputTable($title, $rowArr) {
		$h = '';
		if (!empty($title)) {
			$h .= self::tr(array(),
							self::td(array('colspan' => '2', 'style' => 'padding: 10px;'),
											self::font(array('class' => 'redcapBlockTitle'), $title)));
		}
		foreach ($rowArr as $arr) {
			$label = empty($arr['label']) ? '' : $arr['label'];
			$input = empty($arr['input']) ? '' : $arr['input'];
			$info = empty($arr['info']) ? '' : $arr['info'];
			$h .= self::tr(array(),
							self::td(array('class' => 'cc_label'), $label) .
							self::td(array('class' => 'cc_data'), $input .
											(empty($info) ? '' : self::div(array('class' => 'cc_info'), $info))));
		}
		return self::table(array('style' => 'border: 1px solid #ccc; background-color: #f0f0f0; margin: 20px 0;'), $h);
	}

	/**
	 * Escapes a string for use in HTML.
	 * @param boolean/integer $removeAllTags true to blindly escape all HTML tags;
	 * false to first perform some user-friendly sanitation in cases where the
	 * HTML is displayed to the user (e.g., in a title).
	 */
	static function escape($s, $removeAllTags=true) {
        if ($s === null) return '';
	    if (!is_string($s)) return $s;
		// Set temporary replacement for &nbsp; HTML character code so that html_entity_decode() doesn't mangle it
		$nbsp_replacement = '|*|RC_NBSP|*|';
	    // Replace &nbsp; characters
		$s = str_replace(array("&amp;nbsp;", "&nbsp;"), array($nbsp_replacement, $nbsp_replacement), $s);
		// HTML decode
		$s = html_entity_decode($s, ENT_QUOTES);
		// Replace &nbsp; characters
		$s = str_replace($nbsp_replacement, "&nbsp;", $s);
		// Remove tags if needed
		if ($removeAllTags) $s = strip_tags2($s);
		// Now escape it
		$s_esc = htmlspecialchars($s, ENT_QUOTES);
		// If it has issues with non-characters getting escaped, then fall back to using ENT_SUBSTITUTE
		if (strlen($s_esc) == 0 && strlen($s) > 0) {
			$s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		} else {
			$s = $s_esc;
		}
		// Return string
		return $s;
	}

	/**
	 * Quotes a PHP string for inclusion in JavaScript. Example:
	 * PHP:
	 * $foo = 'I love "quotes"';
	 * JS:
	 * alert(<?php RCView::strToJS($foo); ?>);
	 * Also replaces newlines with spaces because INI strings can have newlines
	 * purely for code-formatting reasons.
	 */
	static function strToJS($s) {
		return '"' . str_replace(array('"', "\r\n", "\n"), array('\\"', " ", " "), $s) . '"';
	}

	/**
	 * Builds an HTML element as requested by the public functions of this class.
	 * @param string $elemType e.g. input, form, hidden, etc.
	 * @param array $attrs keys are attribute names and values are the attribute values. All values will
	 * be encoded for HTML, therefore JavaScript should *NOT* be used here; instead, use jQuery to
	 * bind to events of this element within a $(function(){}) block.
	 * @param string $html any HTML to be included within the open/close tags of this element. If this
	 * is === FALSE, then the tag of this element will self-close.
	 * @param boolean $suppressNewline true if you do not want to follow this element with a newline.
	 * @param boolean $forceOneLiner if true, no indenting will be done and no newlines
	 * will be added that preceed or follow the $html.
	 */
	static function toHtml($elemType, $attrs=array(), $html=false, $suppressNewline=false, $forceOneLiner=false) {
		$h = "<$elemType";
		if(is_array($attrs) && !empty($attrs))
		{
			foreach ($attrs as $key => $val) {
				if ($key == "") continue;
				$h .= " $key=\"" . self::escape($val, false) . '"';
			}
		}
		if ($html === false) {
            $h .= "/>";
		} else {
			$h .= ">";
			if ($html === null || is_array($html)) $html = '';
			if (strlen($html) > 0) {
				// if there are no newlines in the HTML then assume we want a one-liner
				if (strpos($html, "\n") === false || $forceOneLiner) $h .= $html;
				// newlines in the HTML imply that we should add a level of indentation
				else {
					$h .= "\n";
					// ugly hack to deal with elements that contain newline-sensitive text
					$hackMap = array();
					foreach (array('textarea', 'pre') as $elem) {
						$elem . ' ' . preg_match_all("|<$elem.*?>.*?</$elem>|is", $html, $matches);
						foreach ($matches[0] as $match) {
							$hackKey = '{REPLACEME_HACK_' . count($hackMap) . '}';
							$hackMap[$hackKey] = $match;
							$html = str_replace($match, $hackKey, $html);
						}
					}
					$lines = explode("\n", $html);
					foreach ($lines as $line)
						if (!empty($line)) $h .= self::INDENT . "$line\n";
					// clean up after our ugly hack
					foreach ($hackMap as $hackKey => $hackStr)
						$h = str_replace($hackKey, $hackStr, $h);
				}
			}
			$h .= "</$elemType>";
		}
		if (!$suppressNewline) $h .= "\n";
		return $h;
	}

	/**
	* Exports file data, causing the user's browser to download/open the file.
	* @param string $filename the name that the file will be given.
	* @param string $content the contents of the file.
	* @param string $type the MIME type.
	*/
	static function exportFile($filename, $content, $type) {
		header('Pragma: anytextexeptno-cache', true);
		header('Content-type: ' . $type);
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Content-length: ' . strlen($content));
		if ($type == 'application/csv') {
			$content = addBOMtoUTF8($content);
        }
		echo $content;
		exit();
	}

	/** Returns button to return to previous page (with default text) **/
	public static function btnGoBack($text=null) {
		global $lang;
		if ($text == null) $text = $lang['global_77'];
		return self::button(array('class'=>'jqbuttonmed','onclick'=>'history.go(-1);return false;'), "<i class=\"fas fa-chevron-circle-left\"></i> $text");
	}

	/** Returns a note to display to the user regarding disabled API status. */
	static function disabledAPINote() {
		global $lang;
		global $super_user;
		$note = '';
		$note .= $lang['api_01'] . ' ';
		if ($super_user) {
			$note .= $lang['api_07'] . ' ';
			$note .= RCView::a(array('target' => '_blank',
					'style' => 'text-decoration:underline;',
					'href' => APP_PATH_WEBROOT . 'ControlCenter/modules_settings.php'),
						$lang['graphical_view_78']);
		}
		else $note .= $lang['api_06'];
		return $note;
	}

	/** Returns hidden div with simpleDialog class to be displayed via jQueryUI dialog() function **/
	public static function simpleDialog($content="",$title="",$id="",$moreAttrs = array()) {
		if (strlen($title)) $moreAttrs["title"] = $title;
		if (strlen($id)) $moreAttrs["id"] = $id;
		$class = "simpleDialog " . ($moreAttrs["class"] ?? "");
		$moreAttrs["class"] = $class;
		return self::div($moreAttrs, $content);
	}

	/**
	 * RENDER TABS FROM ARRAY WITH 'PAGE' AS KEY AND LABEL AS VALUE
	 */
	public static function renderTabs($tabs=array())
	{
		// Get request URI
		$request_uri = $_SERVER['REQUEST_URI'];
		// If request URI ends with ".php?", then remove "?"
		if (substr($request_uri, -5) == '.php?') $request_uri = substr($request_uri, 0, -1);
		// Get query string parameters for the current page's URL
		$params = (strpos($request_uri, ".php?") === false) ? array() : explode("&", parse_url($request_uri, PHP_URL_QUERY));
		?>
		<div id="sub-nav" style="margin:5px 0 20px;">
			<ul>
				<?php
				foreach ($tabs as $this_url=>$this_label)
				{
					// Parse any querystring params in $this_url and check for match to see if this should be the Active tab
					$these_params = (strpos($this_url, ".php?") === false) ? array() : explode("&", parse_url($this_url, PHP_URL_QUERY));
					// Get $this_page. Check if has route in query string.
					parse_str(parse_url($this_url, PHP_URL_QUERY)??"", $these_param_pairs);
					if (isset($these_param_pairs['route'])) {
						$this_page = $these_param_pairs['route'];
					} else {
						$this_page = parse_url($this_url, PHP_URL_PATH);
					}
					// Add project_id if on a project-level page
					if (defined("PROJECT_ID")) {
						$these_params[] = "pid=" . PROJECT_ID;
					}
					// Format query string for the url to add 'pid'
					$this_url = parse_url($this_url, PHP_URL_PATH);
					if (!empty($these_params)) {
						$this_url .= "?" . implode("&", $these_params);
					}
					// Check for Active tab
					$isActive = false;
					if ($this_page == PAGE && count($these_params) == count($params)) {
						// Make sure all params are same. Loop till it finds mismatch.
						$isActive = true;
						foreach ($params as $this_param) {
							if (!in_array($this_param, $these_params)) $isActive = false;
						}
					}
					?>
					<li <?php if ($isActive) echo 'class="active"'?>>
						<a href="<?php echo APP_PATH_WEBROOT . $this_url ?>" style="font-size:13px;color:#393733;padding:6px 9px 5px 10px;"><?php echo $this_label ?></a>
					</li>
					<?php
				} ?>
			</ul>
		</div>
		<div class="clear"></div>
		<?php
	}

	/**
	 * Returns the supplied Javascript wrapped in <script> tags and an immediately invoked function expression
	 * @param string $body 
	 * @return string 
	 */
	public static function iife($body = "") {
		if (!strlen($body)) return "";
		return "\n<script>\n(function() {\n".$body."\n})();\n</script>\n";
	}

	/**
	 * Returns the supplied JS wrapped in <script type="text/javascript"> tags
	 * @param string $js 
	 * @param bool $on_ready 
	 * @return string 
	 */
	public static function script($js = "", $on_ready = false) {
		if (!strlen($js)) return "";
		if ($on_ready) {
			$js = "$(function() {\n".$js."\n});";
		}
		return "\n<script type=\"text/javascript\">\n$js\n</script>\n";
	}

	/**
	 * Returns the supplied CSS wrapped in <style> tags
	 * @param string $css 
	 * @return string 
	 */
	public static function style($css = "") {
		if (!strlen($css)) return "";
		return "\n<style>\n$css\n</style>\n";
	}

	/**
	 * Returns an icon appropriate for the given extension
	 * @param string $ext 
	 * @param string $class Optional additinal classes
	 * @return string 
	 */
	public static function getFileIcon($ext, $class = "") {
		$icon = "fa-file";
		switch(strtolower($ext)) {
			case "csv":
				$icon = "fa-file-csv"; break;
			case "xls":
			case "xlsx": 
				$icon = "fa-file-excel"; break;
			case "doc":
			case "docx":
				$icon = "fa-file-word"; break;
			case "pdf":
				$icon = "fa-file-pdf"; break;
			case "ppt":
			case "pptx":
				$icon = "fa-file-powerpoint"; break;
			case "jpg":
			case "jpeg":
			case "jpe":
			case "tif":
			case "tiff":
			case "gif":
			case "png":
			case "svg":
			case "webp":
			case "bmp":
				$icon = "fa-file-image"; break;
			case "mov":
			case "mp4":
			case "mpeg":
			case "avi":
				$icon = "fa-file-video"; break;
			case "xml":
				$icon = "fa-file-code"; break;
			case "zip":
				$icon = "fa-file-zipper"; break;
		}
		$icon = trim("fa-solid $icon rc-icon $class");
		return self::fa("$icon");
	}

	public static function ConsortiumVideoLink($label, $video_id, $title, $video_length = "") {
		if ($video_length != "") $video_length = " <small>({$video_length})</small>";
		return self::div([
				"class" => "consortium-video-link",
			], 
			RCIcon::Video("me-1") .
			self::a([
					"href" => "javascript:;",
					"onclick" => "window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=".$video_id."&referer=".SERVER_NAME."&title=".js_escape($title)."','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');"
				],
				$label.$video_length
			)
		);
	}

	/**
	 * Returns a help icon link with the tooltip "Tell me more" (global_58) that shows a dialog
	 * with the given title and content when clicked
	 * @param string $title 
	 * @param string $text 
	 * @param string $extra_classes 
	 * @return string 
	 */
	public static function help($title, $text, $extra_classes = "") {
		return RCView::a([
			"href" => "javascript:;",
			"class" => trim("help $extra_classes"),
			"title" => RCView::tt_attr("global_58"),
			"onclick" => "simpleDialog('".js_escape($text)."','".js_escape($title)."');",
		], "?");
	}




	/**
	 * Trims a string for display in a dropdown. HTML tags are removed.
	 * @param string $string 
	 * @param int $max_length 
	 * @param string $suffix 
	 * @return string 
	 */
	public static function TrimForDropdownDisplay($string, $max_length = 100, $suffix = "&hellip;") {
		$string = strip_tags($string);
		$len = mb_strlen($string);
		if ($len > $max_length) {
			return mb_substr($string, 0, $max_length) . $suffix;
		} else {
			return $string;
		}
	}

	/**
	 * Trims a string and highlights the given text if found inside it. HTML tags are removed.
	 * @param string $string 
	 * @param string $highlight_text 
	 * @param int $max_length
	 * @param string $prefix 
	 * @param string $suffix 
	 * @param string $classes CSS classes to add to the highlighted text (span)
	 * @return string 
	 */
	public static function TrimAndHighlightForDropdownDisplay($string, $highlight_text, $max_length = 100, $prefix = "&hellip;", $suffix = "&hellip;", $classes = "rc-search-highlight") {
		$add_before = "";
		$add_after = "";
		$string = strip_tags($string);
		$len = mb_strlen($string);
		// Trim
		if ($len > $max_length) {
			$match_pos = mb_stripos($string, $highlight_text);
			$remainder_right = $len - ( $match_pos + mb_strlen($highlight_text) );
			$padding_side = floor( ($max_length - 2 - mb_strlen($highlight_text)) / 2);
			if ($padding_side > $remainder_right) {
				$padding_side = $padding_side + ($padding_side - $remainder_right);
			} else {
				$add_after = $suffix;
			}
			$start_pos = max (0, $match_pos - $padding_side);
			if ($start_pos > 0) $add_before = $prefix;
			$value = mb_substr($string, $start_pos, $max_length - 2);
		} else {
			$value = $string;
		}
		// Highlight
		$pos = mb_stripos($value, $highlight_text);
		if ($pos === false) {
			// Do not try to highlight results
			$result = htmlentities($value);
		} else {
			// Highlight search match
			$highlight_text_len = mb_strlen($highlight_text);
			$result = htmlentities(mb_substr($value, 0, $pos))
				. "<span class=\"$classes\">"
				. htmlentities(mb_substr($value, $pos, $highlight_text_len))
				. "</span>"
				. htmlentities(mb_substr($value, $pos + $highlight_text_len));
		}
		// Return with prefix and suffix
		return $add_before . $result . $add_after;
	}
}
