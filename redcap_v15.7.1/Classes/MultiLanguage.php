<?php namespace MultiLanguageManagement;

/**
 * - KNOWN LIMITATIONS
 *   - In translated values, only those piping or embedding sources that are present 
 *     in the reference are available. Anything else will simply not work!
 *   - Translation of PROMIS instruments is not supported - and probably should not be. 
 *     Workaround is to use language-specific PROMIS instruments, probably controlled via 
 *     some logic depending on saved language preference)
 *   - PDFs containing multiple records will always be output in the reference language.
 *   - Translations of matrix group elements will be lost for items of a matrix group
 *     (fields, enums) which had their name changed.
 *   - Section header movements are only tracked while a project is in development mode.
 * 
 * NOTES AND TODOS
 * 
 * - Survey Stats page is not translated yet
 * 
 * - SPECIAL ATTENTION:
 *   - PDF considerations: see https://redcap.vumc.org/community/post.php?id=91212
 * 
 * - Stuff in REDCap that should be addressed:
 *  - "Confidential" logic now relies on RCView::... instead of System::confidental 
 *    Implications? side effects? May need better implementation!
 *  - Several strings are defined for JS in init_functions(), such as e.g. langOkay, etc.
 *    Others (usually) are "hardcoded" in the HTML and, whenever possible, are inside a span.
 *    There may be rare cases where on-the-fly switching must either rely on JS trickery, or
 *    the underlying code must be heavily refactored.
 * 
 * - OTHER TODOs:
 *   - Various other miscellaneous items (see Misc)
 *   - Surveys: Translate stuff from project settings (Custom footer text for survey pages, 
 *     link text and popup text)
 *   - Project settings - Custom text for Project Home Page, for Data Entry pages
 */

use REDCap\Context;
use Alerts;
use Crypto;
use Exception;
use Files;
use Logging;
use Piping;
use Project;
use RCView;
use Session;
use System;
use Throwable;
use UIState;
use User;
use UserRights;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\Task;
use Vanderbilt\REDCap\Classes\MyCap\ZeroDateTask;
use ZipArchive;

class MultiLanguage
{
	#region Constants

	// Note: Using different cookie names for pages within or outside of a project context;
	// For data entry (logged in users), language preference will be stored per project in the user data
	const SURVEY_COOKIE = "redcap-multilanguage-survey";
	const SYSTEM_COOKIE = "redcap-multilanguage";
	// Flag used to convey language information through GET/POST
	const LANG_GET_NAME = "__MLM_lang";
	const LANG_PDF_FORCE = "__MLM_forcePDF";
	const LANG_GET_ECONSENT_FLAG = "__MLM_eConsent";
	const LANG_SURVEY_URL_OVERRIDE = "__lang";
	// UI State object
	const UISTATE_OBJECT = "mlm";
	const UISTATE_LANGPREF = "lang-pref";

	// Items that should not be sent to the browser in data entry (incl. main survey) modes
	const DATAENTRY_SURVEY_BLOCKLIST = ["survey-acknowledgement","survey-confirmation_email_content","survey-confirmation_email_from_display","survey-confirmation_email_subject","survey-end_survey_redirect_url","survey-offline_instructions"];

	// MyCap Supported Languages List
	const  MYCAP_SUPPORTED_LANGS = ['en-US' => '(American) English',
									'it-IT' => 'Italian',
									'zh-CN' => 'Simplified Chinese',
									'hi-IN' => 'Hindi',
									'es-ES' => 'Spanish',
									'ar-SA' => 'Arabic',
									'fr-FR' => 'French',
									'bn-BD' => 'Bengali',
									'pt-BR' => 'Brazilian Portuguese',
									'ur-PK' => 'Urdu',
									'ja-JP' => 'Japanese',
									'pa-IN' => 'Punjabi',
									'de-DE' => 'German',
									'ko-KR' => 'Korean',
									'fil-PH' => 'Tagalog (Filipino)',
									'vi-VN' => 'Vietnamese',
									'ht-HT' => 'Haitian Creole',
									'uk-UA' => 'Ukrainian'];
	#endregion

	#region Data Entry

	/**
	 * Translates the main entry page (i.e. the one displaying fields)
	 * @param Context $context 
	 * @return void 
	 */
	public static function translateDataEntry(Context $context) {
		// Anything to do?
		$active_langs = self::getActiveLangs($context);
		if (count($active_langs) < 1) return;
		// Get settings
		$settings = self::getTranslationSettings($context, true, self::DATAENTRY_SURVEY_BLOCKLIST);
		// Setup ajax verification
		$crypto = Crypto::init();
		$endpoint = APP_PATH_WEBROOT . "index.php?pid={$context->project_id}&route=MultiLanguageController:ajax";
		$ajax = array(
			"verification" => $crypto->encrypt(array(
				"random" => $crypto->genKey(),
				"pid" => $context->project_id,
				"user" => $context->user_id,
				"timestamp" => time(),
			)),
			"endpoint" => $endpoint,
			"csrfToken" => System::getCsrfToken(),
		);
		$settings["ajax"] = $ajax;
		$json = self::convertAssocArrayToJSON($settings);
		// And pass off to the JS implementation
		loadJS("MultiLanguageSurvey.js");
		loadCSS("multilanguage-survey.css");
		print "<script>REDCap.MultiLanguage.init({$json});</script>";
	}

	public static function translateRecordHomePage(Context $context) {
		// TODO
		// print "<script>console.log('translateRecordHomePage()', {$context->toJSON()});</script>";
	}

	public static function translateRecordStatusDashboard(Context $context) {
		// TODO
		// print "<script>console.log('translateRecordStatusDashboard()', {$context->toJSON()});</script>";
	}

	public static function translateAddEditRecords(Context $context) {
		// TODO
	// print "<script>console.log('translateAddEditRecords()', {$context->toJSON()});</script>";
	}

	#endregion

	#region Survey Pages -- Incomplete

	/**
	 * Translates various survey exit pages in the last known (or default) language.
	 * This includes displaying the Survey Queue.
	 * @param Context $context
	 * @return void 
	 */
	public static function exitSurvey(?Context $context = null) {
		// Anything to do?
		$active_langs = self::getActiveLangs($context);
		if (count($active_langs) < 1) return;

		// Language set in URL?
		if(($context == null || $context->lang_id == null) && isset($_GET[MultiLanguage::LANG_SURVEY_URL_OVERRIDE])) {
			$context = Context::Builder($context)->lang_id($_GET[MultiLanguage::LANG_SURVEY_URL_OVERRIDE])->Build();
		}

		// Depending on context, add language switcher
		// Project
		if ($context && $context->project_id != null) {
			self::translateProjectSurveyAccessoryPage($context, $active_langs, "exit-survey");
		}
		else {
			// TODO? - When is this happening?
			$log_context = $context ? $context->toJSON() : "Empty context";
			print "<script>console.log('exitSurvey()', {$log_context});</script>";
		}
	}

	public static function surveyResults(Context $context) {
		// TODO
		// print "<script>console.log('surveyResults()', {$context->toJSON()});</script>";
	}

	#endregion 

	#region Survey Pages

	#region -- Captcha Page

	/**
	 * Translates the survey captcha page
	 * @param Context $context 
	 * @return void 
	 */
	public static function surveyCAPTCHA(Context $context) {
		// Anything to do?
		$active_langs = self::getActiveLangs($context);
		if (count($active_langs) < 1) return;
		// Translate
		self::translateProjectSurveyAccessoryPage($context, $active_langs, "survey-captcha");
	}

	#endregion

	#region -- Redirect Url

	/**
	 * Gets the language-specific survey redirect url
	 * @param Context $context 
	 * @param string $url 
	 * @return string 
	 */
	public static function getSurveyRedirectUrl(Context $context, $url) {
		// Anything to do?
		$lang_id = self::getCurrentLanguage($context);
		if ($lang_id !== false) {
			// Get settings necessary for translation of the Survey Queue (standalone/overlay)
			$active_langs = self::getActiveLangs($context);
			$survey_settings = self::getProjectContextSurveySettings($context, $active_langs);
			$url = self::getSurveyValue($survey_settings, "survey-end_survey_redirect_url", $lang_id);
		}
		return $url;
	}

	#endregion

	#region -- Survey Queue

	/**
	 * Returns code that will provide a language switcher and translations for the Survey Queue
	 * @param Context $context
	 * @param Array $survey_ids List of surveys that are shown in the survey queue
	 * @return string The code
	 */
	public static function displaySurveyQueue(Context $context, $survey_ids) {
		// Anything to do?
		$active_langs = self::getActiveLangs($context);
		if (count($active_langs) < 1) return;
		// Get settings necessary for translation of the Survey Queue (standalone/overlay)
		$survey_settings = self::getSurveyQueueSettings($context, $active_langs, $survey_ids);
		$json = self::convertAssocArrayToJSON($survey_settings);
		// Output depending on display mode (standalone/overlay=ajax)
		$output = "";
		if ($context->is_ajax) {
			$output .= "<script>setTimeout(function() { REDCap.MultiLanguage.updateSurveyQueue({$json}, true); }, 0);</script>";
		}
		else {
			// Add JavaScript
			$output .= loadJS("MultiLanguageSurvey.js", false);
			$output .= loadCSS("multilanguage-survey.css", false);
			// Initialize
			$output .= "<script>REDCap.MultiLanguage.init({$json});</script>";
		}
		return $output;
	}

	/**
	 * Gets a limited set of settings necessary to translate a survey queue
	 * The amount of information is limited in order not to give away any information that would not 
	 * otherwise be contained on the page
	 * @param Context $context 
	 * @param Array $active_langs A list of active languages
	 * @param Array $survey_ids List of surveys that are shown in the survey queue
	 * @return array 
	 */
	private static function getSurveyQueueSettings(Context $context, $active_langs, $survey_ids) {
		$settings = self::getProjectSettings($context->project_id);
		// Prepare the necessary data 
		$survey_settings = array(
			"cookieName" => self::SURVEY_COOKIE,
			"debug" => $settings["debug"],
			"fallbackLang" => $settings["fallbackLang"],
			"highlightMissing" => $settings["highlightMissingSurvey"],
			"autoDetectBrowserLang" => $settings["autoDetectBrowserLang"],
			"langs" => array(),
			"mode" => $context->is_ajax ? "survey-queue-ajax" : "survey-queue",
			"numLangs" => 0,
			"ref" => array(
				"sq-translations" => array(),
				"ui-translations" => array(),
			),
			"refLang" => $settings["refLang"],
			"setLang" => self::getCurrentLanguage($context),
		);
		// Get reference values (also needed as basis for iterating over some stuff when compiling translation data)
		$ref = self::getProjectMetadata($context->project_id);
		// Get user interface metadata
		$ui_meta = self::getUIMetadata(false);
		// Go over all langs and trim to necessary information
		foreach($active_langs as $lang_id) {
			$this_orig = $settings["langs"][$lang_id];
			$this_lang = $settings["langs"][$lang_id];
			// Add the language, but unset the data dictionary (dd) and ui translations (ui)
			// and add back only the fields and other items that are actually needed
			unset($this_lang["dd"]);
			unset($this_lang["ui"]);
			// Survey elements - only title needed, and only of the surveys contained in the survey queue
			$this_lang["sq-translations"] = array();
			foreach($ref["surveys"] as $form_name => $ref_data) {
				$survey_id = $ref_data["id"];
				if (in_array($survey_id, array_keys($survey_ids ?? []))) {
					$this_lang["sq-translations"]["survey-title"][$survey_id][""] = self::performPiping($context, $this_orig["dd"]["survey-title"][$form_name][""]["translation"] ?? "", false, $lang_id);
					$this_lang["sq-translations"]["survey-repeat_survey_btn_text"][$survey_id][""] = self::performPiping($context, $this_orig["dd"]["survey-repeat_survey_btn_text"][$form_name][""]["translation"] ?? "", false, $lang_id);
					// Reference data
					$survey_settings["ref"]["sq-translations"]["survey-title"][$survey_id][""] = self::performPiping($context, $ref_data["survey-title"]["reference"] ?? "", false, $lang_id);
					// Reference data
					$survey_settings["ref"]["sq-translations"]["survey-repeat_survey_btn_text"][$survey_id][""] = self::performPiping($context, $ref_data["survey-repeat_survey_btn_text"]["reference"] ?? "", false, $lang_id);
				}
			}
			// Custom survey queue text
			$this_lang["sq-translations"]["sq-survey_queue_custom_text"][""][""] = self::performPiping($context, $this_orig["dd"]["sq-survey_queue_custom_text"][""][""]["translation"] ?? "", false, $lang_id);
			// User interface translations
			$this_lang["ui-translations"] = array();
			foreach ($this_orig["ui"] as $ui_key => $ui_translation) {
				$ui_data = $ui_meta[$ui_key];
				if ($ui_data) {
					$this_lang["ui-translations"][$ui_data["id"]] = $ui_translation["translation"];
				}
			}
			// Add "prepared" language
			$survey_settings["langs"][$lang_id] = $this_lang;
		}
		// Reference data
		$survey_settings["ref"]["sq-translations"]["sq-survey_queue_custom_text"][""][""] = self::performPiping($context, $ref["surveyQueue"]["sq-survey_queue_custom_text"]["reference"] ?? "", false, $settings["refLang"]);
		// Finally, add UI reference
		foreach ($ui_meta as $ui_key => $ui_data) {
			$survey_settings["ref"]["ui-translations"][$ui_data["id"]] = $ui_data["default"];
		}
		// Update number of languages
		$survey_settings["numLangs"] = count($survey_settings["langs"]);

		return $survey_settings;
	}

	/**
	 * Provides translations for the Survey Queue email.
	 * Note: As a bonus, this allows projects to define custom subject and body for these emails.
	 * @param Context $context
	 * @return Array Keys: "content", "subject"
	 */
	public static function sendSurveyQueueLink(Context $context) {
		// Get language
		$content = self::getUITranslation($context, "survey_520");
		$subject = self::getUITranslation($context, "survey_523");
		return array(
			"content" => $content,
			"subject" => $subject,
		);
	}

	/**
	 * Emits code (HTML, JS) that will provide a language switcher and translations
	 * in case of a disabled Survey Queue.
	 * @param Context $context
	 * @return void 
	 */
	public static function disabledSurveyQueue(Context $context) {
		// Anything to do?
		$active_langs = self::getActiveLangs($context);
		if (count($active_langs) < 1) return;
		// Translate as "survey-queue-disabled"
		self::translateProjectSurveyAccessoryPage($context, $active_langs, "survey-queue-disabled");
	}

	#endregion

	#region -- Survey Login

	/**
	 * Emits code that will provide a language switcher and translations for the Survey Login
	 * @param Context $context
	 * @return void
	 */
	public static function surveyLogin(Context $context) {
		// Is there anything to do?
		$active_langs = self::getActiveLangs($context);
		if (!count($active_langs)) return;
		// Get survey settings
		$settings = self::getTranslationSettings($context, true);
		$settings["mode"] = "survey-login";
		// Remove unneedes survey settings
		$survey_settings_allowed = ["survey-title"];
		foreach ($settings["langs"] as $_ => &$this_lang) {
			foreach ($this_lang["survey-translations"] as $this_key => $_) {
				if (!in_array($this_key, $survey_settings_allowed)) {
					unset($this_lang["survey-translations"][$this_key]);
					unset($settings["ref"]["survey-translations"][$this_key]);
				}
			}
		}
		// Censor some items
		$settings["excludedFields"] = [];
		$json = self::convertAssocArrayToJSON($settings);
		// And pass off to the JS implementation
		loadJS("MultiLanguageSurvey.js");
		loadCSS("multilanguage-survey.css");
		print "<script>REDCap.MultiLanguage.init({$json});</script>";
	}

	#endregion

	#region -- Access Code Page and Survey Return Page

	/**
	 * Emits code (HTML, JS) that will provide a language switcher and translations
	 * for the /surveys/ endpoint when accessed without any parameters (i.e. when the
	 * "Enter your access code" message is displayed).
	 * @return string 
	 */
	public static function displayAccessCodeForm() {
		// We are outside of any project context here. Thus, get a list of system-defined language
		$active_langs = self::getActiveLangs();
		// End here in case there are no languages
		if (!count($active_langs)) return "";

		// Get system settings
		$settings = self::getSystemSettings();

		// Prepare the translation data
		$survey_settings = array(
			"cookieName" => self::SYSTEM_COOKIE,
			"version" => REDCAP_VERSION,
			"debug" => $settings["debug"],
			"fallbackLang" => $settings["refLang"],
			"highlightMissing" => $settings["highlightMissing"],
			"langs" => array(),
			"mode" => "survey-access",
			"numLangs" => 0,
			"ref" => array(
				"ui-translations" => array(),
			),
			"refLang" => $settings["refLang"],
			"initialLang" => self::getCurrentLanguage(),
		);

		// Only a limited set of strings is needed on this page (thus, limit to these)
		$needed = array(
			"alerts_24",
			"calendar_popup_01",
			"design_08",
			"multilang_02",
			"multilang_647",
			"survey_200",
			"survey_619",
			"survey_634",
			"survey_642",
			"survey_1359",
		);

		// Get user interface metadata
		$ui_meta = self::getUIMetadata(false);
		// Go over all langs and trim to necessary information
		foreach($active_langs as $lang_id) {
			$this_orig = $settings["langs"][$lang_id];
			$this_lang = $settings["langs"][$lang_id];
			// Add the language, but unset ui translations (ui)
			// and add back only the items that are actually needed
			unset($this_lang["ui"]);
			unset($this_lang["visible"]);
			// User interface translations (use language string id as key where possible)
			$this_lang["ui-translations"] = array();
			foreach ($needed as $ui_key) {
				$ui_translation = $this_orig["ui"][$ui_key]["translation"] ?? null;
				if (!self::isEmpty($ui_translation)) {
					$this_lang["ui-translations"][$ui_key] = $ui_translation;
				}
			}
			// Add "prepared" language
			$survey_settings["langs"][$lang_id] = $this_lang;
		}
		// Finally, add UI reference (fall back to global $lang if necessary)
		foreach ($needed as $ui_key) {
			$survey_settings["ref"]["ui-translations"][$ui_key] = $ui_meta[$ui_key]["default"] ?? $GLOBALS["lang"][$ui_key];
		}
		// Update number of languages
		$survey_settings["numLangs"] = count($survey_settings["langs"]);

		$rv = "";
		// Add JavaScript
		$rv .= loadJS("MultiLanguageSurvey.js", false);
		$rv .= loadCSS("multilanguage-survey.css", false);
		$json = self::convertAssocArrayToJSON($survey_settings);
		// Initialize
		$rv .= "<script>REDCap.MultiLanguage.init({$json});</script>";
		return $rv;
	}

	/**
	 * Translates the survey return page.
	 * @param Context $context 
	 * @return void 
	 */
	public static function surveyReturnPage(Context $context) {
		// Anything to do?
		$active_langs = self::getActiveLangs($context);
		if (count($active_langs) < 1) return;
		// Hand off to helper that does the actual work
		// TODO - supply a list of needed ui items to reduce size?
		self::translateProjectSurveyAccessoryPage($context, $active_langs, "survey-return");
	}

	/**
	 * Method to translate survey enter/exit pages that do not contain fields
	 * @param Context $context 
	 * @param Array<string> $active_langs 
	 * @param string $mode 
	 * @return void 
	 */
	private static function translateProjectSurveyAccessoryPage(Context $context, $active_langs, $mode) {
		// Get settings, limited to survey title (as this will be output to the browser)
		$allowed = [
			"survey-title", 
			"survey-acknowledgement", 
			"survey-stop_action_acknowledgement",
			"survey-offline_instructions",
			"survey-response_limit_custom_text",
		];
		$survey_settings = self::getProjectContextSurveySettings($context, $active_langs, $allowed);
		$survey_settings["mode"] = $mode;
		$survey_settings["recaptchaSiteKey"] = $GLOBALS["google_recaptcha_site_key"];

		// Add JavaScript
		loadJS("MultiLanguageSurvey.js");
		loadCSS("multilanguage-survey.css");
		$json = self::convertAssocArrayToJSON($survey_settings);
		// Initialize
		print "<script>REDCap.MultiLanguage.init({$json});</script>";
	}

	#endregion

	#region -- Main Survey Page (Fields)

	/**
	 * Adds the necessary information to survey pages (forms) that allow on-the-fly translation
	 * @param Context $context 
	 * @return void 
	 */
	public static function translateSurvey(Context $context) {
		// Is there anything to do?
		$active_langs = self::getActiveLangs($context);
		if (!count($active_langs)) return;
		// Get settings and encode for JS
		$settings = self::getTranslationSettings($context, true, self::DATAENTRY_SURVEY_BLOCKLIST);
		$json = self::convertAssocArrayToJSON($settings);
		// And pass off to the JS implementation
		loadJS("MultiLanguageSurvey.js");
		loadCSS("multilanguage-survey.css");
		print "<script>REDCap.MultiLanguage.init({$json});</script>";
	}

	#endregion

	#endregion

	#region Text-2-Speech

	/**
	 * Gets the voice code for text-to-speech output
	 * @param Context $context 
	 * @return string 
	 */
	public static function getTextToSpeechLanguage(Context $context) {
		$lang_id = self::getCurrentLanguage($context);
		$Proj = new Project($context->project_id);
		if ($lang_id === false) return $Proj->surveys[$context->survey_id]["text_to_speech_language"];
		$settings = self::getTranslationSettings($context, false);
		$t2s_lang = $settings["langs"][$lang_id]["survey-translations"]["survey-text_to_speech_language"];
		if (self::isEmpty($t2s_lang)) {
			$t2s_lang = $settings["langs"][$settings["fallbackLang"]]["survey-translation"]["survey-text_to_speech_language"];
		}
		if (self::isEmpty($t2s_lang)) {
			$t2s_lang = $settings["ref"]["survey-translations"]["survey-text_to_speech_language"];
		}
		return $t2s_lang;
	}

	#endregion

	#region ASI

	/**
	 * Translates ASI invitation details
	 * @param Context $context 
	 * @param Array $asi_attrs These may be modified (passed by reference)!
	 * @return string|false The language into which data was translated into or false
	 */
	public static function translateASIAttributes(Context $context, &$asi_attrs) {
		// Are there any lanugages active?
		$active_langs = self::getActiveLangs($context);
		if (!count($active_langs)) return false;

		$settings = self::getProjectSettings($context->project_id);
		$meta = self::getProjectMetadata($context->project_id);
		// Check if there is a designated field and a value, and if this value is allowed
		$designated = self::getDesignatedFieldValue($context);
		if ($settings["asiSources"][$context->instrument] != "field" || !in_array($designated, $active_langs)) {
			$designated = null;
		}
		// If so, use that language. Otherwise, determine from context
		$lang_id = $designated != null ? $designated : self::getCurrentLanguage($context);
		if ($lang_id === false) return false;
		$is_ref_lang = $lang_id == $settings["refLang"];
		// Get ASI settings
		$asi_id = "{$context->event_id}-{$context->survey_id}";
		$asi_meta = $meta["asis"][$asi_id];
		$dd = $settings["langs"][$lang_id]["dd"];
		foreach (["asi-email_sender_display","asi-email_subject","asi-email_content"] as $type) {
			$translation = $dd[$type][$asi_meta["form"]][$asi_id]["translation"] ?? null;
			if (self::isEmpty($translation) && !$is_ref_lang) {
				// Use fallback (unless it's the default/reference language)
				$fallback_dd = $settings["langs"][$settings["fallbackLang"]]["dd"];
				$translation = $fallback_dd[$type][$asi_meta["form"]][$asi_id]["translation"];
			}
			if (self::isEmpty($translation)) {
				// Use reference
				$translation = $asi_meta[$type]["reference"];
			}
			$attr_type = substr($type, 4);
			$asi_attrs[$attr_type] = $translation;
		}
		$asi_attrs["asi-rtl"] = $settings["langs"][$lang_id]["rtl"];
		return $lang_id;
	}

	#endregion

	#region Alerts & Notifications

	/**
	 * Gets alert translations.
	 * Only three items need to be translated.
	 * As this is called only from one place (Classes/Alerts.php), the project id and the alert id
	 * will always be part of the context, and no checking for these is necessary.
	 * @param Context $context 
	 * @return Array|false Associative array, or false in case MLM is "out".
	 */
	public static function translateAlert(Context $context) {
		// Are there any lanugages active?
		$active_langs = self::getActiveLangs($context);
		if (!count($active_langs)) return false;
		// Check whether the alert is excluded from translation
		$settings = self::getProjectSettings($context->project_id);
		if ($settings["excludedAlerts"][$context->alert_id] === true) return false;
		// Check if there is a designated field and a value, and if this value is allowed
		$designated = self::getDesignatedFieldValue($context);
		if ($settings["alertSources"][$context->alert_id] != "field" || !in_array($designated, $active_langs)) {
			$designated = null;
		}
		// If so, use that language. Otherwise, determine from context
		$lang_id = $designated != null ? $designated : self::getCurrentLanguage($context);
		// Update the context
		$context = Context::Builder($context)->lang_id($lang_id)->Build();
		// Get translations (or reference values)
		$translations = array(
			"email_from_display" => 
				self::getDDTranslation($context, "alert-email_from_display", $context->alert_id),
			"email_subject" => 
				self::getDDTranslation($context, "alert-email_subject", $context->alert_id),
			"alert_message" => 
				self::getDDTranslation($context, "alert-alert_message", $context->alert_id),
			"lang_id" => $lang_id,
			"rtl" => self::isRTL($context->project_id,$lang_id), self::isActive()
		);
		return $translations;
	}

	#endregion

	#region AJAX Support Methods

	/**
	 * Sets the preferred language of a logged-in user
	 * @param mixed $project_id 
	 * @param mixed $lang_id 
	 * @return void 
	 */
	public static function setUserPreferredLanguage($project_id, $lang_id) {
		UIState::saveUIStateValue($project_id, self::UISTATE_OBJECT, self::UISTATE_LANGPREF, $lang_id); 
	}

	/**
	 * Parses an ini file (REDCap language file) and returns an array that (once JSONified) 
	 * is compatible with importing as user interface translations
	 * @param string $content 
	 * @return Array Ajax response
	 */
	public static function parseIniFile($content) {
		// Parse the ini file content
		$ini = parse_ini_string(json_decode($content));
		if ($ini == false) {
			return self::response(
				false, 
				// Failed to parse .ini file.
				RCView::getLangStringByKey("multilang_143") 
			);
		}
		// Take everything that is defined in the metadata and present in the ini file AND not equal to the
		// default value
		$ui_meta = self::getUIMetadata(true);
		$items = array();
		foreach ($ui_meta as $key => $info) {
			if ($info["type"] == "string" && isset($ini[$key])) {
				$items[] = array(
					"id" => $key,
					"translation" => $ini[$key],
				);
			}
		}
		// Return response object
		return self::response(true, "", array(
			"data" => array (
				"creator" => "REDCap MLM",
				"version" => REDCAP_VERSION,
				"uiTranslations" => $items,
			)));
	}

	/**
	 * Gets a list of potential candidates for the designated language field
	 * @param mixed $project_id 
	 * @return Array 
	 */
	public static function getDesignatedFieldCandidates($project_id) {
		$proj_meta = self::getProjectMetadata($project_id);
		$settings = self::getProjectSettings($project_id);
		$Proj = new Project($project_id);
		$active_langs = array();
		foreach ($settings["langs"] as $this_lang) {
			if ($this_lang["active"]) {
				$active_langs[] = $this_lang["key"];
			}
		}
		$designated = array();
		foreach ($proj_meta["forms"] as $form_name => $form_data) {
			$group_label = $form_data["form-name"]["reference"];
			$candidates = array();
			foreach ($form_data["fields"] as $field_name) {
				$field = $proj_meta["fields"][$field_name];
				if ($field["matrix"]) continue; // Skip fields belonging to a matrix group
				if ($field_name == $Proj->table_pk) continue; // Skip record id field
				if ($field["type"] == "select" || 
					$field["type"] == "radio" ||
					($field["type"] == "text" && $field["validation"] == null)
				) {
					$field["name"] = $field_name;
					$field["type"] = $field["type"] == "text" ? "T" : "R";
					$candidates[] = $field;
				}
			}
			if (count($candidates)) {
				$designated[] = array(
					"optgroup_start" => true,
					"label" => $group_label,
					"form" => $form_name,
					"html" => "<optgroup label=\"{$group_label}\">",
				);
				foreach ($candidates as $field) {
					// Determine complete match with active langs
					$complete = $field["type"] == "R" ? 1 : 2;
					if ($field["type"] == "R") {
						$choices = array_keys($field["field-enum"] ?? []);
						foreach ($active_langs as $lang_id) {
							$complete = $complete * (in_array($lang_id, $choices, true) ? 1 : 0);
						}
					}
					// Create label
					$label_max_len = 50 - strlen($field["name"]) - 5;
					$label = trim(preg_replace('/\s\s+/', ' ', self::de_html($field["field-label"]["reference"])));
					if (mb_strlen($label) > $label_max_len) {
						$label = mb_substr($label, 0, max($label_max_len - 3, 0))."...";
					}
					$designated[] = array(
						"field" => $field["name"],
						"label" => $label,
						"complete" => $complete == 1,
						"html" => "<option data-mlm-complete=\"{$complete}\" value=\"{$field["name"]}\">{$field["name"]} [{$field["type"]}] - {$label}</option>",
					);
				}
				$designated[] = array(
					"optgroup_end" => true,
					"html" => "</optgroup>",
				);
			}
		}
		return $designated;
	}

	/**
	 * Gets translations from a system language
	 * @param string $pid 
	 * @param mixed $data 
	 * @return Array 
	 */
	public static function getSystemLanguage($pid, $guid) {
		// Default response
		$settings = self::getSystemSettings();
		$lang = null;
		foreach ($settings["langs"] as $key => $this_lang) {
			if ($this_lang["guid"] == $guid) {
				$lang = $this_lang;
				break;
			}
		}
		if ($lang == null) {
			return self::response(false, RCView::tt_i_strip_tags("multilang_102", [$guid])); // The language '{0}' is not available.
		}
		$response = self::response();
		$ui_meta = self::getUIMetadata($pid == "SYSTEM");
		$response["lang"] = array(
			"key" => $lang["key"],
			"display" => $lang["display"],
			"notes" => $lang["notes"],
			"rtl" => $lang["rtl"],
			"guid" => $lang["guid"],
			"uiTranslations" => array(),
			"uiOptions" => array(),
		);
		foreach ($ui_meta as $key => $meta) {
			if ($meta["type"] == "bool") {
				// Always copy option values
				$response["lang"]["uiOptions"][] = [
					"id" => $key,
					"value" => (isset($lang["ui"][$key]) && $lang["ui"][$key] == true)
				];
			}
			else {
				// Only copy translations if they are set
				if (isset($lang["ui"][$key]) && !self::isEmpty($lang["ui"][$key])) {
					$response["lang"]["uiTranslations"][] = [
						"id" => $key,
						"translation" => $lang["ui"][$key]["translation"],
						// We always use the metadata hash, as system and project base 
						// languages may differ - otherwise, the "items have changed"
						// warning will appear, which is confusing
						"hash" => $ui_meta[$key]["refHash"],
					];
				}
				else if ($lang["key"] == $settings["refLang"]) {
					$response["lang"]["uiTranslations"][] = array(
						"id" => $key,
						"translation" => $ui_meta[$key]["default"],
						"hash" => $ui_meta[$key]["refHash"]
					);
				}
			}
		}
		return $response;
	}

	/**
	 * Gets the hash value of all project metadata (for change tracking)
	 * @param mixed $project_id The project id
	 * @param boolean $tryDraftMode Whether or not to use draft mode values, if available
	 * @return string The hash value
	 */
	public static function getProjectMetadataHash($project_id, $tryDraftMode=false) {
		$pmd = self::getProjectMetadata($project_id, $tryDraftMode);
		$uimd = self::getUIMetadata(false);
		return sha1(serialize($pmd).serialize($uimd));
	}

	/**
	 * Determines if draft mode is the same as the live version of the project metadata
	 * @param mixed $project_id The project id
	 * @return boolean If draft metadata is the same, return TRUE.
	 */
	public static function metadataChangesMadeInDraftMode($project_id) {
		$liveMetaHash = MultiLanguage::getProjectMetadataHash($project_id, false);
		$draftMetaHash = MultiLanguage::getProjectMetadataHash($project_id, true);
		return $liveMetaHash !== $draftMetaHash;
	}

	/**
	 * Determines if draft mode is the same as the live version of the entire translation settings
	 * @param mixed $project_id The project id
	 * @return boolean If draft transation settings is the same, return TRUE.
	 */
	public static function translationChangesMadeInDraftMode($project_id) {
		$draft_settings = MultiLanguage::getProjectSettings($project_id, true);
		$live_settings = MultiLanguage::getProjectSettings($project_id, false);
		$draftMetaHash = sha1(serialize($draft_settings));
		// "status" will differ in the two cases ("draft", "prod") - adjust to compensate
		$live_settings["status"] = "draft";
		$liveMetaHash = sha1(serialize($live_settings));
		return $liveMetaHash !== $draftMetaHash;
	}

	#endregion

	#region Email Translation

	/**
	 * Translates a survey confirmation email
	 * @param Context $context 
	 * @param Array $survey_attrs These will be modified
	 * @return string|false The language into which data was translated into or false
	 */
	public static function translateSurveyAttributes(Context $context, &$survey_attrs) {
		// Anything to do?
		$lang = self::getCurrentLanguage($context);
		if ($lang === false) return false;
		// Get settings (no allowlist, as this stays internal)
		$survey_settings = self::getProjectContextSurveySettings($context, [$lang], null);
		// Apply survey settings
		foreach ($survey_settings["langs"][$lang]["survey-translations"] as $setting => $translation) {
			$setting = substr($setting, 7);
			if (!self::isEmpty($translation)) {
				$survey_attrs[$setting] = $translation;
			}
		}
		return $lang;
	}

	/**
	 * Translates a survey link email
	 * @param Context $context 
	 * @param Array $email_vals These will be modified
	 * @return string|false The language into which data was translated into or false
	 */
	public static function translateSurveyLinkEmail(Context $context, &$email_vals) {
		// Anything to do?
		$lang = self::getCurrentLanguage($context);
		if ($lang === false) return false;
		// Get settings (no allowlist, as this stays internal)
		$survey_settings = self::getProjectContextSurveySettings($context, [$lang], null);
		// Apply translations
		foreach ($email_vals as $key => $_) {
			if ($key == "title") {
				$email_vals[$key] = self::getSurveyValue($survey_settings, "survey-title", $lang);
			}
			else {
				// UI items
				$email_vals[$key] = self::getUITranslation($context, $key);
			}
		}
		return $lang;
	}

	#endregion

	#region Protected Email Mode

	/**
	 * Translates the protected email page
	 * @param Context $context 
	 * @return void 
	 */
	public static function translateProtectedEmailPage(Context $context) {
		// Is a language specified? If not, there is nothing to do
		if ($context->lang_id == null) return;
		// Are there any lanugages active?
		$active_langs = self::getActiveLangs($context);
		if (!count($active_langs)) return;

		$lang_id = $context->lang_id;

		// Check if the set language is active, and if not, then use the fallback language
		// or, ultimately, the reference language
		$settings = self::getProjectSettings($context->project_id);
		if (!in_array($lang_id, $active_langs)) {
			$lang_id = isset($settings["fallbackLang"]) && in_array($settings["fallbackLang"], $active_langs) ? $settings["fallbackLang"] : $settings["refLang"];
		}
		// Check again if the language is available, and if not, use the first available
		if (!in_array($lang_id, $active_langs)) {
			$lang_id = $active_langs[0];
		}
		$mlm_lang = $settings["langs"][$lang_id];
		// Swap out user interface strings in the $lang global
		foreach ($mlm_lang["ui"] as $lang_key => $value) {
			$GLOBALS["lang"][$lang_key] = $value["translation"];
		}
	}

	/**
	 * Gets the translation of the protected email mode custom text (or the default text)
	 * @param Context $context 
	 * @return string 
	 */
	public static function getProtectEmailModeCustomText(Context $context) {
		return self::getDDTranslation($context, "protmail-protected_email_mode_custom_text", "", "");
	}

	#endregion

	#region PDF Translation

	/**
	 * Determines whether a PDF is an eConsent PDF and what language should be used to translate it
	 * @param Context $context 
	 * @return string 
	 */
	public static function getPDFeConsentFlag(Context $context) {
		$lang_id = self::getCurrentLanguage($context);
		$active = $lang_id !== false;
		$flag = "&".self::LANG_GET_NAME."={$lang_id}&".self::LANG_GET_ECONSENT_FLAG."=1";
		return $active ? $flag : "";
	}

	/**
	 * Gets the translation of the PDF custom header text
	 * @param Context $context 
	 * @return string 
	 */
	public static function getPDFCustomHeaderTextTranslation(Context $context) {
		$text = self::getDDTranslation($context, "pdf-pdf_custom_header_text", "", "");
		return $text;
	}

	/**
	 * Gets a list of forms in PDF metadata
	 * @param mixed $metadata 
	 * @return string[] 
	 */
	private static function getPDFMetadataForms($metadata) {
		$forms = array();
		foreach ($metadata as $md) {
			if (isset($md["form_name"])) {
				$forms[$md["form_name"]] = true;
			}
		}
		return array_keys($forms);
	}

	/**
	 *  UI strings needed to render PDF
	 * @var string[]
	 */
	private static $pdf_strings = array(
		"data_entry_101", // Response is only partial and is not complete.
		"data_entry_248", // [signature]
		"data_entry_428", // Version:
		"data_entry_535", // Response was added on {0:Timestamp}.
		"data_export_tool_248", // FILE:
		"form_renderer_41", // <b>Locked</b> on {0}
		"form_renderer_42", // <b>Locked by {0}</b> ({1}) on {2}
		"form_renderer_62", // E-signed by {0} on {1}
		"global_237", // Confidential
		"survey_1365", // Page {0}
	);

	/**
	 * Translates static PDF strings
	 * @param Context $context 
	 * @return array 
	 */
	public static function getPDFStaticStringTranslations(Context $context) {
		$lang_id = $context->lang_id ?? self::getCurrentLanguage($context);
		$survey_settings = self::getTranslationSettings($context, false);
		$translations = [];
		foreach (self::$pdf_strings as $ui_key) {
			$translations[$ui_key] = self::getUIValue($survey_settings, $ui_key, $lang_id);
		}
		return $translations;
	}

	/**
	 * Translates a PDF.
	 * This is essentially a redcap_pdf hook implementation with some extras.
	 * @param Context $context 
	 * @param Array $metadata 
	 * @param string $acknowledgement 
	 * @param string $project_name 
	 * @param Array $data 
	 * @return Array PDF translated strings by form [ form_name => [ ui_string_id => translation ]]
	 */
	public static function translatePDF(Context $context, &$metadata, &$acknowledgement, &$project_name, &$data) {
		// Multi-record or multi-instrument PDF?
		$forms = self::getPDFMetadataForms($metadata);
		$num_forms = count($forms);
		$num_records = count($data);
		$pdf_translations = array();

		// Multi-record exports will ALWAYS be in the reference language!
		if ($num_records > 1 || isset($_GET["allrecords"])) return $pdf_translations;
		
		// Multiple forms are requested from data entry pages only. For PDF requests initiated from data entry
		// pages, use the language set by the logged-in user
		if ($num_forms > 1) {
			$lang_id = self::getCurrentLanguage($context);
		}
		else {
			// If only a single form is in the PDF, then obey the language set in the context
			$lang_id = $context->lang_id ?? self::getCurrentLanguage($context);
		}
		// Process metadata for each form
		foreach ($forms as $form_name) {
			$form_context = Context::Builder($context)->instrument($form_name)->lang_id($lang_id)->Build();
			$survey_settings = self::getTranslationSettings($form_context, false);
			// Apply translations when active
			if ($survey_settings["active"]) {
				// Translate survey title and instructions
				$survey =& self::getSurveyRef($form_name);
				if ($survey != null) {
					$survey["title"] = self::getSurveyValue($survey_settings, "survey-title", $lang_id);
					$survey["instructions"] = self::getSurveyValue($survey_settings, "survey-instructions", $lang_id);
				}
				if (isset($GLOBALS["ProjForms"][$form_name])) {
					$GLOBALS["ProjForms"][$form_name]["menu"] = self::getDDTranslation($form_context, "form-name", $form_name);
				}
				// Translate fields (label, note, header, enum)
				foreach ($metadata as &$fmd) {
					if ($fmd["form_name"] != $form_name) continue;
					$field_name = $fmd["field_name"];
					$fmd["element_label"] = self::getFieldValue($survey_settings, $field_name, "label", $lang_id);
					$fmd["element_note"] = self::getFieldValue($survey_settings, $field_name, "note", $lang_id);
					if (!empty($fmd["element_preceding_header"])) {
						$fmd["element_preceding_header"] = self::getFieldValue($survey_settings, $field_name, "header", $lang_id);
					}
					if ($fmd["element_type"] != "sql") { // Leave SQL fields alone
						$fmd["element_enum"] = self::getFieldEnum($survey_settings, $field_name, $lang_id, $fmd["element_type"] == "slider");
						// Enums in the global $Proj needs to be updated with the same data, as piping will fall back to those values
						$GLOBALS["Proj"]->metadata[$field_name]["element_enum"] = $fmd["element_enum"];
					}
				}
				// Translate static strings needed for PDF rendering
				$pdf_translations[$form_name] = self::getPDFStaticStringTranslations($form_context);
				// Translate the custom PDF header text
				$pdf_translations[$form_name]["__PDF_CUSTOM_HEADER_TEXT"] = self::getPDFCustomHeaderTextTranslation($context);
				// Add language key
				$pdf_translations[$form_name]["__LANG"] = $lang_id;
			}
		}
		return $pdf_translations;
	}

	#endregion

	#region MyCap Translation

	/**
	 * Gets a list of MyCap enabled languages
	 * @param mixed $project_id 
	 * @return array Returns an array of entries [code,display,rtl]
	 */
	public static function getMyCapActiveLanguages($project_id) {
		$context = Context::Builder()->project_id($project_id)->is_mycap()->Build();
		$mycap_langs = [];
		$ps = self::getProjectSettings($project_id);
		$active_langs = self::getActiveLangs($context);

		if (count($active_langs) > 1) {
			// Set Base Language to first position in Language list if more than 1 languages
			$refLang = $ps['refLang'];
			if ($ps['langs'][$refLang]['mycap-active'] == 1) {
				$key = array_search($ps['refLang'], $active_langs);
				unset($active_langs[$key]);
				array_unshift($active_langs, $refLang);
			}
		}

		$myCapSupportedLangCodes = array_keys(self::MYCAP_SUPPORTED_LANGS);
		foreach ($active_langs as $lang_id) {
			$myCapCode = $lang_id;
			$match = strtok($lang_id, '-');
			if (!in_array($lang_id, $myCapSupportedLangCodes)) {
				foreach ($myCapSupportedLangCodes as $code) {
					if (strtok($code, '-') === $match) {
						$myCapCode = $code;
						break;
					}
				}
			}

			$mycap_langs[] = [
				"code" => $myCapCode,
				"redcap-code" => $lang_id,
				"display" => $ps["langs"][$lang_id]["display"],
				"rtl" => $ps["langs"][$lang_id]["rtl"],
			];
		}
		return $mycap_langs;
	}

	#endregion

	#region Data Structure for Survey/Data Entry Translation, and accessors

	/**
	 * Gets the settings necessary to translate a survey
	 * @param Context $context 
	 * @param boolean $do_piping Determines whether piping should be performed
	 * @param Array $blocklist A list of types that should be excluded (default: empty)
	 * @return Array 
	 */
	public static function getTranslationSettings(Context $context, $do_piping, $blocklist = []) {
		// Prepare return data
		$translation_settings = array(
			"active" => false,
			"debug" => false,
			"eventId" => $context->event_id,
			"excludedFields" => array(),
			"fallbackLang" => "",
			"fieldEmbedClass" => "rc-field-embed", // hardcoded in Piping class
			"highlightMissing" => false,
			"langs" => array(),
			"mode" => "dataentry-form",
			"numLangs" => 0,
			"pipingPlaceholder" => Piping::missing_data_replacement,
			"pipingReceiverClass" => Piping::piping_receiver_class,
			"pipingReceiverClassField" => Piping::piping_receiver_class_field,
			"ref" => array(
				"field-translations" => array(),
				"ui-translations" => array(),
				"descriptive-popup-translations" => array(),
			),
			"refLang" => "",
			"setLang" => self::getCurrentLanguage($context),
			"version" => defined("REDCAP_VERSION") ? REDCAP_VERSION : "??",
			"staticMenu" => false,
		);
		if (!$context->is_dataentry) {
			$translation_settings["cookieName"] = self::SURVEY_COOKIE;
			$translation_settings["mode"] = "survey";
			$translation_settings["ref"]["survey-translations"] = array();
		}
		else {
			// Add user's preferred language (so JS can determine whether to update this on first render)
			$translation_settings["userPreferredLang"] = self::getUserPreferredLanguage($context);
		}
		// Not in project context? Return what we have so far.
		if ($context->project_id == null) return $translation_settings;

		// Are there are any active languages?
		$active_langs = self::getActiveLangs($context);
		if (count($active_langs) < 1) return $translation_settings;

		$settings = self::getProjectSettings($context->project_id);
		$Project = new \Project($context->project_id);

		// Update some data
		if ($settings["autoDetectBrowserLang"]) {
			$translation_settings["setLang"] = self::getCurrentLanguage($context, true);
		}
		$fallback_lang = $settings["fallbackLang"];
		$ref_lang = $settings["refLang"];
		// Remove any translations that might exist for the base language (ref lang),
		// as we do not want them to show up (but we also do not want to remove them from the
		// backend)
		if (isset($settings["langs"][$ref_lang])) {
			$settings["langs"][$ref_lang]["dd"] = [];
			$settings["langs"][$ref_lang]["ui"] = [];
		}
		$translation_settings["active"] = true;
		$translation_settings["debug"] = $settings["debug"];
		$translation_settings["refLang"] = $ref_lang;
		$translation_settings["highlightMissing"] = ($context->is_dataentry ? $settings["highlightMissingDataentry"] : $settings["highlightMissingSurvey"]);
		$translation_settings["autoDetectBrowserLang"] = $settings["autoDetectBrowserLang"];
		$translation_settings["fallbackLang"] = $fallback_lang;
		// Excluded fields
		foreach ($settings["excludedFields"] as $exclField => $_) {
			if (in_array($exclField, $context->page_fields)) {
				$translation_settings["excludedFields"][$exclField] = true;
			}
		}
		// Add additional information depending on context
		if ($context->is_pdf) {
			$translation_settings["designatedField"] = $settings["designatedField"];
		}

		// Get reference values (also needed as basis for iterating over some stuff when compiling translation data)
		$ref = self::getProjectMetadata($context->project_id);
		// Get user interface metadata
		$ui_meta = self::getUIMetadata(false);

		// Fields (on page + piped)
		$fields = array_unique(array_merge($context->page_fields, $context->piped_fields));

		#region Translations

		$inactive_fallback = "";
		// Make sure that the fallback language is included (unless it's the reference language)
		if (!in_array($fallback_lang, $active_langs) && $fallback_lang != $ref_lang) {
			$active_langs[] = $fallback_lang;
			$inactive_fallback = $fallback_lang;
		}

		// Go over all langs and trim to necessary information
		foreach($active_langs as $lang_id) {
			$this_orig = self::swapLangDdTypeName($settings["langs"][$lang_id]);
			$this_lang = $settings["langs"][$lang_id];
			if ($lang_id == $inactive_fallback) {
				$this_lang["active"] = false;
			}
			// Add the language, but unset the data dictionary (dd) and ui translations (ui)
			// and add back only the fields and other items that are actually needed
			unset($this_lang["dd"]);
			unset($this_lang["ui"]);
			// Data dictionary - fields
			$this_lang["field-translations"] = array();
			// Go through all fields on the survey page
			foreach ($fields as $field_name) {
				if (is_array($field_name)) {
					// Login field
					$field_context = Context::Builder($context)->event_id($field_name["event_id"])->Build();
					$field_name = $field_name["field"];
				}
				else {
					$field_context = Context::Builder($context)->Build();
				}
				// Check that field exists in project
				if (!array_key_exists($field_name, $ref["fields"])) continue;

				// Get the field - if empty (null), then set a dummy entry
				$field = $this_orig["dd_swap"][$field_name] ?? array(
					"field-empty" => true
				);
				$field_meta = $ref["fields"][$field_name];
				// Add entry for excluded fields
				if (isset($translation_settings["excludedFields"][$field_name]) && $translation_settings["excludedFields"][$field_name]) {
					$field["field-excluded"] = true;
				}
				// Mark yes/no and true/false fields as enums
				if ($field_meta["type"] == "yesno" || $field_meta["type"] == "truefalse") {
					$field["field-enum"] = array();
				}
				// Marked as complete?
				if (isset($field["field-complete"]) && $field["field-complete"]) {
					$field["field-complete"] = true;
				}
				// Process field embedding (see DataEntry::renderForm() - around line 1830)
				foreach (["field-label", "field-note", "field-header"] as $type) {
					if (isset($field[$type]) && self::isEmpty($field[$type])) continue;
					$translation = (!isset($field[$type][""]["translation"]) ? "" : ($do_piping ? self::performPiping($field_context, $field[$type][""]["translation"], true, $lang_id) : $field[$type][""]["translation"]));
					$translation = filter_tags($translation);
					$field[$type] = replaceUrlImgViewEndpoint($translation);
				}
				foreach (["field-video_url"] as $type) {
					if (isset($field[$type])) {
						$translation = (!isset($field[$type][""]["translation"]) ? "" : ($do_piping ? self::performPiping($field_context, $field[$type][""]["translation"], true, $lang_id) : $field[$type][""]["translation"]));
						$video_url = \DataEntry::formatVideoUrl($translation);
						$field[$type] = $video_url[1];
					}
				}
				foreach (["field-enum"] as $type) {
					if ($field_meta["type"] == "yesno") {
						$field[$type] = array();
						$field[$type][0] = filter_tags($this_orig["ui"]["design_99"]["translation"] ?? null); // No
						$field[$type][1] = filter_tags($this_orig["ui"]["design_100"]["translation"] ?? null); // Yes
					}
					else if ($field_meta["type"] == "truefalse") {
						$field[$type] = array();
						$field[$type][0] = filter_tags($this_orig["ui"]["design_187"]["translation"] ?? null); // False
						$field[$type][1] = filter_tags($this_orig["ui"]["design_186"]["translation"] ?? null); // True
					}
					elseif (isset($field[$type]) && is_array($field[$type])) {
						foreach ($field[$type] as $index => $value) {
							$translation = $do_piping ? self::performPiping($field_context, $value["translation"], $type == "field-enum", $lang_id) : $value["translation"];
							$field[$type][$index] = filter_tags($translation);
						}
					}
					// Add missing data codes to select type fields
					if (in_array($field_meta["type"], ["select","sql"]) && count($ref["mdcs"])) {
						foreach ($ref["mdcs"] as $mdc => $_) {
							$field[$type][$mdc] = filter_tags($this_orig["dd"]["mdc-label"][$mdc][""]["translation"] ?? "");
						}
					}
				}
				foreach (["field-actiontag"] as $type) {
					if (isset($field[$type]) && is_array($field[$type])) {
						// The @IF action tag presents some challenges
						// If there has been some @IF processing, there will be a misc_original
						$misc_original = isset($Project->metadata[$field_name]["misc_original"]) ? $Project->metadata[$field_name]["misc_original"] : $Project->metadata[$field_name]["misc"];
						// Replace the action tag names to include their index
						$misc_replaced = $misc_original;
						$pos = 0;
						foreach ($field[$type] as $index => $value) {
							$atName = explode(".", $index, 2)[0];
							$pos = strpos($misc_replaced, $atName, $pos);
							if ($pos !== false) {
								$misc_replaced = substr_replace($misc_replaced, $index, $pos, strlen($atName));
								$pos += strlen($atName);
							}
						}
						// Process @IF
						$misc = strpos($misc_replaced, "@IF") === false ? $misc_replaced : \Form::replaceIfActionTag($misc_replaced, $context->project_id, $context->record, $context->event_id, $context->instrument, $context->instance);
						foreach ($field[$type] as $index => $value) {
							if (strpos($misc, $index) !== false) {
								// Only add translation when the action tag with index number is actually present
								// Note: When an action tag is present multiple times, the last one wins!
								$translation = $do_piping ? self::performPiping($context, $value["translation"], $type == "field-enum", $lang_id) : $value["translation"];
								$atName = explode(".", $index, 2)[0];
								$field[$type][$atName] = $translation;
							}
							// Remove original
							unset($field[$type][$index]);
						}
					}
				}
				$this_lang["field-translations"][$field_name] = $field;
				// Matrix group? Make note and add ref
				$mg_name = $ref["fields"][$field_name]["matrix"] ?? false;
				if ($mg_name) {
					$this_lang["field-translations"][$field_name]["matrix"] = $mg_name;
					// To be able to set matrix header and enum, the values need to be copied to the field
					if (isset($this_orig["dd"]["matrix-enum"][$mg_name])) {
						foreach ($this_orig["dd"]["matrix-enum"][$mg_name] as $index => $value) {
							$translation = $do_piping ? self::performPiping($field_context, $value["translation"], true, $lang_id) : $value["translation"];
							$this_lang["field-translations"][$field_name]["field-enum"][$index] = filter_tags($translation);
						}
					}
					if (isset($this_orig["dd"]["matrix-header"][$mg_name])) {
						$translation = $do_piping ? self::performPiping($field_context, $this_orig["dd"]["matrix-header"][$mg_name][""]["translation"], true, $lang_id) : $this_orig["dd"]["matrix-header"][$mg_name][""]["translation"];
						$this_lang["field-translations"][$field_name]["field-header"] = filter_tags($translation);
					}
				}
			}
			// Items needed for data entry pages only
			if ($context->is_dataentry) {
				// Form names
				$this_lang["form-name"] = array();
				foreach ($ref["forms"] as $form_name => $_) {
					$this_lang["form-name"][$form_name] = filter_tags($this_orig["dd"]["form-name"][$form_name][""]["translation"] ?? "");
				}
				// Event names and custom event labels
				$this_lang["event-name"] = array();
				$this_lang["event-custom_event_label"] = array();
				foreach ($ref["events"] as $event_id => $_) {
					$this_lang["event-name"][$event_id] = filter_tags($this_orig["dd"]["event-name"][$event_id][""]["translation"] ?? "");
					$this_lang["event-custom_event_label"][$event_id] = filter_tags($this_orig["dd"]["event-custom_event_label"][$event_id][""]["translation"] ?? "");
				}
				foreach ($ref["mdcs"] as $mdc => $_) {
					$this_lang["mdc-label"][$mdc] = filter_tags($this_orig["dd"]["mdc-label"][$mdc][""]["translation"] ?? "");
				}
			}
			// Items needed for survey pages only
			if ($context->is_survey) {
				// Survey login error
				$this_lang["sq-translations"] = array(
					"sq-survey_auth_custom_message" => filter_tags($this_orig["dd"]["sq-survey_auth_custom_message"][""][""]["translation"] ?? "")
				);
			}
			// Survey elements (included for data entry pages as well - needed e.g. for PDF generation)
			$this_lang["survey-translations"] = array();
			if (isset($ref["surveys"][$context->instrument])) {
				foreach ($ref["surveys"][$context->instrument] as $survey_element => $ref_data) {
					if (count($blocklist) && in_array($survey_element, $blocklist, true)) {
						// Do not include blocked items
						continue;
					}
					if (starts_with($survey_element, "survey-")) {
						$translation = isset($this_orig["dd"][$survey_element][$context->instrument][""]["translation"]) ? ($do_piping ? self::performPiping($context, $this_orig["dd"][$survey_element][$context->instrument][""]["translation"], false, $lang_id) : $this_orig["dd"][$survey_element][$context->instrument][""]["translation"]) : "";
						$translation = filter_tags($translation);
						$this_lang["survey-translations"][$survey_element] = replaceUrlImgViewEndpoint($translation);
						
						$ref_translation = replaceUrlImgViewEndpoint($do_piping ? self::performPiping($context, $ref_data["reference"], false, $lang_id) : $ref_data["reference"]);
						$ref_translation = filter_tags($ref_translation);
						$translation_settings["ref"]["survey-translations"][$survey_element] = $ref_translation;
					}
				}
			}
			// User interface translations (use language string id as key where possible)
			$this_lang["ui-translations"] = array();
			$boolean_overrides = array();
			foreach ($this_orig["ui"] as $ui_key => $ui_translation) {
				$ui_data = $ui_meta[$ui_key];
				if ($ui_data) {
					if ($ui_data["type"] != "bool") {
						$this_lang["ui-translations"][$ui_data["id"]] = filter_tags($ui_translation["translation"]);
					}
					else {
						// Add to a list when enabled
						if ($ui_translation["translation"]) {
							$boolean_overrides[] = $ui_data;
						}
					}
				}
			}
			// Apply boolean overrides
			foreach ($boolean_overrides as $item) {
				foreach ($item["overrides"] as $id => $override) {
					$this_lang["ui-translations"][$id] = filter_tags($override);
				}
			}
			// Add consent form (text or inline PDF) for e-Consent
			$ec = new \Econsent();
			$this_lang["econsent-consent-form"] = []; // placeholder
			foreach ($ec->getEconsentSettings($context->project_id) as $attr) {
				// Skip if this item is another instrument
				if (($context->is_survey && $attr['survey_id'] != $context->survey_id) || (!$context->is_survey && $Project->surveys[$attr['survey_id']]["form_name"] != $context->instrument)) {
					continue;
				}
				// Get e-Consent settings for this context and this specific language
				$this_lang_econsent = $ec->getEconsentSurveySettings($attr['survey_id'], $context->group_id, $lang_id);
				$pdf_url = "";
				if (isset($this_lang_econsent['consent_form_pdf_doc_id']) && isinteger($this_lang_econsent['consent_form_pdf_doc_id'])) {
					$image_view_page = $context->is_survey ? APP_PATH_SURVEY . "index.php?pid=".$context->project_id."&__passthru=".urlencode("DataEntry/image_view.php") : APP_PATH_WEBROOT . "DataEntry/image_view.php?pid=".$context->project_id;
					$pdf_url = "$image_view_page&doc_id_hash=".Files::docIdHash($this_lang_econsent['consent_form_pdf_doc_id'])."&id=".$this_lang_econsent['consent_form_pdf_doc_id']."&instance=".$context->instance.($context->is_survey ? "&s=".$context->survey_hash : "");
				}
				$this_lang["econsent-consent-form"] = ['inline-pdf-url'=>$pdf_url, 'rich-text'=>filter_tags($this_lang_econsent['consent_form_richtext'] ?? '')];
			}
			// Add descriptive popups
			foreach ($ref["descriptivePopups"] as $popup_id => $popup) {
				foreach ($popup as $key => $ref_data) {
					$translation = $this_orig["dd"]["descriptive-popup"][$key][$popup_id]["translation"] ?? "";
					$ref_translation = $ref_data["reference"];
					$this_lang["descriptive-popup-translations"][$popup_id][$key] = filter_tags($translation);
					$translation_settings["ref"]["descriptive-popup-translations"][$popup_id][$key] = filter_tags($ref_translation);
				}
			}
			// Add "prepared" language
			$translation_settings["langs"][$lang_id] = $this_lang;
		}
		#endregion

		#region Reference values

		// Now we need the project reference data
		// Copy needed reference values and perform field embedding and piping
		foreach ($fields as $field_name) {
			if (is_array($field_name)) {
				// Login field
				$field_context = Context::Builder($context)->event_id($field_name["event_id"])->Build();
				$field_name = $field_name["field"];
			}
			else {
				$field_context = Context::Builder($context)->Build();
			}
			// Check that field exists in project
			if (!array_key_exists($field_name, $ref["fields"])) continue;

			$field = $ref["fields"][$field_name];
			foreach(["field-label", "field-note", "field-header"] as $type) {
				$value = $field[$type]["reference"] ?? "";
				$reference = $do_piping ? self::performPiping($field_context, $value, true, $ref_lang) : $value;
				$field[$type] = $field_context->is_pdf ? $reference : filter_tags($reference); // Not sure why, but filter_tags() here was causing some kind of recursive issue in specific projects
			}
			foreach(["field-video_url"] as $type) {
				$value = $field[$type]["reference"] ?? "";
				if ($value != "") {
					$reference = $do_piping ? self::performPiping($field_context, $value, true, $ref_lang) : $value;
					$video_url = \DataEntry::formatVideoUrl($reference);
					$field[$type] = $video_url[1];
				}
				else {
					$field[$type] = filter_tags($value);
				}
			}
			foreach(["field-enum"] as $type) {
				if ($field["type"] == "yesno") {
					// Add 1: 'Yes' and 0: 'No'
					$to_process = array(
						0 => array("reference" => RCView::getLangStringByKey("design_99")),
						1 => array("reference" => RCView::getLangStringByKey("design_100")),
					);
				}
				else if ($field["type"] == "truefalse") {
					// Add 1: 'True' and 0: 'False'
					$to_process = array(
						0 => array("reference" => RCView::getLangStringByKey("design_187")),
						1 => array("reference" => RCView::getLangStringByKey("design_186")),
					);
				}
				else if ($field["type"] == "slider" && $field["field-enum"] == null) {
					// Force empty strings for sliders that have no field enum
					$to_process = [
						"left" => [ "reference" => "", "refHash" => "" ],
						"middle" => [ "reference" => "", "refHash" => "" ],
						"right" => [ "reference" => "", "refHash" => "" ]
					];
				}
				else {
					$to_process = $field[$type];
				}
				if (is_array($to_process)) {
					foreach ($to_process as $index => $value) {
						$value = $value["reference"] ?? "";
						$reference = $do_piping ? self::performPiping($field_context, $value, true, $ref_lang) : $value;
						$field[$type][$index] = filter_tags($reference);
					}
				}
				// Add missing data codes to select type fields
				if (in_array($field["type"], ["select","sql"]) && count($ref["mdcs"])) {
					foreach ($ref["mdcs"] as $mdc => $_) {
						$field[$type][$mdc] = filter_tags($ref["mdcs"][$mdc]["reference"] ?? "");
					}
				}
				
			}
			foreach(["field-actiontag"] as $type) {
				if (is_array($field[$type]) && count($field[$type])) {

					// The @IF action tag presents some challenges
					// If there has been some @IF processing, there will be a misc_original
					$misc_original = isset($Project->metadata[$field_name]["misc_original"]) ? $Project->metadata[$field_name]["misc_original"] : $Project->metadata[$field_name]["misc"];
					// Replace the action tag names to include their index
					$misc_replaced = $misc_original;
					$pos = 0;
					foreach ($field[$type] as $index => $value) {
						$atName = explode(".", $index, 2)[0];
						$pos = strpos($misc_replaced, $atName, $pos);
						if ($pos !== false) {
							$misc_replaced = substr_replace($misc_replaced, $index, $pos, strlen($atName));
							$pos += strlen($atName);
						}
					}
					// Process @IF
					$misc = strpos($misc_replaced, "@IF") === false ? $misc_replaced : \Form::replaceIfActionTag($misc_replaced, $context->project_id, $context->record, $context->event_id, $context->instrument, $context->instance);
					foreach ($field[$type] as $index => $value) {
						if (strpos($misc, $index) !== false) {
							// Only add reference when the action tag with index number is actually present
							// Note: When an action tag is present multiple times, the last one wins!
							$value = $value["reference"] ?? "";
							$reference = $do_piping ? self::performPiping($field_context, $value, false, $ref_lang) : $value;
							// Note: Last one wins!
							$atName = explode(".", $index, 2)[0];
							$field[$type][$atName] = $reference;
						}
						// Remove original
						unset($field[$type][$index]);
					}
				}
			}
			// Belonging to a matrix group
			if ($field["matrix"]) {
				// Add reference
				$mg_name = $field["matrix"];
				$mg_ref = $ref["matrixGroups"][$mg_name];
				unset($mg_ref["fields"]);
				// Set ref
				foreach ($mg_ref as $type => $td) {
					if ($type == "matrix-enum") {
						foreach ($td as $index => $value) {
							$mg_ref[$type][$index] = filter_tags($do_piping ? self::performPiping($field_context, $value["reference"], true, $ref_lang) : $value["reference"]);
						}
					}
					else if (starts_with($type, "matrix-")) {
						$mg_ref[$type] = filter_tags($do_piping ? self::performPiping($field_context, $td["reference"], true, $ref_lang) : $td["reference"]);
					}
				}
				$translation_settings["ref"]["matrix-translations"][$mg_name] = $mg_ref;
				// Copy the matrix header and enums to the field
				$field["field-header"] = $mg_ref["matrix-header"] ?? "";
				$field["field-enum"] = $mg_ref["matrix-enum"] ?? "";
			}
			// Is the field piped?
			$field["piped"] = in_array($field_name, $context->piped_fields, true);
			// Is it on the page?
			$field["onPage"] = in_array($field_name, $context->page_fields, true);
			$translation_settings["ref"]["field-translations"][$field_name] = $field;
		}
		// Items needed for data entry pages only
		if ($context->is_dataentry) {
			// Form names
			foreach ($ref["forms"] as $form_name => $form_data) {
				$translation_settings["ref"]["form-name"][$form_name] = filter_tags($form_data["form-name"]["reference"] ?? "");
			}
			// Event names and custom event labels
			foreach ($ref["events"] as $event_id => $event_data) {
				$translation_settings["ref"]["event-name"][$event_id] = filter_tags($event_data["event-name"]["reference"] ?? "");
				$translation_settings["ref"]["event-custom_event_label"][$event_id] = filter_tags($event_data["event-custom_event_label"]["reference"] ?? "");
			}
			// Missing data code labels
			foreach ($ref["mdcs"] as $mdc => $mdc_data) {
				$translation_settings["ref"]["mdc-label"][$mdc] = filter_tags($mdc_data["reference"]);
			}
		}
		// Items needed for surveys only
		if ($context->is_survey) {
			// Login error message
			$translation_settings["ref"]["sq-translations"]["sq-survey_auth_custom_message"] = filter_tags($ref["surveyQueue"]["sq-survey_auth_custom_message"]["reference"] ?? "");
		}
		// Finally, add UI reference
		foreach ($ui_meta as $ui_key => $ui_data) {
			$translation_settings["ref"]["ui-translations"][$ui_data["id"]] = $ui_data["default"];
		}
		// And the project id field
		$translation_settings["ref"]["recordIdField"] = $ref["recordIdField"];
		#endregion

		// Apply @LANGUAGE-FORCE
		if ($context->instrument) {
			$force = self::getLanguageForceActionTags($context);
			$force = strip_tags(Piping::replaceVariablesInLabel($force, $context->record, $context->event_id, $context->instance));
			// When the language exists and is active, replace langs by just this one language
			if (in_array($force, $active_langs)) {
				$translation_settings["langs"] = array (
					$force => $translation_settings["langs"][$force]
				);
				$translation_settings["fallbackLang"] = $force;
				$translation_settings["setLang"] = $force;
			}
		}
		// Apply @LANGUAGE-MENU-STATIC (consider @IF), only on surveys (and never for PDFs)
		if ($context->is_survey && !$context->is_pdf) {
			foreach ($context->page_fields as $_ => $field_name) {
				$original_misc = $Project->metadata[$field_name]["misc"];
				if (empty($original_misc)) continue;
				if (strpos($original_misc, "@LANGUAGE-MENU-STATIC") === false) continue;
				$replaced_misc = \Form::replaceIfActionTag($original_misc, $context->project_id, $context->record, $context->event_id, $context->instrument, $context->instance);
				$translation_settings["staticMenu"] = strpos($replaced_misc, "@LANGUAGE-MENU-STATIC") !== false;
				if ($translation_settings["staticMenu"]) {
					break; // One instance is enough
				}
			}
		}

		// Update number of languages
		$translation_settings["numLangs"] = count($translation_settings["langs"]);

		return $translation_settings;
	}

	/**
	 * Gets the data value of a field
	 * @param string $name 
	 * @param Context $context 
	 * @return string 
	 */
	private static function getFieldDataValue($field_name, Context $context) {
		$data = \Records::getData($context->project_id, "array", $context->record, $field_name, $context->event_id, $context->group_id);
		$Proj = new Project($context->project_id);
		$form = $Proj->metadata[$field_name]["form_name"];
		if ($Proj->isRepeatingFormOrEvent($context->event_id, $form)) {
			if ($Proj->isRepeatingEvent($context->event_id)) {
				return $data[$context->record]["repeat_instances"][$context->event_id][""][$context->instance][$field_name];
			}
			else {
				return $data[$context->record]["repeat_instances"][$context->event_id][$context->instrument][$context->instance][$field_name];
			}
		}
		else {
			return $data[$context->record][$context->event_id][$field_name];
		}
	}

	/**
	 * Gets a limited set of settings necessary to translate a survey accessory page (i.e. a page without fields)
	 * The amount of information is limited in order not to give away any information that would not 
	 * otherwise be contained on the page
	 * @param Context $context 
	 * @param Array $active_langs A list of active languages
	 * @param Array|null $survey_settings_allowed A list of survey settings to include
	 * @return array 
	 */
	private static function getProjectContextSurveySettings(Context $context, $active_langs, $survey_settings_allowed = null) {
		$settings = self::getProjectSettings($context->project_id);
		// Prepare the necessary data 
		$survey_settings = array(
			"cookieName" => self::SURVEY_COOKIE,
			"debug" => $settings["debug"],
			"fallbackLang" => $settings["fallbackLang"],
			"highlightMissing" => $settings["highlightMissingSurvey"],
			"autoDetectBrowserLang" => $settings["autoDetectBrowserLang"],
			"langs" => array(),
			"mode" => "",
			"numLangs" => 0,
			"ref" => array(
				"survey-translations" => array(),
				"ui-translations" => array(),
			),
			"refLang" => $settings["refLang"],
			"setLang" => self::getCurrentLanguage($context),
			"version" => defined("REDCAP_VERSION") ? REDCAP_VERSION : "?.?.?",
		);
		// Get reference values (also needed as basis for iterating over some stuff when compiling translation data)
		$ref = self::getProjectMetadata($context->project_id);
		// Get user interface metadata
		$ui_meta = self::getUIMetadata(false);
		// Go over all langs and trim to necessary information
		foreach($active_langs as $lang_id) {
			$this_orig = $settings["langs"][$lang_id];
			$this_lang = $settings["langs"][$lang_id];
			// Add the language, but unset the data dictionary (dd) and ui translations (ui)
			// and add back only the fields and other items that are actually needed
			unset($this_lang["dd"]);
			unset($this_lang["ui"]);
			// Survey elements - only title and acknowledgement needed
			$this_lang["survey-translations"] = array();
			if (isset($ref["surveys"][$context->instrument])) {
				foreach ($ref["surveys"][$context->instrument] as $survey_element => $ref_data) {
					if (starts_with($survey_element, "survey-") && ($survey_settings_allowed == null || in_array($survey_element, $survey_settings_allowed))) {
						$wrap = $ref["surveys"][$context->instrument][$survey_element]["wrap"] ?? false;
						$this_lang["survey-translations"][$survey_element] = self::performPiping($context, $this_orig["dd"][$survey_element][$context->instrument][""]["translation"] ?? "", false, $lang_id, $wrap);
						$survey_settings["ref"]["survey-translations"][$survey_element] = self::performPiping($context, $ref_data["reference"], false, $settings["refLang"], $wrap);
					}
				}
			}
			// User interface translations (use language string id as key where possible)
			$this_lang["ui-translations"] = array();
			foreach ($this_orig["ui"] as $ui_key => $ui_translation) {
				$ui_data = $ui_meta[$ui_key];
				if ($ui_data) {
					$this_lang["ui-translations"][$ui_data["id"]] = $ui_translation["translation"];
				}
			}
			// Add "prepared" language
			$survey_settings["langs"][$lang_id] = $this_lang;
		}
		// Finally, add UI reference
		foreach ($ui_meta as $ui_key => $ui_data) {
			$survey_settings["ref"]["ui-translations"][$ui_data["id"]] = $ui_data["default"];
		}
		// Update number of languages
		$survey_settings["numLangs"] = count($survey_settings["langs"]);

		return $survey_settings;
	}


	/**
	 * Gets a UI item translation
	 * @param Array $ss Survey Settings
	 * @param string $key The id of the value
	 * @param string $lang_id The language id
	 * @return mixed 
	 */
	private static function getUIValue($ss, $key, $lang_id) {
		$ref_val = $ss["ref"]["ui-translations"][$key] ?? "";
		$is_ref_lang = $lang_id == $ss["refLang"];
		$mlm_lang = ($lang_id && array_key_exists($lang_id, $ss["langs"])) ? ($ss["langs"][$lang_id] ?? false) : ($ss["langs"][$ss["fallbackLang"]] ?? false);
		if ($mlm_lang) {
			$val = $mlm_lang["ui-translations"][$key] ?? "";
			if (self::isEmpty($val) && $lang_id != $ss["fallbackLang"] && !$is_ref_lang) {
				$val = $ss["langs"][$ss["fallbackLang"]]["ui-translations"][$key] ?? "";
			}
			return self::isEmpty($val) ? $ref_val : $val;
		}
		// Use from reference
		return $ref_val;
	}

	/**
	 * Gets a field item translation
	 * @param Array $ss Survey Settings
	 * @param string $fn Name of the field
	 * @param string $type Type of the field item
	 * @param string $lang_id The language id
	 * @return string 
	 */
	private static function getFieldValue($ss, $fn, $type, $lang_id) {
		$type = "field-{$type}";
		$ref_val = $ss["ref"]["field-translations"][$fn][$type];
		$is_ref_lang = $lang_id == $ss["refLang"];
		$mlm_lang = array_key_exists(($lang_id ?? ''), $ss["langs"]) ? $ss["langs"][$lang_id] : $ss["langs"][$ss["fallbackLang"]];
		if ($mlm_lang) {
			$val = $mlm_lang["field-translations"][$fn][$type];
			if (self::isEmpty($val) && $lang_id != $ss["fallbackLang"] && !$is_ref_lang) {
				$val = $ss["langs"][$ss["fallbackLang"]]["field-translations"][$fn][$type];
			}
			return self::isEmpty($val) ? $ref_val : $val;
		}
		// Use from reference
		return $ref_val;
	}

	/**
	 * Gets a field enum translation
	 * @param Array $ss Survey Settings
	 * @param string $fn Name of the field
	 * @param string $lang_id The language id
	 * @param bool $is_slider Whether this is a slider
	 * @return string 
	 */
	private static function getFieldEnum($ss, $fn, $lang_id, $is_slider = false) {
		// Anything?
		$ref_enum = $ss["ref"]["field-translations"][$fn]["field-enum"];
		if (self::isEmpty($ref_enum)) return null;
		// Try to get translation
		$is_ref_lang = $lang_id == $ss["refLang"];
		$mlm_lang = array_key_exists(($lang_id ?? ''), $ss["langs"]) ? $ss["langs"][$lang_id] : $ss["langs"][$ss["fallbackLang"]];
		if ($mlm_lang) {
			$enum = array();
			foreach ($ref_enum as $key => $ref_val) {
				$val = $mlm_lang["field-translations"][$fn]["field-enum"][$key] ?? "";
				if (self::isEmpty($val) && $lang_id != $ss["fallbackLang"] && !$is_ref_lang) {
					$val = $ss["langs"][$ss["fallbackLang"]]["field-translations"][$fn]["field-enum"][$key] ?? "";
					if (self::isEmpty($val)) {
						$val = $ref_val;
					}
				}
				$enum[$key] = self::isEmpty($val) ? $ref_val : $val;
			}
			return self::isEmpty($enum) ? null : ($is_slider ? self::encodeSliderEnum($enum) : arrayToEnum($enum));
		}
		// Use from reference
		return $is_slider ? self::encodeSliderEnum($ref_enum) : arrayToEnum($ref_enum);
	}

	/**
	 * Gets a survey setting translation
	 * @param mixed $ss Survey Settings
	 * @param mixed $id The setting
	 * @param mixed $lang_id The language id
	 * @return string 
	 */
	public static function getSurveyValue($ss, $id, $lang_id) {
		$ref_val = $ss["ref"]["survey-translations"][$id];
		$is_ref_lang = $lang_id == $ss["refLang"];
		$mlm_lang = array_key_exists(($lang_id ?? ''), $ss["langs"]) ? $ss["langs"][$lang_id] : $ss["langs"][$ss["fallbackLang"]];
		if ($mlm_lang) {
			$val = $mlm_lang["survey-translations"][$id];
			if (self::isEmpty($val) && $lang_id != $ss["fallbackLang"] && !$is_ref_lang) {
				$val = $ss["langs"][$ss["fallbackLang"]]["survey-translations"][$id];
			}
			return self::isEmpty($val) ? $ref_val : $val;
		}
		// Use from reference
		return $ref_val;
	}

	#endregion

	#region System Settings

	/**
	 * Gets system settings
	 * @return Array
	 */
	public static function getSystemSettings() {
		// Serve from cache?
		if (self::$systemSettingsCache !== null) {
			return self::$systemSettingsCache;
		}
		// Initialize the array
		$settings = array(
			"debug" => false,
			"disabled" => false,
			"highlightMissing" => false,
			"langs" => array(),
			"refLang" => null,
			"initialLang" => null,
			"version" => $GLOBALS["redcap_version"],
			"status" => "system",
			"usageStats" => null,
			"deleted" => []
		);

		// Get system config data
		$stored = self::readConfig("SYSTEM", true);
		foreach ($stored as $lang_id => $data) {
			if (strlen($lang_id)) {
				// Language
				foreach (self::$lang_settings_system as $name => $meta) {
					$value = $data[$name] ?? $meta["default"];
					if ($meta["type"] == "bool") $value = $value === "1";
					$settings["langs"][$lang_id][$name] = $value; 
				}
			}
			else {
				// Settings
				foreach (self::$global_settings_system as $name => $meta) {
					$value = $data[$name] ?? $meta["default"];
					if ($meta["type"] == "bool") {
						$value = $value === "1";
					}
					else if ($meta["type"] == "list") {
						$value = self::splitList($value);
					}
					else if ($meta["type"] == "json") {
						$value = self::isEmpty($value) ? array() : json_decode($value, true);
					}
					$settings[$name] = $value;
				}
			}
		}

		// Add UI translations and determine subscribed-to status
		foreach ($settings["langs"] as $lang_id => &$this_lang) {
			// User interface
			$this_lang["ui"] = self::readUITranslations("SYSTEM", $lang_id);
			$this_lang["subscribed-to-details"] = self::getSubscribedToStatus($this_lang["guid"]);
			$this_lang["subscribed-to"] = $this_lang["subscribed-to-details"]["active"] > 0;
		}
		// Set the default language if not set already
		if (self::isEmpty($settings["refLang"]) && count($settings["langs"])) {
			$settings["refLang"] = array_key_first($settings["langs"]);
		}
		// Set the initial language if not set already
		if (self::isEmpty($settings["initialLang"]) && count($settings["langs"])) {
			$active_langs = array();
			foreach (array_keys($settings["langs"]) as $lang_id) {
				if ($settings["langs"][$lang_id]["active"]) {
					$active_langs[] = $lang_id;
				}
			}
			$settings["initialLang"] = count($active_langs) ? $active_langs[0] : null;
		}
		self::$systemSettingsCache = $settings;
		return $settings;
	}
	private static $systemSettingsCache = null;

	#endregion

	#region Designated Field

	/**
	 * Gets the value of a designated language field (if set)
	 * Note: This uses the designated email mechanism from Classes/Project.php
	 * @param Context $context 
	 * @return string|null The value of the designated language field, or null
	 */
	public static function getDesignatedFieldValue(Context $context) {
		$settings = self::getProjectSettings($context->project_id);
		// In case there is no designated field or no record, return null
		$designated = $settings["designatedField"];
		if ($context->record == null || self::isEmpty($designated)) return null;
		// Get data
		$Proj = new Project($context->project_id);
		// Determine arm from event_id (if set, otherwise assume arm 1)
		$arm_num = $context->arm_num ?? ($context->event_id ? $Proj->eventInfo[$context->event_id]["arm_num"] : "1");
		// Can use the mechanism provided by the Project class for designated email fields
		$data = $Proj->getEmailInvitationFieldValues($context->record, [$designated], $arm_num);
		$value = $data[$designated] ?? "";
		return $value;
	}

	/**
	 * Gets the name of a designated language field (if set)
	 * @param mixed $project_id
	 * @return string|null The value of the designated language field, or null
	 */
	public static function getDesignatedField($project_id) {
		$settings = self::getProjectSettings($project_id);
		// In case there is no designated field or no record, return null
		$designated = $settings["designatedField"];
		return $designated;
	}

	#endregion

	#region Project XML Support

	/**
	 * Prepares project settings for inclusion in a project xml file by
	 * replacing table PK ids with "transferrable" ids (i.e., event ids are replaced
	 * with unique event name, ASI ids with "unique_event_name-form_name", and alert 
	 * ids with alert number)
	 * @param string|int $project_id The project id of the project exported as XML
	 * @return array 
	 */
	public static function getProjectSettingsForProjectXml($project_id) {
		$settings = self::getProjectSettings($project_id);
		$meta = self::getProjectMetadata($project_id);
		// Transform event data (replace event id with unique event name)
		foreach ($meta["events"] as $event_id => $event) {
			foreach ($settings["langs"] as $_ => &$this_lang) {
				foreach (["event-name","event-custom_event_label"] as $type) {
					if (isset($this_lang["dd"][$type][$event_id])) {
						$this_lang["dd"][$type][$event["uniqueEventName"]] = $this_lang["dd"][$type][$event_id];
						unset($this_lang["dd"][$type][$event_id]);
					}
				}
			}
		}
		// Transform ASIs (replace composite event_id-survey_id with unique_event_name)
		foreach ($meta["asis"] as $asi_id => $asi) {
			foreach ($settings["langs"] as $_ => &$this_lang) {
				foreach (["asi-email_subject","asi-email_content","asi-email_sender_display"] as $type) {
					if (isset($this_lang["dd"]["$type"][$asi["form"]][$asi_id])) {
						$this_lang["dd"]["$type"][$asi["form"]][$asi["uniqueEventName"]] = $this_lang["dd"]["$type"][$asi["form"]][$asi_id];
						unset($this_lang["dd"]["$type"][$asi["form"]][$asi_id]);
					}
				}
			}
		}
		// Transform alerts (replace alert id with sequential alert number)
		foreach ($meta["alerts"] as $alert_id => $alert) {
			foreach ($settings["langs"] as $_ => &$this_lang) {
				foreach (["alert-email_subject","alert-alert_message","alert-email_from_display"] as $type) {
					if (isset($this_lang["dd"]["$type"][$alert_id])) {
						$this_lang["dd"]["$type"][$alert["alertNum"]] = $this_lang["dd"]["$type"][$alert_id];
						unset($this_lang["dd"]["$type"][$alert_id]);
					}
				}
			}
			// Excluded alerts and alert sources
			foreach (["excludedAlerts","alertSources"] as $item) {
				if (isset($settings[$item][$alert_id])) {
					$settings[$item][$alert["alertNum"]] = $settings[$item][$alert_id];
					unset($settings[$item][$alert_id]);
				}
			}
		}
		// MyCap settings - TODO - also: we should filter based on what to include in the XML. This applies to Alerts, survey settings, ASI, too.


		return $settings;
	}

	/**
	 * Adapts project settings from a project xml to be applied to the given project by
	 * replacing named identifiers with project-specific table PKs (events, ASIs, alerts).
	 * @param string|int $project_id The project id to import the settings into
	 * @param array $settings Project settings transformed through `prepareProjectSettingsForProjectXml()`
	 * @return array 
	 */
	public static function adaptProjectSettingsFromProjectXml($project_id, $settings) {
		foreach ($settings["langs"] as $_ => &$this_lang) {
			// Adapt event data
			foreach (["event-name","event-custom_event_label"] as $type) {
				if (isset($this_lang["dd"][$type])) {
					foreach ($this_lang["dd"][$type] as $uen => $value) {
						$event_id = self::getEventIdByUniqueEventName($project_id, $uen);
						unset($this_lang["dd"][$type][$uen]);
						if ($event_id !== null) {
							$this_lang["dd"][$type][$event_id] = $value;
						}
					}
				}
			}
			// Adapt ASIs
			foreach (["asi-email_subject","asi-email_content","asi-email_sender_display"] as $type) {
				if (isset($this_lang["dd"][$type])) {
					foreach ($this_lang["dd"][$type] as $form => $events_data) {
						foreach ($this_lang["dd"][$type][$form] as $uen => $value) {
							$asi_id = self::getAsiIdByFormAndUniqueEventName($project_id, $form, $uen);
							unset($this_lang["dd"][$type][$form][$uen]);
							if ($asi_id !== null) {
								$this_lang["dd"][$type][$form][$asi_id] = $value;
							}
						}
					}
				}
			}
			// Adapt alerts
			foreach (["alert-email_subject","alert-alert_message","alert-email_from_display"] as $type) {
				if (isset($this_lang["dd"][$type])) {
					foreach ($this_lang["dd"][$type] as $alert_num => $value) {
						$alert_id = self::getAlertIdByAlertNum($project_id, $alert_num);
						unset($this_lang["dd"][$type][$alert_num]);
						if ($alert_id !== null) {
							$this_lang["dd"][$type][$alert_id] = $value;
						}
					}
				}
			}
		}
		// Adapt excluded alerts and alert sources
		foreach (["excludedAlerts","alertSources"] as $item) {
			foreach ($settings[$item] as $alert_num => $value) {
				$alert_id = self::getAlertIdByAlertNum($project_id, $alert_num);
				unset($settings[$item][$alert_num]);
				if ($alert_id !== null) {
					$settings[$item][$alert_id] = $value;
				}
			}
		}
		// MyCap settings
		// TODO
		return $settings;
	}

	/**
	 * Gets the alert id within the given project for the given alert number, or null if an alert with
	 * the given number does not exist
	 * @param string|int $project_id 
	 * @param int $alert_num 
	 * @return int|null 
	 */
	private static function getAlertIdByAlertNum($project_id, $alert_num) {
		$meta = self::getProjectMetadata($project_id);
		foreach ($meta["alerts"] as $alert_id => $alert) {
			if ($alert["alertNum"] == $alert_num) {
				return $alert_id;
			}
		}
		return null;
	}

	/**
	 * Gets the event id within the given project for the given unique event name, or null if 
	 * the event with the given name does not exist
	 * @param string|int $project_id 
	 * @param string $uen 
	 * @return int|null 
	 */
	private static function getEventIdByUniqueEventName($project_id, $uen) {
		$meta = self::getProjectMetadata($project_id);
		foreach ($meta["events"] as $event_id => $event) {
			if ($event["uniqueEventName"] == $uen) {
				return $event_id;
			}
		}
		return null;
	}

	/**
	 * Gets the composite (event_id-survey_id) ASI id within the given project for the given 
	 * form name and unique event name, or null if such an ASI does not exist
	 * @param string|int $project_id 
	 * @param string $uen 
	 * @return int|null 
	 */
	private static function getAsiIdByFormAndUniqueEventName($project_id, $form, $uen) {
		$meta = self::getProjectMetadata($project_id);
		foreach ($meta["asis"] as $asi_id => $asi) {
			if ($asi["uniqueEventName"] == $uen && $asi["form"] == $form) {
				return $asi_id;
			}
		}
		return null;
	}

	#endregion

	#region Project Settings

	/**
	 * Gets the project settings
	 * @param string|int $project_id 
	 * @param bool $tryDraftMode 
	 * @return mixed 
	 */
	public static function getProjectSettings($project_id, $tryDraftMode=false)
	{
		$draft_preview_enabled = ($GLOBALS["draft_preview_enabled"] ?? false);
		$tryDraftMode = $draft_preview_enabled || ($tryDraftMode && self::inDraftMode($project_id));
		// Serve from cache?
		if (!$tryDraftMode && isset(self::$projectSettingsCache[$project_id])) {
			return self::$projectSettingsCache[$project_id];
		} elseif ($tryDraftMode && isset(self::$projectSettingsCache_temp[$project_id])) {
			return self::$projectSettingsCache_temp[$project_id];
		}
		// Initialize the array
		$settings = array(
			"version" => $GLOBALS["redcap_version"],
			"langs" => array(),
			"projectId" => $project_id,
			"designatedField" => null,
			"status" => self::inDevelopmentMode($project_id) ? "dev" : ($tryDraftMode ? "draft" : "prod"),
		);

		// Get config data for the project (global )
		$stored = self::readConfig($project_id, true, $tryDraftMode);
		foreach ($stored as $lang_id => $data) {
			if (strlen($lang_id)) {
				// Language
				foreach (self::$lang_settings_project as $name => $meta) {
					$value = $data[$name] ?? $meta["default"];
					$settings["langs"]["$lang_id"][$name] = self::processConfigValue($meta["type"], $value); 
				}
			}
			else {
				// Settings
				foreach (self::$global_settings_project as $name => $meta) {
					$value = $data[$name] ?? $meta["default"];
					$settings[$name] = self::processConfigValue($meta["type"], $value);
				}
			}
		}

		// Subscription status
		$sys_settings = self::getSystemSettings();
		$forced_subscription = $sys_settings["force-subscription"] && !$settings["optional-subscription"];
		$sys_allow_ui_overrides = !$sys_settings["disable-ui-overrides"];

		// Add translations and update subscription status
		foreach ($settings["langs"] as $lang_id => &$this_lang) {
			// Data dictionary (includes survey settings, ASIs, ...)
			$this_lang["dd"] = self::readMetadataTranslations($project_id, $lang_id, null, $tryDraftMode);
			// User interface
			// Subscribed?
			if ($this_lang["subscribed"] && $this_lang["syslang"]) {
				// Use system langauge
				$sys_lang_id = self::getSystemLanguageKeyFromGuid($this_lang["syslang"]);
				$this_lang["ui"] = $sys_lang_id ? self::readUITranslations("SYSTEM", $sys_lang_id, false) : [];
				// Overrides?
				if ($sys_allow_ui_overrides || $settings["allow-ui-overrides"]) {
					$overrides = self::readUITranslations($project_id, $lang_id, $tryDraftMode);
					foreach ($overrides as $ui_key => $ui_data) {
						$this_lang["ui"][$ui_key] = $ui_data;
					}
				}
			}
			else {
				$this_lang["ui"] = self::readUITranslations($project_id, $lang_id, $tryDraftMode);
			}
			// Update subscription status in case of forced subscription
			if ($this_lang["syslang"] && $forced_subscription) {
				$this_lang["subscribed"] = true;
			}
			// Ensure subscription status is off if there is no syslang
			if (empty($this_lang["syslang"])) {
				$this_lang["subscribed"] = false;
			}
		}

		$meta = self::getProjectMetadata($project_id, $tryDraftMode);
		// Alerts - set language source defaults
		if (!is_array($settings["alertSources"])) $settings["alertSources"] = [];
		foreach ($meta["alerts"] as $alert_id => $_) {
			if (!isset($settings["alertSources"][$alert_id])) {
				$settings["alertSources"][$alert_id] = "field";
			}
		}
		// ASIs - set language source defaults
		if (!is_array($settings["asiSources"])) $settings["asiSources"] = [];
		foreach ($meta["asis"] as $_ => $asi) {
			if (!isset($settings["asiSources"][$asi["form"]])) {
				$settings["asiSources"][$asi["form"]] = "field";
			}
		}

		// Ensure that refLang and fallbackLang exist
		if (!array_key_exists($settings["refLang"], $settings["langs"])) {
			$settings["refLang"] = count($settings["langs"]) ? array_key_first($settings["langs"]) : "";
		}
		if (!array_key_exists($settings["fallbackLang"], $settings["langs"])) {
			$settings["fallbackLang"] = count($settings["langs"]) ? array_key_first($settings["langs"]) : "";
		}

		if ($tryDraftMode) {
			self::$projectSettingsCache_temp[$project_id] = $settings;
		} else {
			self::$projectSettingsCache[$project_id] = $settings;
		}
		return $settings;
	}
	private static $projectSettingsCache = array();
	private static $projectSettingsCache_temp = array();

	#endregion

	#region Save Configuration Data

	/**
	 * Saves setup data
	 * @param int|string $project_id
	 * @param array $data The data (a data structure matching that generated by getProjectSetting or getSystemsettings)
	 * @return Array Response
	 */
	public static function save($project_id, $data) {
		try {
			$inDraftMode = self::inDraftMode($project_id);
			// Variables for recording some info for logging
			$log_langs_added = array();
			$log_langs_removed = array();
			$log_active_changed = array();
			$log_visible_changed = array();
			$log_num_items_updated = array("" => 0);
			$log_error = "";
			// Default response
			$response = self::response();
			// Initial determinations
			if ($project_id == "SYSTEM") {
				$lang_settings = self::$lang_settings_system;
				$global_settings = self::$global_settings_system;
				$is_system = true;
				$pmd = $pmd_map = null;
			}
			else {
				// When a project is in PRODUCTION mode and DRAFT MODE is not on, then reject saving
				if (self::inProductionMode($project_id) && !$inDraftMode) {
					throw new Exception("Saving is disabled in PRODUCTION projects that are not in DRAFT mode.");
				}
				$lang_settings = self::$lang_settings_project;
				$global_settings = self::$global_settings_project;
				$is_system = false;
				// Add updated hash to response
				$response["hash"] = self::getProjectMetadataHash($project_id, true); // Use metadata_temp if we're in draft mode
				$pmd = self::getProjectMetadata($project_id, true);
				$pmd_map = self::getProjectMetadataMap($project_id);
			}
			// Only admins can set certain settings on - sanitize by removing them for non-admins
			if (!UserRights::isSuperUserNotImpersonator()) {
				foreach (self::$global_settings_project as $gsp_name => $gsp_meta) {
					if ($gsp_meta["admin"] == true) {
						unset($global_settings[$gsp_name]);
					}
				}
			}
			if (!count($data["langs"]) || $is_system) {
				// Special: when there are no languages, or in system context, remove the fallbackLang key
				unset($data["fallbackLang"]);
			}
			else {
				// Otherwise, check whether the reference and fallback languages actually exist
				// If not (should never happen), we just use the first language in this case
				if (!isset($data["langs"][$data["refLang"]])) {
					$data["refLang"] = array_key_first($data["langs"]);
				}
				if (!isset($data["langs"][$data["fallbackLang"]])) {
					$data["fallbackLang"] = array_key_first($data["langs"]);
				}
			}

			// Get the currently saved settings and determine any update actions
			$current_config = self::readConfig($project_id, true, $inDraftMode);
			$langs_in_config = array_filter(array_keys($current_config));
			$langs_in_data = array_keys($data["langs"] ?? []);
			foreach ($langs_in_data as $lang_id) {
				$log_num_items_updated[$lang_id] = 0;
				if (!in_array($lang_id, $langs_in_config, true)) {
					$log_langs_added[$lang_id] = $data["langs"][$lang_id]["display"];
				}
			}
			foreach ($langs_in_config as $lang_id) {
				if (!in_array($lang_id, $langs_in_data, true)) {
					$log_langs_removed[$lang_id] = $current_config[$lang_id]["display"];
				}
			}
			// If saving project settings, get a list of system languages for verification
			if ($project_id != "SYSTEM") {
				$valid_sys_lang_guids = [];
				foreach (self::getSystemLanguages() as $_ => $syslang) {
					$valid_sys_lang_guids[] = $syslang["guid"];
				}
			}

			// Start database transaction
			db_query("SET AUTOCOMMIT=0");
			db_query("BEGIN");

			$store_config = function($lang_id, $name, $new, $old, $type) use ($project_id, $pmd, &$log_active_changed, &$log_visible_changed, &$log_num_items_updated) {
				if ($type == "bool") {
					$new = $new ? "1" : "";
				}
				else if ($type == "list") {
					$new = join(",", array_keys($new));
				}
				else if ($type == "json") {
					if ($name == "excludedSettings") {
						// Cleanup array (remove empty) 
						$new_new = array();
						foreach ($new as $form => $settings) {
							if (!self::isEmpty($form) && !self::isEmpty($settings)) {
								$new_new[$form] = $settings;
							}
						}
						$new = $new_new;
					}
					else if ($name == "alertSources") {
						// Check alert IDs against current project metadata
						foreach ($new as $alert_id => $_) {
							if (!array_key_exists($alert_id, $pmd["alerts"])) {
								unset ($new[$alert_id]);
							}
						}
					}
					else if ($name == "asiSources") {
						// Check form names against current project metadata
						foreach ($new as $form_name => $_) {
							if (!array_key_exists($form_name, $pmd["forms"])) {
								unset ($new[$form_name]);
							}
						}
					}
					$new = self::isEmpty($new) ? "" : self::convertAssocArrayToJSON($new);
				}
				$rv = $new;
				if ($new !== $old) {
					if (strlen($new)) {
						$update = !self::isEmpty($old);
						self::storeConfig($update, $project_id, $lang_id, $name, $new);
					}
					else {
						self::removeConfig($project_id, $lang_id, $name);
						$rv = null;
					}
					// Record 'active' change for logging
					$set_to_on = strlen($new) > 0;
					if ($name == "active") {
						$log_active_changed[$lang_id] = $set_to_on;
					}
					else if ($name == "disabled") {
						$log_active_changed[""] = $set_to_on;
					}
					else if ($name == "visible") {
						$log_visible_changed[$lang_id] = $set_to_on;
					}
					$log_num_items_updated[$lang_id]++;
				}
				// Return actual value
				return $rv;
			};
			foreach ($global_settings as $name => $meta) {
				$new_value = $data[$name] ?? $meta["default"];
				$old_value = $current_config[""][$name] ?? $meta["default"];
				if ($old_value === false) {
					$old_value = "";
				}
				$stored_val = $store_config(null, $name, $new_value, $old_value, $meta["type"]);
				// Update current config with actually stored values 
				// (in order to make decisions later in the same DB transaction)
				// This is only needed for base project settings
				if ($stored_val !== null) {
					$current_config[""][$name] = $stored_val;
				}
				else {
					$current_config[""][$name] = $meta["default"];
				}
			}
			foreach ($langs_in_data as $lang_id) {
				foreach ($lang_settings as $name => $meta) {
					$new_value = $data["langs"][$lang_id][$name] ?? $meta["default"];
					// Check for subscriptions - does the subscribed language exist?
					if ($name == "syslang" && $project_id != "SYSTEM") {
						if (!in_array($new_value, $valid_sys_lang_guids, true)) {
							$new_value = "";
						}
					}
					$old_value = $current_config[$lang_id][$name] ?? $meta["default"];
					if ($old_value === false) {
						$old_value = "";
					}
					$store_config($lang_id, $name, $new_value, $old_value, $meta["type"]);
				}
			}

			// Store ui translations
			$store_ui = function($lang_id, $item, $new_hash, $old_hash, $new, $old, $isBool, $current) use ($project_id, &$log_num_items_updated) {
				if ($isBool) {
					$new = $new ? "1" : "";
				}
				if ($new !== $old || $new_hash !== $old_hash) {
					if (strlen($new)) {
						$update = !$isBool && isset($current[$item]);
						self::storeUITranslation($update, $project_id, $lang_id, $item, $new, $new_hash);
					}
					else {
						self::removeUITranslation($project_id, $lang_id, $item);
					}
					$log_num_items_updated[$lang_id]++;
				}
			};
			if ($project_id == "SYSTEM") {
				foreach ($data["langs"] as $lang_id => $this_lang) {
					$current_ui_translations = self::readUITranslations($project_id, $lang_id, $inDraftMode);
					foreach (self::getUIMetadata(true) as $item => $meta) {
						$new_value = $this_lang["ui"][$item]["translation"] ?? "";
						$old_value = $current_ui_translations[$item]["translation"] ?? "";
						$new_hash = $this_lang["ui"][$item]["refHash"] ?? "";
						$old_hash = $current_ui_translations[$item]["refHash"] ?? "";
						$store_ui($lang_id, $item, $new_hash, $old_hash, $new_value, $old_value, $meta["type"] == "bool", $current_ui_translations);
						unset($current_ui_translations[$item]);
					}
					// Cleanup - delete any left over items (probably orphaned due to REDCap updates)
					foreach ($current_ui_translations as $item => $data) {
						self::removeUITranslation($project_id, $lang_id, $item);
					}
				}
			}
			else {
				// In projects, store UI items depending on subscription status to a sys language
				// For this, we need the current system settings
				$system_config = $project_id == "SYSTEM" ? null : self::readConfig("SYSTEM");
				// Are overrides (for subscribed languages) allowed?
				$ui_overrides_allowed = !((isset($system_config[""]["disable-ui-overrides"]) && $system_config[""]["disable-ui-overrides"] == "1"))
					|| (isset($current_config[""]["allow-ui-overrides"]) && $current_config[""]["allow-ui-overrides"] == "1");
				foreach ($data["langs"] as $lang_id => $this_lang) {
					$current_ui_translations = self::readUITranslations($project_id, $lang_id, $inDraftMode);
					$this_subscribed = $this_lang["subscribed"];
					$this_syslang_guid = $this_lang["syslang"];
					// Load the system language (for comparison, only if needed)
					$this_sys_lang = ($this_subscribed && $this_syslang_guid) ?
						self::getSystemLanguage($project_id, $this_syslang_guid)["lang"] : null;
					// Only perform any updates when either not subscribed, overrides are allowed, or the 
					// sys lang is null (if no updates are performed, items will be purged from the DB)
					if (!$this_subscribed || $ui_overrides_allowed || $this_sys_lang == null) {
						foreach (self::getUIMetadata(false) as $item => $meta) {
							$new_value = $this_lang["ui"][$item]["translation"] ?? "";
							$old_value = $current_ui_translations[$item]["translation"] ?? "";
							$new_hash = $this_lang["ui"][$item]["refHash"] ?? "";
							$old_hash = $current_ui_translations[$item]["refHash"] ?? "";
							// Store when not subscribed or when different
							if (!$this_subscribed || ($new_value != "" && $new_value != $this_sys_lang["ui"][$item]["translation"])) {
								$store_ui($lang_id, $item, $new_hash, $old_hash, $new_value, $old_value, $meta["type"] == "bool", $current_ui_translations);
								unset($current_ui_translations[$item]);
							}
						}
					}
					// Cleanup - delete any left over items (probably orphaned due to REDCap updates)
					foreach ($current_ui_translations as $item => $data) {
						self::removeUITranslation($project_id, $lang_id, $item);
					}
				}

			}

			// Store forms and survey translations - only in the project context
			if (!$is_system) {
				$store = function($lang_id, $type, $name, $index, $new, $old) use ($project_id, $pmd, $pmd_map, &$log_num_items_updated) {
					// Only do anything if there is a value AND a change (in either translation _or_ hash)
					$new_value = $new["translation"];
					$old_value = $old["translation"];
					if (
						!self::isEmpty($new_value) &&
						(
							$new_value !== $old_value
							||
							$new["refHash"] !== $old["refHash"]
						)
					) {
						$update = !self::isEmpty($old_value);
						$hash = $new["refHash"] ?? null;
						self::storeMetadataTranslation($update, $project_id, $lang_id, $type, $name, $index, $new_value, $hash);
						$log_num_items_updated[$lang_id]++;
					}
					if (self::isEmpty($new_value)) {
						self::removeMetadataTranslation($project_id, $lang_id, $type, $name, $index);
						$log_num_items_updated[$lang_id]++;
					}
				};
				$empty = array(
					"translation" => null,
					"refHash" => null,
				);
				foreach ($data["langs"] as $lang_id => $this_lang) {
					$current_dd_translations = self::readMetadataTranslations($project_id, $lang_id, null, $inDraftMode);
					if (isset($this_lang["dd"]) && is_array($this_lang["dd"])) {
						foreach ($this_lang["dd"] as $type => $typeData) {
							foreach ($typeData as $name => $nameData) {
								foreach ($nameData as $index => $new_value) {
									if (starts_with($type, "field-") && $pmd["fields"][$name]["isPROMIS"]) continue; // Skip fields on PROMIS forms
									if (self::checkTypeNameIndex($pmd, $pmd_map, $type, $name, $index)) {
										$old_value = $current_dd_translations[$type][$name][$index] ?? $empty;
										$store($lang_id, $type, $name, $index, $new_value, $old_value);
										// Unset from current - all that's left will be deleted
										unset($current_dd_translations[$type][$name][$index]);
									}
								}
							}
						}
					}
					// Delete all remaining (obsolete or empty/false values)
					foreach ($current_dd_translations as $type => $typeData) {
						foreach ($typeData as $name => $nameData) {
							foreach ($nameData as $index => $_) {
								self::removeMetadataTranslation($project_id, $lang_id, $type, $name, $index);
							}
						}
					}
				}
			}
			// Cleanup 
			// Delete all data for non-existing languages from all tables
			foreach ($langs_in_config as $key) {
				if (!in_array($key, $langs_in_data)) {
					self::purgeLanguage($project_id, $key);
				}
			}
			// Delete all GUID links to deleted system languages
			if ($project_id == "SYSTEM") {
				foreach ($data["deleted"] as $guid => $_) {
					self::purgeSysLangLink($guid);
				}
			}
			db_query("COMMIT");
		}
		catch (\Throwable $ex) {
			db_query("ROLLBACK");
			$response = self::exceptionResponse($ex);
			$log_error = $ex->getMessage();
		}
		finally {
			db_query("SET AUTOCOMMIT=1");
			// Logging
			$n_total = array_reduce($log_num_items_updated, function($carry, $item) {
				return $carry + $item;
			}, 0);
			$description = $inDraftMode ? "(DRAFT MODE) - " : "";
			$description .= isset($log_active_changed[""]) ? (($log_active_changed[""] ? "DEACTIVATED" : "ACTIVATED") . "; ") : "";
			$description .= "Updated items: $n_total [";
			foreach ($log_num_items_updated as $lang_id => $n) {
				if ($n > 0) {
					$description .= ($lang_id ? $lang_id : "General") . ": $n, ";
				}
			}
			$description = trim(trim($description), ",")."]; ";
			$description .= "Languages: " . count($langs_in_data) . " [";
			$description .= join(", ", array_map(function($lang_id) use ($log_active_changed, $log_visible_changed) {
				$rv = $lang_id . " ";
				if (array_key_exists($lang_id, $log_active_changed)) {
					$rv .= $log_active_changed[$lang_id] ? "(activated)" : "(deactivated)";
				}
				if (array_key_exists($lang_id, $log_visible_changed)) {
					$rv .= $log_visible_changed[$lang_id] ? "(visible)" : "(hidden)";
				}
				return trim($rv);
			}, $langs_in_data));
			$description .= "]";
			if (count($log_langs_removed)) {
				$description .= "; Removed: ";
				foreach ($log_langs_removed as $lang_id => $name) {
					$description .= "$lang_id ($name), ";
				}
				$description = trim(trim($description), ",");
			}
			if (strlen($log_error)) {
				$description = "ERROR! " . $description . "; Error: " . $log_error;
			}
			self::log("Save configuration", $description, USERID, $is_system ? null : $project_id);
		}
		return $response;
	}

	#endregion

	#region Generate Project Metadata

	/**
	 * Prepares a data structure of project metadata, including the reference strings
	 * The results are cached.
	 * @return Array
	 */
	public static function getProjectMetadata($project_id, $tryDraftMode=false) {
		$draft_preview_enabled = ($GLOBALS["draft_preview_enabled"] ?? false);
		$tryDraftMode = $tryDraftMode || $draft_preview_enabled;
		// Serve from cache if possible
		if (!$tryDraftMode && isset(self::$projectMetadataCache[$project_id])) {
			return self::$projectMetadataCache[$project_id];
		}
		// Build metadata from scratch
		global $lang;
		$Proj = new Project($project_id);
		// Ensure all data is available
		$Proj->loadMetadata();
		$Proj->loadSurveys();
		$Proj->getUniqueEventNames();
		// MyCap
		$mycap_enabled = $Proj->project['mycap_enabled'] && $GLOBALS["mycap_enabled_global"];
		$myCapProj = $mycap_enabled ? new \Vanderbilt\REDCap\Classes\MyCap\MyCap($project_id) : null;
		// Assemble a data structure with the information necessary for the setup page to work
		#region Forms and Surveys
		$forms = array();
		$surveys = array();
		$survey_ids = array(); // [ survey_id => name ]
		$ProjMetadata = $tryDraftMode && self::inDraftMode($project_id) ? $Proj->metadata_temp : $Proj->metadata;
		$ProjForms = $tryDraftMode && self::inDraftMode($project_id) ? $Proj->forms_temp : $Proj->forms;
		$shared_library_forms = self::getSharedLibraryForms($project_id);
		foreach ($ProjForms as $name => $data) {
			$is_survey = isset($data["survey_id"]);
			$is_promis = \PROMIS::isPromisInstrument($name)[0];
			$forms[$name] = array(
				"form-name" => array(
					"reference" => ensureUTF($data["menu"]),
					"refHash" => self::getChangeTrackHash($data["menu"]),
					"prompt" => ensureUTF(RCView::tt("multilang_270")), //= Form name translation:
				),
				"numFields" => count($data["fields"]),
				"isSurvey" => $is_survey,
				"isFromSharedLibrary" => in_array($name, $shared_library_forms),
				"isPROMIS" => $is_promis,
				"fields" => $is_promis ? [] : array_keys($data["fields"]), // Skip fields of PROMIS instruments
				"myCapTaskId" => null,
				"myCapTaskItems" => null,
				"myCapTaskType" => "",
				"staticMenu" => false,
			);
			#region Survey
			if ($is_survey) {
				$survey_id = $data["survey_id"] * 1;
				$survey_ids[$survey_id] = $name; // Store for later as key (integer)
				$survey = $Proj->surveys[$data["survey_id"]];
				$surveys[$name] = array(
					"id" => $survey_id,
					"form" => $name,
					"hasASIs" => false,
					"asis" => array(),
					"survey-title" => array (
						"name" => ensureUTF(RCView::tt("survey_49")), //= Survey Title
						"reference" => ensureUTF($survey["title"]),
						"refHash" => self::getChangeTrackHash($survey["title"]),
						"prompt" => ensureUTF(RCView::tt("multilang_269")), //= Translation of the survey title:
						"title" => ensureUTF(RCView::tt("multilang_268")), //= Survey Title and Prompts
						"mode" => "text",
						"wrap" => true,
					),
					"survey-instructions" => array (
						"name" => ensureUTF(RCView::tt("survey_65")), //= Survey Instructions
						"reference" => ensureUTF($survey["instructions"]),
						"refHash" => self::getChangeTrackHash($survey["instructions"]),
						"prompt" => ensureUTF(RCView::tt("multilang_267")), //= Translation of the survey instructions:
						"mode" => "textarea",
						"wrap" => true,
					),
					"survey-acknowledgement" => array (
						"name" => ensureUTF(RCView::tt("survey_747")), //= Survey Completion Text
						"reference" => ensureUTF($survey["acknowledgement"]),
						"refHash" => self::getChangeTrackHash($survey["acknowledgement"]),
						"prompt" => ensureUTF(RCView::tt("multilang_266")), //= Translation of the survey completion text:
						"disabled" => $survey["acknowledgement"] === null,
						"mode" => "textarea",
						"wrap" => true,
					),
					"survey-logo_alt_text" => array (
						"name" => ensureUTF(RCView::tt("survey_49")), //= Survey Title
						"reference" => ensureUTF($survey["title"]),
						"refHash" => self::getChangeTrackHash($survey["title"]),
						"prompt" => ensureUTF(RCView::tt("multilang_265")), //= Text for the 'alt' attribute of the custom survey logo:
						"disabled" => $survey["logo"] == null, 
						"mode" => "text",
						"wrap" => false,
					),
					"survey-offline_instructions" => array (
						"name" => ensureUTF(RCView::tt("multilang_264")), //= Offline Instructions
						"reference" => ensureUTF($survey["offline_instructions"]),
						"refHash" => self::getChangeTrackHash($survey["offline_instructions"]),
						"prompt" => ensureUTF(RCView::tt("multilang_263")), //= Translation of the custom text to display when the survey is offline:
						"mode" => "textarea",
						"wrap" => true,
					),
					"survey-stop_action_acknowledgement" => array (
						"name" => ensureUTF(RCView::tt("multilang_260")), //= Stop Action Completion Text
						"reference" => ensureUTF($survey["stop_action_acknowledgement"]),
						"refHash" => self::getChangeTrackHash($survey["stop_action_acknowledgement"]),
						"prompt" => ensureUTF(RCView::tt("multilang_259")), //= Translation of the stop-action alternative survey completion text:
						"mode" => "textarea",
						"wrap" => true,
					),
					"survey-response_limit_custom_text" => array (
						"name" => ensureUTF(RCView::tt("multilang_258")), //= Response Limit Text
						"reference" => ensureUTF($survey["response_limit_custom_text"]),
						"refHash" => self::getChangeTrackHash($survey["response_limit_custom_text"]),
						"prompt" => ensureUTF(RCView::tt("multilang_257")), //= Translation of the custom response limit text:
						"mode" => "textarea",
						"wrap" => true,
					),
					"survey-survey_btn_text_prev_page" => array (
						"name" => ensureUTF(RCView::tt("multilang_595")), //= Survey-specific Previous Page Button Label
						"reference" => ensureUTF($survey["survey_btn_text_prev_page"]),
						"refHash" => self::getChangeTrackHash($survey["survey_btn_text_prev_page"]),
						"prompt" => ensureUTF(RCView::lang_i("multilang_594", [$lang["data_entry_537"]])), //= Translation of the '{0}' button label:
						"disabled" => $survey["survey_btn_text_prev_page"] === null,
						"title" => ensureUTF(RCView::tt("multilang_593")), //= Custom Survey Submit Buttons:
						"mode" => "text",
					),
					"survey-survey_btn_text_next_page" => array (
						"name" => ensureUTF(RCView::tt("multilang_596")), //= Survey-specific Next Page Button Label
						"reference" => ensureUTF($survey["survey_btn_text_next_page"]),
						"refHash" => self::getChangeTrackHash($survey["survey_btn_text_next_page"]),
						"prompt" => ensureUTF(RCView::lang_i("multilang_594", [$lang["data_entry_536"]])), //= Translation of the '{0}' button label:
						"disabled" => $survey["survey_btn_text_next_page"] === null,
						"mode" => "text",
					),
					"survey-survey_btn_text_submit" => array (
						"name" => ensureUTF(RCView::tt("multilang_597")), //= Survey-specific Submit Button Label
						"reference" => ensureUTF($survey["survey_btn_text_submit"]),
						"refHash" => self::getChangeTrackHash($survey["survey_btn_text_submit"]),
						"prompt" => ensureUTF(RCView::lang_i("multilang_594", [$lang["survey_200"]])), //= Translation of the '{0}' button label:
						"disabled" => $survey["survey_btn_text_submit"] === null,
						"mode" => "text",
					),
					"survey-confirmation_email_from_display" => array (
						"name" => ensureUTF(RCView::tt("multilang_256")), // Confirmation Email From Display
						"reference" => ensureUTF($survey["confirmation_email_from_display"]),
						"refHash" => self::getChangeTrackHash($survey["confirmation_email_from_display"]),
						"prompt" => ensureUTF(RCView::tt("multilang_255")), //= Language-specific email from display name:
						"title" => ensureUTF(RCView::tt("multilang_254")), //= Confirmation Email
						"mode" => "text",
						"wrap" => false,
					),
					"survey-confirmation_email_subject" => array (
						"name" => ensureUTF(RCView::tt("multilang_253")), //= Confirmation Email Subject
						"reference" => ensureUTF($survey["confirmation_email_subject"]),
						"refHash" => self::getChangeTrackHash($survey["confirmation_email_subject"]),
						"prompt" => ensureUTF(RCView::tt("multilang_354")), //= Translation of the email subject:"),
						"mode" => "text",
						"wrap" => false,
					),
					"survey-confirmation_email_content" => array (
						"name" => ensureUTF(RCView::tt("multilang_252")), //= Confirmation Email Content
						"reference" => ensureUTF($survey["confirmation_email_content"]),
						"refHash" => self::getChangeTrackHash($survey["confirmation_email_content"]),
						"prompt" => ensureUTF(RCView::tt("multilang_353")), //= Translation of the message body:
						"mode" => "textarea",
						"wrap" => false,
					),
					"survey-text_to_speech" => array (
						"name" => ensureUTF(RCView::tt("multilang_251")), //= Text-to-Speech
						"reference" => ensureUTF($survey["text_to_speech"]),
						"refHash" => self::getChangeTrackHash($survey["text_to_speech"]),
						"prompt" => ensureUTF(RCView::tt("multilang_250")), //= Language-specific text-to-speech option:
						"select" => array (
							"0" => ensureUTF($lang["global_23"]), // Disabled
							"1" => ensureUTF($lang["system_config_27"] . " " . $lang["survey_995"]), // Enabled (initially on)
							"2" => ensureUTF($lang["system_config_27"] . " " . $lang["survey_996"]), // Enabled (initially off)
						),
						"disabled" => $GLOBALS["enable_survey_text_to_speech"] != "1",
						"title" => ensureUTF(RCView::tt("multilang_249")), //= Text-to-Speech Options
						"mode" => "select",
						"wrap" => false,
					),
					"survey-text_to_speech_language" => array (
						"name" => ensureUTF(RCView::tt("multilang_248")), //= Text-to-Speech Language/Voice
						"reference" => ensureUTF($survey["text_to_speech_language"]),
						"refHash" => self::getChangeTrackHash($survey["text_to_speech_language"]),
						"prompt" => ensureUTF(RCView::tt("multilang_247")), //= Language-specific text-to-speech voice:
						"select" => \Survey::getTextToSpeechLanguages(), 
						"disabled" => $GLOBALS["enable_survey_text_to_speech"] != "1",
						"mode" => "select",
						"wrap" => false,
					),
					"survey-repeat_survey_btn_text" => array (
						"name" => ensureUTF(RCView::tt("multilang_246")), //= Repeat Survey Button Text
						"reference" => ensureUTF($survey["repeat_survey_btn_text"]),
						"refHash" => self::getChangeTrackHash($survey["repeat_survey_btn_text"]),
						"prompt" => ensureUTF(RCView::tt("multilang_245")), //= Translation of the 'Take this survey again' button label:
						"disabled" => $survey["repeat_survey_btn_text"] === null,
						"title" => ensureUTF(RCView::tt("multilang_562")), //= Repeating Survey
						"mode" => "text",
						"wrap" => true,
					),
					"survey-end_survey_redirect_url" => array (
						"name" => ensureUTF(RCView::tt("multilang_244")), //= Redirect URL
						"reference" => ensureUTF($survey["end_survey_redirect_url"]),
						"refHash" => self::getChangeTrackHash($survey["end_survey_redirect_url"]),
						"prompt" => ensureUTF(RCView::tt("multilang_243")), //= Language-specific redirect url after when survey is completed:
						"disabled" => $survey["end_survey_redirect_url"] === null,
						"title" => ensureUTF(RCView::tt("multilang_578")), //= Survey Redirection
						"mode" => "text",
						"wrap" => false,
					),
				);
			}
			#endregion
		}
		#endregion
		#region Matrix groups
		$matrix_groups = array();
		foreach ($ProjMetadata as $field => $data) {
			$grid_name = $data["grid_name"];
			if ($grid_name) {
				if (!isset($matrix_groups[$grid_name])) {
					$matrix_groups[$grid_name] = array(
						"form" => $data["form_name"],
						"fields" => array (),
						"matrix-enum" => array(),
						"isPROMIS" => $is_promis,
						"enum-order" => [],
					);
					foreach (self::formatEnum($data["element_enum"], $data["element_type"]) as $code => $ref) {
						$matrix_groups[$grid_name]["matrix-enum"][$code] = array(
							"reference" => ensureUTF($ref),
							"refHash" => self::getChangeTrackHash($ref),
						);
						$matrix_groups[$grid_name]["enum-order"][] = $code;
					}
				}
				$matrix_groups[$grid_name]["fields"][$field] = true;
				if ($data["element_preceding_header"] !== null) {
					$matrix_groups[$grid_name]["matrix-header"] = array(
						"reference" => ensureUTF($data["element_preceding_header"]),
						"refHash" => self::getChangeTrackHash($data["element_preceding_header"]),
					);
				}
			}
		}
		#endregion
		#region Fields
		$fields = array();
		foreach ($ProjMetadata as $field => $data) {
			$form = $forms[$data["form_name"]];
			// Determine type - special case _complete fields!
			// It has been reported that in rare circumstances, e.g., when a project is reconstituted from a malformed project xml, there can be fields without a field type. For now, we assume descriptive.
			$type = $data["element_type"] ?? "descriptive";
			// Skipping header for matrix group fields
			if ($type == "select" && ends_with($field, "_complete") && array_key_exists(str_replace("_complete", "", $field), $ProjForms)) {
				$type = "formstatus";
			}
			// Suppress the enum? For matrix fields, yes/no, and true/false
			$is_matrix = strlen($data["grid_name"]??"") > 0;
			$suppress_enum = $is_matrix || $data["element_type"] == "yesno" || $data["element_type"] == "truefalse";
			$fields[$field] = array (
				"formName" => $data["form_name"],
				"type" => $type,
				"isPROMIS" => $form["isPROMIS"],
				"validation" => ensureUTF($data["element_validation_type"]),
				"field-label" => array(
					"reference" => ensureUTF($data["element_label"]),
					"refHash" => self::getChangeTrackHash($data["element_label"]),
				),
				"field-note" => array(
					"reference" => ensureUTF($data["element_note"]),
					"refHash" => self::getChangeTrackHash($data["element_note"]),
				),
				"field-header" => $is_matrix ? null : array(
					"reference" => ensureUTF($data["element_preceding_header"]),
					"refHash" => self::getChangeTrackHash($data["element_preceding_header"]),
				),
				"field-video_url" => array(
					"reference" => ensureUTF($data["video_url"]),
					"refHash" => self::getChangeTrackHash($data["video_url"]),
				),
				// "misc" => $data["misc"], // Note: 'misc' will NOT be translated - see actiontags
				"field-enum" => null,
				"field-actiontag" => self::getTranslatableActionTags($data["misc"], $type),
				"matrix" => $data["grid_name"],
				// Need to explicitly store enum order
				"enum-order" => [],
				// MyCap task item?
				"isTaskItem" => $mycap_enabled && strpos($data["misc"]??"", "@MC-TASK-") !== false,
			);
			// Add enum data
			if (!$suppress_enum && $data["element_enum"]) {
				$fields[$field]["field-enum"] = array();
				$enumArray = self::formatEnum($data["element_enum"], $data["element_type"]);
				if ($enumArray) {
					foreach ($enumArray as $code => $ref) {
						$fields[$field]["field-enum"][$code] = array(
							"reference" => ensureUTF($ref),
							"refHash" => self::getChangeTrackHash($ref),
						);
						$fields[$field]["enum-order"][] = $code;
					}
				}
			}
		}
		#endregion
		#region ASIs
		$asis = array();
		if (count($survey_ids)) {
			$to_string_fn = function($item) { return "$item"; };
			$valid_event_ids = array_map($to_string_fn, array_keys($Proj->eventInfo));
			foreach (self::readASIData($survey_ids, $valid_event_ids) as $asi) {
				$asi_eventid = $asi["event_id"] * 1;
				$asi_surveyid = $asi["survey_id"] * 1;
				$asi_id = "{$asi_eventid}-{$asi_surveyid}";
				$asis[$asi_id] = array (
					"event_id" => $asi_eventid,
					"survey_id" => $asi_surveyid,
					"form" => $survey_ids[$asi_surveyid],
					"uniqueEventName" => $Proj->uniqueEventNames[$asi_eventid] ?? "",
					"asi-email_subject" => array(
						"reference" => $asi["email_subject"],
						"refHash" => self::getChangeTrackHash($asi["email_subject"]),
						"prompt" =>ensureUTF(RCView::tt("multilang_354")), //= Translation of the email subject:
						"mode" => "text"
					),
					"asi-email_content" => array(
						"reference" => $asi["email_content"],
						"refHash" => self::getChangeTrackHash($asi["email_content"]),
						"prompt" => ensureUTF(RCView::tt("multilang_353")), //= Translation of the message body:
						"mode" => "textarea"
					),
					"asi-email_sender_display" => array(
						"reference" => $asi["email_sender_display"],
						"refHash" => self::getChangeTrackHash($asi["email_sender_display"]),
						"prompt" => ensureUTF(RCView::tt("multilang_355")), //= Language-specific sender display name:
						"mode" => "text"
					),
				);
				// Note in surveys, that ASIs are present and which
				$surveys[$survey_ids[$asi_surveyid]]["hasASIs"] = true;
				$surveys[$survey_ids[$asi_surveyid]]["asis"][] = $asi_id;
			}
		}
		#endregion
		#region Survey Queue
		$sq = array(
			"sq-survey_queue_custom_text" => array (
				"reference" => ensureUTF($Proj->project["survey_queue_custom_text"]),
				"refHash" => self::getChangeTrackHash($Proj->project["survey_queue_custom_text"]),
				"prompt" => ensureUTF(RCView::tt("multilang_242")), //= Translation for the custom text to display at top of Survey Queue:
			),
			"sq-survey_auth_custom_message" => array (
				"reference" => ensureUTF($Proj->project["survey_auth_custom_message"]),
				"refHash" => self::getChangeTrackHash($Proj->project["survey_auth_custom_message"]),
				"prompt" => ensureUTF(RCView::tt("multilang_356")), //= Translation for the Survey Login ustom error message:
			)
		);
		#endregion
		#region Events
		$events = array();
		foreach ($Proj->eventInfo as $event_id => $event) {
			$event = array(
				"id" => $event_id,
				"armNum" => $event["arm_num"],
				"armId" => $event["arm_id"],
				"armName" => ensureUTF($event["arm_name"]),
				"uniqueEventName" => ensureUTF($Proj->uniqueEventNames[$event_id]),
				"event-name" => array(
					"reference" => ensureUTF($event["name"]),
					"refHash" => self::getChangeTrackHash($event["name"]),
					"prompt" => ensureUTF(RCView::tt("multilang_217")), //= Event name translation:
				),
				"event-custom_event_label" => array(
					"reference" => ensureUTF($event["custom_event_label"]),
					"refHash" => self::getChangeTrackHash($event["custom_event_label"]),
					"prompt" => ensureUTF(RCView::tt("multilang_218")), //= Custom event label translation:
				),
			);
			$events[$event_id] = $event;
		}
		#endregion
		#region Alerts & Notifications
		$alerts = array();
		$alertOb = new Alerts();
		foreach ($alertOb->getAlertSettings($project_id) as $alert_id => $alert) {
			$alert = array(
				"id" => $alert_id,
				"uniqueId" => Alerts::ALERT_UNIQUE_ID_PREFIX . "$alert_id",
				"alertNum" => "#".$alert["alert_number"],
				"title" => ensureUTF($alert["alert_title"]),
				"type" => $alert["alert_type"],
				"alert-email_from_display" => array(
					"reference" => ensureUTF($alert["email_from_display"]),
					"refHash" => self::getChangeTrackHash($alert["email_from_display"]),
					"prompt" => ensureUTF(RCView::tt("multilang_355")), //= Language-specific sender display name:
					"mode" => "text",
				),
				"alert-email_subject" => array(
					"reference" => ensureUTF($alert["email_subject"]),
					"refHash" => self::getChangeTrackHash($alert["email_subject"]),
					"prompt" => ensureUTF(RCView::tt("multilang_354")), //= Translation of the email subject:
					"mode" => "text"
				),
				"alert-alert_message" => array(
					"reference" => ensureUTF($alert["alert_message"]),
					"refHash" => self::getChangeTrackHash($alert["alert_message"]),
					"prompt" => ensureUTF(RCView::tt("multilang_353")), //= Translation of the message body:
					"mode" => "textarea"
				),
				"alert-sendgrid_template_data" => array(
					"reference" => ensureUTF($alert["sendgrid_template_data"]),
					"refHash" => self::getChangeTrackHash($alert["sendgrid_template_data"]),
					"prompt" => ensureUTF(RCView::tt("multilang_598")), //= Translation of the template data:
					"mode" => "text"
				),
			);
			$alerts[$alert_id] = $alert;
		}
		#endregion
		#region Missing Data Codes
		$missing_data_codes = array();
		foreach (parseEnum($Proj->project["missing_data_codes"]) as $mdc => $label) {
			$missing_data_codes[$mdc] = array(
				"reference" => ensureUTF($label),
				"refHash" => self::getChangeTrackHash($label),
			);
		}
		#endregion
		#region PDF Customizations
		$pdf_customizations = array();
		if (isset($Proj->project["pdf_custom_header_text"]) && $Proj->project["pdf_custom_header_text"] !== null) {
			$pdf_customizations[""]["pdf-pdf_custom_header_text"] = array(
				"reference" => ensureUTF($Proj->project["pdf_custom_header_text"]),
				"refHash" => self::getChangeTrackHash($Proj->project["pdf_custom_header_text"]),
				"prompt" => ensureUTF(RCView::tt("multilang_352")), //= Translation of the PDF custom header text:
				"mode" => "text",
			);
		}
		#endregion
		#region Protected Mail
		$protected_mail_settings = array();
		if ($Proj->project["protected_email_mode"] == 1) {
			$protected_email_custom_text = strlen($Proj->project["protected_email_mode_custom_text"]) ? $Proj->project["protected_email_mode_custom_text"] : $lang["email_users_36"];
			$protected_mail_settings[""]["protmail-protected_email_mode_custom_text"] = array(
				"reference" => ensureUTF($protected_email_custom_text),
				"refHash" => self::getChangeTrackHash($protected_email_custom_text),
				"prompt" => ensureUTF(RCView::tt("multilang_351")), //= Translation of the REDCap Secure Messaging text:
				"mode" => "textarea",
			);
		}
		#endregion
		#region MyCap
		$mycap_settings = [
			"mycap-app_title" => null,
			"mycap-baseline_task" => [],
			"pages" => [],
			"contacts" => [],
			"links" => [],
			"taskToForm" => [],
			// This is a list of task items that are not event-specific
			// (in the order in which they should be rendered).
			"orderedListOfTaskItems" => [
				"task_title" => true,
				"extendedConfig_intendedUseDescription" => true,
				"extendedConfig_trailMakingInstruction" => true,
				"extendedConfig_infoTitle" => true,
				"extendedConfig_infoInstructions" => true,
				"extendedConfig_captureTitle" => true,
				"extendedConfig_captureInstructions" => true,
				"extendedConfig_speechInstruction" => true,
				"extendedConfig_shortSpeechInstruction" => true,
			],
			// This is a list of all possible event-specific task items
			// (in the order in which they should be rendered).
			"orderedListOfEventSpecificTaskItems" => [
				"instruction_step_title" => true,
				"instruction_step_content" => true,
				"completion_step_title" => true,
				"completion_step_content" => true
			],
		];
		if ($mycap_enabled) {
			#region App Title
			$mycap_settings["mycap-app_title"] = [
				"reference" => $myCapProj->project["name"]??"",
				"refHash" => self::getChangeTrackHash($myCapProj->project["name"]??""),
				"prompt" => ensureUTF(RCView::tt("multilang_714")), //= Translation of the MyCAP app title:
				"mode" => "text",
			];
			#endregion
			#region Baseline Date Settings
			$zdt = \Vanderbilt\REDCap\Classes\MyCap\ZeroDateTask::getBaselineDateSettings($project_id);
			if ($zdt["enabled"] ?? false) {
				$mycap_settings["mycap-baseline_task"] = [
					"mycap-baseline_title" => [
						"reference" => $zdt["title"],
						"refHash" => self::getChangeTrackHash($zdt["title"]),
						"prompt" => ensureUTF(RCView::tt("mycap_mobile_app_108")), //= Task Title
						"mode" => "text",
					],
					"mycap-baseline_question1" => [
						"reference" => $zdt["question1"],
						"refHash" => self::getChangeTrackHash($zdt["question1"]),
						"prompt" => ensureUTF(RCView::tt("multilang_744")), //= Question whether baseline date is today
						"mode" => "text",
					],
					"mycap-baseline_question2" => [
						"reference" => $zdt["question2"],
						"refHash" => self::getChangeTrackHash($zdt["question2"]),
						"prompt" => ensureUTF(RCView::tt("multilang_745")), //= Question to ask for past date
						"mode" => "text",
					],
					"mycap-baseline_instr_title" => [
						"reference" => ($zdt["instructionStep"]["title"]??""),
						"refHash" => self::getChangeTrackHash($zdt["instructionStep"]["title"]??""),
						"prompt" => ensureUTF(RCView::tt("multilang_728")), //= Instruction Step - Title
						"mode" => "text",
					],
					"mycap-baseline_instr_content" => [
						"reference" => ($zdt["instructionStep"]["content"]??""),
						"refHash" => self::getChangeTrackHash($zdt["instructionStep"]["content"]??""),
						"prompt" => ensureUTF(RCView::tt("multilang_729")), //= Instruction Step - Title
						"mode" => "textarea",
					]
				];
			}
			#endregion
			#region About Pages
			$pages = (new \Vanderbilt\REDCap\Classes\MyCap\Page())->getAboutPagesSettings($project_id);
			foreach ($pages as $page) {
				$mycap_settings["pages"][$page["page_id"]] = [
					"id" => intval($page["page_id"]),
					"identifier" => $page["identifier"],
					"order" => intval($page["page_order"]),
					"customImage" => $page["image_type"] == ".Custom",
					"mycap-about-page_title" => [
						"reference" => $page["page_title"],
						"refHash" => self::getChangeTrackHash($page["page_title"]),
						"prompt" => ensureUTF(RCView::tt("multilang_715")), //= Translation of the page title:
						"mode" => "text",
					],
					"mycap-about-page_content" => [
						"reference" => $page["page_content"],
						"refHash" => self::getChangeTrackHash($page["page_content"]),
						"prompt" => ensureUTF(RCView::tt("multilang_716")), //= Translation of the page content:
						"mode" => "textarea",
					],
					// TODO - Implement image type
					// "mycap-about-custom_logo" => [
					//     "reference" => intval($page["custom_logo"]),
					//     "refHash" => self::getChangeTrackHash($page["custom_logo"]),
					//     "prompt" => ensureUTF(RCView::tt("multilang_")), //= Language-specific version of the custom image:
					//     "mode" => "image",
					// ],
				];
			}
			#endregion
			#region Contacts
			$contacts = \Vanderbilt\REDCap\Classes\MyCap\Contact::getContacts($project_id);
			foreach ($contacts as $contact) {
				$mycap_settings["contacts"][$contact["contact_id"]] = [
					"id" => intval($contact["contact_id"]),
					"identifier" => $contact["identifier"],
					"order" => intval($contact["contact_order"]),
					"mycap-contact-contact_header" => [
						"reference" => $contact["contact_header"],
						"refHash" => self::getChangeTrackHash($contact["contact_header"]),
						"prompt" => ensureUTF(RCView::tt("multilang_717")), //= Translation of the contact page title:
						"mode" => "text",
					],
					"mycap-contact-contact_title" => [
						"reference" => $contact["contact_title"],
						"refHash" => self::getChangeTrackHash($contact["contact_title"]),
						"prompt" => ensureUTF(RCView::tt("multilang_718")), //= Language-specific version of the contact name:
						"mode" => "text",
					],
					"mycap-contact-phone_number" => [
						"reference" => $contact["phone_number"],
						"refHash" => self::getChangeTrackHash($contact["phone_number"]),
						"prompt" => ensureUTF(RCView::tt("multilang_719")), //= Language-specific version of the contact phone:
						"mode" => "text",
					],
					"mycap-contact-email" => [
						"reference" => $contact["email"],
						"refHash" => self::getChangeTrackHash($contact["email"]),
						"prompt" => ensureUTF(RCView::tt("multilang_720")), //= Language-specific version of the contact email:
						"mode" => "text",
					],
					"mycap-contact-website" => [
						"reference" => $contact["website"],
						"refHash" => self::getChangeTrackHash($contact["website"]),
						"prompt" => ensureUTF(RCView::tt("multilang_721")), //= Language-specific version of the contact website URL:
						"mode" => "text",
					],
					"mycap-contact-additional_info" => [
						"reference" => $contact["additional_info"],
						"refHash" => self::getChangeTrackHash($contact["additional_info"]),
						"prompt" => ensureUTF(RCView::tt("multilang_722")), //= Translation of the additional contact info:
						"mode" => "textarea",
					],
				];
			}
			#endregion
			#region Links
			$links = \Vanderbilt\REDCap\Classes\MyCap\Link::getLinks($project_id);
			foreach ($links as $link) {
				$mycap_settings["links"][$link["link_id"]] = [
					"id" => intval($link["link_id"]),
					"identifier" => $link["identifier"],
					"order" => intval($link["link_order"]),
					"mycap-link-link_name" => [
						"reference" => $link["link_name"],
						"refHash" => self::getChangeTrackHash($link["link_name"]),
						"prompt" => ensureUTF(RCView::tt("multilang_723")), //= Translation of the link name:
						"mode" => "text",
					],
					"mycap-link-link_url" => [
						"reference" => $link["link_url"],
						"refHash" => self::getChangeTrackHash($link["link_url"]),
						"prompt" => ensureUTF(RCView::tt("multilang_724")), //= Translation of the link URL:
						"mode" => "text",
					],
				];
			}
			#endregion
			#region MyCap Tasks
			$tasks = \Vanderbilt\REDCap\Classes\MyCap\Task::getAllTasksSettings($project_id);
			foreach ($tasks as $task_id => $task) {
				$task_items_template = \Vanderbilt\REDCap\Classes\MyCap\ActiveTask::getActiveTaskTranslatableItems($task["question_format"]);
				$task_items = [];
				$ext_config = json_decode(($task["extended_config_json"] ?? ""), true) ?? [];
				// Add all items from the template
				foreach ($task_items_template as $task_item_key => $task_item_base) {
					$val = starts_with($task_item_key, "extendedConfig_") 
						? ($ext_config[substr($task_item_key, 15)] ?? "")
						: ($task[$task_item_key] ?? "");
					$task_items[$task_item_key] = $task_item_base;
					$task_items[$task_item_key]["reference"] = $val;
					$task_items[$task_item_key]["refHash"] = self::getChangeTrackHash($val);
				}

				$schedules = Task::getTaskSchedules($task_id, '', $project_id);
				foreach ($schedules as $task_event_id => $task_schedule) {
					$ext_config = json_decode(($task_schedule["extended_config_json"] ?? ""), true) ?? [];
					// Copy items and add reference and hash to each item
					foreach ($task_items_template as $task_item_key => $task_item_base) {
						$task_item_key_as_idx = starts_with($task_item_key, "extendedConfig_") ? substr($task_item_key, 15) : $task_item_key;
						$val = $ext_config[$task_item_key_as_idx] ?? ($task_schedule[$task_item_key_as_idx] ?? '');
						// Index by {event id}-{task id}
						$task_items["$task_event_id-$task_id"][$task_item_key] = $task_item_base;
						$task_items["$task_event_id-$task_id"][$task_item_key]["reference"] = $val;
						$task_items["$task_event_id-$task_id"][$task_item_key]["refHash"] = self::getChangeTrackHash($val);
					}
				}
				// MyCap tasks are an extension of instruments
				$forms[$task["form_name"]]["myCapTaskItems"] = $task_items;
				$forms[$task["form_name"]]["myCapTaskId"] = $task_id;
				$forms[$task["form_name"]]["myCapTaskType"] = \Vanderbilt\REDCap\Classes\MyCap\ActiveTask::getTaskType($task["question_format"]);
				// Task -> Form map
				$mycap_settings["taskToForm"]["$task_id"] = $task["form_name"];
			}
			#endregion
		}
		#endregion
		#region Descriptive Popups
		$descriptive_popups = [];
		foreach (\DescriptivePopup::getPopupsForProject($project_id) as $popup) {
			$popup_uid = $popup["popup_id"]; // TODO: Find a better (more transferrable) project-unique id; this uid should be limited by the field name naming rules.
			$descriptive_popups[$popup_uid] = [
				"inline_text" => [
					"prompt" => RCView::tt("multilang_788"),
					"reference" => ensureUTF($popup["inline_text"]),
					"refHash" => self::getChangeTrackHash($popup["inline_text"]),
				],
				"inline_text_popup_description" => [
					"prompt" => RCView::tt("multilang_785"),
					"reference" => ensureUTF($popup["inline_text_popup_description"]),
					"refHash" => self::getChangeTrackHash($popup["inline_text_popup_description"]),
				],
			];
		}
		#endregion
		// Assemble all into final metadata structure
		$projectMetadata = array (
			"alerts" => $alerts,
			"asis" => $asis,
			"events" => $events,
			"fields" => $fields,
			"fieldTypes" => self::getFieldTypesMetadata(),
			"forms" => $forms,
			"longitudinal" => $Proj->longitudinal,
			"matrixGroups" => $matrix_groups,
			"mdcs" => $missing_data_codes,
			"pid" => $project_id,
			"pdfCustomizations" => $pdf_customizations,
			"protectedMail" => $protected_mail_settings,
			"surveyQueue" => $sq,
			"surveys" => $surveys,
			"surveysEnabled" => $Proj->project["surveys_enabled"] == "1",
			"emptyHash" => self::getChangeTrackHash(""),
			"recordIdField" => $Proj->table_pk,
			"myCapEnabled" => $mycap_enabled,
			"myCap" => $mycap_settings,
			"descriptivePopups" => $descriptive_popups,
		);
		// Cache result for repeated use
		self::$projectMetadataCache[$project_id] = $projectMetadata;
		return $projectMetadata;
	}
	/**
	 * Project metadata cache
	 * @var array
	 */
	private static $projectMetadataCache = array();

	/**
	 * Generates an array with hints and prompts how to render a field's translation UI depending on its type
	 * @return array 
	 */
	private static function getFieldTypesMetadata() {
		global $lang;

		return array(
			"actiontag" => array (
				"value" => ensureUTF(RCView::tt("multilang_271")), //= Translation of the @Action Tag value(s):
				"colHeader" => ensureUTF(RCView::tt("multilang_272")), //= Action&nbsp;Tag
			),
			"matrix" => array(
				"header" => ensureUTF(RCView::tt("multilang_273")), //= Matrix header translation:
				"enum" => ensureUTF(RCView::tt("multilang_274")), //= Choices translation:
				"colHeader" => ensureUTF(RCView::tt("multilang_179")), //= Code
			),
			"header" => array(
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
			),
			"text" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
			),
			"textarea" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
			),
			"calc" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
			),
			"select" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
				"enum" => ensureUTF(RCView::tt("multilang_274")), //= Choices translation:
				"colHeader" => ensureUTF(RCView::tt("multilang_179")), //= Code
			),
			"radio" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
				"enum" => ensureUTF(RCView::tt("multilang_274")), //= Choices translation:
				"colHeader" => ensureUTF(RCView::tt("multilang_179")), //= Code
			),
			"checkbox" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
				"enum" => ensureUTF(RCView::tt("multilang_274")), //= Choices translation:
				"colHeader" => ensureUTF(RCView::tt("multilang_179")), //= Code
			),
			"yesno" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
				"enumHints" => ensureUTF(RCView::lang_i("multilang_790", [
					$lang["multilang_296"] // Yes/No Field
				])), //= <i>{0:FieldType}:</i> Choice labels are fixed and cannot be translated on a per-field basis (See User Interface translations).
			),
			"truefalse" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
				"enumHints" => ensureUTF(RCView::lang_i("multilang_790", [
					$lang["multilang_297"] //= True/False Field
				])), //= <i>{0:FieldType}:</i> Choice labels are fixed and cannot be translated on a per-field basis (See User Interface translations).

			),
			"file" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
			),
			"slider" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
				"enum" => ensureUTF(RCView::tt("multilang_278")), //= Slider labels translation:
				"enumLabels" => array (
					"left" => ensureUTF($lang["design_665"]),
					"middle" => ensureUTF($lang["design_666"]),
					"right" => ensureUTF($lang["design_667"]),
				),
				"colHeader" => ensureUTF(RCView::tt("multilang_279")), //= Slider&nbsp;Label
			),
			"descriptive" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
				"video_url" => ensureUTF(RCView::tt("multilang_280")), //= Language-specific URL of the embedded video:
			),
			"sql" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_276")), //= Field label translation:
				"note" => ensureUTF(RCView::tt("multilang_277")), //= Field note translation:
			),
			"formstatus" => array (
				"header" => ensureUTF(RCView::tt("multilang_275")), //= Section header translation:
				"label" => ensureUTF(RCView::tt("multilang_281")), //= Form status label translation:
				"enum" => ensureUTF(RCView::tt("multilang_274")), //= Choices translation:
				"colHeader" => ensureUTF(RCView::tt("multilang_282")), //= Form&nbsp;Status
			),
			"mdc" => array (
				"label" => ensureUTF(RCView::tt("multilang_186")), // Missing Data Code label translation:
				"colHeader" => ensureUTF(RCView::tt("multilang_179")), // Code
			),
			"form" => array (
				"name" => ensureUTF(RCView::tt("multilang_216")), // Instrument name translation:
			),
			"event" => array (
				"name" => ensureUTF(RCView::tt("multilang_217")), // Event name translation:
				"custom_event_label" => ensureUTF(RCView::tt("multilang_218")), // Custom event label translation:
			),
		);
	}


	/**
	 * Builds a lookup data structure to easily check whether a type-name-index combination is valid for the given project
	 * @param mixed $project_id 
	 * @return Array The map [type [name [index]]] = true
	 */
	private static function getProjectMetaDataMap($project_id) {
		$pmd = self::getProjectMetadata($project_id, true);
		$map = [];
		// alert-
		if (isset($pmd["alerts"]) && is_array($pmd["alerts"])) {
			foreach ($pmd["alerts"] as $name => $_) {
				$map["alert-email_from_display"][$name][""] = true;
				$map["alert-email_subject"][$name][""] = true;
				$map["alert-alert_message"][$name][""] = true;
				$map["alert-sendgrid_template_data"][$name][""] = true;
				$map["alert-excluded"][$name][""] = true;
				if ($_["type"] == "SENDGRID_TEMPLATE") {
					// Allow sendgrid template data keys to be stored in index field
					$template_data = json_decode($_["alert-sendgrid_template_data"]["reference"], TRUE);
					foreach ($template_data as $key => $value) {
						$map["alert-sendgrid_template_data"][$name][$key] = true;
					}
				}
			}
		}
		// asi-
		if (isset($pmd["asis"]) && is_array($pmd["asis"])) {
			foreach ($pmd["asis"] as $name => $asi) {
				$form_name = $asi["form"];
				$map["asi-email_content"][$form_name][$name] = true;
				$map["asi-email_sender_display"][$form_name][$name] = true;
				$map["asi-email_subject"][$form_name][$name] = true;
			}
		}
		// event-
		if (isset($pmd["events"]) && is_array($pmd["events"])) {
			foreach ($pmd["events"] as $name => $_) {
				$map["event-name"][$name][""] = true;
				$map["event-custom_event_label"][$name][""] = true;
			}
		}
		// field-
		if (isset($pmd["fields"]) && is_array($pmd["fields"])) {
			foreach ($pmd["fields"] as $name => $field_info) {
				foreach ($field_info as $type => $type_info) {
					if ($type == "field-enum" || $type == "field-actiontag") {
						if ($type_info) {
							foreach ($type_info as $index => $_) {
								$map[$type][$name][$index] = true;
							}
						}
					}
					else if (starts_with($type, "field-")) {
						$map[$type][$name][""] = true;
					}
				}
				$map["field-complete"][$name][""] = true;
			}
		}
		// form-
		if (isset($pmd["forms"]) && is_array($pmd["forms"])) {
			foreach ($pmd["forms"] as $name => $_) {
				$map["form-active"][$name][""] = true;
				$map["form-name"][$name][""] = true;
			}
		}
		// matrix-
		if (isset($pmd["matrixGroups"]) && is_array($pmd["matrixGroups"])) {
			foreach ($pmd["matrixGroups"] as $name => $mg_info) {
				foreach ($mg_info as $type => $type_info) {
					if ($type == "matrix-enum") {
						foreach ($type_info as $index => $_) {
							$map[$type][$name][$index] = true;
						}
					}
					else if (starts_with($type, "matrix-")) {
						$map[$type][$name][""] = true;
					}
				}
				$map["matrix-complete"][$name][""] = true;
			}
		}
		// mdc-
		if (isset($pmd["mdcs"]) && is_array($pmd["mdcs"])) {
			foreach ($pmd["mdcs"] as $name => $_) {
				$map["mdc-label"][$name][""] = true;
			}
		}
		// mycap-
		$map["mycap-app_title"][""][""] = true;
		foreach ($pmd["myCap"]["mycap-baseline_task"] as $name => $item) {
			$map["mycap-baseline_task"][$name][""] = true;
		}
		foreach (["pages", "contacts", "links"] as $cat) {
			foreach ($pmd["myCap"][$cat] as $name => $items) {
				foreach ($items as $type => $_) {
					if (starts_with($type, "mycap-")) {
						$map[$type][$name][""] = true;
					}
				}
			}
		}
		foreach ($pmd["forms"] as $form_name => $form) {
			if ($form["myCapTaskId"] != null) {
				$map["task-complete"][$form_name][""] = true;
				foreach ($form["myCapTaskItems"] as $task_item => $_) {
					if (array_key_exists($task_item, $pmd["myCap"]["orderedListOfTaskItems"])) {
						$map["task-".$task_item][$form["myCapTaskId"]][""] = true;
					}
				}
				foreach ($pmd["events"] as $event_id => $_) {
					$event_id_task_id = "$event_id-{$form["myCapTaskId"]}";
					if (isset($form["myCapTaskItems"][$event_id_task_id])) {
						foreach ($form["myCapTaskItems"][$event_id_task_id] as $task_item => $_) {
							$map["task-".$task_item][$event_id_task_id][""] = true;
						}
					}
				}
			}
		}
		// pdf-
		if (isset($pmd["pdfCustomizations"]) && is_array($pmd["pdfCustomizations"])) {
			$map["pdf-pdf_custom_header_text"][""][""] = true;
		}
		// protmail
		if (isset($pmd["protectedMail"]) && is_array($pmd["protectedMail"])) {
			$map["protmail-protected_email_mode_custom_text"][""][""] = true;
		}
		// sq-
		if (isset($pmd["surveyQueue"]) && is_array($pmd["surveyQueue"])) {
			foreach ($pmd["surveyQueue"] as $type => $_) {
				if (starts_with($type, "sq-")) {
					$map[$type][""][""] = true;
				}
			}
		}
		// survey-
		if (isset($pmd["surveys"]) && is_array($pmd["surveys"])) {
			foreach ($pmd["surveys"] as $name => $survey_info) {
				foreach ($survey_info as $type => $_) {
					if (starts_with($type, "survey-")) {
						$map[$type][$name][""] = true;
					}
				}
				$map["survey-active"][$name][""] = true;
			}
		}
		// descriptive-popup-
		if (isset($pmd["descriptivePopups"]) && is_array($pmd["descriptivePopups"])) {
			foreach ($pmd["descriptivePopups"] as $popup_uid => $_) {
				$map["descriptive-popup-complete"][""][$popup_uid] = true;
				foreach (["inline_text", "inline_text_popup_description"] as $name) {
					$map["descriptive-popup"][$name][$popup_uid] = true;
				}
			}
		}
		return $map;
	}

	#endregion

	#region Generate User Interface and Validation Metadata

	/**
	 * Gets the strings for the UI category labels
	 * @return string[] 
	 */
	public static function getUICategories() {
		$cats = array (
			"ui-all" => RCView::tt("dashboard_12"), // All
			"ui-common" => RCView::tt("multilang_192"), // Common
			"ui-fields" => RCView::tt("multilang_191"), // Field Types
			"ui-dataentry" => RCView::tt("bottom_20"), // Data Entry
			"ui-survey" => RCView::tt("survey_437"), // Survey
			//"ui-mycap" => RCView::tt("mycap_mobile_app_101"), // MyCap // TODO: Uncomment to enable MyCap UI translations
			"ui-validation" => RCView::tt("multilang_193"), // Validation
			"ui-protmail" => RCView::tt("multilang_189"), // Protected Mail
			"ui-captcha" => RCView::tt("multilang_190"), // reCAPTCHA
		);
		foreach ($cats as $k => $v) {
			$cats[$k] = ensureUTF($v);
		}
		return $cats;
	}

	/**
	 * Gets the strings for the UI subheadings
	 * @return string[] 
	 */
	public static function getUISubheadings() {
		$subheadings = array (
			"navigation" => RCView::tt("multilang_283"), //= Page Navigation
			"controls" => RCView::tt("multilang_284"), //= Controls & Display Elements
			"dialogs" => RCView::tt("multilang_285"), //= Dialog Elements
			"misc" => RCView::tt("multilang_286"), //= Miscellaneous Elements
			"login" => RCView::tt("survey_573"), //= Survey Login
			"completion" => RCView::tt("multilang_287"), //= Survey Completion
			"start" => RCView::tt("multilang_288"), //= Survey Start / Continuation
			"stop" => RCView::tt("multilang_289"), //= Survey Stop
			"save" => RCView::tt("multilang_290"), //= Survey Save & Return Later
			"email" => RCView::tt("multilang_291"), //= Survey Email
			"sms" => RCView::tt("alerts_201"), //= SMS Text Message
			"promis" => RCView::tt("multilang_303"), //= PROMIS
			"results" => RCView::tt("multilang_292"), //= Survey Results
			"queue" => RCView::tt("survey_505"), //= Survey Queue
			"speech" => RCView::tt("multilang_251"), //= Text-to-Speech
			"print" => RCView::tt("multilang_293"), //= Printing
			"errors" => RCView::tt("multilang_294"), //= Error Messages
			"field_checkbox" => RCView::tt("multilang_295"), //= Checkbox Field
			"field_yesno" => RCView::tt("multilang_296"), //= Yes/No Field
			"field_truefalse" => RCView::tt("multilang_297"), //= True/False Field
			"field_dropdown" => RCView::tt("multilang_298"), //= Dropdown Field
			"field_slider" => RCView::tt("multilang_299"), //= Slider Field
			"field_date" => RCView::tt("multilang_300"), //= Date & Time Fields
			"field_upload" => RCView::tt("multilang_301"), //= File Upload & Signature Fields
			"field_desc" => RCView::tt("multilang_302"), //= Descriptive Field
			"field_matrix" => RCView::tt("multilang_706"), //= Matrix of Fields
			"captcha" => RCView::tt("multilang_190"), // reCAPTCHA
			"protmail_msg" => RCView::tt("multilang_196"), // Protected Mail Message,
			"protmail_codemsg" => RCView::tt("multilang_197"), // Protected Mail Code Message,
			"protmail_codecheck" => RCView::tt("multilang_198"), // Protected Mail Code Check,
			"protmail_display" => RCView::tt("multilang_199"), // Protected Mail Display,
			"pdf" => RCView::tt("global_85"), // PDF
			"econsent" => RCView::tt("multilang_194"), // eConsent
			"dataentry_secondary" => RCView::tt("multilang_195"), // Secondary Unique Field
			"dataentry_locking" => RCView::tt("rights_115"), //= Locking / Unlocking
			"dataentry_promis" => RCView::tt("multilang_303"), //= PROMIS
			"dataentry_misc" => RCView::tt("global_156"), //= Miscellaneous
			"dataentry_collection_menu" => RCView::tt("multilang_304"), //= Data Collection Menu
			"dataentry_actions_menu" => RCView::tt("multilang_305"), //= Actions Menu
			"dataentry_record_actions_menu" => RCView::tt("multilang_306"), //= Record Actions Menu
			"dataentry_save" => RCView::tt("multilang_307"), //= Save Buttons
			"dataentry_delete" => RCView::tt("multilang_308"), //= Deletion Notices & Confirmation
			"dataentry_reason" => RCView::tt("multilang_652"), //= Change Reason Popup
			"dataentry_context_msg" => RCView::tt("multilang_309"), //= Action Messages
			"dataentry_mdc" => RCView::tt("multilang_310"), //= Default Missing Data Code Labels
			"dataentry_simultaneous" => RCView::tt("multilang_579"), // Simultaneous Users Notification
			"validation_messages" => RCView::tt("multilang_311"), //= Validation Messages
			"validation_secondary" => RCView::tt("multilang_195"), //= Secondary Unique Field
			"validation_types" => RCView::tt("multilang_576"), //= Validation Types
			"mycap_general" => RCView::tt("control_center_360"), // General
			"mycap_activities" => RCView::tt("multilang_725"), // Activities
			"mycap_profiles" => RCView::tt("multilang_726"), // Profiles
			"mycap_messages" => RCView::tt("multilang_753"), // Messages
			"mycap_about" => RCView::tt("multilang_754"), // About
			"mycap_contacts" => RCView::tt("multilang_755"), // Contacts
			"mycap_links" => RCView::tt("multilang_756"), // Links
			"mycap_tasks" => RCView::tt("multilang_757"), // Tasks
			"mycap_atgeneral" => RCView::tt("multilang_758"), // Active Task: General
			"mycap_holepeg" => RCView::tt("multilang_759"), // Active Task: 9-Hole Peg Test
			"mycap_amsler" => RCView::tt("multilang_760"), // Active Task: Amsler Grid
			"mycap_audio" => RCView::tt("multilang_761"), // Active Task: Audio Recording
			"mycap_fitness" => RCView::tt("multilang_762"), // Active Task: Fitness
			"mycap_psat" => RCView::tt("multilang_763"), // Active Task: Paced Serial Addition Test (PSAT)
			"mycap_selfie" => RCView::tt("multilang_764"), // Active Task: Selfie Capture
			"mycap_swalk" => RCView::tt("multilang_765"), // Active Task: Gait and Balance (Short Walk)
			"mycap_spatial" => RCView::tt("multilang_766"), // Active Task: Spatial Memory
			"mycap_stroop" => RCView::tt("multilang_767"), // Active Task: Stroop
			"mycap_phonation" => RCView::tt("multilang_768"), // Active Task: Sustained Phonation
			"mycap_twalk" => RCView::tt("multilang_769"), // Active Task: Timed Walk
			"mycap_hanoi" => RCView::tt("multilang_770"), // Active Task: Tower of Hanoi
			"mycap_trail" => RCView::tt("multilang_771"), // Active Task: Trail Making Test
		);
		foreach ($subheadings as $k => $v) {
			$subheadings[$k] = ensureUTF($v);
		}
		return $subheadings;
	}

	/**
	 * Gets default values and other metadata for the user interface translations
	 * @param bool $is_project Whether data should be generated for project or system context
	 * @return array 
	 */
	public static function getUIMetadata($is_system = false) {
		// Serve from cache
		if (self::$uiMetadataCache) return self::$uiMetadataCache;
		// Build
		global $lang;
		// The order in this array determines the order on screen
		$metadata = array (
			// Font size
			"survey_1130" => [
				"category" => "ui-survey",
				"group" => "controls",
				"type" => "string",
				"id" => "survey_1130",
				"default" => $lang["survey_1130"], //= Click to decrease font size
				"prompt" => RCView::lang_i("multilang_312", [$lang["survey_1130"]]), //= The '{0}' tooltip text:
			],
			"survey_1129" => [
				"category" => "ui-survey",
				"group" => "controls",
				"type" => "string",
				"id" => "survey_1129",
				"default" => $lang["survey_1129"], //= Click to increase font size
				"prompt" => RCView::lang_i("multilang_312", [$lang["survey_1129"]]), //= The '{0}' tooltip text:
			],
			// Language switching
			"multilang_02" => [
				"category" => "ui-common survey dataentry",
				"group" => "controls",
				"type" => "string",
				"id" => "multilang_02",
				"default" => $lang["multilang_02"], //= Change language
				"prompt" => RCView::lang_i("multilang_312", [$lang["multilang_02"]]), //= The '{0}' tooltip text:
			],
			// Survey Access Code page prompt
			"multilang_647" => [
				"category" => "ui-common survey",
				"group" => "controls",
				"type" => "string",
				"id" => "multilang_647",
				"default" => $lang["multilang_647"], //= Note, there may be more and/or ...
				"prompt" => RCView::tt("multilang_648"), //= The survey access page languages note:
			],
			// [*DATA REMOVED*]]
			"data_entry_540" => [
				"category" => "ui-common survey dataentry",
				"group" => "controls",
				"type" => "string",
				"id" => "data_entry_540",
				"default" => $lang["data_entry_540"], //= [*DATA REMOVED*]]
				"prompt" => RCView::lang_i("multilang_315", [$lang["data_entry_540"]]), //= The '{0}' placeholder text:
			],
			// Working
			"design_08" => [
				"category" => "ui-common survey dataentry",
				"group" => "controls",
				"type" => "string",
				"id" => "design_08",
				"default" => $lang["design_08"], //= Working...
				"prompt" => RCView::lang_i("multilang_336", [$lang["design_08"]]), //= The '{0}' indicator:
			],
			// Paging and Submit
			"survey_1347" => [
				"category" => "ui-survey",
				"group" => "navigation",
				"type" => "string",
				"id" => "survey_1347",
				"default" => $lang["survey_1347"], //= Page {0} of {1}
				"prompt" => RCView::tt("multilang_317"), //= Page number (use {0} and {1} as placeholders for the current page and the total number of pages, respectively):
			],
			"survey_1559" => [
				"category" => "ui-survey",
				"group" => "navigation",
				"type" => "string",
				"id" => "survey_1559",
				"default" => $lang["survey_1559"], //= Show greeting message/instructions
				"prompt" => RCView::lang_i("multilang_323", [RCView::tt_attr("survey_1559")]), //= The '{0}' link label:
			],
			"paging_symbols" => [
				"category" => "ui-survey",
				"group" => "navigation",
				"type" => "bool",
				"id" => "paging_symbols",
				"default" => false,
				"prompt" => RCView::lang_i("multilang_318", array( //= Use {0} and {1} symbols for previous/next buttons?
					"<i class=\"fas fa-angle-double-left\"></i>",
					"<i class=\"fas fa-angle-double-right\"></i>"
				), false),
				"overrides" => array(
					"data_entry_536" => "<i class=\"fas fa-angle-double-right\"></i>",
					"data_entry_537" => "<i class=\"fas fa-angle-double-left\"></i>",
				),
			],
			"data_entry_536" => [
				"category" => "ui-survey",
				"group" => "navigation",
				"type" => "string",
				"id" => "data_entry_536",
				"default" => $lang["data_entry_536"], //= Next Page >>
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_536"]]), //= The '{0}' button label:
			],
			"data_entry_537" => [
				"category" => "ui-survey",
				"group" => "navigation",
				"type" => "string",
				"id" => "data_entry_537",
				"default" => $lang["data_entry_537"], //= << Previous Page
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_537"]]), //= The '{0}' button label:
			],
			"submit_symbol" => [
				"category" => "ui-survey",
				"group" => "navigation",
				"type" => "bool",
				"id" => "submit_symbol",
				"default" => false,
				"prompt" => RCView::lang_i("multilang_321", array( //= Use the {0} symbol for the submit button instead of a text label?
					"<i class=\"fas fa-paper-plane\"></i>"
				), false),
				"overrides" => array(
					"survey_200" => "<i class=\"fas fa-paper-plane\"></i>",
				),
			],
			"survey_200" => [
				"category" => "ui-survey",
				"group" => "navigation",
				"type" => "string",
				"id" => "survey_200",
				"default" => $lang["survey_200"], //= Submit
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_200"]]), //= The '{0}' button label:
			],


			// Misc labels, links, buttons
			"form_renderer_19" => [
				"category" => "ui-common survey dataentry",
				"group" => "controls",
				"type" => "string",
				"id" => "form_renderer_19",
				"default" => $lang["form_renderer_19"], //= Expand
				"prompt" => RCView::lang_i("multilang_323", [$lang["form_renderer_19"]]), //= The '{0}' link label:
			],
			"form_renderer_20" => [
				"category" => "ui-common survey dataentry",
				"group" => "controls",
				"type" => "string",
				"id" => "form_renderer_20",
				"default" => $lang["form_renderer_20"], //= reset
				"prompt" => RCView::lang_i("multilang_323", [$lang["form_renderer_20"]]), //= The '{0}' link label:
				],
			"data_entry_39" => [
				"category" => "ui-common survey dataentry",
				"group" => "controls",
				"type" => "string",
				"id" => "data_entry_39",
				"default" => $lang["data_entry_39"], //= must provide value
				"prompt" => RCView::lang_i("multilang_336", [$lang["data_entry_39"]]), //= The '{0}' indicator:
			],
			"form_renderer_66" => [
				"category" => "ui-common survey dataentry",
				"group" => "controls",
				"type" => "string",
				"id" => "form_renderer_66",
				"default" =>  $lang["form_renderer_66"], //= Toggle full screen view
				"prompt" => RCView::lang_i("multilang_335", [$lang["form_renderer_66"]]), //= The '{0}' button label:
			],
			"calendar_popup_01" => [
				"category" => "ui-common survey dataentry",
				"group" => "dialogs",
				"type" => "string",
				"id" => "calendar_popup_01",
				"default" => $lang["calendar_popup_01"], //= Close
				"prompt" => RCView::lang_i("multilang_335", [$lang["calendar_popup_01"]]), //= The '{0}' button label:
			],
			"global_53" => [
				"category" => "ui-common survey dataentry",
				"group" => "dialogs",
				"type" => "string",
				"id" => "global_53",
				"default" =>  $lang["global_53"], //= Cancel
				"prompt" => RCView::lang_i("multilang_335", [$lang["global_53"]]), //= The '{0}' button label:
			],
			"design_654" => [
				"category" => "ui-common survey dataentry",
				"group" => "dialogs",
				"type" => "string",
				"id" => "design_654",
				"default" =>  $lang["design_654"], //= Confirm
				"prompt" => RCView::lang_i("multilang_335", [$lang["design_654"]]), //= The '{0}' button label:
			],
			"alerts_24" => [
				"category" => "ui-common survey dataentry",
				"group" => "dialogs",
				"type" => "string",
				"id" => "alerts_24",
				"default" => $lang["alerts_24"], //= Alert
				"prompt" => RCView::lang_i("multilang_329", [$lang["alerts_24"]]), //= The '{0}' popup window title:
			],
			"global_01" => [
				"category" => "ui-common",
				"group" => "dialogs",
				"type" => "string",
				"id" => "global_01",
				"default" => $lang["global_01"], //= ERROR
				"prompt" => RCView::lang_i("multilang_329", [$lang["global_01"]]), //= The '{0}' popup window title:
			],
			"global_03" => [
				"category" => "ui-common",
				"group" => "dialogs",
				"type" => "string",
				"id" => "global_03",
				"default" => $lang["global_03"], //= NOTICE
				"prompt" => RCView::lang_i("multilang_329", [$lang["global_03"]]), //= The '{0}' popup window title:
			],
			"global_64" => [
				"category" => "ui-common",
				"group" => "dialogs",
				"type" => "string",
				"id" => "global_64",
				"default" => $lang["global_64"], //= Woops! An error occurred. Please try again.
				"prompt" => RCView::tt("multilang_332"), //= Text for non-specific errors:
			],
			"survey_1241" => [
				"category" => "ui-survey",
				"group" => "controls",
				"type" => "string",
				"id" => "survey_1241",
				"default" => $lang["survey_1241"], //= You may now close this tab/window
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_1241"]]), //= The '{0}' button label:
			],
			"global_47" => [
				"category" => "ui-common",
				"group" => "misc",
				"type" => "string",
				"id" => "global_47",
				"default" => $lang["global_47"], //= or
				"prompt" => RCView::lang_i("multilang_334", [$lang["global_47"]]), //= The alternatives separator '- {0} -' (dashes may be added automatically):
			],
			"scheduling_35" => [
				"category" => "ui-survey",
				"group" => "print",
				"type" => "string",
				"id" => "scheduling_35",
				"default" => $lang["scheduling_35"], //= Print
				"prompt" => RCView::lang_i("multilang_335", [$lang["scheduling_35"]]), //= The '{0}' button label:
			],
			"system_config_623" => [
				"category" => "ui-survey",
				"group" => "print",
				"type" => "string",
				"id" => "system_config_623",
				"default" => $lang["system_config_623"], //= Print text below
				"prompt" => RCView::lang_i("multilang_335", [$lang["system_config_623"]]), //= The '{0}' button label:
			],
			"global_143" => [
				"category" => "ui-common",
				"group" => "misc",
				"type" => "string",
				"id" => "global_143",
				"default" => $lang["global_143"], //= Checked
				"prompt" => RCView::lang_i("multilang_336", [$lang["global_143"]]), //= The '{0}' indicator:
			],
			"global_144" => [
				"category" => "ui-common",
				"group" => "misc",
				"type" => "string",
				"id" => "global_144",
				"default" => $lang["global_144"], //= Unchecked
				"prompt" => RCView::lang_i("multilang_336", [$lang["global_144"]]), //= The '{0}' indicator:
			],
			"data_entry_203" => [
				"category" => "ui-common survey dataentry",
				"group" => "misc",
				"type" => "string",
				"id" => "data_entry_203",
				"default" => $lang["data_entry_203"], //= Value removed!
				"prompt" => RCView::lang_i("multilang_337", [$lang["data_entry_203"]]), //= The '{0}' notification:
			],
			"data_entry_602" => [
				"category" => "ui-survey",
				"group" => "errors",
				"type" => "string",
				"id" => "data_entry_602",
				"default" => $lang["data_entry_602"], //= We're sorry, but your current web browser (Internet Explorer {0}) is not compatible with this web page. In order to continue on this page, we recommend you upgrade your web browser to a newer version or else use Firefox or Google Chrome instead. Thanks for understanding!
				"prompt" => RCView::tt("multilang_651"), //= Internet Explorer 6-8 incompatibility notice:
			],
			"docs_1101" => [
				"category" => "ui-survey",
				"group" => "errors",
				"type" => "string",
				"id" => "docs_1101",
				"default" => $lang["docs_1101"], //= Preview of file is not available
				"prompt" => RCView::lang_i("multilang_333", [$lang["docs_1101"]]), //= The '{0}' hint:
			],
			"global_215" => [
				"category" => "ui-survey",
				"group" => "errors",
				"type" => "string",
				"id" => "global_215",
				"default" => $lang["global_215"],
				"prompt" => RCView::lang_i("multilang_810", [$lang["global_215"]]), //= The '{0}' <i>Branching Logic/Calculations</i> error message:
			],
			"global_217" => [
				"category" => "ui-survey",
				"group" => "errors",
				"type" => "string",
				"id" => "global_217",
				"default" => $lang["global_217"],
				"prompt" => RCView::lang_i("multilang_810", [$lang["global_217"]]), //= The '{0}' <i>Branching Logic/Calculations</i> error message:
			],
			"global_313" => [
				"category" => "ui-survey",
				"group" => "errors",
				"type" => "string",
				"id" => "global_313",
				"default" => $lang["global_313"],
				"prompt" => RCView::lang_i("multilang_810", [$lang["global_313"]]), //= The '{0}' <i>Branching Logic/Calculations</i> error message:
			],
			"global_316" => [
				"category" => "ui-survey",
				"group" => "errors",
				"type" => "string",
				"id" => "global_316",
				"default" => $lang["global_316"],
				"prompt" => RCView::lang_i("multilang_810", [$lang["global_316"]]), //= The '{0}' <i>Branching Logic/Calculations</i> error message:
			],
			"global_315" => [
				"category" => "ui-survey",
				"group" => "errors",
				"type" => "string",
				"id" => "global_315",
				"default" => $lang["global_315"],
				"prompt" => RCView::lang_i("multilang_810", [$lang["global_315"]]), //= The '{0}' <i>Branching Logic/Calculations</i> error message:
			],
			"global_319" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "global_319",
				"default" => $lang["global_319"],
				"prompt" => RCView::lang_i("multilang_810", [$lang["global_319"]]), //= The '{0}' <i>Branching Logic/Calculations</i> error message:
			],
			"global_314" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "global_314",
				"default" => $lang["global_314"],
				"prompt" => RCView::lang_i("multilang_810", [$lang["global_314"]]), //= The '{0}' <i>Branching Logic/Calculations</i> error message:
			],
			"global_213" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "global_213",
				"default" => $lang["global_213"],
				"prompt" => RCView::lang_i("multilang_810", [$lang["global_213"]]), //= The '{0}' <i>Branching Logic/Calculations</i> error message:
			],
			"global_317" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "global_317",
				"default" => $lang["global_317"],
				"prompt" => RCView::lang_i("multilang_810", [$lang["global_317"]]), //= The '{0}' <i>Branching Logic/Calculations</i> error message:
			],
			"global_320" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "global_320",
				"default" => $lang["global_320"],
				"prompt" => RCView::lang_i("multilang_810", [$lang["global_320"]]), //= The '{0}' <i>Branching Logic/Calculations</i> error message:
			],
			"data_entry_731" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "data_entry_731",
				"default" => $lang["data_entry_731"],
				"prompt" => RCView::lang_i("multilang_811", [$lang["data_entry_731"]]), //= The '{0}' <i>Erase Values due to Branching Logic</i> dialog message:
			],
			"data_entry_728" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "data_entry_728",
				"default" => $lang["data_entry_728"],
				"prompt" => RCView::lang_i("multilang_811", [$lang["data_entry_728"]]), //= The '{0}' <i>Erase Values due to Branching Logic</i> dialog message:
			],
			"data_entry_729" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "data_entry_729",
				"default" => $lang["data_entry_729"],
				"prompt" => RCView::lang_i("multilang_811", [$lang["data_entry_729"]]), //= The '{0}' <i>Erase Values due to Branching Logic</i> dialog message:
			],
			"data_entry_730" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "data_entry_730",
				"default" => $lang["data_entry_730"],
				"prompt" => RCView::lang_i("multilang_811", [$lang["data_entry_730"]]), //= The '{0}' <i>Erase Values due to Branching Logic</i> dialog message:
			],
			"data_entry_734" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "data_entry_734",
				"default" => $lang["data_entry_734"],
				"prompt" => RCView::lang_i("multilang_811", [$lang["data_entry_734"]]), //= The '{0}' <i>Erase Values due to Branching Logic</i> dialog message:
			],
			"data_entry_732" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "data_entry_732",
				"default" => $lang["data_entry_732"],
				"prompt" => RCView::lang_i("multilang_811", [$lang["data_entry_732"]]), //= The '{0}' <i>Erase Values due to Branching Logic</i> dialog message:
			],
			"data_entry_733" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "data_entry_733",
				"default" => $lang["data_entry_733"],
				"prompt" => RCView::lang_i("multilang_811", [$lang["data_entry_733"]]), //= The '{0}' <i>Erase Values due to Branching Logic</i> dialog message:
			],
			"data_entry_735" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "data_entry_735",
				"default" => $lang["data_entry_735"],
				"prompt" => RCView::lang_i("multilang_812", [$lang["data_entry_735"]]), //= The '{0}' <i>Field hidden by Branching Logic</i> dialog message:
			],
			"data_entry_735" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "data_entry_735",
				"default" => $lang["data_entry_735"],
				"prompt" => RCView::lang_i("multilang_812", [$lang["data_entry_735"]]), //= The '{0}' <i>Field hidden by Branching Logic</i> dialog message:
			],
			"data_entry_736" => [
				"category" => "ui-dataentry",
				"group" => "errors",
				"type" => "string",
				"id" => "data_entry_736",
				"default" => $lang["data_entry_736"],
				"prompt" => RCView::lang_i("multilang_812", [$lang["data_entry_736"]]), //= The '{0}' <i>Field hidden by Branching Logic</i> dialog message:
			],
			"survey_1140" => [
				"category" => "ui-common",
				"group" => "misc",
				"type" => "string",
				"id" => "survey_1140",
				"default" => $lang["survey_1140"], //= image
				"prompt" => RCView::tt("multilang_339"), //= Generic image alt attribute:
			],
			"form_renderer_22" => [
				"category" => "ui-common",
				"group" => "misc",
				"type" => "string",
				"id" => "form_renderer_22",
				"default" => $lang["form_renderer_22"], //= Disclaimer
				"prompt" => RCView::lang_i("multilang_323", [$lang["form_renderer_22"]]), //= The '{0}' link label:
			],
			"global_125" => [
				"category" => "ui-common",
				"group" => "controls",
				"type" => "string",
				"id" => "global_125",
				"default" => $lang["global_125"], // Update
				"prompt" => RCView::lang_i("multilang_335", [$lang["global_125"]]), //= The '{0}' button label:
			],
			"data_entry_260" => [
				"category" => "ui-common",
				"group" => "controls",
				"type" => "string",
				"id" => "data_entry_260",
				"default" => $lang["data_entry_260"], // Type to begin searching
				"prompt" => RCView::lang_i("multilang_313", [$lang["data_entry_260"]]), //= The '{0}' prompt:
			],
			// Word and Char limits
			"data_entry_404" => [
				"category" => "ui-common",
				"group" => "controls",
				"type" => "string",
				"id" => "data_entry_404",
				"default" => $lang["data_entry_404"], // characters remaining
				"prompt" => RCView::lang_i("multilang_592", [$lang["data_entry_404"]]), //= The '{0}' message (this will be prepended by the actual count):
			],
			"data_entry_403" => [
				"category" => "ui-common",
				"group" => "controls",
				"type" => "string",
				"id" => "data_entry_403",
				"default" => $lang["data_entry_403"], // words remaining
				"prompt" => RCView::lang_i("multilang_592", [$lang["data_entry_403"]]), //= The '{0}' message (this will be prepended by the actual count):
			],
			// Checkbox fields
			"data_entry_518" => [
				"category" => "ui-fields",
				"group" => "field_checkbox",
				"type" => "string",
				"id" => "data_entry_518",
				"default" => $lang["data_entry_518"], //= The option {0} can only be selected by itself. Selecting this option will clear your previous selections for this checkbox field. Are you sure?
				"prompt" => RCView::tt("multilang_314"), //= The @NONEOFTHEABOVE alert message (use {0} as placeholder for the choice label):
			],
			"data_entry_412" => [
				"category" => "ui-fields",
				"group" => "field_checkbox",
				"type" => "string",
				"id" => "data_entry_412",
				"default" => $lang["data_entry_412"], //= Incompatible checkbox selection
				"prompt" => RCView::lang_i("multilang_329", [$lang["data_entry_412"]]), //= The '{0}' popup window title:
			],
			"data_entry_417" => [
				"category" => "ui-fields",
				"group" => "field_checkbox",
				"type" => "string",
				"id" => "data_entry_417",
				"default" => $lang["data_entry_417"], //= Yes, clear other selections
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_417"]]), //= The '{0}' button label:
			],
			// Yes/No fields
			"design_100" => [
				"category" => "ui-fields",
				"group" => "field_yesno",
				"type" => "string",
				"id" => "design_100",
				"default" => $lang["design_100"], //= Yes
				"prompt" => RCView::lang_i("multilang_316", [$lang["design_100"]]), //= Label for the '{0}' option:
			],
			"design_99" => [
				"category" => "ui-fields",
				"group" => "field_yesno",
				"type" => "string",
				"id" => "design_99",
				"default" => $lang["design_99"], //= No
				"prompt" => RCView::lang_i("multilang_316", [$lang["design_99"]]), //= Label for the '{0}' option:
				],
			// True/False fields
			"design_186" => [
				"category" => "ui-fields",
				"group" => "field_truefalse",
				"type" => "string",
				"id" => "design_186",
				"default" => $lang["design_186"], //= True
				"prompt" => RCView::lang_i("multilang_316", [$lang["design_186"]]), //= Label for the '{0}' option:
				],
			"design_187" => [
				"category" => "ui-fields",
				"group" => "field_truefalse",
				"type" => "string",
				"id" => "design_187",
				"default" => $lang["design_187"], //= False
				"prompt" => RCView::lang_i("multilang_316", [$lang["design_187"]]), //= Label for the '{0}' option:
				],
			// Dropdowns
			"data_entry_444" => [
				"category" => "ui-fields",
				"group" => "field_dropdown",
				"type" => "string",
				"id" => "data_entry_444",
				"default" => $lang["data_entry_444"], //= Click to view choices
				"prompt" => RCView::tt("multilang_319"), //= Dropdown arrow alt text:
			],
			// Sliders
			"design_722" => [
				"category" => "ui-fields",
				"group" => "field_slider",
				"type" => "string",
				"id" => "design_722",
				"default" => $lang["design_722"], //= Change the slider above to set a response
				"prompt" => RCView::tt("multilang_320"), //= Message displayed for unset sliders (desktop):
			],
			"design_721" => [
				"category" => "ui-fields",
				"group" => "field_slider",
				"type" => "string",
				"id" => "design_721",
				"default" => $lang["design_721"], //= Tap the slider above to set a response
				"prompt" => RCView::tt("multilang_322"), //= Message displayed for unset sliders (mobile):
			],
			"survey_1142" => [
				"category" => "ui-fields",
				"group" => "field_slider",
				"type" => "string",
				"id" => "survey_1142",
				"default" => $lang["survey_1142"], //= 0% means
				"prompt" => RCView::lang_i("multilang_324", [$lang["survey_1142"]]), //= The '{0}' slider voice explanation:
			],
			"survey_1143" => [
				"category" => "ui-fields",
				"group" => "field_slider",
				"type" => "string",
				"id" => "survey_1143",
				"default" => $lang["survey_1143"], // 50% means
				"prompt" => RCView::lang_i("multilang_324", [$lang["survey_1143"]]), //= The '{0}' slider voice explanation:
			],
			"survey_1144" => [
				"category" => "ui-fields",
				"group" => "field_slider",
				"type" => "string",
				"id" => "survey_1144",
				"default" => $lang["survey_1144"], // 100% means
				"prompt" => RCView::lang_i("multilang_324", [$lang["survey_1144"]]), //= The '{0}' slider voice explanation:
			],
			"data_entry_609" => [
				"category" => "ui-fields",
				"group" => "field_slider",
				"type" => "string",
				"id" => "data_entry_609",
				"default" => $lang["data_entry_609"], // (Place a mark on the scale above)
				"prompt" => RCView::tt("multilang_696"), //= The mark slider prompt shown on PDFs:
			],

			// Date fields
			"dashboard_32" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "dashboard_32",
				"default" => $lang["dashboard_32"], // Today
				"prompt" => RCView::lang_i("multilang_335", [$lang["dashboard_32"]]), //= The '{0}' button label:
			],
			"calendar_widget_choosedatehint" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_choosedatehint",
				"default" => $lang["calendar_widget_choosedatehint"], // Click to select a date
				"prompt" => RCView::lang_i("multilang_312", [$lang["calendar_widget_choosedatehint"]]), //= The '{0}' tooltip text:
			],
			"calendar_widget_done" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_done",
				"default" => $lang["calendar_widget_done"], // Done
				"prompt" => RCView::lang_i("multilang_335", [$lang["calendar_widget_done"]]), //= The '{0}' button label:
			],
			"calendar_widget_prev" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_prev",
				"default" => $lang["calendar_widget_prev"], // Prev
				"prompt" => RCView::lang_i("multilang_312", [$lang["calendar_widget_prev"]]), //= The '{0}' tooltip text:
			],
			"calendar_widget_next" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_next",
				"default" => $lang["calendar_widget_next"], // Next
				"prompt" => RCView::lang_i("multilang_312", [$lang["calendar_widget_next"]]), //= The '{0}' tooltip text:
			],
			"form_renderer_29" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "form_renderer_29",
				"default" => $lang["form_renderer_29"], // Now
				"prompt" => RCView::lang_i("multilang_335", [$lang["form_renderer_29"]]), //= The '{0}' button label:
			],
			"calendar_widget_choosedatetimehint" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_choosedatetimehint",
				"default" => $lang["calendar_widget_choosedatetimehint"], // Click to select a date/time
				"prompt" => RCView::lang_i("multilang_312", [$lang["calendar_widget_choosedatetimehint"]]), //= The '{0}' tooltip text:
			],
			"calendar_widget_choosetime" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_choosetime",
				"default" => $lang["calendar_widget_choosetime"], // Choose Time
				"prompt" => RCView::lang_i("multilang_329", [$lang["calendar_widget_choosetime"]]), //= The '{0}' popup window title:
			],
			"global_13" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "global_13",
				"default" => $lang["global_13"], // Time
				"prompt" => RCView::lang_i("multilang_325", [$lang["global_13"]]), //= The '{0}' label:
			],
			"calendar_widget_hour" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_hour",
				"default" => $lang["calendar_widget_hour"], // Hour
				"prompt" => RCView::lang_i("multilang_325", [$lang["calendar_widget_hour"]]), //= The '{0}' label:
			],
			"calendar_widget_min" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_min",
				"default" => $lang["calendar_widget_min"], // Minute
				"prompt" => RCView::lang_i("multilang_325", [$lang["calendar_widget_min"]]), //= The '{0}' label:
			],
			"calendar_widget_sec" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_sec",
				"default" => $lang["calendar_widget_sec"], // Second
				"prompt" => RCView::lang_i("multilang_325", [$lang["calendar_widget_sec"]]), //= The '{0}' label:
			],
			"calendar_widget_choosetimehint" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_choosetimehint",
				"default" => $lang["calendar_widget_choosetimehint"], // Click to select a time
				"prompt" => RCView::lang_i("multilang_312", [$lang["calendar_widget_choosetimehint"]]), //= The '{0}' tooltip text:
			],
			"calendar_widget_month_day_long_sun" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_long_sun",
				"default" => $lang["calendar_widget_month_day_long_sun"], // Sunday
				"prompt" => RCView::lang_i("multilang_326", [$lang["calendar_widget_month_day_long_sun"]]), //= The '{0}' full day name:
			],
			"calendar_widget_month_day_short_sun" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_short_sun",
				"default" => $lang["calendar_widget_month_day_short_sun"], // Su
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_day_long_sun"]]), //= The '{0}' abbreviated day name:
			],
			"calendar_widget_month_day_long_mon" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_long_mon",
				"default" => $lang["calendar_widget_month_day_long_mon"], // Monday
				"prompt" => RCView::lang_i("multilang_326", [$lang["calendar_widget_month_day_long_mon"]]), //= The '{0}' full day name:
			],
			"calendar_widget_month_day_short_mon" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_short_mon",
				"default" => $lang["calendar_widget_month_day_short_mon"], // Mo
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_day_long_mon"]]), //= The '{0}' abbreviated day name:
			],
			"calendar_widget_month_day_long_tues" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_long_tues",
				"default" => $lang["calendar_widget_month_day_long_tues"], // Tuesday
				"prompt" => RCView::lang_i("multilang_326", [$lang["calendar_widget_month_day_long_tues"]]), //= The '{0}' full day name:
			],
			"calendar_widget_month_day_short_tues" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_short_tues",
				"default" => $lang["calendar_widget_month_day_short_tues"], // Tu
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_day_long_tues"]]), //= The '{0}' abbreviated day name:
			],
			"calendar_widget_month_day_long_wed" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_long_wed",
				"default" => $lang["calendar_widget_month_day_long_wed"], // Wednesday
				"prompt" => RCView::lang_i("multilang_326", [$lang["calendar_widget_month_day_long_wed"]]), //= The '{0}' full day name:
			],
			"calendar_widget_month_day_short_wed" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_short_wed",
				"default" => $lang["calendar_widget_month_day_short_wed"], // We
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_day_long_wed"]]), //= The '{0}' abbreviated day name:
			],
			"calendar_widget_month_day_long_thurs" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_long_thurs",
				"default" => $lang["calendar_widget_month_day_long_thurs"], // Thursday
				"prompt" => RCView::lang_i("multilang_326", [$lang["calendar_widget_month_day_long_thurs"]]), //= The '{0}' full day name:
			],
			"calendar_widget_month_day_short_thurs" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_short_thurs",
				"default" => $lang["calendar_widget_month_day_short_thurs"], // Th
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_day_long_thurs"]]), //= The '{0}' abbreviated day name:
			],
			"calendar_widget_month_day_long_fri" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_long_fri",
				"default" => $lang["calendar_widget_month_day_long_fri"], // Friday
				"prompt" => RCView::lang_i("multilang_326", [$lang["calendar_widget_month_day_long_fri"]]), //= The '{0}' full day name:
			],
			"calendar_widget_month_day_short_fri" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_short_fri",
				"default" => $lang["calendar_widget_month_day_short_fri"], // Fr
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_day_long_fri"]]), //= The '{0}' abbreviated day name:
			],
			"calendar_widget_month_day_long_sat" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_long_sat",
				"default" => $lang["calendar_widget_month_day_long_sat"], // Saturday
				"prompt" => RCView::lang_i("multilang_326", [$lang["calendar_widget_month_day_long_sat"]]), //= The '{0}' full day name:
			],
			"calendar_widget_month_day_short_sat" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_day_short_sat",
				"default" => $lang["calendar_widget_month_day_short_sat"], // Sa
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_day_long_sat"]]), //= The '{0}' abbreviated day name:
			],
			"calendar_widget_month_jan" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_jan",
				"default" => $lang["calendar_widget_month_jan"], // Jan
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_jan"]]), //= The '{0}' abbreviated month name:
			],
			"calendar_widget_month_feb" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_feb",
				"default" => $lang["calendar_widget_month_feb"], // Feb
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_feb"]]), //= The '{0}' abbreviated month name:
			],
			"calendar_widget_month_mar" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_mar",
				"default" => $lang["calendar_widget_month_mar"], // Mar
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_mar"]]), //= The '{0}' abbreviated month name:
			],
			"calendar_widget_month_apr" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_apr",
				"default" => $lang["calendar_widget_month_apr"], // Apr
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_apr"]]), //= The '{0}' abbreviated month name:
			],
			"calendar_widget_month_may" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_may",
				"default" => $lang["calendar_widget_month_may"], // May
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_may"]]), //= The '{0}' abbreviated month name:
			],
			"calendar_widget_month_jun" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_jun",
				"default" => $lang["calendar_widget_month_jun"], // Jun
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_jun"]]), //= The '{0}' abbreviated month name:
			],
			"calendar_widget_month_jul" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_jul",
				"default" => $lang["calendar_widget_month_jul"], // Jul
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_jul"]]), //= The '{0}' abbreviated month name:
			],
			"calendar_widget_month_aug" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_aug",
				"default" => $lang["calendar_widget_month_aug"], // Aug
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_aug"]]), //= The '{0}' abbreviated month name:
			],
			"calendar_widget_month_sep" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_sep",
				"default" => $lang["calendar_widget_month_sep"], // Sep
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_sep"]]), //= The '{0}' abbreviated month name:
			],
			"calendar_widget_month_oct" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_oct",
				"default" => $lang["calendar_widget_month_oct"], // Oct
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_oct"]]), //= The '{0}' abbreviated month name:
			],
			"calendar_widget_month_nov" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_nov",
				"default" => $lang["calendar_widget_month_nov"], // Nov
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_nov"]]), //= The '{0}' abbreviated month name:
			],
			"calendar_widget_month_dec" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "calendar_widget_month_dec",
				"default" => $lang["calendar_widget_month_dec"], // Dec
				"prompt" => RCView::lang_i("multilang_327", [$lang["calendar_widget_month_dec"]]), //= The '{0}' abbreviated month name:
			],
			"multilang_108" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "multilang_108",
				"default" => $lang["multilang_108"], // H:M
				"prompt" => RCView::lang_i("multilang_330", [$lang["multilang_108"]]), //= The '{0}' date format indicator:
			],
			"multilang_109" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "multilang_109",
				"default" => $lang["multilang_109"], // Y-M-D
				"prompt" => RCView::lang_i("multilang_330", [$lang["multilang_109"]]), //= The '{0}' date format indicator:
				],
			"multilang_110" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "multilang_110",
				"default" => $lang["multilang_110"], // M-D-Y
				"prompt" => RCView::lang_i("multilang_330", [$lang["multilang_110"]]), //= The '{0}' date format indicator:
				],
			"multilang_111" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "multilang_111",
				"default" => $lang["multilang_111"], // D-M-Y
				"prompt" => RCView::lang_i("multilang_330", [$lang["multilang_111"]]), //= The '{0}' date format indicator:
				],
			"multilang_112" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "multilang_112",
				"default" => $lang["multilang_112"], // Y-M-D H:M
				"prompt" => RCView::lang_i("multilang_330", [$lang["multilang_112"]]), //= The '{0}' date format indicator:
				],
			"multilang_113" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "multilang_113",
				"default" => $lang["multilang_113"], // M-D-Y H:M
				"prompt" => RCView::lang_i("multilang_330", [$lang["multilang_113"]]), //= The '{0}' date format indicator:
				],
			"multilang_114" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "multilang_114",
				"default" => $lang["multilang_114"], // D-M-Y H:M
				"prompt" => RCView::lang_i("multilang_330", [$lang["multilang_114"]]), //= The '{0}' date format indicator:
				],
			"multilang_115" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "multilang_115",
				"default" => $lang["multilang_115"], // Y-M-D H:M:S
				"prompt" => RCView::lang_i("multilang_330", [$lang["multilang_115"]]), //= The '{0}' date format indicator:
				],
			"multilang_116" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "multilang_116",
				"default" => $lang["multilang_116"], // M-D-Y H:M:S
				"prompt" => RCView::lang_i("multilang_330", [$lang["multilang_116"]]), //= The '{0}' date format indicator:
				],
			"multilang_117" => [
				"category" => "ui-fields",
				"group" => "field_date",
				"type" => "string",
				"id" => "multilang_117",
				"default" => $lang["multilang_117"], // D-M-Y H:M:S
				"prompt" => RCView::lang_i("multilang_330", [$lang["multilang_117"]]), //= The '{0}' date format indicator:
				],
			// File upload fields
			"form_renderer_23" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_23",
				"default" => $lang["form_renderer_23"], // Upload file
				"prompt" => RCView::lang_i("multilang_331", [$lang["form_renderer_23"]]), //= The '{0}' link label and popup title:
				],
			"data_entry_459" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_entry_459",
				"default" => $lang["data_entry_459"], // Upload new version
				"prompt" => RCView::lang_i("multilang_331", [$lang["data_entry_459"]]), //= The '{0}' link label and popup title:
				],
			"form_renderer_24" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_24",
				"default" => $lang["form_renderer_24"], // Remove file
				"prompt" => RCView::lang_i("multilang_323", [$lang["form_renderer_24"]]), //= The '{0}' link label:
			],
			"form_renderer_25" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_25",
				"default" => $lang["form_renderer_25"], // Send-It
				"prompt" => RCView::lang_i("multilang_323", [$lang["form_renderer_25"]]), //= The '{0}' link label:
				],
			"data_entry_468" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_entry_468",
				"default" => $lang["data_entry_468"], // (replace and keep current file)
				"prompt" => RCView::lang_i("multilang_333", [$lang["data_entry_468"]]), //= The '{0}' hint:
				],
			"data_entry_62" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_entry_62",
				"default" => $lang["data_entry_62"], // Select a file then click the 'Upload File' button
				"prompt" => RCView::lang_i("multilang_313", [$lang["data_entry_62"]]), //= The '{0}' prompt:
			],
			"survey_1146" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "survey_1146",
				"default" => $lang["survey_1146"], // Click to upload a file in a modal dialog.
				"prompt" => RCView::lang_i("multilang_333", [$lang["survey_1146"]]), //= The '{0}' hint:
			],
			"form_renderer_47" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_47",
				"default" => $lang["form_renderer_47"], // You must first choose a file to upload
				"prompt" => RCView::lang_i("multilang_340", [$lang["form_renderer_47"]]), //= The '{0}' error message:
			],
			"form_renderer_52" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_52",
				"default" => $lang["form_renderer_52"], // Delete file?
				"prompt" => RCView::lang_i("multilang_329", [$lang["form_renderer_52"]]), //= The '{0}' popup window title:
				],
			"form_renderer_51" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_51",
				"default" => $lang["form_renderer_51"], // Are you sure you want to permanently remove this file?
				"prompt" => RCView::lang_i("multilang_341", [$lang["form_renderer_51"]]), //= The '{0}' dialog message:
				],
			"design_397" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "design_397",
				"default" => $lang["design_397"], // Yes, delete it
				"prompt" => RCView::lang_i("multilang_335", [$lang["design_397"]]), //= The '{0}' button label:
				],
			"form_renderer_59" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_59",
				"default" => $lang["form_renderer_59"], // File deleted
				"prompt" => RCView::lang_i("multilang_329", [$lang["form_renderer_59"]]), //= The '{0}' popup window title:
				],
			"form_renderer_50" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_50",
				"default" => $lang["form_renderer_50"], // Provide reason for deleting this file (reason will be logged):
				"prompt" => RCView::lang_i("multilang_313", [$lang["form_renderer_50"]]), //= The '{0}' prompt:
				],
			"data_entry_526" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_entry_526",
				"default" => $lang["data_entry_526"], // Max file size: {0} MB
				"prompt" => RCView::tt("multilang_342"), //= The maximum file size notice (use {0} as placeholder for the number of megabytes):
			],
			"data_entry_64" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_entry_64",
				"default" => $lang["data_entry_64"], // Loading...
				"prompt" => RCView::lang_i("multilang_336", [$lang["data_entry_64"]]), //= The '{0}' indicator:
				],
			"data_entry_65" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_entry_65",
				"default" => $lang["data_entry_65"], // Upload in progress...
				"prompt" => RCView::lang_i("multilang_343", [$lang["data_entry_65"]]), //= The '{0}' message:
			],
			"data_entry_455" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_entry_455",
				"default" => $lang["data_entry_455"], // Confirm file
				"prompt" => RCView::lang_i("multilang_329", [$lang["data_entry_455"]]), //= The '{0}' popup window title:
				],
			"data_entry_454" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_entry_454",
				"default" => $lang["data_entry_454"], // By clicking the Confirm button below, you are confirming that the following file is the correct file that you wish to upload here:
				"prompt" => RCView::lang_i("multilang_341", [$lang["data_entry_454"]]), //= The '{0}' dialog message:
				],
			"data_entry_453" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_entry_453",
				"default" => $lang["data_entry_453"], // File Upload: Confirmation & username/password verification
				"prompt" => RCView::lang_i("multilang_329", [$lang["data_entry_453"]]), //= The '{0}' popup window title:
			],
			"data_entry_460" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_entry_460",
				"default" => $lang["data_entry_460"], // By providing your REDCap password and clicking the Confirm button below, you are confirming that the following file is the correct file that you wish to upload here:
				"prompt" => RCView::lang_i("multilang_341", [$lang["data_entry_460"]]), //= The '{0}' dialog message:
			],
			"data_entry_461" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_entry_461",
				"default" => $lang["data_entry_461"], // Your file will be uploaded once you successfully initiate this confirmation process.
				"prompt" => RCView::lang_i("multilang_344", [$lang["data_entry_461"]]), //= The '{0}' notice:
			],
			"form_renderer_26" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_26",
				"default" => $lang["form_renderer_26"], // [The file upload feature is disabled]
				"prompt" => RCView::lang_i("multilang_344", [$lang["form_renderer_26"]]), //= The '{0}' notice:
			],
			"form_renderer_48" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_48",
				"default" => $lang["form_renderer_48"], // The username or password that you entered is incorrect! Please try again.
				"prompt" => RCView::lang_i("multilang_340", [$lang["form_renderer_48"]]), //= The '{0}' error message:
				],
			"form_renderer_49" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_49",
				"default" => $lang["form_renderer_49"], // You MUST provide a reason for deleting this file. Please try again.
				"prompt" => RCView::lang_i("multilang_340", [$lang["form_renderer_49"]]), //= The '{0}' error message:
				],
			"form_renderer_44" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_44",
				"default" => $lang["form_renderer_44"], // CANNOT UPLOAD FILE!
				"prompt" => RCView::lang_i("multilang_329", [$lang["form_renderer_44"]]), //= The '{0}' popup window title:
				],
			"form_renderer_45" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_45",
				"default" => $lang["form_renderer_45"], // We're sorry, but Apple does not support uploading files onto web pages in their Mobile Safari browser for iOS devices (iPhones, iPads, and iPod Touches) that are running iOS version 5.1 and below. Because it appears that you are using an iOS device on such an older version, you will not be able to upload a file here. This is not an issue in REDCap but is merely a limitation imposed by Apple. NOTE: iOS version 6 and above *does* support uploading of pictures and videos (but not other file types).
				"prompt" => RCView::tt("multilang_345"), //= Text of the iOS limitation notification:
			],
			"form_renderer_57" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_57",
				"default" => $lang["form_renderer_57"], // The file ""<b>{0}</b>"" has been deleted.
				"prompt" => RCView::tt("multilang_346"), //= The file deletion confirmation message (the placeholder {0} will contain the file name):
			],
			"form_renderer_58" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_58",
				"default" => $lang["form_renderer_58"], // Version {0} of the file has been deleted.
				"prompt" => RCView::tt("multilang_347"), //= The file version deletion confirmation message (use {0} as placeholder for the version number):
			],
			"form_renderer_60" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_60",
				"default" => $lang["form_renderer_60"], // File was successfully uploaded!
				"prompt" => RCView::lang_i("multilang_343", [$lang["form_renderer_60"]]), //= The '{0}' message:
			],
			"dataqueries_160" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "dataqueries_160",
				"default" => $lang["dataqueries_160"], // There was an error during file upload!
				"prompt" => RCView::lang_i("multilang_340", [$lang["dataqueries_160"]]), //= The '{0}' error message:
				],
			"data_export_tool_248" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "data_export_tool_248",
				"default" => $lang["data_export_tool_248"], // FILE:
				"prompt" => RCView::lang_i("multilang_348", [$lang["data_export_tool_248"]]), //= The '{0}' title (used in PDFs):
			],
			// Signature fields
			"survey_1147" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "survey_1147",
				"default" => $lang["survey_1147"], // Signature
				"prompt" => RCView::lang_i("multilang_325", [$lang["survey_1147"]]), //= The '{0}' label:
			],
			"form_renderer_31" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_31",
				"default" => $lang["form_renderer_31"], // Add signature
				"prompt" => RCView::lang_i("multilang_331", [$lang["form_renderer_31"]]), //= The '{0}' link label and popup title:
			],
			"form_renderer_43" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_43",
				"default" => $lang["form_renderer_43"], // Remove signature
				"prompt" => RCView::lang_i("multilang_323", [$lang["form_renderer_43"]]), //= The '{0}' link label:
				],
			"form_renderer_30" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_30",
				"default" => $lang["form_renderer_30"], // Save signature
				"prompt" => RCView::lang_i("multilang_335", [$lang["form_renderer_30"]]), //= The '{0}' button label:
				],
			"survey_1147" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "survey_1147",
				"default" => $lang["survey_1147"], // Signature
				"prompt" => RCView::tt("multilang_349"), //= Alt attribute of the signature image:
			],
			"form_renderer_46" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_46",
				"default" => $lang["form_renderer_46"], // You must first sign your signature
				"prompt" => RCView::lang_i("multilang_340", [$lang["form_renderer_46"]]), //= The '{0}' error message:
			],
			"form_renderer_63" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_63",
				"default" => $lang["form_renderer_63"], // Delete uploaded signature?
				"prompt" => RCView::lang_i("multilang_329", [$lang["form_renderer_63"]]), //= The '{0}' popup window title:
				],
			"form_renderer_64" => [
				"category" => "ui-fields",
				"group" => "field_upload",
				"type" => "string",
				"id" => "form_renderer_64",
				"default" => $lang["form_renderer_64"], // Are you sure you want to permanently remove the uploaded signature?
				"prompt" => RCView::lang_i("multilang_341", [$lang["form_renderer_64"]]), //= The '{0}' dialog message:
				],
			// Descriptive text fields
			"design_205" => [
				"category" => "ui-fields",
				"group" => "field_desc",
				"type" => "string",
				"id" => "design_205",
				"default" => $lang["design_205"], // Attachment:
				"prompt" => RCView::lang_i("multilang_325", [$lang["design_205"]]), //= The '{0}' label:
			],
			"global_290" => [
				"category" => "ui-fields",
				"group" => "field_desc",
				"type" => "string",
				"id" => "global_290",
				"default" => $lang["global_290"], // View media
				"prompt" => RCView::lang_i("multilang_335", [$lang["global_290"]]), //= The '{0}' button label:
			],
			"data_entry_610" => [
				"category" => "ui-fields",
				"group" => "field_desc",
				"type" => "string",
				"id" => "data_entry_610",
				"default" => $lang["data_entry_610"], // Media
				"prompt" => RCView::lang_i("multilang_329", [$lang["data_entry_610"]]), //= The '{0}' popup window title:
			],
			"design_204" => [
				"category" => "ui-fields",
				"group" => "field_desc",
				"type" => "string",
				"id" => "design_204",
				"default" => $lang["design_204"], // [Attachment not found]
				"prompt" => RCView::lang_i("multilang_337", [$lang["design_204"]]), //= The '{0}' notification:
			],
			"global_121" => [
				"category" => "ui-fields",
				"group" => "field_desc",
				"type" => "string",
				"id" => "global_121",
				"default" => $lang["global_121"], // [Your browser does not support the HTML5 Audio element.]
				"prompt" => RCView::lang_i("multilang_337", [$lang["global_121"]]), //= The '{0}' notification:
				],
			// Matrix fields
			"data_entry_204" => [
				"category" => "ui-fields",
				"group" => "field_matrix",
				"type" => "string",
				"id" => "data_entry_204",
				"default" => $lang["data_entry_204"], // (One selection allowed per column)
				"prompt" => RCView::lang_i("multilang_325", [$lang["data_entry_204"]]), //= The '{0}' label:
			],
			// reCAPTCHA
			"survey_1242" => [
				"category" => "ui-captcha",
				"group" => "captcha",
				"type" => "string",
				"id" => "survey_1242",
				"default" => $lang["survey_1242"], // To proceed to the survey, please check off the box and click the button below.
				"prompt" => RCView::tt("multilang_350"), //= The reCAPTCHA prompt:
			],
			"survey_1243" => [
				"category" => "ui-captcha",
				"group" => "captcha",
				"type" => "string",
				"id" => "survey_1243",
				"default" => $lang["survey_1243"], // Begin survey
				"prompt" => RCView::lang_i("multilang_789", [$lang["survey_1243"], $lang["multilang_190"]]), //= The '{0}' button label ({1:ContextInfo}):
			],
			"survey_1244" => [
				"category" => "ui-captcha",
				"group" => "captcha",
				"type" => "string",
				"id" => "survey_1244",
				"default" => $lang["survey_1244"], // Invalid reCAPTCHA response! Please try again.
				"prompt" => RCView::lang_i("multilang_340", [$lang["survey_1244"]]), //= The '{0}' error message:
			],

			// Protected Mail
			"email_users_36" => [
				"category" => "ui-protmail",
				"group" => "protmail_msg",
				"type" => "string",
				"id" => "email_users_36",
				"default" => $lang["email_users_36"], // REDCap Secure Messaging
				"prompt" => RCView::lang_i("multilang_357", [$lang["email_users_36"]]), //= The '{0}' default banner text:
			],
			"email_users_37" => [
				"category" => "ui-protmail",
				"group" => "protmail_msg",
				"type" => "string",
				"id" => "email_users_37",
				"default" => $lang["email_users_37"], // {0} has sent you a secure email message.
				"prompt" => RCView::tt("multilang_358"), //= The sender information text (use {0} as placeholder for display name and email address of the sender):
			],
			"email_users_38" => [
				"category" => "ui-protmail",
				"group" => "protmail_msg",
				"type" => "string",
				"id" => "email_users_38",
				"default" => $lang["email_users_38"], // Read the message.
				"prompt" => RCView::lang_i("multilang_335", [$lang["email_users_38"]]), //= The '{0}' button label:
			],
			"email_users_39" => [
				"category" => "ui-protmail",
				"group" => "protmail_msg",
				"type" => "string",
				"id" => "email_users_39",
				"default" => $lang["email_users_39"], // Learn about messages protected by REDCap.
				"prompt" => RCView::lang_i("multilang_323", [$lang["email_users_39"]]), //= The '{0}' link label:
			],
			"global_234" => [
				"category" => "ui-protmail",
				"group" => "protmail_codemsg",
				"type" => "string",
				"id" => "global_234",
				"default" => $lang["global_234"], // REDCap secure message verification
				"prompt" => RCView::tt("multilang_359"), //= The subject of the Protected Email verification email:
			],
			"email_users_99" => [
				"category" => "ui-protmail",
				"group" => "protmail_codemsg",
				"type" => "string",
				"id" => "email_users_99",
				"default" => $lang["email_users_99"], // Your REDCap Secure Messaging code is <b>{0}</b>
				"prompt" => RCView::tt("multilang_360"), //= The Protected Email code notice (use {0} as placeholder for the code):
			],
			"email_users_100" => [
				"category" => "ui-protmail",
				"group" => "protmail_codemsg",
				"type" => "string",
				"id" => "email_users_100",
				"default" => $lang["email_users_100"], // (This code will expire in {0} minutes)
				"prompt" => RCView::tt("multilang_361"), //= The Protected Email code expiration notice (use {0} as placeholder for the time in minutes):
			],
			"email_users_40" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_40",
				"default" => $lang["email_users_40"], // Quick security check before viewing your email
				"prompt" => RCView::tt("multilang_362"), //= Security check page title:
			],
			"email_users_47" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_47",
				"default" => $lang["email_users_47"], // Because you have not visited this page before or in a long time, we just sent you <u>a new email containing a security code</u> that will be used to verify your identify here. Please return to your inbox right now to retrieve that security code, and then enter it below. Thanks!
				"prompt" => RCView::tt("multilang_363"), //= Security check informational text:
			],
			"email_users_46" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_46",
				"default" => $lang["email_users_46"], // Because you have not visited this page before or in a long time, we need to send you <u>a new email containing a security code</u> that will be used to verify your identify here. <b>Please select your email address from the list of all recipients below, and click the 'Send code' button</b>, which will send you a new email with a code. Then return to your inbox to retrieve that security code, and enter it below.
				"prompt" => RCView::tt("multilang_364"), //= Security check informational text (multiple recipients):
			],
			"email_users_42" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_42",
				"default" => $lang["email_users_42"], // Send code to this address
				"prompt" => RCView::lang_i("multilang_335", [$lang["email_users_42"]]), //= The '{0}' button label:
			],
			"email_users_45" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_45",
				"default" => $lang["email_users_45"], // After receiving the code via email, enter it below.
				"prompt" => RCView::lang_i("multilang_333", [$lang["email_users_45"]]), //= The '{0}' hint:
			],
			"email_users_44" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_44",
				"default" => $lang["email_users_44"], // This is a public computer
				"prompt" => RCView::lang_i("multilang_325", [$lang["email_users_44"]]), //= The '{0}' label:
			],
			"email_users_82" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_82",
				"default" => $lang["email_users_82"], // (Unchecking this checkbox will store a cookie on this device.)
				"prompt" => RCView::lang_i("multilang_344", [$lang["email_users_82"]]), //= The '{0}' notice:
			],
			"email_users_101" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_101",
				"default" => $lang["email_users_101"], // Enter code
				"prompt" => RCView::lang_i("multilang_315", [$lang["email_users_101"]]), //= The '{0}' placeholder text:
			],
			"email_users_43" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_43",
				"default" => $lang["email_users_43"], // Submit code
				"prompt" => RCView::lang_i("multilang_335", [$lang["email_users_43"]]), //= The '{0}' button label:
			],
			"email_users_41" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_41",
				"default" => $lang["email_users_41"], // ERROR: Incorrect code was entered. Please try again!
				"prompt" => RCView::lang_i("multilang_340", [$lang["email_users_41"]]), //= The '{0}' error message:
			],
			"email_users_50" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_50",
				"default" => $lang["email_users_50"], // Check your inbox!
				"prompt" => RCView::lang_i("multilang_329", [$lang["email_users_50"]]), //= The '{0}' popup window title:
			],
			"email_users_35" => [
				"category" => "ui-protmail",
				"group" => "protmail_codecheck",
				"type" => "string",
				"id" => "email_users_35",
				"default" => $lang["email_users_35"], // The code was successfully emailed to {0}
				"prompt" => RCView::tt("multilang_365"), //= The dialog text confirming that the email has been sent (use {0} as placeholder for the email address):
			],
			"global_37" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "global_37",
				"default" => $lang["global_37"], // From:
				"prompt" => RCView::lang_i("multilang_325", [$lang["global_37"]]), //= The '{0}' label:
			],
			"global_38" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "global_38",
				"default" => $lang["global_38"], // To:
				"prompt" => RCView::lang_i("multilang_325", [$lang["global_38"]]), //= The '{0}' label:
			],
			"alerts_128" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "alerts_128",
				"default" => $lang["alerts_128"], // Attachments
				"prompt" => RCView::lang_i("multilang_325", [$lang["alerts_128"]]), //= The '{0}' label:
			],
			"email_users_48" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "email_users_48",
				"default" => $lang["email_users_48"], // {0} files
				"prompt" => RCView::tt("multilang_366"), //= The number of files indicator (plural; use {0} as placeholder for the number of files):
			],
			"email_users_49" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "email_users_49",
				"default" => $lang["email_users_49"], // {0} file
				"prompt" => RCView::tt("multilang_367"), //= The number of files indicator (singular; use {0} as placeholder for the number of files):
			],
			"ws_176" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "ws_176",
				"default" => $lang["ws_176"], // just now
				"prompt" => RCView::lang_i("multilang_368", [$lang["ws_176"]]), //= Email sent time label - '{0}':
				],
			"ws_177" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "ws_177",
				"default" => $lang["ws_177"], // less than a minute ago
				"prompt" => RCView::lang_i("multilang_368", [$lang["ws_177"]]), //= Email sent time label - '{0}':
			],
			"ws_178" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "ws_178",
				"default" => $lang["ws_178"], // {0} minute ago
				"prompt" => RCView::tt("multilang_369"), //= Email sent time - '{0} minute ago' (singular; use {0} as placeholder for the number of minutes):
			],
			"ws_179" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "ws_179",
				"default" => $lang["ws_179"], // {0} minutes ago
				"prompt" => RCView::tt("multilang_370"), //= Email sent time - '{0} minutes ago' (plural; use {0} as placeholder for the number of minutes):
			],
			"ws_180" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "ws_180",
				"default" => $lang["ws_180"], // {0} hour ago
				"prompt" => RCView::tt("multilang_371"), //= Email sent time - '{0} hour ago' (singular; use {0} as placeholder for the number of hours):
			],
			"ws_181" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "ws_181",
				"default" => $lang["ws_181"], // {0} hours ago
				"prompt" => RCView::tt("multilang_372"), //= Email sent time - '{0} hours ago' (plural; use {0} as placeholder for the number of hours):
			],
			"ws_182" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "ws_182",
				"default" => $lang["ws_182"], // {0} day ago
				"prompt" => RCView::tt("multilang_373"), //= Email sent time - '{0} day ago' (singular; use {0} as placeholder for the number of days):
			],
			"ws_183" => [
				"category" => "ui-protmail",
				"group" => "protmail_display",
				"type" => "string",
				"id" => "ws_183",
				"default" => $lang["ws_183"], // {0} days ago
				"prompt" => RCView::tt("multilang_374"), //= Email sent time - '{0} days ago' (plural; use {0} as placeholder for the number of days):
			],

			// Survey completion
			"dataqueries_278" => [
				"category" => "ui-survey",
				"group" => "completion",
				"type" => "string",
				"id" => "dataqueries_278",
				"default" => $lang["dataqueries_278"], // Close survey
				"prompt" => RCView::lang_i("multilang_335", [$lang["dataqueries_278"]]), //= The '{0}' button label:
			],
			"survey_111" => [
				"category" => "ui-survey",
				"group" => "completion",
				"type" => "string",
				"id" => "survey_111",
				"default" => $lang["survey_111"], // Thank you for your interest, but you have already completed this survey.
				"prompt" => RCView::tt("multilang_375"), //= The survey already completed notification message:
			],
			"survey_1101" => [
				"category" => "ui-survey",
				"group" => "completion",
				"type" => "string",
				"id" => "survey_1101",
				"default" => $lang["survey_1101"], // Thank you for your interest; however, the survey is closed because the maximum number of responses has been reached.
				"prompt" => RCView::tt("multilang_376"), //= The response limit reached notification message:
			],
			"survey_1105" => [
				"category" => "ui-survey",
				"group" => "completion",
				"type" => "string",
				"id" => "survey_1105",
				"default" => $lang["survey_1105"], // Thank you for your interest; however, the time limit for survey completion has been reached, so you will not be able to begin or continue the survey.
				"prompt" => RCView::tt("multilang_377"), //= The time limit reached notification message:
			],
			"survey_219" => [
				"category" => "ui-survey",
				"group" => "completion",
				"type" => "string",
				"id" => "survey_219",
				"default" => $lang["survey_219"], // Thank you for your interest; however, the time limit for survey completion has been reached, so you will not be able to begin or continue the survey.
				"prompt" => RCView::tt("multilang_710"), //= The default survey offline message:
			],
			"survey_1357" => [
				"category" => "ui-survey",
				"group" => "completion",
				"type" => "string",
				"id" => "survey_1357",
				"default" => $lang["survey_1357"], // {0} instance of this survey has been recorded
				"prompt" => RCView::tt("multilang_378"), //= The repeat instance recorded confirmation message (singular; use {0} as placeholder for the number of instances):
			],
			"survey_1356" => [
				"category" => "ui-survey",
				"group" => "completion",
				"type" => "string",
				"id" => "survey_1356",
				"default" => $lang["survey_1356"], // {0} instances of this survey have been recorded
				"prompt" => RCView::tt("multilang_379"), //= The repeat instances recorded confirmation message (plural; use {0} as placeholder for the number of instances):
			],
			"survey_1097" => [
				"category" => "ui-survey",
				"group" => "completion",
				"type" => "string",
				"id" => "survey_1097",
				"default" => $lang["survey_1097"], // Submit and
				"prompt" => RCView::tt("multilang_545"), //= Text for the 'Submit and' prompt (followed by the 'Take survey again' button):
			],
			// Login
			"global_239" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "global_239",
				"default" => $lang["global_239"], // Username:
				"prompt" => RCView::lang_i("multilang_313", [$lang["global_239"]]), //= The '{0}' prompt:
			],
			"global_240" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "global_240",
				"default" => $lang["global_240"], // Password:
				"prompt" => RCView::lang_i("multilang_313", [$lang["global_240"]]), //= The '{0}' prompt:
			],
			"survey_573" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_573",
				"default" => $lang["survey_573"], // Survey Login
				"prompt" => RCView::lang_i("multilang_329", [$lang["survey_573"]]), //= The '{0}' popup window title:
			],
			"survey_310" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_310",
				"default" => $lang["survey_310"], // Survey title:
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_310"]]), //= The '{0}' prompt:
			],
			"survey_574" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_574",
				"default" => $lang["survey_574"], // Before beginning or continuing this survey, you must first log in by successfully entering the correct values below.
				"prompt" => RCView::tt("multilang_380"), //= Introduction to the survey login instructions:
			],
			"survey_587" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_587",
				"default" => $lang["survey_587"], // You must successfully enter a value for the field below.
				"prompt" => RCView::tt("multilang_381"), //= Instructions stating that a value must be entered:
			],
			"survey_1345" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_1345",
				"default" => $lang["survey_1345"], // You must successfully enter at least {0} out of the {1} fields below.
				"prompt" => RCView::tt("multilang_382"), //= Instructions regarding the minimum nubmer of required fields (use {0} and {1} as placeholders):
			],
			"survey_578" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_578",
				"default" => $lang["survey_578"], // You must successfully enter a value for ALL the fields below.
				"prompt" => RCView::tt("multilang_383"), //= Instructions stating that all fields are required:
			],
			"survey_594" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_594",
				"default" => $lang["survey_594"], // Please note that the login is *not* case sensitive.
				"prompt" => RCView::tt("multilang_384"), //= Note that login is not case sensitive:
			],
			"survey_1066" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_1066",
				"default" => $lang["survey_1066"], // Show value
				"prompt" => RCView::lang_i("multilang_325", [$lang["survey_1066"]]), //= The '{0}' label:
			],
			"survey_1343" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_1343",
				"default" => $lang["survey_1343"], // <b>ERROR:</b> The login was not successful because the value entered was not correct. Try again.
				"prompt" => RCView::tt("multilang_385"), //= The error message displayed when the login fails (note singular):
			],
			"survey_1344" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_1344",
				"default" => $lang["survey_1344"], // <b>ERROR:</b> The login was not successful because the values entered were not correct. Try again.
				"prompt" => RCView::tt("multilang_386"), //= The error message displayed when the login fails (note plural):
			],
			"survey_589" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_589",
				"default" => $lang["survey_589"], // Unfortunately, it appears that no login fields can be displayed for you because no data has been entered for those login fields for you beforehand, so there is no way to log in. Please contact your survey administrator to have this issue corrected.
				"prompt" => RCView::tt("multilang_387"), //= The message displayed when there is no data for the login fields:
			],
			"global_05" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "global_05",
				"default" => $lang["global_05"], // ACCESS DENIED!
				"prompt" => RCView::lang_i("multilang_329", [$lang["global_05"]]), //= The '{0}' popup window title:
			],
			"survey_1353" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_1353",
				"default" => $lang["survey_1353"], // For security purposes, this survey has been temporarily disabled because it has exceeded the maximum amount of failed login attempts that are allowed within a set period of time (<b>{0} minutes</b>).
				"prompt" => RCView::tt("multilang_388"), //= The access denied informational text (use {0} as placeholder for the number of minutes):
			],
			"config_functions_45" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "config_functions_45",
				"default" => $lang["config_functions_45"], // Log In
				"prompt" => RCView::lang_i("multilang_335", [$lang["config_functions_45"]]), //= The '{0}' button label:
			],
			"survey_675" => [
				"category" => "ui-survey",
				"group" => "login",
				"type" => "string",
				"id" => "survey_675",
				"default" => $lang["survey_675"], // Survey login session is still active
				"prompt" => RCView::lang_i("multilang_337", [$lang["survey_675"]]), //= The '{0}' notification:
			],
			"data_entry_262" => [
				"category" => "ui-survey",
				"group" => "misc",
				"type" => "string",
				"id" => "data_entry_262",
				"default" => $lang["data_entry_262"], // Please note that although the value entered here is obfuscated and not readable, the value will be visible outside of this page to the individuals that are administering this survey.
				"prompt" => RCView::tt("multilang_389"), //= Password disclaimer text:
			],
			// PDF
			"data_export_tool_248" => [
				"category" => "ui-survey",
				"group" => "pdf",
				"type" => "string",
				"id" => "data_export_tool_248",
				"default" => $lang["data_export_tool_248"], // FILE:
				"prompt" => RCView::lang_i("multilang_336", [$lang["data_export_tool_248"]]), //= The '{0}' indicator:
				],
			"survey_1365" => [
				"category" => "ui-survey",
				"group" => "pdf",
				"type" => "string",
				"id" => "survey_1365",
				"default" => $lang["survey_1365"], // Page {0}
				"prompt" => RCView::tt("multilang_390"), //= The page number label (use {0} as placeholder for the page number):
			],
			"global_237" => [
				"category" => "ui-survey",
				"group" => "pdf",
				"type" => "string",
				"id" => "global_237",
				"default" => $lang["global_237"], // Confidential
				"prompt" => RCView::lang_i("multilang_344", [$lang["global_237"]]), //= The '{0}' notice:
			],
			"survey_1139" => [
				"category" => "ui-survey",
				"group" => "pdf",
				"type" => "string",
				"id" => "survey_1139",
				"default" => $lang["survey_1139"], // Download your survey response (PDF):
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_1139"]]), //= The '{0}' prompt:
			],
			"design_121" => [
				"category" => "ui-survey",
				"group" => "pdf",
				"type" => "string",
				"id" => "design_121",
				"default" => $lang["design_121"], // Download
				"prompt" => RCView::lang_i("multilang_335", [$lang["design_121"]]), //= The '{0}' button label:
			],
			"data_entry_535" => [
				"category" => "ui-survey",
				"group" => "pdf",
				"type" => "string",
				"id" => "data_entry_535",
				"default" => $lang["data_entry_535"], // Response was added on {0:Timestamp}.
				"prompt" => RCView::tt("multilang_391"), //= The response added statement (use {0} as placeholder for the timestamp):
			],
			"data_entry_101" => [
				"category" => "ui-survey",
				"group" => "pdf",
				"type" => "string",
				"id" => "data_entry_101",
				"default" => $lang["data_entry_101"], // Response is only partial and is not complete.
				"prompt" => RCView::tt("multilang_392"), //= The partial completion notice:
			],
			// eConsent
			"survey_1169" => [
				"category" => "ui-survey",
				"group" => "econsent",
				"type" => "string",
				"id" => "survey_1169",
				"default" => $lang["survey_1169"], // Displayed below is a read-only copy of your survey responses. Please review it and the options at the bottom.
				"prompt" => RCView::tt("multilang_393"), //= The intoductory text on the eConsent page:
			],
			"survey_1171" => [
				"category" => "ui-survey",
				"group" => "econsent",
				"type" => "string",
				"id" => "survey_1171",
				"default" => $lang["survey_1171"], // I certify that all of my information in the document above is correct. I understand that clicking 'Submit' will electronically sign the form and that signing this form electronically is the equivalent of signing a physical document.
				"prompt" => RCView::tt("multilang_394"), //= The label for the eConsent confirmation checkbox:
			],
			"survey_1170" => [
				"category" => "ui-survey",
				"group" => "econsent",
				"type" => "string",
				"id" => "survey_1170",
				"default" => $lang["survey_1170"], // If any information above is not correct, you may click the 'Previous Page' button to go back and correct it.
				"prompt" => RCView::tt("multilang_395"), //= The eConsent verification notice:
			],
			"data_entry_428" => [
				"category" => "ui-survey",
				"group" => "econsent",
				"type" => "string",
				"id" => "data_entry_428",
				"default" => $lang["data_entry_428"], // Version:
				"prompt" => RCView::tt("multilang_396"), //= The eConsent version title:
			],
			"survey_1264" => [
				"category" => "ui-survey",
				"group" => "econsent",
				"type" => "string",
				"id" => "survey_1264",
				"default" => $lang["survey_1264"], // Erase your signature(s) in this survey?
				"prompt" => RCView::lang_i("multilang_329", [$lang["survey_1264"]]), //= The '{0}' popup window title:
			],
			"survey_1265" => [
				"category" => "ui-survey",
				"group" => "econsent",
				"type" => "string",
				"id" => "survey_1265",
				"default" => $lang["survey_1265"], // You have provided your signature on an earlier page in this survey. This may include typing your name, a PIN, and/or signing your signature. You are allowed to return to a previous page in the survey, but if you do so, please be advised that your signature(s) will be automatically removed, after which you will need to provide it again before you can complete the survey. You will also be able to modify any of your existing responses to the questions in this survey. If this is okay, you may proceed to an earlier page in the survey by clicking the button below.
				"prompt" => RCView::tt("multilang_398"), //= The notification that the signature will be erased:
			],
			"survey_1266" => [
				"category" => "ui-survey",
				"group" => "econsent",
				"type" => "string",
				"id" => "survey_1266",
				"default" => $lang["survey_1266"], // Erase my signature(s) and go to earlier page
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_1266"]]), //= The '{0}' button label:
			],
			"survey_1364" => [
				"category" => "ui-survey",
				"group" => "econsent",
				"type" => "string",
				"id" => "survey_1364",
				"default" => $lang["survey_1364"], // View PDF
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_1364"]]), //= The '{0}' button label:
			],
			"survey_1341" => [
				"category" => "ui-survey",
				"group" => "econsent",
				"type" => "string",
				"id" => "survey_1341",
				"default" => $lang["survey_1341"], // Download PDF
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_1341"]]), //= The '{0}' button label:
			],
			"survey_1366" => [
				"category" => "ui-survey",
				"group" => "econsent",
				"type" => "string",
				"id" => "survey_1366",
				"default" => $lang["survey_1366"], // This browser does not support PDFs. Please download the PDF <b>in a new tab</b> to view it:
				"prompt" => RCView::tt("multilang_399"), //= The 'PDF not supported in browser' notification message:
			],
			"data_entry_606" => [
				"category" => "ui-survey",
				"group" => "econsent",
				"type" => "string",
				"id" => "data_entry_606",
				"default" => $lang["data_entry_606"], //= If you are having trouble viewing the PDF or if your browser does not support viewing multi-page PDFs, you may open the PDF in a new tab to view it:
				"prompt" => RCView::tt("multilang_692"), //= The 'View PDF' in a new tab prompt:
			],
			// Survey Stop
			"survey_1311" => [
				"category" => "ui-survey",
				"group" => "stop",
				"type" => "string",
				"id" => "survey_1311",
				"default" => $lang["survey_1311"], // End Survey
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_1311"]]), //= The '{0}' button label:
			],
			"survey_1312" => [
				"category" => "ui-survey",
				"group" => "stop",
				"type" => "string",
				"id" => "survey_1312",
				"default" => $lang["survey_1312"], // Return and Edit Response
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_1312"]]), //= The '{0}' button label:
			],
			"data_entry_199" => [
				"category" => "ui-survey",
				"group" => "stop",
				"type" => "string",
				"id" => "data_entry_199",
				"default" => $lang["data_entry_199"], // SAVE YOUR CHANGES?
				"prompt" => RCView::lang_i("multilang_329", [$lang["data_entry_199"]]), //= The '{0}' popup window title:
			],
			"data_entry_265" => [
				"category" => "ui-survey",
				"group" => "stop",
				"type" => "string",
				"id" => "data_entry_265",
				"default" => $lang["data_entry_265"], // Are you sure you wish to close the survey? Any responses you have added on this page will be lost if you close the survey now. IF YOU DO NOT WISH TO ABANDON YOUR CHANGES, click the 'Stay on page' button below and then click the button at the bottom of the page to save your changes.
				"prompt" => RCView::tt("multilang_400"), //= The save changes dialog text:
			],
			"survey_01" => [
				"category" => "ui-survey",
				"group" => "stop",
				"type" => "string",
				"id" => "survey_01",
				"default" => $lang["survey_01"], // End the survey?
				"prompt" => RCView::lang_i("multilang_329", [$lang["survey_01"]]), //= The '{0}' popup window title:
			],
			"survey_02" => [
				"category" => "ui-survey",
				"group" => "stop",
				"type" => "string",
				"id" => "survey_02",
				"default" => $lang["survey_02"], // You have selected an option that triggers this survey to end right now.
				"prompt" => RCView::tt("multilang_401"), //= The 'End the survey?' text:
			],
			"survey_1313" => [
				"category" => "ui-survey",
				"group" => "stop",
				"type" => "string",
				"id" => "survey_1313",
				"default" => $lang["survey_1313"], // To save your responses and end the survey, click the 'End Survey' button below. If you have selected the wrong option by accident and/or wish to return to the survey, click the 'Return and Edit Response' button.
				"prompt" => RCView::tt("multilang_402"), //= The 'End the survey?' instructions:
			],
			"survey_14" => [
				"category" => "ui-survey",
				"group" => "stop",
				"type" => "string",
				"id" => "survey_14",
				"default" => $lang["survey_14"], // Thank you for your interest, but you are not a participant for this survey.
				"prompt" => RCView::tt("multilang_403"), //= The 'Not a participant' message:
			],
			// Text-2-Speech
			"survey_997" => [
				"category" => "ui-survey",
				"group" => "speech",
				"type" => "string",
				"id" => "survey_997",
				"default" => $lang["survey_997"], // Enable speech
				"prompt" => RCView::lang_i("multilang_312", [$lang["survey_997"]]), //= The '{0}' tooltip text:
			],
			"survey_998" => [
				"category" => "ui-survey",
				"group" => "speech",
				"type" => "string",
				"id" => "survey_998",
				"default" => $lang["survey_998"], // Disable speech
				"prompt" => RCView::lang_i("multilang_312", [$lang["survey_998"]]), //= The '{0}' tooltip text:
			],
			// Partial Completion
			"survey_163" => [
				"category" => "ui-survey",
				"group" => "start",
				"type" => "string",
				"id" => "survey_163",
				"default" => $lang["survey_163"], // You have partially completed this survey.
				"prompt" => RCView::tt("multilang_404"), //= The title of the 'partial completion' notice:
			],
			"survey_162" => [
				"category" => "ui-survey",
				"group" => "start",
				"type" => "string",
				"id" => "survey_162",
				"default" => $lang["survey_162"], // You have not completed the entire survey, and your responses are thus considered only partially complete. For security reasons, you will not be allowed to continue taking the survey from the place where you stopped. So you have the option to 1) leave your survey responses unchanged as they are, or 2) you may start the survey over from the beginning so that you may complete it (this will delete all your previously existing responses when you begin again). To start the survey again, click the button below.
				"prompt" => RCView::tt("multilang_405"), //= The text of the 'partial completion' notice:
			],
			"control_center_422" => [
				"category" => "ui-survey",
				"group" => "start",
				"type" => "string",
				"id" => "control_center_422",
				"default" => $lang["control_center_422"], // Start Over
				"prompt" => RCView::lang_i("multilang_335", [$lang["control_center_422"]]), //= The '{0}' button label:
			],
			"survey_982" => [
				"category" => "ui-survey",
				"group" => "start",
				"type" => "string",
				"id" => "survey_982",
				"default" => $lang["survey_982"], // ERASE YOUR RESPONSES? Are you sure you wish to start the survey over from the beginning? Please note that doing so will erase ALL your responses already entered for this survey.
				"prompt" => RCView::tt("multilang_406"), //= The text of the 'start over' confirmation popup:
			],
			"survey_1131" => [
				"category" => "ui-survey",
				"group" => "start",
				"type" => "string",
				"id" => "survey_1131",
				"default" => $lang["survey_1131"], // This field cannot be edited because its value originates from the Participant List.
				"prompt" => RCView::tt("multilang_407"), //= The 'cannot edit' hint for email/phone fields:
			],
			// Survey Access Page
			"survey_619" => [
				"category" => "ui-survey",
				"group" => "start",
				"type" => "string",
				"id" => "survey_619",
				"default" => $lang["survey_619"], // Please enter your access code to begin the survey
				"prompt" => RCView::tt("multilang_408"), //= The 'survey access code' prompt:
			],
			"survey_642" => [
				"category" => "ui-survey",
				"group" => "start",
				"type" => "string",
				"id" => "survey_642",
				"default" => $lang["survey_642"], // Please note that the access code is *not* case sensitive.
				"prompt" => RCView::tt("multilang_409"), //= The 'survey access code not case sensitive' hint:
			],
			"survey_634" => [
				"category" => "ui-survey",
				"group" => "start",
				"type" => "string",
				"id" => "survey_634",
				"default" => $lang["survey_634"], // Please enter the survey access code given to you.
				"prompt" => RCView::tt("multilang_410"), //= The 'survey access code needed' alert message:
			],
			"survey_1359" => [
				"category" => "ui-survey",
				"group" => "start",
				"type" => "string",
				"id" => "survey_1359", // <b>ERROR:</b> The code entered was not valid! Please try again.
				"default" => $lang["survey_1359"],
				"prompt" => RCView::tt("multilang_411"), //= The 'invalid survey access code' error message:
			],

			// Save Survey Return Page
			"data_entry_215" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "data_entry_215",
				"default" => $lang["data_entry_215"], // Save & Return Later
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_215"]]), //= The '{0}' button label:
			],
			"survey_118" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_118",
				"default" => $lang["survey_118"], // Return Code
				"prompt" => RCView::lang_i("multilang_325", [$lang["survey_118"]]), //= The '{0}' label:
			],
			"survey_657" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_657",
				"default" => $lang["survey_657"], // Return Code:
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_118"]]), //= The '{0}' prompt:
			],
			"survey_658" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_658",
				"default" => $lang["survey_658"], // 'Return Code' needed to return
				"prompt" => RCView::lang_i("multilang_329", [$lang["survey_118"]]), //= The '{0}' popup window title:
			],
			"survey_659" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_659",
				"default" => $lang["survey_659"], // Copy or write down the Return Code below. Without it, you will not be able to return and continue this survey. Once you have the code, click <i>Close</i> and follow the other instructions on this page.
				"prompt" => RCView::lang_i("multilang_341", [$lang["survey_118"]]), //= The '{0}' dialog message:
			],
			"survey_112" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_112",
				"default" => $lang["survey_112"], // Your survey responses were saved!
				"prompt" => RCView::tt("multilang_412"), //= The title of the 'save and return later' page:
			],
			"survey_1346" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_1346",
				"default" => $lang["survey_1346"], // You have chosen to stop the survey for now and return at a later time to complete it. To return to this survey, you will need both the {0:SurveyLink} and your {1:ReturnCode}. See the instructions below.
				"prompt" => RCView::tt("multilang_413"), //= The 'save and return later' instructions:
			],
			"survey_119" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_119",
				"default" => $lang["survey_119"], // A return code is <b>*required*</b> in order to continue the survey where you left off. Please write down the value listed below.
				"prompt" => RCView::tt("multilang_414"), //= The 'Return Code' instructions:
			],
			"survey_581" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_581",
				"default" => $lang["survey_581"], // You have chosen to stop the survey for now and return at a later time to complete it. To return to this survey, you will need the survey link to this survey.
				"prompt" => RCView::tt("multilang_415"), //= The 'survey return link' instructions:
			],
			"survey_120" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_120",
				"default" => $lang["survey_120"], // The return code will NOT be included in the email below.
				"prompt" => RCView::tt("multilang_416"), //= The notice that the 'Return Code' is not included:
			],
			"data_entry_117" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "data_entry_117",
				"default" => $lang["data_entry_117"], // Return Code for participant to continue survey:
				"prompt" => RCView::tt("multilang_417"), //= The return code label on data entry forms:
			],
			"survey_121" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_121",
				"default" => $lang["survey_121"], // Survey link for returning
				"prompt" => RCView::tt("multilang_418"), //= The survey link title:
			],
			"survey_123" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_123",
				"default" => $lang["survey_123"], // You may bookmark this page to return to the survey, OR you can have the survey link emailed to you by providing your email address below. For security purposes, <b>the return code will NOT be included in the email</b>. If you do not receive the email soon afterward, please check your Junk Email folder.
				"prompt" => RCView::tt("multilang_419"), //= The survey link instructions:
			],
			"survey_124" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_124",
				"default" => $lang["survey_124"], // Send Survey Link
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_124"]]), //= The '{0}' button label:
			],
			"survey_126" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_126",
				"default" => $lang["survey_126"], // Or if you wish, you may continue with this survey again now.
				"prompt" => RCView::tt("multilang_420"), //= The text of the 'continue now' notification:
			],
			"survey_127" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_127",
				"default" => $lang["survey_127"], // Continue Survey Now
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_127"]]), //= The '{0}' button label:
			],
			"survey_1600" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_1600",
				"default" => $lang["survey_1600"], // Your email address will not be associated with or stored with your survey responses.
				"prompt" => RCView::tt("multilang_421"), //= The 'email will not be stored' notice:
			],
			"survey_583" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_583",
				"default" => $lang["survey_583"], // You may bookmark this page to return to the survey, OR you can have the survey link emailed to you by providing your email address below. If you do not receive the email soon afterward, please check your Junk Email folder.
				"prompt" => RCView::tt("multilang_422"), //= The 'bookmark this page' instructions:
			],
			"survey_1288" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_1288",
				"default" => $lang["survey_1288"], // Email sent!
				"prompt" => RCView::lang_i("multilang_329", [$lang["survey_1288"]]), //= The '{0}' popup window title:
			],
			"survey_1358" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_1358",
				"default" => $lang["survey_1358"], // The email was successfully sent to:
				"prompt" => RCView::lang_i("multilang_341", [$lang["survey_1358"]]), //= The '{0}' dialog message:
				],
			"survey_582" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_582",
				"default" => $lang["survey_582"], // You have just been sent an email containing a link for continuing the survey. If you do not receive the email soon, please check your Junk Email folder.
				"prompt" => RCView::tt("multilang_423"), //= The 'email just sent' notice:
			],
			"survey_122" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_122",
				"default" => $lang["survey_122"], // You have just been sent an email containing a link for continuing the survey. For security purposes, <b>the email does NOT contain the return code</b>, but the code is still required to continue the survey. If you do not receive the email soon, please check your Junk Email folder.
				"prompt" => RCView::tt("multilang_424"), //= The 'email (without return code) just sent' notice:
			],
			"survey_1354" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_1354",
				"default" => $lang["survey_1354"], // You may return to this survey in the future to modify your responses by navigating to the survey URL and entering your Survey Login credentials.
				"prompt" => RCView::tt("multilang_425"), //= The 'return with credentials' notice:
			],
			"survey_1355" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_1355",
				"default" => $lang["survey_1355"], // You may return to this survey in the future to modify your responses by navigating to the survey URL and entering the code below.
				"prompt" => RCView::tt("multilang_426"), //= The 'return with return code' notice:
			],
			// Survey Return Widget
			"survey_22" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_22",
				"default" => $lang["survey_22"], // Returning?
				"prompt" => RCView::lang_i("multilang_323", [$lang["survey_22"]]), //= The '{0}' link label:
				],
			"survey_1141" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_1141",
				"default" => $lang["survey_1141"], // Click here to return and continue the survey where you left off.
				"prompt" => RCView::tt("multilang_427"), //= The text for the alt attribute of the 'Returning?' link:
			],
			"survey_23" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_23",
				"default" => $lang["survey_23"], // Begin where you left off.
				"prompt" => RCView::lang_i("multilang_344", [$lang["survey_23"]]), //= The '{0}' notice:
			],
			"survey_24" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_24",
				"default" => $lang["survey_24"], // If you have already completed part of the survey, you may continue where you left off. All you need is the return code given to you previously. Click the link below to begin entering your return code and continue the survey.
				"prompt" => RCView::tt("multilang_428"), //= The 'returning to survey' instructions:
			],
			"survey_25" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_25",
				"default" => $lang["survey_25"], // Continue the survey
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_25"]]), //= The '{0}' button label:
				],
			// Survey Return Page
			"survey_661" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_661",
				"default" => $lang["survey_661"], // To continue the survey, please enter the RETURN CODE that was auto-generated for you when you left the survey.
				"prompt" => RCView::tt("multilang_429"), //= The 'survey continuation' instructions:
			],
			"survey_641" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_641",
				"default" => $lang["survey_641"], // Please note that the return code is *not* case sensitive.
				"prompt" => RCView::tt("multilang_430"), //= The 'Return Code not case sensitive' notice:
			],
			"survey_662" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_662",
				"default" => $lang["survey_662"], // Submit your Return Code
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_662"]]), //= The '{0}' button label:
				],
			"survey_110" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_110",
				"default" => $lang["survey_110"], // Alternatively, if you have forgotten your return code or simply wish to start the survey over from the beginning, you may delete all your existing survey responses and start over.
				"prompt" => RCView::tt("multilang_431"), //= The 'start over' instructions:
			],
			"survey_161" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_161",
				"default" => $lang["survey_161"], // The return code you entered was incorrect. Please try again.
				"prompt" => RCView::tt("multilang_432"), //= The 'invalid return code' error message:
			],
			"survey_663" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_663",
				"default" => $lang["survey_663"], // NOTE: Be sure that you are *not* entering a Survey Access Code, which is different from a Return Code. Survey Access Codes allow you to navigate to the survey page, but only Return Codes allow you to return when you have already started the survey.
				"prompt" => RCView::tt("multilang_433"), //= The 'Survey Access Code/Return Code mismatch' explanation:
			],
			"survey_674" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_674",
				"default" => $lang["survey_674"], // We're sorry, but this survey response cannot be modified because someone has locked the response to prevent any changes to it. It can only be unlocked by a survey administrator who has locking/unlocking privileges. If this seems incorrect, please contact your survey administrator for this survey.
				"prompt" => RCView::tt("multilang_434"), //= The message displayed when a survey has been locked:
			],
			"survey_1156" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_1156",
				"default" => $lang["survey_1156"], // You have partially completed this survey, but your response is LOCKED.
				"prompt" => RCView::tt("multilang_435"), //= The message displayed when a partial survey has been locked:
			],
			"survey_1155" => [
				"category" => "ui-survey",
				"group" => "save",
				"type" => "string",
				"id" => "survey_1155",
				"default" => $lang["survey_1155"], // You have not completed the entire survey, and your responses are thus considered only partially complete. However, this survey response has been locked by a survey administrator, so you will not be able to complete the survey until someone has unlocked it. If this seems incorrect, please contact your survey administrator for this survey.
				"prompt" => RCView::tt("multilang_436"), //= The explanation for a partial survey that has been locked:
			],
			// Partial email message
			"survey_144" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_144",
				"default" => $lang["survey_144"], // Survey partially completed
				"prompt" => RCView::tt("sendit_25"), //= Email subject:
			],
			"survey_141" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" =>  "survey_141",
				"default" =>  $lang["survey_141"], // [This message was automatically generated.]
				"prompt" => RCView::tt("multilang_437"), //= The 'automatically generated' notice:
			],
			"survey_1514" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_1514",
				"default" => $lang["survey_1514"], // Thank you for partially completing the survey.
				"prompt" => RCView::tt("multilang_713"), //= The 'thank you' message when there is no survey title:
			],
			"survey_1360" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_1360",
				"default" => $lang["survey_1360"], // Thank you for partially completing the survey '{0}'.
				"prompt" => RCView::tt("multilang_438"), //= The 'thank you' message (use {0} as placeholder for the survey title):
			],
			"survey_584" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_584",
				"default" => $lang["survey_584"], // You may continue your progress on this survey by clicking the link below.
				"prompt" => RCView::tt("multilang_439"), //= The 'survey progress' notice:
			],
			"survey_143" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_143",
				"default" => $lang["survey_143"], // ou may continue your progress on this survey by clicking the link below. You will need your return code that was given to you on the survey webpage.
				"prompt" => RCView::tt("multilang_440"), //= The 'return code needed' notice:
			],
			"survey_495" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_495",
				"default" => $lang["survey_495"], // If you need to retrieve your return code, please contact the survey administrator that originally sent you the survey invitation.
				"prompt" => RCView::tt("multilang_441"), //= The 'contact' notice:
			],
			"survey_135" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_135",
				"default" => $lang["survey_135"], // If the link above does not work, try copying the link below into your web browser:
				"prompt" => RCView::tt("multilang_442"), //= The 'copy link' notice:
			],
			// Send confirmation email?
			"survey_764" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_764",
				"default" => $lang["survey_764"], // Enter your email to receive confirmation message?
				"prompt" => RCView::tt("multilang_443"), //= The title for the confirmation email prompt:
			],
			"survey_765" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_765",
				"default" => $lang["survey_765"], // A confirmation email is supposed to be sent to all respondents that have completed the survey, but because your email address is not on file, the confirmation email cannot be sent automatically. If you wish to receive it, enter your email address below.
				"prompt" => RCView::tt("multilang_444"), //= The confirmation email prompt:
			],
			"survey_515" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_515",
				"default" => $lang["survey_515"], // Enter email address
				"prompt" => RCView::lang_i("multilang_315", [$lang["survey_515"]]), //= The '{0}' placeholder text:
			],
			"survey_766" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_766",
				"default" => $lang["survey_766"], // Send confirmation email
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_766"]]), //= The '{0}' button label:
			],
			"survey_181" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_181",
				"default" => $lang["survey_181"], // Email successfully sent!
				"prompt" => RCView::lang_i("multilang_343", [$lang["survey_181"]]), //= The '{0}' message:
			],
			"survey_732" => [
				"category" => "ui-survey",
				"group" => "email",
				"type" => "string",
				"id" => "survey_732",
				"default" => $lang["survey_732"], //= [Reminder]
				"prompt" => RCView::tt("multilang_628"), //= The notice prepended to the subject of reminder emails:
			],
			// SMS Text Message
			"survey_865" => [
				"category" => "ui-survey",
				"group" => "sms",
				"type" => "string",
				"id" => "survey_865",
				"default" => $lang["survey_865"], //= To begin the survey, reply to this message with any text.
				"prompt" => RCView::lang_i("multilang_450", [$lang["survey_865"]]), //= Translation of '{0}':
			],
			"survey_866" => [
				"category" => "ui-survey",
				"group" => "sms",
				"type" => "string",
				"id" => "survey_866",
				"default" => $lang["survey_866"], //= To begin the phone survey, reply to this message with any text to receive a phone call.
				"prompt" => RCView::lang_i("multilang_450", [$lang["survey_1583"]]), //= Translation of '{0}':
			],
			"survey_1583" => [
				"category" => "ui-survey",
				"group" => "sms",
				"type" => "string",
				"id" => "survey_1583",
				"default" => $lang["survey_1583"], //= To begin the phone survey, call {0:PhoneNumber}
				"prompt" => RCView::lang_i("multilang_712", [$lang["survey_1583"]]), //= Translation of '{0}' (insert the placeholder at the appropriate location in the translated text):
			],
			"survey_1584" => [
				"category" => "ui-survey",
				"group" => "sms",
				"type" => "string",
				"id" => "survey_1583",
				"default" => $lang["survey_1584"], //= To begin the survey, visit {0:SurveyLink}
				"prompt" => RCView::lang_i("multilang_712", [$lang["survey_1584"]]), //= Translation of '{0}' (insert the placeholder at the appropriate location in the translated text):
			],
			// PROMIS
			"survey_561" => [
				"category" => "ui-survey",
				"group" => "promis",
				"type" => "string",
				"id" => "survey_561",
				"default" => $lang["survey_561"], // Skip question
				"prompt" => RCView::lang_i("multilang_450", [$lang["survey_561"]]), //= Translation of '{0}':
			],
			"survey_562" => [
				"category" => "ui-survey",
				"group" => "promis",
				"type" => "string",
				"id" => "survey_562",
				"default" => $lang["survey_562"], // WARNING: Value not selected
				"prompt" => RCView::lang_i("multilang_450", [$lang["survey_562"]]), //= Translation of '{0}':
			],
			"survey_560" => [
				"category" => "ui-survey",
				"group" => "promis",
				"type" => "string",
				"id" => "survey_560",
				"default" => $lang["survey_560"], // Please select a value for the question displayed.
				"prompt" => RCView::lang_i("multilang_450", [$lang["survey_560"]]), //= Translation of '{0}':
			],
			"survey_563" => [
				"category" => "ui-survey",
				"group" => "promis",
				"type" => "string",
				"id" => "survey_563",
				"default" => $lang["survey_563"], // Go back and enter value
				"prompt" => RCView::lang_i("multilang_450", [$lang["survey_563"]]), //= Translation of '{0}':
			],
			"survey_559" => [
				"category" => "ui-survey",
				"group" => "promis",
				"type" => "string",
				"id" => "survey_559",
				"default" => $lang["survey_559"], // You must either 1) select a value for the question displayed, or 2) skip this question by clicking the 'Skip Question' button below.
				"prompt" => RCView::lang_i("multilang_450", [$lang["survey_559"]]), //= Translation of '{0}':
			],
			// Survey Results
			"survey_167" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_167",
				"default" => $lang["survey_167"], // View Survey Results
				"prompt" => RCView::tt("multilang_445"), //= The 'survey results' title:
			],
			"survey_169" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_169",
				"default" => $lang["survey_169"], // Displayed on this page are the aggregate results from this survey with each question listed in the order that it appears on the survey.
				"prompt" => RCView::tt("multilang_446"), //= The 'survey results' instructions:
			],
			"survey_209" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_209",
				"default" => $lang["survey_209"], // Graphs:
				"prompt" => RCView::tt("multilang_447"), //= The 'Graphs:' title:
			],
			"survey_1348" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_1348",
				"default" => $lang["survey_1348"], // All bar charts will have bars displayed in {0:blue}, and any of the choices that you selected in your survey response will have *asterisks* around that choice's label on the left of the bar chart. You will also see your response appended to the right end of the bar in {1:orange}.
				"prompt" => RCView::tt("multilang_448"), //= The barchart explanation (use {0} and {1} as placeholders for 'blue' and 'orange', see below):
			],
			"survey_1349" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_1349",
				"default" => $lang["survey_1349"], // For scatter plots, the values of other survey participants will be displayed in {0:blue}, the median value will be displayed in {1:red}, and the value you entered will be displayed in {2:orange}.
				"prompt" => RCView::tt("multilang_449"), //= The scatterplot explanation (use {0}, {1}, and {2} as placeholders for 'blue', 'red', and 'orange', see below):
			],
			"survey_171" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_171",
				"default" => $lang["survey_171"], // blue
				"prompt" => RCView::lang_i("multilang_450", [$lang["survey_171"]]), //= Translation of '{0}':
			],
			"survey_173" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_173",
				"default" => $lang["survey_173"], // orange
				"prompt" => RCView::lang_i("multilang_450", [$lang["survey_173"]]), //= Translation of '{0}':
			],
			"survey_176" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_176",
				"default" => $lang["survey_176"], // red
				"prompt" => RCView::lang_i("multilang_450", [$lang["survey_176"]]), //= Translation of '{0}':
			],
			"survey_210" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_210",
				"default" => $lang["survey_210"], // Statistics:
				"prompt" => RCView::tt("multilang_451"), //= The 'Statistics:' title:
			],
			"survey_211" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_211",
				"default" => $lang["survey_211"], // Descriptive statistics will be displayed below for each question. For categorical (multiple choice) questions, it will display a count of all missing and unique values, as well as the frequency of occurrence for each choice selected in the question. For numerical questions, it will display a count of all missing and unique values along with other useful statistics, such as the minimum, maximum, mean, and standard deviation across all responses. Also displayed are percentiles and a listing of the five lowest and highest values for that question.
				"prompt" => RCView::tt("multilang_452"), //= The descriptive statistics explanation:
			],
			"survey_178" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_178",
				"default" => $lang["survey_178"], // Email this page
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_178"]]), //= The '{0}' button label:
				],
			"survey_179" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_179",
				"default" => $lang["survey_179"], // Email address:
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_179"]]), //= The '{0}' prompt:
			],
			"survey_201" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_201",
				"default" => $lang["survey_201"], // <b>NOTE:</b> The email sent to the address above will <u>NOT</u> contain the survey results code, which must be written down and kept.
				"prompt" => RCView::tt("multilang_453"), //= The note indicating that the survey results code will not be included in the email message:
			],
			"survey_193" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_193",
				"default" => $lang["survey_193"], // Want to return to this page later?
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_193"]]), //= The '{0}' prompt:
			],
			"survey_194" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_194",
				"default" => $lang["survey_194"], // Write down your survey results code:
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_194"]]), //= The '{0}' prompt:
			],
			"survey_195" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_195",
				"default" => $lang["survey_195"], // You can ONLY return to this page again if you have your survey results code. Please write it down now.
				"prompt" => RCView::tt("multilang_454"), //= The 'results return' notice:
			],
			"survey_196" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_196",
				"default" => $lang["survey_196"], // <b>ERROR:</b><br>The survey results code you just entered does NOT match the one originally given to you.
				"prompt" => RCView::tt("multilang_455"), //= The invalid results code error message:
			],
			"locking_25" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "locking_25",
				"default" => $lang["locking_25"], // Please try again.
				"prompt" => RCView::lang_i("multilang_313", [$lang["locking_25"]]), //= The '{0}' prompt:
			],
			"survey_197" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_197",
				"default" => $lang["survey_197"], // Want to view the aggregate survey responses again?
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_197"]]), //= The '{0}' prompt:
			],
			"survey_198" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_198",
				"default" => $lang["survey_198"], // Enter your survey results code:
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_198"]]), //= The '{0}' prompt:
			],
			"survey_199" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_199",
				"default" => $lang["survey_199"], // If you enter the survey results code given to you right after you completed this survey, you will be able to view the aggregate survey results. If you do not have the survey results code, you will not be able to view the results.
				"prompt" => RCView::tt("multilang_456"), //= The return code notice:
			],
			"survey_168" => [
				"category" => "ui-survey",
				"group" => "results",
				"type" => "string",
				"id" => "survey_168",
				"default" => $lang["survey_168"], // Unfortunately, the survey results cannot be viewed at this time because the minimum number of completed responses for this survey has not yet been met. Please return again to this page in the near future to view the survey results. <b>IMPORTANT:</b> Make sure to write down your survey results code before leaving this page.
				"prompt" => RCView::tt("multilang_457"), //= The return later notice:
			],
			
			//
			// Survey Queue
			//
			"survey_505" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_505",
				"default" => $lang["survey_505"], // Survey Queue
				"prompt" => RCView::tt("multilang_458"), //= The 'Survey Queue' title:
			],
			"survey_509" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_509",
				"default" => $lang["survey_509"], // Close survey queue
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_509"]]), //= The '{0}' button label:
			],
			"dataqueries_23" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "dataqueries_23",
				"default" => $lang["dataqueries_23"], // Status
				"prompt" => RCView::lang_i("multilang_459", [$lang["dataqueries_23"]]), //= The '{0}' column title:
			],
			"survey_49" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_49",
				"default" => $lang["survey_49"], // Survey Title
				"prompt" => RCView::lang_i("multilang_459", [$lang["survey_49"]]), //= The '{0}' column title:
			],
			"survey_507" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_507",
				"default" => $lang["survey_507"], // Completed
				"prompt" => RCView::lang_i("multilang_336", [$lang["survey_507"]]), //= The '{0}' indicator:
			],
			"survey_511" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_511",
				"default" => $lang["survey_511"], // To begin the next survey, click the 'Begin survey' button next to the title.
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_511"]]), //= The '{0}' prompt:
			],
			"survey_504" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_504",
				"default" => $lang["survey_504"], // Begin survey
				"prompt" => RCView::lang_i("multilang_789", [$lang["survey_504"], $lang["survey_505"]]), //= The '{0}' button label ({1:ContextInfo}):
			],
			"data_entry_174" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "data_entry_174",
				"default" => $lang["data_entry_174"], // Edit response
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_174"]]), //= The '{0}' button label:
			],
			"survey_1090" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_1090",
				"default" => $lang["survey_1090"], // Take this survey again
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_1090"]]), //= The '{0}' button label:
			],
			"survey_506" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_506",
				"default" => $lang["survey_506"], // Listed below is your survey queue, which lists any other surveys that you have not yet completed.
				"prompt" => RCView::tt("multilang_460"), //= The 'incomplete surveys' prompt:
			],
			"survey_508" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_508",
				"default" => $lang["survey_508"], // Thank you for your interest, but this Survey Queue link is not valid.
				"prompt" => RCView::tt("multilang_461"), //= The 'invalid Survey Queue link' message:
			],
			"survey_1352" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_1352",
				"default" => $lang["survey_1352"], // {0} surveys completed!
				"prompt" => RCView::tt("multilang_462"), //= The message indicating how many surveys have been completed (use '{0}' as placeholder for the number):
			],
			"survey_536" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_536",
				"default" => $lang["survey_536"], // All surveys in your queue have been completed!
				"prompt" => RCView::tt("multilang_463"), //= The message indicating that all surveys have been completed:
			],
			"survey_535" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_535",
				"default" => $lang["survey_535"], // (view all)
				"prompt" => RCView::lang_i("multilang_323", [$lang["survey_535"]]), //= The '{0}' link label:
			],
			// Survey Queue link
			"survey_512" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_512",
				"default" => $lang["survey_512"], // Link to Survey Queue
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_512"]]), //= The '{0}' button label:
			],
			"survey_510" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_510",
				"default" => $lang["survey_510"], // Get link to my survey queue
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_510"]]), //= The '{0}' button label:
			],
			// Get Survey Queue link popup
			"survey_516" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_516",
				"default" => $lang["survey_516"], // To obtain your survey queue link, which will allow you to return to your survey queue in the future, you may copy and paste the link displayed in the text box below, or you may have it emailed to you at your email address.
				"prompt" => RCView::tt("multilang_464"), //= The survey queue link explanation:
			],
			"survey_513" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_513",
				"default" => $lang["survey_513"], // Copy and paste the survey queue link
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_513"]]), //= The '{0}' prompt:
				],
			"global_46" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "global_46",
				"default" => $lang["global_46"], // OR
				"prompt" => RCView::tt("multilang_465"), //= The '&ndash; OR &ndash;' divider (dashes will be added automatically):
			],
			"survey_514" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_514",
				"default" => $lang["survey_514"], // Send the survey queue link in an email
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_514"]]), //= The '{0}' prompt:
				],
			"survey_524" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_524",
				"default" => $lang["survey_524"], // EMAIL SENT!
				"prompt" => RCView::lang_i("multilang_329", [$lang["survey_524"]]), //= The '{0}' popup window title:
			],
			"survey_1351" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_1351",
				"default" => $lang["survey_1351"], // Your email was successfully sent to {0}.
				"prompt" => RCView::tt("multilang_466"), //= The 'email sent' popup message (use {0} as placeholder for the email address):
			],
			"survey_522" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_522",
				"default" => $lang["survey_522"], // Please enter an email address
				"prompt" => RCView::lang_i("multilang_313", [$lang["survey_522"]]), //= The '{0}' prompt:
			],
			"survey_180" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_180",
				"default" => $lang["survey_180"], // Send
				"prompt" => RCView::lang_i("multilang_335", [$lang["survey_180"]]), //= The '{0}' button label:
			],
			"survey_523" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_523",
				"default" => $lang["survey_523"], // Your survey queue link
				"prompt" => RCView::tt("multilang_467"), //= The 'survey queue link' email subject:
			],
			"survey_520" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "survey_520",
				"default" => $lang["survey_520"], // Please follow this link to your Survey Queue to complete your surveys: 
				"prompt" => RCView::tt("multilang_468"), //= The 'survey queue link' email body (this will be followed by the survey link):
			],
			"data_entry_522" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "data_entry_522",
				"default" => $lang["data_entry_522"], // Response was completed on {0}.
				"prompt" => RCView::tt("multilang_469"), //= The survey completed status message (use {0} as placeholder for the timestamp):
			],
			"data_entry_523" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "data_entry_523",
				"default" => $lang["data_entry_523"], //Response was completed on {0} by {1}.
				"prompt" => RCView::tt("multilang_470"), //= The survey completed status message (use {0} and {1} as placeholders for timestamp and identifier, respectively):
			],
			"data_entry_524" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "data_entry_524",
				"default" => $lang["data_entry_524"], // Response was added on {0}.
				"prompt" => RCView::tt("multilang_471"), //= The survey added status message (use {0} as placeholder for the timestamp):
			],
			"data_entry_525" => [
				"category" => "ui-survey",
				"group" => "queue",
				"type" => "string",
				"id" => "data_entry_525",
				"default" => $lang["data_entry_525"], //Response was added on {0} by {1}.
				"prompt" => RCView::tt("multilang_472"), //= The survey added status message (use {0} and {1} as placeholders for timestamp and identifier, respectively):
			],
			// Data Entry Form
			"data_entry_427" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_secondary",
				"type" => "string",
				"id" => "data_entry_427",
				"default" => $lang["data_entry_427"], // NOTE: Modifying this value will also change the value any corresponding instances of this field in other Events or Repeating Instances where this form is used.
				"prompt" => RCView::tt("multilang_473"), //= The secondary unique field note:
			],
			// Locking / Unlocking
			"form_renderer_18" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "form_renderer_18",
				"default" => $lang["form_renderer_18"], // Lock
				"prompt" => RCView::lang_i("multilang_323", [$lang["form_renderer_18"]]), //= The '{0}' link label:
			],
			"data_entry_493" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_493",
				"default" => $lang["data_entry_493"], // Lock this instrument?
				"prompt" => RCView::lang_i("multilang_313", [$lang["data_entry_493"]]), //= The '{0}' prompt:
			],
			"data_entry_494" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_494",
				"default" => $lang["data_entry_494"], // If locked, no user will be able to modify this instrument for this record until someone with Instrument Level Lock/Unlock privileges unlocks it.
				"prompt" => RCView::tt("multilang_474"), //= The 'instrument locked' explanation:
			],
			"data_entry_182" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_182",
				"default" => $lang["data_entry_182"], // Unlock form
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_182"]]), //= The '{0}' button label:
			],
			"form_renderer_42" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "form_renderer_42",
				"default" => $lang["form_renderer_42"], // <b>Locked by {0}</b> ({1}) on {2}
				"prompt" => RCView::tt("multilang_475"), //= The locking info statement (use {0}, {1}, and {2} as placeholders for userid, user full name, and timestamp, respectively):
			],
			"form_renderer_55" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "form_renderer_55",
				"default" => $lang["form_renderer_55"], // <b>Instrument locked by {0}</b> ({1}) on {2}
				"prompt" => RCView::tt("multilang_476"), //= The locking info header (use {0}, {1}, and {2} as placeholders for userid, user full name, and timestamp, respectively):
			],
			"form_renderer_56" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "form_renderer_56",
				"default" => $lang["form_renderer_56"], // The instrument '{0}' has been locked for record '{1}'.
				"prompt" => RCView::tt("multilang_477"), //= The locking info header explanation (use {0} and {1} as placeholders for instrument name and record id, respectively):
			],
			"form_renderer_40" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "form_renderer_40",
				"default" => $lang["form_renderer_40"], // If you have instrument-level locking/unlocking privileges, you may unlock this instrument at the bottom of the page.
				"prompt" => RCView::tt("multilang_478"), //= The locking header unlocking explanation:
			],
			"bottom_110" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "bottom_110",
				"default" => $lang["bottom_110"], // Lock entire record
				"prompt" => RCView::lang_i("multilang_323", [$lang["bottom_110"]]), //= The '{0}' link label:
			],
			"bottom_111" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "bottom_111",
				"default" => $lang["bottom_111"], // Unlock entire record
				"prompt" => RCView::lang_i("multilang_323", [$lang["bottom_111"]]), //= The '{0}' link label:
			],
			"data_entry_691" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_691",
				"default" => $lang["bottom_111"], // This form has now been unlocked.
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_691"]]), //= Locking/unlocking dialog text
			],
			"data_entry_692" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_692",
				"default" => $lang["data_entry_692"], // Also, the existing e-signature has been negated.
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_692"]]), //= Locking/unlocking dialog text
			],
			"data_entry_693" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_693",
				"default" => $lang["data_entry_693"], // Users can now modify the data again on this form.
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_693"]]), //= Locking/unlocking dialog text
			],
			"data_entry_694" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_694",
				"default" => $lang["data_entry_694"], // UNLOCK SUCCESSFUL!
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_694"]]), //= Locking/unlocking dialog text
			],
			"data_entry_695" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_695",
				"default" => $lang["data_entry_695"], // Are you sure you wish to unlock this form for this record?
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_695"]]), //= Locking/unlocking dialog text
			],
			"data_entry_696" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_696",
				"default" => $lang["data_entry_696"], // UNLOCK FORM?
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_696"]]), //= Locking/unlocking dialog text
			],
			"data_entry_697" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_697",
				"default" => $lang["data_entry_697"], // Unlock
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_697"]]), //= Locking/unlocking dialog text
			],
			"data_entry_698" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_698",
				"default" => $lang["data_entry_698"], // NOTICE: Unlocking this form will also negate the current e-signature.
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_698"]]), //= Locking/unlocking dialog text
			],
			"data_entry_699" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_699",
				"default" => $lang["data_entry_699"], // LOCK FORM?
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_699"]]), //= Locking/unlocking dialog text
			],
			"data_entry_700" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_700",
				"default" => $lang["data_entry_700"], // Are you sure you wish to lock this form for this record?
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_700"]]), //= Locking/unlocking dialog text
			],
			"data_entry_701" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_701",
				"default" => $lang["data_entry_701"], // The form has now been locked. The page will now reload to reflect this change.
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_701"]]), //= Locking/unlocking dialog text
			],
			"data_entry_702" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "data_entry_702",
				"default" => $lang["data_entry_702"], // LOCK SUCCESSFUL!
				"prompt" => RCView::lang_i("multilang_809", [$lang["data_entry_702"]]), //= Locking/unlocking dialog text
			],
			// E-Signature
			"form_renderer_62" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_locking",
				"type" => "string",
				"id" => "form_renderer_62",
				"default" => $lang["form_renderer_62"], // E-signed by {0} on {1}
				"prompt" => RCView::tt("multilang_479"), //= The E-Signature statement (use {0} and {1} as placeholders for the user id and the timestamp, respectively):
			],
			// PROMIS
			"data_entry_249" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_promis",
				"type" => "string",
				"id" => "data_entry_249",
				"default" => $lang["data_entry_249"], // Auto-scoring Instrument
				"prompt" => RCView::tt("multilang_480"), //= PROMIS auto-scoring title:
			],
			"data_entry_250" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_promis",
				"type" => "string",
				"id" => "data_entry_250",
				"default" => $lang["data_entry_250"], // This instrument is an auto-scoring instrument. It can only be taken in survey form, and all fields below are thus permanently locked and uneditabled.
				"prompt" => RCView::tt("multilang_481"), //= PROMIS auto-scoring locked message:
			],
			"data_entry_520" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_promis",
				"type" => "string",
				"id" => "data_entry_520",
				"default" => $lang["data_entry_520"], // Since this record has not been created yet, you will need to visit the {0} page to begin this auto-scoring instrument in survey mode.
				"prompt" => RCView::tt("multilang_482"), //= PROMIS auto-scoring create record message (the placeholder {0} will contain a link to the Survey Distribution Tools page):
			],
			"data_entry_217" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_promis",
				"type" => "string",
				"id" => "data_entry_217",
				"default" => $lang["data_entry_217"], // Adaptive Instrument
				"prompt" => RCView::tt("multilang_483"), //= PROMIS adaptive title:
			],
			"data_entry_218" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_promis",
				"type" => "string",
				"id" => "data_entry_218",
				"default" => $lang["data_entry_218"], // This instrument is a computer adaptive test (CAT), so its questions are generated dynamically based on answers given. Because it is dynamic, it can only be taken in survey form, and all fields below are thus permanently locked and uneditabled.
				"prompt" => RCView::tt("multilang_484"), //= PROMIS adaptive locked message:
			],
			"data_entry_521" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_promis",
				"type" => "string",
				"id" => "data_entry_521",
				"default" => $lang["data_entry_521"], // Since this record has not been created yet, you will need to visit the {0} page to begin this adaptive instrument in survey mode.
				"prompt" => RCView::tt("multilang_485"), //= PROMIS adaptive create record message (the placeholder {0} will contain a link to the Survey Distribution Tools page):
			],
			// Data Entry - Miscellaneous
			"form_renderer_21" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "form_renderer_21",
				"default" => $lang["form_renderer_21"], // View equation
				"prompt" => RCView::lang_i("multilang_323", [$lang["form_renderer_21"]]), //= The '{0}' link label:
			],
			"data_entry_263" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "data_entry_263",
				"default" => $lang["data_entry_263"], // Please note that although the value entered here is obfuscated and not readable on this page, the value will be visible outside of this page (e.g., in a report or data export) to the individuals that are administering this project.
				"prompt" => RCView::tt("multilang_486"), //= The password disclaimer text:
			],
			"survey_413" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_413",
				"default" => $lang["survey_413"], // Invitation status:
				"prompt" => RCView::lang_i("multilang_325", [$lang["survey_413"]]), //= The '{0}' label
			],
			"survey_1361" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_1361",
				"default" => $lang["survey_1361"], // Scheduled to send at {0}
				"prompt" => RCView::tt("multilang_487"), //= The 'invitation to be sent' tooltip (use {0} as placeholder for the timestamp):
			],
			"survey_1362" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_1362",
				"default" => $lang["survey_1362"], // Was sent at {0}
				"prompt" => RCView::tt("multilang_488"), //= The 'invitation sent' tooltip (use {0} as placeholder for the timestamp):
			],
			"survey_1363" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_1363",
				"default" => $lang["survey_1363"], // Invitation failed to send (originally scheduled for {0})
				"prompt" => RCView::tt("multilang_489"), //= The 'invitation failed to send' tooltip (use {0} as placeholder for the timestamp):
			],
			"survey_649" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_649",
				"default" => $lang["survey_649"], // Survey options
				"prompt" => RCView::lang_i("multilang_490", [$lang["survey_649"]]), //= The '{0}' menu label:
			],
			"survey_220" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_220",
				"default" => $lang["survey_220"], // Open survey
				"prompt" => RCView::lang_i("multilang_491", [$lang["survey_220"]]), //= The '{0}' menu item label:
			],
			"bottom_02" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "bottom_02",
				"default" => $lang["bottom_02"], // Log out
				"prompt" => RCView::lang_i("multilang_491", [$lang["bottom_02"]]), //= The '{0}' menu item label:
			],
			"survey_278" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_278",
				"default" => $lang["survey_278"], // Compose survey invitation
				"prompt" => RCView::lang_i("multilang_491", [$lang["survey_278"]]), //= The '{0}' menu item label:
			],
			"survey_628" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_628",
				"default" => $lang["survey_628"], // Survey Access Code
				"prompt" => RCView::lang_i("multilang_491", [$lang["survey_628"]]), //= The '{0}' menu item label:
			],
			"survey_664" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_664",
				"default" => $lang["survey_664"], // QR Code
				"prompt" => RCView::lang_i("multilang_491", [$lang["survey_664"]]), //= The '{0}' menu item label:
			],
			"data_entry_181" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "data_entry_181",
				"default" => $lang["data_entry_181"], // View data history
				"prompt" => RCView::lang_i("multilang_312", [$lang["data_entry_181"]]), //= The '{0}' tooltip text:
			],
			"dataqueries_145" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "dataqueries_145",
				"default" => $lang["dataqueries_145"], // View comment log
				"prompt" => RCView::lang_i("multilang_312", [$lang["dataqueries_145"]]), //= The '{0}' tooltip text:
			],
			"survey_412" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_412",
				"default" => $lang["survey_412"], // No invites sent yet
				"prompt" => RCView::lang_i("multilang_312", [$lang["survey_412"]]), //= The '{0}' tooltip text:
			],
			"data_entry_503" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "data_entry_503",
				"default" => $lang["data_entry_503"], // To rename the record, see the record action drop-down at top of the {0}.
				"prompt" => RCView::tt("multilang_492"), //= The record renaming hint (use {0} as a placeholder for the Record Home Page link):
			],
			"grid_42" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "grid_42",
				"default" => $lang["grid_42"], // Record Home Page
				"prompt" => RCView::lang_i("multilang_323", [$lang["grid_42"]]), //= The '{0}' link label:
				],
			"data_entry_148" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "data_entry_148",
				"default" => $lang["data_entry_148"], // Survey response is editable
				"prompt" => RCView::lang_i("multilang_337", [$lang["data_entry_148"]]), //= The '{0}' notification:
				],
			"data_entry_149" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "data_entry_149",
				"default" => $lang["data_entry_149"], // You have permission to edit this survey response from its original values.
				"prompt" => RCView::lang_i("multilang_337", [$lang["data_entry_149"]]), //= The '{0}' notification:
				],
			"data_entry_150" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "data_entry_150",
				"default" => $lang["data_entry_150"], // (now editing)
				"prompt" => RCView::lang_i("multilang_337", [$lang["data_entry_150"]]), //= The '{0}' notification:
				],
			"survey_1350" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_1350",
				"default" => $lang["survey_1350"], // View all contributors to this response.
				"prompt" => RCView::lang_i("multilang_323", [$lang["survey_1350"]]), //= The '{0}' link label:
			],
			"grid_54" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "grid_54",
				"default" => $lang["grid_54"], // Record {0} belongs to another Data Access Group
				"prompt" => RCView::tt("multilang_493"), //= The record / data access group mismatch alert (use {0} as a placeholder for the record id):
			],
			"grid_14" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "grid_14",
				"default" => $lang["grid_14"], // You cannot access this project record because it is already being used by another Data Access Group within this project. You will have to use a different record if you wish to create a new project record.
				"prompt" => RCView::tt("multilang_494"), //= The record / data access group mismatch explanation:
			],
			"grid_15" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "grid_15",
				"default" => $lang["grid_15"], // Go back to the previous page
				"prompt" => RCView::lang_i("multilang_323", [$lang["grid_15"]]), //= The '{0}' link label:
			],
			"grid_55" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "grid_55",
				"default" => $lang["grid_55"], // <b>Record ""{0}"" is a new {1}.</b> To create the record and begin entering data for it, click any gray status icon below.
				"prompt" => RCView::tt("multilang_495"), //= The 'new record' hint (use {0} and {1} as placeholders for the record id and the record id label, respectively):
			],
			"data_entry_151" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "data_entry_151",
				"default" => $lang["data_entry_151"], // In order to begin editing the response, you must click the Edit Response button above.
				"prompt" => RCView::tt("multilang_496"), //= The 'edit survey response' hint:
			],
			"data_entry_572" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "data_entry_572",
				"default" => $lang["data_entry_572"], // Response was initially started on {0}.
				"prompt" => RCView::tt("multilang_555"), //= Displayed for response 'start time' at top of data entry form:
			],
			"data_entry_573" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "data_entry_573",
				"default" => $lang["data_entry_573"], // (It is unknown when the response was initially started.)
				"prompt" => RCView::tt("multilang_556"), //= Displayed for response 'start time' at top of data entry form:
			],
			"data_entry_574" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "data_entry_574",
				"default" => $lang["data_entry_574"], // The total completion time of the response is {0}.
				"prompt" => RCView::tt("multilang_557"), //= Displayed for response 'start time' at top of data entry form:
			],
			"control_center_4469" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "control_center_4469",
				"default" => $lang["control_center_4469"], // seconds
				"prompt" => RCView::tt("multilang_558"), //= Unit for durations of time (seconds):
			],
			"survey_428" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_428",
				"default" => $lang["survey_428"], // minutes
				"prompt" => RCView::tt("multilang_559"), //= Unit for durations of time (minutes):
			],
			"survey_427" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_427",
				"default" => $lang["survey_427"], // hours
				"prompt" => RCView::tt("multilang_560"), //= Unit for durations of time (hours):
			],
			"survey_426" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "survey_426",
				"default" => $lang["survey_426"], // days
				"prompt" => RCView::tt("multilang_561"), //= Unit for durations of time (days):
			],
			"data_entry_601" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_misc",
				"type" => "string",
				"id" => "data_entry_601",
				"default" => $lang["data_entry_601"], // Field should be hidden by branching logic but is shown because it has a value
				"prompt" => RCView::tt("multilang_649"), //= Tooltip for the field-should-be-hidden indicator:
			],


			// Data Entry - Data Collection Menu
			"bottom_47" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_collection_menu",
				"type" => "string",
				"id" => "bottom_47",
				"default" => $lang["bottom_47"], // Data Collection
				"prompt" => RCView::lang_i("multilang_490", [$lang["bottom_47"]]), //= The '{0}' menu label:
			],
			"app_24" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_collection_menu",
				"type" => "string",
				"id" => "app_24",
				"default" => $lang["app_24"], // Survey Distribution Tools
				"prompt" => RCView::lang_i("multilang_490", [$lang["app_24"]]), //= The '{0}' menu label:
			],
			"invite_participants_01" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_collection_menu",
				"type" => "string",
				"id" => "invite_participants_01",
				"default" => $lang["invite_participants_01"], // Get a public survey link or build a participant list for inviting respondents
				"prompt" => RCView::lang_i("multilang_497", [$lang["app_24"]]), //= The '{0}' subtext:
			],
			"global_91" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_collection_menu",
				"type" => "string",
				"id" => "global_91",
				"default" => $lang["global_91"], // Record Status Dashboard
				"prompt" => RCView::lang_i("multilang_490", [$lang["global_91"]]), //= The '{0}' menu label:
			],
			"bottom_60" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_collection_menu",
				"type" => "string",
				"id" => "bottom_60",
				"default" => $lang["bottom_60"], // View data collection status of all records
				"prompt" => RCView::lang_i("multilang_497", [$lang["global_91"]]), //= The '{0}' subtext:
			],
			"bottom_62" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_collection_menu",
				"type" => "string",
				"id" => "bottom_62",
				"default" => $lang["bottom_62"], // Add / Edit Records
				"prompt" => RCView::lang_i("multilang_490", [$lang["bottom_62"]]), //= The '{0}' menu label:
			],
			"bottom_64" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_collection_menu",
				"type" => "string",
				"id" => "bottom_64",
				"default" => $lang["bottom_64"], // Create new records or edit/view existing ones
				"prompt" => RCView::lang_i("multilang_497", [$lang["bottom_62"]]), //= The '{0}' subtext:
			],
			"bottom_72" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_collection_menu",
				"type" => "string",
				"id" => "bottom_72",
				"default" => $lang["bottom_72"], // View / Edit Records
				"prompt" => RCView::lang_i("multilang_490", [$lang["bottom_72"]]), //= The '{0}' menu label:
			],
			"bottom_73" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_collection_menu",
				"type" => "string",
				"id" => "bottom_73",
				"default" => $lang["bottom_73"], // View or edit existing records
				"prompt" => RCView::lang_i("multilang_497", [$lang["bottom_72"]]), //= The '{0}' subtext:
			],
			"bottom_63" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_collection_menu",
				"type" => "string",
				"id" => "bottom_63",
				"default" => $lang["bottom_63"], // Select other record
				"prompt" => RCView::lang_i("multilang_323", [$lang["bottom_63"]]), //= The '{0}' link label:
			],
			"global_238" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_collection_menu",
				"type" => "string",
				"id" => "global_238",
				"default" => $lang["global_238"], // Data Collection Instruments:
				"prompt" => RCView::lang_i("multilang_325", [$lang["global_238"]]), //= The '{0}' label:
			],
			"bottom_23" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "bottom_23",
				"default" => $lang["bottom_23"], // Event:
				"prompt" => RCView::lang_i("multilang_325", [$lang["bottom_23"]]), //= The '{0}' label:
			],

			// Data Entry Actions Menu
			"edit_project_29" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "edit_project_29",
				"default" => $lang["edit_project_29"], // Actions:
				"prompt" => RCView::lang_i("multilang_325", [$lang["edit_project_29"]]), //= The '{0}' label:
			],
			"data_entry_202" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_202",
				"default" => $lang["data_entry_202"], // Modify instrument
				"prompt" => RCView::lang_i("multilang_490", [$lang["data_entry_202"]]), //= The '{0}' menu label:
			],
			"data_export_tool_158" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_export_tool_158",
				"default" => $lang["data_export_tool_158"], // Download PDF of instrument(s)
				"prompt" => RCView::lang_i("multilang_490", [$lang["data_export_tool_158"]]), //= The '{0}' menu label:
			],
			"data_entry_542" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_542",
				"default" => $lang["data_entry_542"], // This data entry form (blank)
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_542"]]), //= The '{0}' menu item label:
			],
			"data_entry_543" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_543",
				"default" => $lang["data_entry_543"], // This survey (blank)
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_543"]]), //= The '{0}' menu item label:
			],
			"data_entry_544" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_544",
				"default" => $lang["data_entry_544"], // This data entry form with saved data
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_544"]]), //= The '{0}' menu item label:
			],
			"data_entry_545" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_545",
				"default" => $lang["data_entry_545"], // This survey with saved data
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_545"]]), //= The '{0}' menu item label:
			],
			"data_entry_546" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_546",
				"default" => $lang["data_entry_546"], // This data entry form with saved data (via browser's <i>Save as PDF</i>)
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_546"]]), //= The '{0}' menu item label:
			],
			"data_entry_599" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_599",
				"default" => $lang["data_entry_599"], // NOTICE: Because some data values on this page have been modified but not saved yet, the resulting PDF may contain both saved and yet-to-be-saved values. If this is understood, click OK to proceed.
				"prompt" => RCView::tt("multilang_705"), //= The warning message shown when trying to print a data entry form (Saves as PDF) with unsaved changes:
			],
			"data_entry_547" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_547",
				"default" => $lang["data_entry_547"], // This survey with saved data (via browser's <i>Save as PDF</i>)
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_547"]]), //= The '{0}' menu item label:
			],
			"data_entry_548" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_548",
				"default" => $lang["data_entry_548"], // This data entry form with saved data (compact)
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_548"]]), //= The '{0}' menu item label:
			],
			"data_entry_549" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_549",
				"default" => $lang["data_entry_549"], // This survey with saved data (compact)
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_549"]]), //= The '{0}' menu item label:
			],
			"data_entry_556" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_556",
				"default" => $lang["data_entry_556"], // This survey with saved data (for survey participant)
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_556"]]), //= The '{0}' menu item label:
			],
			"data_entry_550" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_550",
				"default" => $lang["data_entry_550"], // All data entry forms (blank)
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_550"]]), //= The '{0}' menu item label:
			],
			"data_entry_551" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_551",
				"default" => $lang["data_entry_551"], // All forms/surveys (blank)
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_551"]]), //= The '{0}' menu item label:
			],
			"data_entry_552" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_552",
				"default" => $lang["data_entry_552"], // All data entry forms with saved data
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_552"]]), //= The '{0}' menu item label:
			],
			"data_entry_553" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_553",
				"default" => $lang["data_entry_553"], // All forms/surveys with saved data
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_553"]]), //= The '{0}' menu item label:
			],
			"data_entry_554" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_554",
				"default" => $lang["data_entry_554"], // All data entry forms with saved data (compact)
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_554"]]), //= The '{0}' menu item label:
			],
			"data_entry_555" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_555",
				"default" => $lang["data_entry_555"], // All forms/surveys with saved data (compact)
				"prompt" => RCView::lang_i("multilang_491", [$lang["data_entry_555"]]), //= The '{0}' menu item label:
			],
			"data_entry_557" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_557",
				"default" => $lang["data_entry_557"], // <b>Video</b>: Basic data entry
				"prompt" => RCView::lang_i("multilang_323", [$lang["data_entry_557"]]), //= The '{0}' link label:
			],
			"data_entry_558" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_actions_menu",
				"type" => "string",
				"id" => "data_entry_558",
				"default" => $lang["data_entry_558"], // Overview of Basic Data Entry
				"prompt" => RCView::tt("multilang_498"), //= The title of the window showing the basic data entry video:
			],

			// Record Actions Menu
			"grid_51" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_record_actions_menu",
				"type" => "string",
				"id" => "grid_51",
				"default" => $lang["grid_51"], // Choose action for record
				"prompt" => RCView::lang_i("multilang_335", [$lang["grid_51"]]), //= The '{0}' button label:
			],
			"data_entry_315" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_record_actions_menu",
				"type" => "string",
				"id" => "data_entry_315",
				"default" => $lang["data_entry_315"], // Download ZIP file of all uploaded documents
				"prompt" => RCView::lang_i("multilang_490", [$lang["data_entry_315"]]), //= The '{0}' menu label:
			],
			"data_entry_313" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_record_actions_menu",
				"type" => "string",
				"id" => "data_entry_313",
				"default" => $lang["data_entry_313"], // Download PDF of record data for all instruments/events
				"prompt" => RCView::lang_i("multilang_490", [$lang["data_entry_313"]]), //= The '{0}' menu label:
			],
			"data_entry_314" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_record_actions_menu",
				"type" => "string",
				"id" => "data_entry_314",
				"default" => $lang["data_entry_314"], // Download ZIP file of all uploaded documents
				"prompt" => RCView::lang_i("multilang_490", [$lang["data_entry_314"]]), //= The '{0}' menu label:
			],
			"data_entry_425" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_record_actions_menu",
				"type" => "string",
				"id" => "data_entry_425",
				"default" => $lang["data_entry_425"], // (compact)
				"prompt" => RCView::tt("multilang_499"), //= The 'Compact PDF' suffix:
			],
			"data_entry_323" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_record_actions_menu",
				"type" => "string",
				"id" => "data_entry_323",
				"default" => $lang["data_entry_323"], // Assign to Data Access Group
				"prompt" => RCView::lang_i("multilang_490", [$lang["data_entry_323"]]), //= The '{0}' menu label:
			],
			"data_entry_564" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_record_actions_menu",
				"type" => "string",
				"id" => "data_entry_564",
				"default" => $lang["data_entry_564"], // Assign to Data Access Group (or unassign/reassign)
				"prompt" => RCView::lang_i("multilang_490", [$lang["data_entry_564"]]), //= The '{0}' menu label:
			],
			"data_entry_316" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_record_actions_menu",
				"type" => "string",
				"id" => "data_entry_316",
				"default" => $lang["data_entry_316"], // Rename record
				"prompt" => RCView::lang_i("multilang_490", [$lang["data_entry_316"]]), //= The '{0}' menu label:
			],

			// Save Buttons
			"data_entry_288" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_288",
				"default" => $lang["data_entry_288"], // Save & Exit Form
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_288"]]), //= The '{0}' button label:
			],
			"data_entry_289" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_289",
				"default" => $lang["data_entry_289"], // Save & ...
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_289"]]), //= The '{0}' button label:
			],
			"data_entry_292" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_292",
				"default" => $lang["data_entry_292"], // Save & Stay
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_292"]]), //= The '{0}' button label:
			],
			"data_entry_210" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_210",
				"default" => $lang["data_entry_210"], // Save & Go To Next Form
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_210"]]), //= The '{0}' button label:
			],
			"data_entry_275" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_275",
				"default" => $lang["data_entry_275"], // Save & Add New Instance
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_275"]]), //= The '{0}' button label:
			],
			"data_entry_276" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_276",
				"default" => $lang["data_entry_276"], // Save & Go To Next Instance
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_276"]]), //= The '{0}' button label:
			],
			"data_entry_409" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_409",
				"default" => $lang["data_entry_409"], // Save & Exit Record
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_409"]]), //= The '{0}' button label:
			],
			"data_entry_410" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_410",
				"default" => $lang["data_entry_410"], // Save & Go To Next Record
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_410"]]), //= The '{0}' button label:
			],
			"data_entry_212" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_212",
				"default" => $lang["data_entry_212"], // Save & Mark Survey as Complete
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_212"]]), //= The '{0}' button label:
			],

			"data_entry_287" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_287",
				"default" => $lang["data_entry_287"], // More save options
				"prompt" => RCView::tt("multilang_500"), //= The 'Save & ...' tooltip:
			],
			"data_entry_291" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_291",
				"default" => $lang["data_entry_291"], // Click the down arrow button to view more save options.
				"prompt" => RCView::tt("multilang_501"), //= The 'Save & ...' popup title:
			],
			"data_entry_290" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_save",
				"type" => "string",
				"id" => "data_entry_290",
				"default" => $lang["data_entry_290"], // The option chosen for this button will become its default for you next time in this project.
				"prompt" => RCView::tt("multilang_502"), //= The 'Save & ...' popup text:
			],
			
			// Deletion
			"data_entry_266" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_delete",
				"type" => "string",
				"id" => "data_entry_266",
				"default" => $lang["data_entry_266"], // Sorry, but this data entry form contains one or more fields used in randomization, and since the current record has already been randomized, you will NOT be allowed to delete all data for this form.
				"prompt" => RCView::tt("multilang_503"), //= The 'deletion due to randomization field not possible' alert:
			],
			"data_entry_243" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_delete",
				"type" => "string",
				"id" => "data_entry_243",
				"default" => $lang["data_entry_243"], // Are you sure you wish to PERMANENTLY delete this record's data on THIS INSTRUMENT ONLY for THIS EVENT ONLY?
				"prompt" => RCView::tt("multilang_504"), //= The 'delete instrument' (longitudinal) confirmation:
			],
			"data_entry_239" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_delete",
				"type" => "string",
				"id" => "data_entry_239",
				"default" => $lang["data_entry_239"], // Are you sure you wish to PERMANENTLY delete this record's data on THIS INSTRUMENT ONLY?
				"prompt" => RCView::tt("multilang_505"), //= The 'delete instrument' confirmation:
			],
			"data_entry_241" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_delete",
				"type" => "string",
				"id" => "data_entry_241",
				"default" => $lang["data_entry_241"], // Also, this survey response will be reverted back to Incomplete survey status as if the survey had never been taken.
				"prompt" => RCView::tt("multilang_506"), //= The 'reversion of survey status after deletion' notice:
			],
			"data_entry_559" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_delete",
				"type" => "string",
				"id" => "data_entry_559",
				"default" => $lang["data_entry_559"], // NOTE: This only applies to the current repeating instance of this form, which is Instance <b>{0}</b>.
				"prompt" => RCView::tt("multilang_507"), //= The 'deletion limited to single instance' notice:
			],
			"data_entry_190" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_delete",
				"type" => "string",
				"id" => "data_entry_190",
				"default" => $lang["data_entry_190"], // This process is permanent and CANNOT BE REVERSED.
				"prompt" => RCView::tt("multilang_508"), //= The 'action cannot be reversed' notice:
			],
			"data_entry_234" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_delete",
				"type" => "string",
				"id" => "data_entry_234",
				"default" => $lang["data_entry_234"], // Delete data for THIS FORM only
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_234"]]), //= The '{0}' button label:
			],
			"data_entry_560" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_delete",
				"type" => "string",
				"id" => "data_entry_560",
				"default" => $lang["data_entry_560"], // NOTE: To delete the entire record (all forms/events), see the record action drop-down at top of the {0:LinkToRecordHomePage}.
				"prompt" => RCView::tt("multilang_509"), //= The 'delete entire record' hint (use {0} as placeholder for the 'Record Home Page' link):
			],
			"data_entry_561" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_delete",
				"type" => "string",
				"id" => "data_entry_561",
				"default" => $lang["data_entry_561"], // Also, to delete all the data from THIS EVENT only, see the bottom row of the status table on the {0:LinkToRecordHomePage}.
				"prompt" => RCView::tt("multilang_510"), //= The 'delete entire event' hint (use {0} as placeholder for the 'Record Home Page' link):
			],
			"data_entry_235" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_delete",
				"type" => "string",
				"id" => "data_entry_235",
				"default" => $lang["data_entry_235"], // Delete data for THIS EVENT only
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_235"]]), //= The '{0}' button label:
			],
			// Change Reason Popup
			"data_entry_603" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_reason",
				"type" => "string",
				"id" => "data_entry_603",
				"default" => $lang["data_entry_603"], // Please supply reason for data changes
				"prompt" => RCView::lang_i("multilang_450", [$lang["data_entry_603"]]), //= Translation of '{0}':
			],
			"data_entry_68" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_reason",
				"type" => "string",
				"id" => "data_entry_68",
				"default" => $lang["data_entry_68"], // You must now supply the reason for the data changes being made on this page in the text box below.
				"prompt" => RCView::lang_i("multilang_450", [$lang["data_entry_68"]]), //= Translation of '{0}':
			],
			"data_entry_69" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_reason",
				"type" => "string",
				"id" => "data_entry_69",
				"default" => $lang["data_entry_69"], // Reason for changes:
				"prompt" => RCView::lang_i("multilang_450", [$lang["data_entry_69"]]), //= Translation of '{0}':
			],
			"data_entry_70" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_reason",
				"type" => "string",
				"id" => "data_entry_70",
				"default" => $lang["data_entry_70"], // You must enter a reason for the data changes.
				"prompt" => RCView::lang_i("multilang_450", [$lang["data_entry_70"]]), //= Translation of '{0}':
			],
			"report_builder_28" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_reason",
				"type" => "string",
				"id" => "report_builder_28",
				"default" => $lang["report_builder_28"], // Save Changes
				"prompt" => RCView::lang_i("multilang_450", [$lang["report_builder_28"]]), //= Translation of '{0}':
			],
			
			// Context Messages
			"data_entry_507" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_context_msg",
				"type" => "string",
				"id" => "data_entry_507",
				"default" => $lang["data_entry_507"], // Editing existing {0:RecordID-Label} <b>{1:RecordID}</b>.
				"prompt" => RCView::tt("multilang_511"), //= The 'Editing existing record' message (use {0} and {1} as placeholders for the record id label and record id, respectively):
			],
			"data_entry_508" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_context_msg",
				"type" => "string",
				"id" => "data_entry_508",
				"default" => $lang["data_entry_508"], // Adding new {0:RecordID-Label} <b>{1:RecordID}</b>.
				"prompt" => RCView::tt("multilang_512"), //= The 'Adding new record' message (use {0} and {1} as placeholders for the record id label and record id, respectively):
			],
			"data_entry_509" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_context_msg",
				"type" => "string",
				"id" => "data_entry_509",
				"default" => $lang["data_entry_509"], // {0:RecordID-Label} <b>{1:RecordID}</b> successfully edited.
				"prompt" => RCView::tt("multilang_513"), //= The 'Successfully edited record' message (use {0} and {1} as placeholders for the record id label and record id, respectively):
			],
			"data_entry_510" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_context_msg",
				"type" => "string",
				"id" => "data_entry_510",
				"default" => $lang["data_entry_510"], // {0:RecordID-Label} <b>{1:RecordID}</b> successfully added.
				"prompt" => RCView::tt("multilang_514"), //= The 'Successfully added record' message (use {0} and {1} as placeholders for the record id label and record id, respectively):
			],
			"data_entry_511" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_context_msg",
				"type" => "string",
				"id" => "data_entry_511",
				"default" => $lang["data_entry_511"], // {0:RecordID-Label} <b>{1:RecordID}</b> successfully deleted.
				"prompt" => RCView::tt("multilang_515"), //= The 'Successfully deleted record' message (use {0} and {1} as placeholders for the record id label and record id, respectively):
			],
			"data_entry_512" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_context_msg",
				"type" => "string",
				"id" => "data_entry_512",
				"default" => $lang["data_entry_512"], // {0:RecordID-Label} <b>{1:RecordID}</b> data entry cancelled - not saved.
				"prompt" => RCView::tt("multilang_516"), //= The 'Data entry cancelled' message (use {0} and {1} as placeholders for the record id label and record id, respectively):
			],
			"data_entry_513" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_context_msg",
				"type" => "string",
				"id" => "data_entry_513",
				"default" => $lang["data_entry_513"], // {0:RecordID-Label} <b>{1:RecordID}</b> was successfully renamed!
				"prompt" => RCView::tt("multilang_517"), //= The 'Successfully renamed record' message (use {0} and {1} as placeholders for the record id label and record id, respectively):
			],
			"data_entry_514" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_context_msg",
				"type" => "string",
				"id" => "data_entry_514",
				"default" => $lang["data_entry_514"], // {0:RecordID-Label} <b>{1:RecordID}</b> was successfully assigned to a Data Access Group!
				"prompt" => RCView::tt("multilang_518"), //= The 'Successfully assigned record to DAG' message (use {0} and {1} as placeholders for the record id label and record id, respectively):
			],
			"data_entry_515" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_context_msg",
				"type" => "string",
				"id" => "data_entry_515",
				"default" => $lang["data_entry_515"], // {0:RecordID-Label} <b>{1:RecordID}</b> was successfully unassigned from its Data Access Group!
				"prompt" => RCView::tt("multilang_519"), //= The 'Successfully unassigned record from DAG' message (use {0} and {1} as placeholders for the record id label and record id, respectively):
			],
			"data_entry_516" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_context_msg",
				"type" => "string",
				"id" => "data_entry_516",
				"default" => $lang["data_entry_516"], // {0:RecordID-Label} <b>{1:RecordID}</b> successfully deleted entire event of data.
				"prompt" => RCView::tt("multilang_520"), //= The 'Event successfully deleted' message (use {0} and {1} as placeholders for the record id label and record id, respectively):
			],
			"data_entry_517" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_context_msg",
				"type" => "string",
				"id" => "data_entry_517",
				"default" => $lang["data_entry_517"], // {0:RecordID-Label} <b>{1:RecordID}</b> {2:Instance} successfully edited<br/><b>but {3:RecordID-Label} could not be changed because it already exists</b>.
				"prompt" => RCView::tt("multilang_521"), //= The 'Could not change record id' message (use {0}, {1}, {2}, and {3} as placeholders for the record id label, record id, instance, and record id label, respectively):
			],

			// Missing Data Code Labels
			"missing_data_mdc_ni" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_ni",
				"default" => $lang["missing_data_mdc_ni"], // No information
				"prompt" => RCView::lang_i("multilang_522", ['NI']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_inv" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_inv",
				"default" => $lang["missing_data_mdc_inv"], // Invalid
				"prompt" => RCView::lang_i("multilang_522", ['INV']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_unk" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_unk",
				"default" => $lang["missing_data_mdc_unk"], // Unknown
				"prompt" => RCView::lang_i("multilang_522", ['UNK']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_nask" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_nask",
				"default" => $lang["missing_data_mdc_nask"], // Not asked
				"prompt" => RCView::lang_i("multilang_522", ['NASK']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_asku" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_asku",
				"default" => $lang["missing_data_mdc_asku"], // Asked but unknown
				"prompt" => RCView::lang_i("multilang_522", ['ASKU']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_nav" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_nav",
				"default" => $lang["missing_data_mdc_nav"], // Temporarily unavailable
				"prompt" => RCView::lang_i("multilang_522", ['NAV']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_msk" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_msk",
				"default" => $lang["missing_data_mdc_msk"], // Masked
				"prompt" => RCView::lang_i("multilang_522", ['MSK']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_na" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_na",
				"default" => $lang["missing_data_mdc_na"], // Not applicable
				"prompt" => RCView::lang_i("multilang_522", ['NA']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_navu" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_navu",
				"default" => $lang["missing_data_mdc_navu"], // Not available
				"prompt" => RCView::lang_i("multilang_522", ['NAVU']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_np" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_np",
				"default" => $lang["missing_data_mdc_np"], // Not present
				"prompt" => RCView::lang_i("multilang_522", ['NP']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_qs" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_qs",
				"default" => $lang["missing_data_mdc_qs"], // Sufficient quantity
				"prompt" => RCView::lang_i("multilang_522", ['QS']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_qi" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_qi",
				"default" => $lang["missing_data_mdc_qi"], // Insufficient quantity
				"prompt" => RCView::lang_i("multilang_522", ['QI']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_trc" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_trc",
				"default" => $lang["missing_data_mdc_trc"], // Trace
				"prompt" => RCView::lang_i("multilang_522", ['TRC']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_unc" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_unc",
				"default" => $lang["missing_data_mdc_unc"], // Unencoded
				"prompt" => RCView::lang_i("multilang_522", ['UNC']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_der" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_der",
				"default" => $lang["missing_data_mdc_der"], // Derived
				"prompt" => RCView::lang_i("multilang_522", ['DER']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_pinf" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_pinf",
				"default" => $lang["missing_data_mdc_pinf"], // Positive infinity
				"prompt" => RCView::lang_i("multilang_522", ['PINF']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_ninf" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_ninf",
				"default" => $lang["missing_data_mdc_ninf"], // Negative infinity
				"prompt" => RCView::lang_i("multilang_522", ['NINF']), //= The '{0}' default missing data code label:
			],
			"missing_data_mdc_oth" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_mdc_oth",
				"default" => $lang["missing_data_mdc_oth"], // Other
				"prompt" => RCView::lang_i("multilang_522", ['OTH']), //= The '{0}' default missing data code label:
			],
			"missing_data_04" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_04",
				"default" => $lang["missing_data_04"], // Missing Data Codes
				"prompt" => RCView::lang_i("multilang_325", [$lang["missing_data_04"]]), //= The '{0}' label:
			],
			"missing_data_03" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_03",
				"default" => $lang["missing_data_03"], // Mark field as missing
				"prompt" => RCView::lang_i("multilang_312", [$lang["missing_data_03"]]), //= The '{0}' tooltip text:
			],
			"missing_data_01" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_01",
				"default" => $lang["missing_data_01"], // Mark field as:
				"prompt" => RCView::lang_i("multilang_490", [$lang["missing_data_01"]]), //= The '{0}' menu label:
			],
			"missing_data_02" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_mdc",
				"type" => "string",
				"id" => "missing_data_02",
				"default" => $lang["missing_data_02"], // [Clear value]
				"prompt" => RCView::lang_i("multilang_491", [$lang["missing_data_02"]]), //= The '{0}' menu item label:
			],

			// Simultaneous Users Notification
			"data_entry_279" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_simultaneous",
				"type" => "string",
				"id" => "data_entry_279",
				"default" => $lang["data_entry_279"], // Simultaneous users - Data editing capabilities are disabled (read-only mode)
				"prompt" => RCView::tt("multilang_580"), //= Notification title:
			],
			"data_entry_527" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_simultaneous",
				"type" => "string",
				"id" => "data_entry_527",
				"default" => $lang["data_entry_527"], // Another user ({0}) is currently on this data collection instrument editing the same record ({1}).
				"prompt" => RCView::tt("multilang_581"), //= Concurrent user info (use {0} and {1} as placeholders for the user name and record name, respectively):
			],
			"data_entry_280" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_simultaneous",
				"type" => "string",
				"id" => "data_entry_280",
				"default" => $lang["data_entry_280"], // To prevent data entry conflicts, you will not be allowed to edit this record on this form until the other user has left the page. Until then, you may click the button below at any time to check if they have left, or you may edit other forms for this record and check back later.
				"prompt" => RCView::tt("multilang_582"), //= Explanatory text:
			],
			"data_entry_528" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_simultaneous",
				"type" => "string",
				"id" => "data_entry_528",
				"default" => $lang["data_entry_528"], // If this conflict is in error, and you have confirmed that the user listed above is no longer on this page for this record, then you will either have to wait for the logout time to expire ({0} minutes from the time of the other user's last activity on this page), or you can have that other user log back in to REDCap, which will immediately release this page and allow you to access it.
				"prompt" => RCView::tt("multilang_583"), //= Further explanatory text:
			],
			"data_entry_84" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_simultaneous",
				"type" => "string",
				"id" => "data_entry_84",
				"default" => $lang["data_entry_84"], // Check again
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_84"]]), //= The '{0}' button label:
			],
			"data_entry_85" => [
				"category" => "ui-dataentry",
				"group" => "dataentry_simultaneous",
				"type" => "string",
				"id" => "data_entry_85",
				"default" => $lang["data_entry_85"], // This conflict is in error
				"prompt" => RCView::lang_i("multilang_323", [$lang["data_entry_85"]]), //= The '{0}' link label:
			],

			// Validation
			"config_functions_52" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_52",
				"default" => $lang["config_functions_52"], // This value you provided is not a number. Please try again.
				"prompt" => RCView::tt("multilang_523"), //= The message of the <i>number</i> validation popup:
			],
			"config_functions_53" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_53",
				"default" => $lang["config_functions_53"], // This value you provided is not an integer. Please try again.
				"prompt" => RCView::tt("multilang_524"), //= The message of the <i>integer</i> validation popup:
			],
			"config_functions_54" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_54",
				"default" => $lang["config_functions_54"], // The value entered is not a valid Vanderbilt Medical Record Number (i.e. 4- to 9-digit number, excluding leading zeros). Please try again.
				"prompt" => RCView::tt("multilang_525"), //= The message of the <i>Vanderbilt MRN</i> validation popup:
			],
			"config_functions_55" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_55",
				"default" => $lang["config_functions_55"], // The value entered in this field must be a date. You may use one of several formats (ex. YYYY-MM-DD or MM/DD/YYYY), but the final result must constitute a real date. Please try again.
				"prompt" => RCView::tt("multilang_526"), //= The message of the <i>date</i> validation popup:
			],
			"config_functions_56" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_56",
				"default" => $lang["config_functions_56"], // The value you provided must be within the suggested range.
				"prompt" => RCView::tt("multilang_527"), //= The message of the <i>hard range</i> validation popup:
			],
			"config_functions_57" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_57",
				"default" => $lang["config_functions_57"], // The value you provided is outside the suggested range.
				"prompt" => RCView::tt("multilang_528"), //= The message of the <i>soft range (1)</i> validation popup:
			],
			"config_functions_58" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_58",
				"default" => $lang["config_functions_58"], // This value is admissible, but you may wish to double check it.
				"prompt" => RCView::tt("multilang_529"), //= The message of the <i>soft range (2)</i> validation popup:
			],
			"config_functions_59" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_59",
				"default" => $lang["config_functions_59"], // The value entered must be a time value in the following format HH:MM within the range 00:00-23:59 (e.g., 04:32 or 23:19).
				"prompt" => RCView::tt("multilang_530"), //= The message of the <i>time</i> validation popup:
			],
			"config_functions_60" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_60",
				"default" => $lang["config_functions_60"], // This field must be a 5 or 9 digit U.S. ZIP Code (like 94043). Please re-enter it now.
				"prompt" => RCView::tt("multilang_531"), //= The message of the <i>ZIP code</i> validation popup:
			],
			"config_functions_130" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_130",
				"default" => $lang["config_functions_130"], // This field must be a 10 digit U.S. phone number (like 415 555 1212). Please re-enter it now.
				"prompt" => RCView::tt("multilang_532"), //= The message of the <i>phone</i> validation popup:
			],
			"config_functions_62" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_62",
				"default" => $lang["config_functions_62"], // This field must be a valid email address (like joe@user.com). Please re-enter it now.
				"prompt" => RCView::tt("multilang_533"), //= The message of the <i>email</i> validation popup:
			],
			"config_functions_77" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_77",
				"default" => $lang["config_functions_77"], // The value you provided could not be validated because it does not follow the expected format. Please try again.
				"prompt" => RCView::tt("multilang_534"), //= The message of the <i>regex</i> validation popup:
			],
			"global_287" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "global_287",
				"default" => $lang["global_287"], // Invalid value!
				"prompt" => RCView::tt("multilang_654"), //= The title of the 'Invalid value!' popup for auto-complete drop-down fields:
			],
			"survey_681" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "survey_681",
				"default" => $lang["survey_681"], // You entered an invalid value. Please try again.
				"prompt" => RCView::tt("multilang_653"), //= The message of the 'Invalid value!' popup for auto-complete drop-down fields:
			],
			"config_functions_94" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "config_functions_94",
				"default" => $lang["config_functions_94"], // Required format:
				"prompt" => RCView::tt("multilang_535"), //= The message of the <i>general</i> validation popup:
			],
			"data_entry_433" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_433",
				"default" => $lang["data_entry_433"], // This field's value contains extra spaces at the beginning or end. Would you like to remove them?
				"prompt" => RCView::tt("multilang_536"), //= The message of the 'remove spaces' popup:
			],
			"data_entry_529" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_529",
				"default" => $lang["data_entry_529"], // NOTE: Some fields are required!
				"prompt" => RCView::tt("multilang_537"), //= The required fields notification title:
			],
			"data_entry_72" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_72",
				"default" => $lang["data_entry_72"], // Your data was successfully saved, but you did not provide a value for some fields that require a value. Please enter a value for the fields on this page that are listed below.
				"prompt" => RCView::tt("multilang_538"), //= The required fields notification text:
			],
			"data_entry_73" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_73",
				"default" => $lang["data_entry_73"], // Provide a value for...
				"prompt" => RCView::lang_i("multilang_313", [$lang["data_entry_73"]]), //= The '{0}' prompt:
			],
			"data_entry_74" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_74",
				"default" => $lang["data_entry_74"], // Ignore and go to next form
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_74"]]), //= The '{0}' button label:
			],
			"data_entry_76" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_76",
				"default" => $lang["data_entry_76"], // Ignore and leave record
				"prompt" => RCView::lang_i("multilang_335", [$lang["data_entry_76"]]), //= The '{0}' button label:
			],
			"design_401" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "design_401",
				"default" => $lang["design_401"], // Okay
				"prompt" => RCView::lang_i("multilang_335", [$lang["design_401"]]), //= The '{0}' button label:
			],
			"data_entry_423" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_423",
				"default" => $lang["data_entry_423"], // The fields listed below had values entered, but unfortunately the maximum number of responses that can be entered for the choice(s) you selected for those fields has already been reached. Please note that <b>your selections for the fields below were *NOT SAVED*.</b> If you wish to select a different choice for those fields, please do so and then re-submit the page.
				"prompt" => RCView::tt("multilang_539"), //= The MAXCHOICE validation text:
			],
			"data_entry_424" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_424",
				"default" => $lang["data_entry_424"], // Fields in which the maximum response limit was reached:
				"prompt" => RCView::tt("multilang_540"), //= The MAXCHOICE affected fields prompt:
			],
			"data_entry_271" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_271",
				"default" => $lang["data_entry_271"], // The fields listed below had values entered that are not considered valid. Please note that <b>the invalid values were *NOT SAVED*.</b> If you wish to save a correct value for them, please re-enter a value for these fields and re-submit the page.
				"prompt" => RCView::tt("multilang_541"), //= The 'invalid values' popup text:
			],
			"data_entry_272" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_272",
				"default" => $lang["data_entry_272"], // Fields with invalid values:
				"prompt" => RCView::tt("multilang_542"), //= The 'invalid fields' prompt:
			],
			"data_entry_662" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_662",
				"default" => $lang["data_entry_662"], // ERROR: Value must be unique!
				"prompt" => RCView::tt("data_entry_664"), //= The 'secondary unique field' error prompt:
			],
			"data_entry_663" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_663",
				"default" => $lang["data_entry_663"], // The value you just entered for a field <b>could not be saved</b> because its value cannot duplicate a value that already exists for another record.
				"prompt" => RCView::tt("data_entry_664"), //= The 'secondary unique field' error prompt (for data entry forms):
			],
			"data_entry_665" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_665",
				"default" => $lang["data_entry_665"], // The value you just entered for a question <b>could not be saved</b> because its value cannot duplicate a value that already exists for another survey response.
				"prompt" => RCView::tt("data_entry_666"), //= The 'secondary unique field' error prompt (for surveys):
			],
			"data_entry_530" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_530",
				"default" => $lang["data_entry_530"], // WARNING: Invalid values entered!
				"prompt" => RCView::tt("multilang_543"), //= The 'invalid fields' popup title:
			],
			"data_entry_421" => [
				"category" => "ui-validation",
				"group" => "validation_messages",
				"type" => "string",
				"id" => "data_entry_421",
				"default" => $lang["data_entry_421"], // Cannot select choice! The maximum number of choices has been selected.
				"prompt" => RCView::tt("multilang_544"), //= The 'maximum number of choices reached' message:
			],
			// Secondary Unique Field
			"data_entry_105" => [
				"category" => "ui-validation",
				"group" => "validation_secondary",
				"type" => "string",
				"id" => "data_entry_105",
				"default" => $lang["data_entry_105"], // DUPLICATE VALUE!
				"prompt" => RCView::lang_i("multilang_587", [$lang["data_entry_105"]]), //= The {0} popup title:
			],
			"data_entry_575" => [
				"category" => "ui-validation",
				"group" => "validation_secondary",
				"type" => "string",
				"id" => "data_entry_575",
				"default" => $lang["data_entry_575"], // The current question requires that its value never duplicate the value from a prior survey response. The value you entered ("{0}") has already been taken, so please enter another value. You must change this value before you can proceed.
				"prompt" => RCView::tt("multilang_588"), //= The duplicate value popup message (surveys; use {0} as placeholder for the value):
			],
			"data_entry_576" => [
				"category" => "ui-validation",
				"group" => "validation_secondary",
				"type" => "string",
				"id" => "data_entry_576",
				"default" => $lang["data_entry_576"], // The current field is the secondary unique field ({0}), so its value must be unique for all records and cannot be duplicated. Another record or another event within the current record already has this same value ("{1}"). You must change this value before you can proceed.
				"prompt" => RCView::tt("multilang_589"), //= The duplicate value popup message (data entry forms; use {0} and {1} as placeholders for the field name and value, respectively):
			],
		);
		// Add validations from redcap_validation_types table
		$validations = self::readValidationTypes();
		foreach ($validations as $val_name => $val_info) {
			// Skip validation types that are not visible when generating for project MLM
			if (!$is_system && !$val_info["visible"]) continue;
			$val_id = "_valtype_".$val_name;
			$visible = $is_system ? 
				($val_info["visible"] ? '<i class="fas fa-eye text-info"></i> ' : '<i class="fas fa-eye-slash text-secondary"></i> ') :
				"";
			$metadata[$val_id] = array(
				"category" => "ui-validation",
				"group" => "validation_types",
				"type" => "string",
				"id" => $val_id,
				"default" => $val_info["label"],
				"prompt" => RCView::lang_i("multilang_577", [
					$visible,
					$val_info["label"],
				], false), //= Translation of the '{0}' validation label{1}:
			);
		}
		// Add MyCap UI strings
		// We parse these from $lang - They all start with mycapui, followed by the subcategory, 
		// then key (all separated by _)

		// TODO: Uncomment the foreach loop to enable MyCap UI translations
		// foreach ($lang as $k => $v) {
		//     if (substr($k, 0, 8) == "mycapui_") {
		//         $parts = explode("_", $k);
		//         $group = "mycap_".$parts[1];
		//         $metadata[$k] = array(
		//             "category" => "ui-mycap",
		//             "group" => $group,
		//             "type" => "string",
		//             "id" => $k,
		//             "default" => $v,
		//             "prompt" => RCView::tt("multilang_727"), //= Translation for:
		//         );
		//     }
		// }
		// Fix potential UTF-8 issues and add hash values
		foreach ($metadata as $k => $v) {
			$default_val = ensureUTF($v["default"]);
			$metadata[$k]["default"] = $default_val;
			$metadata[$k]["prompt"] = ensureUTF($v["prompt"]);
			$metadata[$k]["refHash"] = self::getChangeTrackHash($default_val);
		}
		self::$uiMetadataCache = $metadata;
		return self::$uiMetadataCache;
	}
	/**
	 * User interface metadata cache
	 * @var mixed
	 */
	private static $uiMetadataCache = null;

	#endregion

	#region Snapshots & Export Files

	/**
	 * Renders a data structure for exporting a language (final output will
	 * be prepared in the browser, including file format - json or csv)
	 * @param mixed $project_id 
	 * @param mixed $user 
	 * @param mixed $data 
	 * @return array 
	 */
	public static function getExportFile($project_id, $user, $payload) {
		$request = json_decode($payload, true);
		$settings = $project_id == "SYSTEM" 
			? self::getSystemSettings() 
			: self::getProjectSettings($project_id, true);
		$lang_id = $request["lang"];
		if (!isset($settings["langs"][$lang_id])) {
			return self::response(
				false, 
				RCView::tt_i_strip_tags("multilang_102", [$lang_id]) // The language '{0}' is not available.
			); 
		}
		// Log
		$draft_mode = self::inDraftMode($project_id);
		$in_draft_mode = $draft_mode ? " (draft mode)" : "";
		if ($request["mode"] == "changes") {
			self::log("Export", "Changed items of language [{$lang_id}] were exported{$in_draft_mode}.", $user, $project_id);
		}
		else if ($request["mode"] == "single-form") {
			self::log("Export", "Single-form items of language [{$lang_id}] were exported for form '{$request["form"]}'{$in_draft_mode}.", $user, $project_id);
		}
		else {
			self::log("Export", "Language [{$lang_id}] was exported{$in_draft_mode}.", $user, $project_id);
		}
		// Create export
		$include_prompt = $request["prompts"] == true;
		$include_ref = $request["defaults"] == true;
		$include_notes = $request["notes"] == true;
		$limit = $request["limit"];
		$subset = $request["items"] != null;
		$time = time();
		$content = self::exportLanguage($project_id, $lang_id, $time, true, $include_prompt, $include_ref, $include_notes, $limit, $request["mode"], $request["items"], $request["form"], $draft_mode);
		// Add instructions
		$content["instructions"] = $request["format"] == "json"
			// JSON instructions
			? (RCView::getLangStringByKey("multilang_747") . ($project_id == "SYSTEM" ? "" : (" ".RCView::getLangStringByKey("multilang_138"))))
			// CSV instructions 
			: (RCView::getLangStringByKey("multilang_212") . ($project_id == "SYSTEM" ? "" : (" ".RCView::getLangStringByKey("multilang_138"))));
		$name = "REDCapTranslation_";
		$name .= $subset ? "Subset_" : "";
		$name .= "{$lang_id}_";
		$name .= $project_id == "SYSTEM" ? "sys_" : "pid{$project_id}_";
		$name .= date("Ymd-His", $time);
		// Return response
		return self::response(true, "", [
			"name" => $name,
			"content" => $content,
		]);
	}

	/**
	 * Gets the snapshots in a project
	 * @param int|string $project_id 
	 * @return (false|string)[]|(true|string|array)[] An AJAX response array
	 */
	public static function getSnapshots($project_id) {
		$settings = self::getProjectSettings($project_id);
		if ($settings["disabled"]) {
			return self::response(false, RCView::tt_strip_tags("multilang_546")); //= Multi-Language Management is disabled for this project.
		}
		return self::response(true, "", array(
			"snapshots" => self::readSnapshots($project_id),
		));
	}

	/**
	 * Builds an export data structure for the given language
	 * @param mixed $project_id 
	 * @param string $lang_id 
	 * @param int $time Current time (Unix timestamp)
	 * @param bool $full Whether to generate a full (true) or sparse (false) export (i.e. only including translated items)
	 * @param bool $include_prompt Whether to include translation prompts
	 * @param bool $include_ref Whether to include reference values
	 * @param bool|array $limit Whether to limit the included items
	 * @param string $mode Export mode (all, changed, single-form)
	 * @param array|null $items A subset of items to be exported
	 * @param string $form The name of a form
	 * @param bool $draftMode Whether to export for draft mode
	 * @return array 
	 */
	private static function exportLanguage($project_id, $lang_id, $time, $full = false, $include_prompt = false, $include_ref = false, $include_notes = false, $limit = false, $mode = "all", $items = null, $form = "", $draftMode = false) {
		$is_system = $project_id == "SYSTEM";
		$uimd = self::getUIMetadata($is_system);
		$settings = $is_system
			? self::getSystemSettings() 
			: self::getProjectSettings($project_id, $draftMode);
		$mlm_lang = $settings["langs"][$lang_id];
		$dd = $mlm_lang["dd"] ?? "";
		$export = array(
			"creator" => "REDCap MLM",
			"version" => "v".$settings["version"],
			"timestamp" => date("Y-m-d H:i:s", $time),
			"instructions" => "", // filled later - depending on format
			"key" => $lang_id,
			"display" => $mlm_lang["display"],
		);
		if ($include_notes) {
			$export["notes"] = $mlm_lang["notes"];
		}
		$export["rtl"] = $mlm_lang["rtl"];
		$export["sort"] = $mlm_lang["sort"];
		$subset = $items != null;
		// In case items is not null (i.e. this is a subset export), set limit and full to false
		if ($mode == "changes") {
			$limit = false;
			$full = false;
		}
		#region User Interface
		if (!$limit || $limit["ui"]) {
			$export["uiTranslations"] = array();
			$export["uiOptions"] = array();
			foreach ($uimd as $key => $meta) {
				if ($subset && !isset($items["ui"][$key])) continue;
				$val = $mlm_lang["ui"][$key]["translation"] ?? "";
				// For subset exports, use ref hash from meta
				$hash = $subset ? $meta["refHash"] : ($mlm_lang["ui"][$key]["refHash"] ?? $meta["refHash"]);
				$item = array(
					"id" => $key,
				);
				if ($include_prompt) {
					$item["prompt"] = self::de_html($meta["prompt"]);
				}
				if ($include_ref) {
					$item["default"] = $meta["default"];
				}
				if ($meta["type"] == "bool") {
					$item["value"] = $val === true;
					$export["uiOptions"][] = $item;
				}
				else if ($full || !self::isEmpty($val)) {
					$item["translation"] = $val;
					$item["hash"] = $hash;
					$export["uiTranslations"][] = $item;
				}
			}
		}
		#endregion
		// System export is done
		if ($project_id == "SYSTEM") {
			return $export;
		}
		// Add project items
		$pmd = self::getProjectMetadata($project_id);
		#region Forms
		if (!$limit || $limit["forms"]) {
			$export["formTranslations"] = array();
			foreach ($pmd["forms"] as $formname => $meta) {
				if ($subset && !isset($items["form-name"][$formname])) continue;
				$item = array(
					"id" => $formname,
					"active" => (isset($dd["form-active"][$formname][""]["translation"]) && $dd["form-active"][$formname][""]["translation"] == "1"),
				);
				// Form name
				if (!self::isEmpty($meta["form-name"]["reference"])) {
					$val = $dd["form-name"][$formname][""]["translation"] ?? "";
					$sub_item = array(
						"hash" => $meta["form-name"]["refHash"],
					);
					if ($include_prompt) $sub_item["prompt"] = self::de_html($pmd["fieldTypes"]["form"]["name"] ?? "");
					if ($include_ref) $sub_item["default"] = $meta["form-name"]["reference"] ?? "";
					$sub_item["translation"] = $val;
					$skip = $subset && !isset($items["form-name"][$formname]);
					if (($full || !self::isEmpty($val)) && !$skip) {
						$item["name"] = $sub_item;
					}
				}
				if (count($item) > 2) {
					$export["formTranslations"][] = $item;
				}
			}
		}
		#endregion
		#region Events
		if (!$limit || $limit["events"]) {
			$export["eventTranslations"] = array();
			foreach ($pmd["events"] as $event_id => $meta) {
				$item = array(
					"id" => $meta["uniqueEventName"],
				);
				// Event name
				if (!self::isEmpty($meta["event-name"]["reference"])) {
					$val = $dd["event-name"][$event_id][""]["translation"] ?? "";
					$sub_item = array(
						"hash" => $meta["event-name"]["refHash"],
					);
					if ($include_prompt) $sub_item["prompt"] = self::de_html($pmd["fieldTypes"]["event"]["name"] ?? "");
					if ($include_ref) $sub_item["default"] = $meta["event-name"]["reference"] ?? "";
					$sub_item["translation"] = $val;
					$skip = $subset && !isset($items["event-name"][$event_id]);
					if (($full || !self::isEmpty($val)) && !$skip) {
						$item["name"] = $sub_item;
					}
				}
				// Custom Event Label
				if (!self::isEmpty($meta["event-custom_event_label"]["reference"])) {
					$val = $dd["event-custom_event_label"][$event_id][""]["translation"];
					$sub_item = array(
						"hash" => $meta["event-custom_event_label"]["refHash"],
					);
					if ($include_prompt) $sub_item["prompt"] = self::de_html($pmd["fieldTypes"]["event"]["custom_event_label"]);
					if ($include_ref) $sub_item["default"] = $meta["event-custom_event_label"]["reference"];
					$sub_item["translation"] = $val;
					$skip = $subset && !isset($items["event-custom_event_label"][$event_id]);
					if (($full || !self::isEmpty($val)) && !$skip) {
						$item["custom_event_label"] = $sub_item;
					}
				}
				if (count($item) > 2) {
					$export["eventTranslations"][] = $item;
				}
			}
		}
		#endregion
		#region Fields
		if (!$limit || $limit["fields"]) {
			$export["fieldTranslations"] = array();
			foreach ($pmd["fields"] as $fieldname => $meta) {
				if ($meta["isPROMIS"]) continue; // Skip fields on PROMIS forms
				$item = array(
					"id" => $fieldname,
					"form" => $meta["formName"],
				);
				$min_field_count = 2;
				// MyCap Task-associated field?
				if ($pmd["myCapEnabled"] && $meta["isTaskItem"]) {
					$item["note"] = RCView::getLangStringByKey("multilang_750");
					$min_field_count = 3;
				}
				// Header
				$val = $dd["field-header"][$fieldname][""]["translation"] ?? "";
				if (!self::isEmpty($meta["field-header"]["reference"]) || !self::isEmpty($val)) {
					$sub_item = array(
						"hash" => $meta["field-header"]["refHash"],
					);
					if ($include_prompt) $sub_item["prompt"] = self::de_html($pmd["fieldTypes"]["header"]["header"]);
					if ($include_ref) $sub_item["default"] = $meta["field-header"]["reference"];
					$sub_item["translation"] = $val;
					$skip = $subset && !isset($items["field-header"][$fieldname]);
					if (($full || !self::isEmpty($val)) && !$skip) {
						$item["header"] = $sub_item;
					}
				}
				// Label
				$val = $dd["field-label"][$fieldname][""]["translation"] ?? "";
				if (!self::isEmpty($meta["field-label"]["reference"]) || !self::isEmpty($val)) {
					$sub_item = array(
						"hash" => $meta["field-label"]["refHash"],
					);
					if ($include_prompt) $sub_item["prompt"] = self::de_html($pmd["fieldTypes"][$meta["type"]]["label"]);
					if ($include_ref) $sub_item["default"] = $meta["field-label"]["reference"];
					$sub_item["translation"] = $val;
					$skip = $subset && !isset($items["field-label"][$fieldname]);
					if (($full || !self::isEmpty($val)) && !$skip) {
						$item["label"] = $sub_item;
					}
				}
				// Note
				$val = $dd["field-note"][$fieldname][""]["translation"] ?? "";
				if (!self::isEmpty($meta["field-note"]["reference"]) || !self::isEmpty($val)) {
					$sub_item = array(
						"hash" => $meta["field-note"]["refHash"],
					);
					if ($include_prompt) $sub_item["prompt"] = self::de_html($pmd["fieldTypes"][$meta["type"]]["note"]);
					if ($include_ref) $sub_item["default"] = $meta["field-note"]["reference"];
					$sub_item["translation"] = $val;
					$skip = $subset && !isset($items["field-note"][$fieldname]);
					if (($full || !self::isEmpty($val)) && !$skip) {
						$item["note"] = $sub_item;
					}
				}
				// Video Url
				$val = $dd["field-video_url"][$fieldname][""]["translation"] ?? "";
				if (!self::isEmpty($meta["field-video_url"]["reference"]) || !self::isEmpty($val)) {
					$sub_item = array(
						"hash" => $meta["field-video_url"]["refHash"],
					);
					if ($include_prompt) $sub_item["prompt"] = self::de_html($pmd["fieldTypes"][$meta["type"]]["video_url"]);
					if ($include_ref) $sub_item["default"] = $meta["field-video_url"]["reference"];
					$sub_item["translation"] = $val;
					$skip = $subset && !isset($items["field-video_url"][$fieldname]);
					if (($full || !self::isEmpty($val)) && !$skip) {
						$item["video_url"] = $sub_item;
					}
				}
				// Field Enums
				if (isset($meta["field-enum"])) {
					$enum_items = array();
					foreach ($meta["field-enum"] as $choice => $choiceData) {
						$val = $dd["field-enum"][$fieldname][$choice]["translation"] ?? "";
						$enum_item = array(
							"id" => $choice,
							"hash" => $choiceData["refHash"],
						);
						if ($include_prompt) $enum_item["prompt"] = self::de_html($pmd["fieldTypes"][$meta["type"]]["enum"]);
						if ($include_ref) $enum_item["default"] = $choiceData["reference"];
						$enum_item["translation"] = $val;
						$skip = $subset && !isset($items["field-enum"][$fieldname][$choice]);
						if (($full || !self::isEmpty($val)) && !$skip) {
							$enum_items[] = $enum_item;
						}
					}
					if (count($enum_items)) {
						$item["enum"] = $enum_items;
					}
				}
				// Action Tags
				if (isset($meta["field-actiontag"])) {
					$at_items = array();
					foreach ($meta["field-actiontag"] as $index => $atData) {
						$val = $dd["field-actiontag"][$fieldname][$index]["translation"] ?? "";
						$at_item = array(
							"id" => $index,
							"hash" => $atData["refHash"],
							"tag" => $atData["tag"],
						);
						if ($include_prompt) $at_item["prompt"] = self::de_html($pmd["fieldTypes"]["actiontag"]["value"]);
						if ($include_ref) $at_item["default"] = $atData["reference"];
						$at_item["translation"] = $val;
						$skip = $subset && !isset($items["field-actiontag"][$fieldname][$index]);
						if (($full || !self::isEmpty($val)) && !$skip) {
							$at_items[] = $at_item;
						}
					}
					if (count($at_items)) {
						$item["actiontags"] = $at_items;
					}
				}
				if (count($item) > $min_field_count) {
					$export["fieldTranslations"][] = $item;
				}
			}
		}
		#endregion
		#region Matrix Groups
		if (!$limit || $limit["fields"]) { // Matrix Groups are fields
			$export["matrixTranslations"] = array();
			foreach ($pmd["matrixGroups"] as $matrixname => $meta) {
				if ($meta["isPROMIS"]) continue; // Skip matrix groups on PROMIS forms
				$item = array(
					"id" => $matrixname,
					"form" => $meta["form"],
				);
				// Header
				$val = $dd["matrix-header"][$matrixname][""]["translation"] ?? "";
				if (!self::isEmpty($meta["matrix-header"]["reference"]) || !self::isEmpty($val)) {
					$sub_item = array(
						"hash" => $meta["matrix-header"]["refHash"],
					);
					if ($include_prompt) $sub_item["prompt"] = self::de_html($pmd["fieldTypes"]["matrix"]["header"]);
					if ($include_ref) $sub_item["default"] = $meta["matrix-header"]["reference"];
					$sub_item["translation"] = $val;
					$skip = $subset && !isset($items["matrix-header"][$matrixname]);
					if (($full || !self::isEmpty($val)) && !$skip) {
						$item["header"] = $sub_item;
					}
				}
				// Field Enums
				if (isset($meta["matrix-enum"])) {
					$enum_items = array();
					foreach ($meta["matrix-enum"] as $choice => $choiceData) {
						$val = $dd["matrix-enum"][$matrixname][$choice]["translation"] ?? "";
						$enum_item = array(
							"id" => $choice,
							"hash" => $choiceData["refHash"],
						);
						if ($include_prompt) $enum_item["prompt"] = self::de_html($pmd["fieldTypes"]["matrix"]["enum"]);
						if ($include_ref) $enum_item["default"] = $choiceData["reference"];
						$enum_item["translation"] = $val;
						$skip = $subset && !isset($items["matrix-enum"][$matrixname][$choice]);
						if (($full || !self::isEmpty($val)) && !$skip) {
							$enum_items[] = $enum_item;
						}
					}
					if (count($enum_items)) {
						$item["enum"] = $enum_items;
					}
				}
				if (count($item) > 2) {
					$export["matrixTranslations"][] = $item;
				}
			}
		}
		#endregion
		#region Survey Settings
		if (!$limit || $limit["surveys"]) {
			$export["surveyTranslations"] = array();
			foreach ($pmd["surveys"] as $form_name => $survey) {
				$item = array(
					"id" => $form_name,
					"active" => (isset($dd["survey-active"][$form_name][""]["translation"]) && $dd["survey-active"][$form_name][""]["translation"] == "1"),
				);
				$survey_settings = array();
				foreach ($survey as $type => $meta) {
					if (starts_with($type, "survey-")) {
						$val = $dd[$type][$form_name][""]["translation"] ?? "";
						if (!self::isEmpty($meta["reference"]) || !self::isEmpty($val)) {
							$survey_setting = array(
								"id" => $type,
								"hash" => $meta["refHash"],
							);
							if ($include_prompt) $survey_setting["prompt"] = self::de_html($meta["prompt"]);
							if ($include_ref) $survey_setting["default"] = $meta["reference"];
							$survey_setting["translation"] = $val;
							if (isset($meta["select"]) && $meta["select"]) {
								foreach($meta["select"] as $key => $value){
									$survey_setting["valid-translation-values"][] = "$key = $value";
								}
							}
							$skip = $subset && !isset($items[$type][$form_name]);
							if (($full || !self::isEmpty($val)) && !$skip) {
								$survey_settings[] = $survey_setting;
							}
						}
					} 
				}
				if (count($survey_settings)) {
					$item["settings"] = $survey_settings;
				}
				if (count($item) > 2 || !$subset) {
					$export["surveyTranslations"][] = $item;
				}
			}
		}
		#endregion
		#region Survey Queue
		if (!$limit || $limit["surveyqueue"]) {
			$export["sqTranslations"] = array();
			foreach ($pmd["surveyQueue"] as $type => $meta) {
				if (!self::isEmpty($meta["reference"])) {
					$item = array(
						"id" => $type,
						"hash" => $meta["refHash"],
					);
					$val = $dd[$type][""][""]["translation"] ?? "";
					if ($include_prompt) $item["prompt"] = self::de_html($meta["prompt"]);
					if ($include_ref) $item["default"] = $meta["reference"];
					$item["translation"] = $val;
					$skip = $subset && !isset($items[$type]);
					if (($full || !self::isEmpty($val)) && !$skip) {
						$export["sqTranslations"][] = $item;
					}
				}
			}
		}
		#endregion
		#region ASIs
		if (!$limit || $limit["asis"]) {
			$export["asiTranslations"] = array();
			foreach ($pmd["asis"] as $asi_id => $asi) {
				$asi_settings = array();
				foreach ($asi as $type => $meta) {
					if (starts_with($type, "asi-") && !self::isEmpty($meta["reference"])) {
						$val = $dd[$type][$asi["form"]][$asi_id]["translation"] ?? "";
						$asi_setting = array(
							"id" => $type,
							"hash" => $meta["refHash"],
						);
						if ($include_prompt) $asi_setting["prompt"] = self::de_html($meta["prompt"]);
						if ($include_ref) $asi_setting["default"] = $meta["reference"];
						$asi_setting["translation"] = $val;
						$skip = $subset && !isset($items[$type][$asi_id]);
						if (($full || !self::isEmpty($val)) && !$skip) {
							$asi_settings[] = $asi_setting;
						}
					}
				}
				if (count($asi_settings)) {
					$asi_event_name = $pmd["events"][$asi["event_id"]]["uniqueEventName"];
					$asi_form = $asi["form"];
					$asi_composite_id = "{$asi_event_name}-{$asi_form}";
					$export["asiTranslations"][] = array(
						"id" => $asi_composite_id,
						"form" => $asi_form,
						"event" => $asi_event_name,
						"settings" => $asi_settings,
					);
				}
			}
		}
		#endregion
		#region Alerts
		if (!$limit || $limit["alerts"]) {
			$export["alertTranslations"] = array();
			foreach ($pmd["alerts"] as $alert_id => $alert) {
				$alert_settings = array();
				foreach ($alert as $type => $meta) {
					if (starts_with($type, "alert-") && !self::isEmpty($meta["reference"])) {
						if ($type == "alert-sendgrid_template_data") {
							$template_data = json_decode($meta['reference'], TRUE);
							foreach ($template_data as $key => $reference_value) {
								$val = $dd[$type][$alert_id][$key]["translation"] ?? "";
								$alert_setting = array(
									"id" => $type .":".$key,
									"hash" => $meta["refHash"],
								);
								if ($include_prompt) $alert_setting["prompt"] = self::de_html($meta["prompt"] . ' ' . $key);
								if ($include_ref) $alert_setting["default"] = $reference_value;
								$alert_setting["translation"] = $val;
								$skip = $subset && !isset($items[$type][$alert_id]);
								if (($full || !self::isEmpty($val)) && !$skip) {
									$alert_settings[] = $alert_setting;
								} 
							}
						} else {
							$val = $dd[$type][$alert_id][""]["translation"] ?? "";
							$alert_setting = array(
								"id" => $type,
								"hash" => $meta["refHash"],
							);
							if ($include_prompt) $alert_setting["prompt"] = self::de_html($meta["prompt"]);
							if ($include_ref) $alert_setting["default"] = $meta["reference"];
							$alert_setting["translation"] = $val;
							$skip = $subset && !isset($items[$type][$alert_id]);
							if (($full || !self::isEmpty($val)) && !$skip) {
								$alert_settings[] = $alert_setting;
							}
						}
					}
				}
				if (count($alert_settings)) {
					$alerts = array(
						"id" => $alert["alertNum"],
						"pid-pk" => "{$project_id}-{$alert_id}", // within-project match helper
					);
					if (!self::isEmpty($alert["title"])) $alerts["title"] = $alert["title"];
					$alerts["settings"] = $alert_settings;
					$export["alertTranslations"][] = $alerts;
				}
			}
		}
		#endregion
		#region Missing Data Codes
		if (!$limit || $limit["mdc"]) {
			$export["mdcTranslations"] = array();
			foreach ($pmd["mdcs"] as $mdc => $meta) {
				if (!self::isEmpty($meta["reference"])) {
					$val = $dd["mdc-label"][$mdc][""]["translation"] ?? "";
					$mdc_setting = array(
						"id" => $mdc,
						"hash" => $meta["refHash"],
					);
					if ($include_prompt) $mdc_setting["prompt"] = self::de_html($pmd["fieldTypes"]["mdc"]["label"]);
					if ($include_ref) $mdc_setting["default"] = $meta["reference"];
					$mdc_setting["translation"] = $val;
					$skip = $subset && !isset($items["mdc-label"][$mdc]);
					if (($full || !self::isEmpty($val)) && !$skip) {
						$export["mdcTranslations"][] = $mdc_setting;
					}
				}
			}
		}
		#endregion
		#region PDF Customizations
		if ((!$limit || $limit["pdf"]) && isset($pmd["pdfCustomizations"])) {
			$export["pdfTranslations"] = array();
			if (isset($pmd["pdfCustomizations"][""])) {
				foreach ($pmd["pdfCustomizations"][""] as $type => $meta) {
					if (!self::isEmpty($meta["reference"])) {
						$item = array(
							"id" => $type,
							"hash" => $meta["refHash"],
						);
						$val = $dd[$type][""][""]["translation"] ?? "";
						if ($include_prompt) $item["prompt"] = self::de_html($meta["prompt"]);
						if ($include_ref) $item["default"] = $meta["reference"];
						$item["translation"] = $val;
						$skip = $subset && !isset($items[$type]);
						if (($full || !self::isEmpty($val)) && !$skip) {
							$export["pdfTranslations"][] = $item;
						}
					}
				}
			}
		}
		#endregion
		#region Protected Email
		if ((!$limit || $limit["protemail"]) && isset($pmd["protectedMail"])) {
			$export["protemailTranslations"] = array();
			if (isset($pmd["protectedMail"][""])) {
				foreach ($pmd["protectedMail"][""] as $type => $meta) {
					if (!self::isEmpty($meta["reference"])) {
						$item = array(
							"id" => $type,
							"hash" => $meta["refHash"],
						);
						$val = $dd[$type][""][""]["translation"] ?? "";
						if ($include_prompt) $item["prompt"] = self::de_html($meta["prompt"]);
						if ($include_ref) $item["default"] = $meta["reference"];
						$item["translation"] = $val;
						$skip = $subset && !isset($items[$type]);
						if (($full || !self::isEmpty($val)) && !$skip) {
							$export["protemailTranslations"][] = $item;
						}
					}
				}
			}
		}
		#endregion
		#region MyCap
		if ((!$limit || $limit["mycap"]) && isset($pmd["myCapEnabled"])) {
			$mycap = $pmd["myCap"];
			$export["myCapTranslations"] = array();
			#region MyCap Title
			$type = "mycap-app_title";
			$meta = $mycap[$type];
			$item = [
				"hash" => $meta["refHash"],
			];
			if ($include_prompt) $item["prompt"] = self::de_html($meta["prompt"]);
			if ($include_ref) $item["default"] = $meta["reference"];
			$item["translation"] = $dd[$type][""][""]["translation"] ?? "";
			if (!$subset || isset($items[$type])
			) {
				$export["myCapTranslations"][] = [
					"type" => $type,
					"mycap-app_title" => $item,
				];
			}
			#endregion
			#region MyCap Pages, Contacts, Links
			foreach (["pages", "contacts", "links"] as $ptype) {
				$ptype_meta = $mycap[$ptype];
				$itype = "mycap-".$ptype;
				foreach ($ptype_meta as $itype_id => $itype_items) {
					$item = [
						"type" => $itype,
						"id" => $itype_id,
						"order" => $itype_items["order"],
					];
					foreach ($itype_items as $itype_type => $itype_meta) {
						if (starts_with($itype_type, "mycap-")) {
							$itype_item = [
								"hash" => $itype_meta["refHash"],
							];
							if ($include_prompt) $itype_item["prompt"] = self::de_html($itype_meta["prompt"]);
							if ($include_ref) $itype_item["default"] = $itype_meta["reference"];
							$itype_item["translation"] = $dd[$itype_type][$itype_id][""]["translation"] ?? "";
							if (!empty($itype_meta["reference"])) {
								$item[$itype_type] = $itype_item;
							}
						}
					}
					if (count($item) > 2 && (!$subset || isset($items[$itype]))) {
						$export["myCapTranslations"][] = $item;
					}
				}
			}
			#endregion
			#region Baseline Task
			$task_items = [
				"type" => "mycap-baseline_task",
			];
			foreach ($mycap["mycap-baseline_task"] as $itype => $itype_meta) {
				$sub_item = [];
				if (!empty($itype_meta["reference"])) {
					$sub_item["hash"] = $itype_meta["refHash"];
					if ($include_prompt) $sub_item["prompt"] = self::de_html($itype_meta["prompt"]);
					if ($include_ref) $sub_item["default"] = $itype_meta["reference"];
					$sub_item["translation"] = $dd[$itype][""][""]["translation"] ?? "";
				}
				if (!$subset || isset($items[$itype])) {
					$task_items[$itype] = $sub_item;
				}
			}
			if (count($task_items) > 1) {
				$export["myCapTranslations"][] = $task_items;
			}
			#endregion
			#region Tasks
			foreach ($mycap["taskToForm"] as $task_id => $form_name) {
				$tasks = $pmd["forms"][$form_name]["myCapTaskItems"] ?? [];
				$task_items = [
					"type" => "mycap-task",
					"form" => $form_name,
					"id" => $task_id,
				];
				// Task items not specific to events
				foreach ($pmd["myCap"]["orderedListOfTaskItems"] as $task_item_name => $_) {
					$dd_type = "task-$task_item_name";
					if (!isset($tasks[$task_item_name])) continue;
					if (!empty($tasks[$task_item_name]["reference"])) {
						$sub_item = [
							"hash" => $tasks[$task_item_name]["refHash"],
						];
						if ($include_prompt) $sub_item["prompt"] = self::de_html($tasks[$task_item_name]["prompt"]);
						if ($include_ref) $sub_item["default"] = $tasks[$task_item_name]["reference"];
						$sub_item["translation"] = $dd[$dd_type][$task_id][""]["translation"] ?? "";
						if (!$subset || isset($items[$dd_type][$task_id])) {
							$task_items[$dd_type] = $sub_item;
						}
					}
				}
				if (count($task_items) > 3) {
					$export["myCapTranslations"][] = $task_items;
				}
			}
			#endregion
		}
		#endregion

		// Remove empty arrays if this is a subset export
		if ($subset) {
			foreach (array_keys($export) as $key) {
				if (is_array($export[$key]) && count($export[$key]) == 0) {
					unset($export[$key]);
				}
			}
		}

		return $export;
	}

	/**
	 * Strips HTML tags and reverts entity-encoding
	 * @param mixed $html 
	 * @return string 
	 */
	private static function de_html($html) {
		return ($html === null) ? "" : html_entity_decode(strip_tags(str_replace("<br>", " ", $html)));
	}

	/**
	 * Prepares a snapshot file for download into the browser
	 * @param mixed $project_id 
	 * @param mixed $user 
	 * @param mixed $snapshot_id 
	 * @return array 
	 */
	public static function downloadSnapshot($project_id, $user, $snapshot_id) {
		$settings = self::getProjectSettings($project_id);
		if ($settings["disabled"]) {
			return self::response(false, RCView::getLangStringByKey("multilang_546")); //= Multi-Language Management is disabled for this project.
		}
		try {
			// Get the snapshot row to extract the edoc_id
			$snapshot_raw = self::readSnapshotRaw($snapshot_id);
			if ($snapshot_raw === false || $snapshot_raw["project_id"] != $project_id) {
				throw new Exception(RCView::getLangStringByKey("multilang_548")); //= This snapshot does not exist!
			}
			// Retrieve the file contents
			$edoc_id = $snapshot_raw["edoc_id"];
			$file = Files::getEdocContentsAttributes($edoc_id);
			if ($file == false) {
				throw new Exception(RCView::getLangStringByKey("multilang_554")); //= Failed to retrieve the snapshot file from edocs.
			}

			// Log action
			self::log("Download snapshot", "The snapshot [{$snapshot_id}] with edoc id [{$edoc_id}] has been downloaded.", $user, $project_id);
			// And set as response
			$reponse = self::response(true, "", array(
				"id" => $snapshot_id,
				"content" => base64_encode($file[2]),
				"name" => $file[1],
				"mime" => $file[0],
			));
		}
		catch(Throwable $ex) {
			$reponse = self::exceptionResponse($ex);
		}
		return $reponse;
	}

	/**
	 * Creates a new snapshot
	 * @param mixed $project_id 
	 * @param string $user The user name
	 * @return array 
	 */
	public static function createSnapshot($project_id, $user) {
		$settings = self::getProjectSettings($project_id);
		if ($settings["disabled"]) {
			return self::response(false, RCView::getLangStringByKey("multilang_546")); //= Multi-Language Management is disabled for this project.
		}
		try {
			$user_info= User::getUserInfo($user);
			$ui_id = $user_info["ui_id"];
			$settings = self::getProjectSettings($project_id);
			$rand = rand(100001,999999);
			$temp_file_name = APP_PATH_TEMP . "MLM_Snapshot_" . date('YmdHis') . "_p{$project_id}_u{$ui_id}_r{$rand}.zip";
			$time = time();
			$file_ts = date("Ymd-His", $time);
			
			// Create a snapshot ZIP
			$zip = new ZipArchive();
			if (!$zip->open($temp_file_name, ZipArchive::CREATE)) {
				throw new Exception("Faild to create ZIP file in TEMP folder ('".APP_PATH_TEMP."'). Is the folder writeable?");
			}
			// Add languages
			foreach (array_keys($settings["langs"]) as $lang_id) {
				$name = "REDCapTranslation_{$lang_id}_pid{$project_id}_{$file_ts}.json";
				$lang_export = self::exportLanguage($project_id, $lang_id, $time, false, false, false, true); // Include notes
				$content = json_encode($lang_export, JSON_PRETTY_PRINT);
				$zip->addFromString($name, $content);
			}
			// Add project general settings
			list($name, $general_settings) = self::getProjectGeneralSettings($project_id, $file_ts);
			$content = json_encode($general_settings, JSON_PRETTY_PRINT);
			$zip->addFromString($name.".json", $content);
			$zip->close();

			// Store the file in edocs
			$edoc_id = Files::uploadFile(array(
				"name" => "REDCap_MLM_Snapshot_pid{$project_id}_{$file_ts}.zip",
				"tmp_name" => $temp_file_name,
				"size" => filesize($temp_file_name)
			), $project_id);
			if (!$edoc_id) {
				throw new Exception(RCView::getLangStringByKey("multilang_553")); //= Failed to upload the snapshot file to the edocs location.
			}
			$edoc_meta = self::readEDocsMetadata($edoc_id);
			if ($edoc_meta === false) {
				throw new Exception(RCView::getLangStringByKey("multilang_552")); //= Failed to read edocs metadata from the database.
			}
			// Add to snapshot table
			$snapshot_id = self::createSnapshotEntry($project_id, $edoc_id, $ui_id);
			if ($snapshot_id === false) {
				throw new Exception(RCView::getLangStringByKey("multilang_551")); //= Failed to add the snapshot to the database.
			}
			// Prepare response object
			$created_by = "{$user} ({$user_info["user_firstname"]} {$user_info["user_lastname"]})";
			$snapshot = array(
				"id" => $snapshot_id,
				"created_ts" => $edoc_meta["stored_date"],
				"created_by" => $created_by,
				"deleted_ts" => null,
				"deleted_by" => null,
			);
			$response = self::response(true, "", array(
				"snapshot" => $snapshot,
			));
			// Log
			self::log("Create snapshot", "A snapshot of the settings has been created [ID={$snapshot_id}].", $user, $project_id);
		}
		catch(Throwable $ex) {
			$response = self::exceptionResponse($ex);
		}
		finally {
			// Just in case - this should not exist any longer
			@unlink($temp_file_name);
		}
		return $response;
	}

	/**
	 * Generates the general MLM project settings and name
	 * @param array $project_id 
	 * @param string $timestamp 
	 * @return array [name, settings-array]
	 */
	private static function getProjectGeneralSettings($project_id, $timestamp) {
		$settings = self::getProjectSettings($project_id);
		$pmd = self::getProjectMetadata($project_id);
		$name = "REDCapTranslation_Settings_pid{$settings["projectId"]}_{$timestamp}";
		$proj_export = array(
			"creator" => "REDCap MLM",
			"version" => $settings["version"],
			"timestamp" => date("Y-m-d H:i:s"),
			"instructions" => RCView::getLangStringByKey("multilang_175"),
			"langActive" => array(),
			"myCapActive" => array(),
		);
		// Language Active and MyCap status
		foreach ($settings["langs"] as $key => $this_lang) {
			$proj_export["langActive"][$key] = $this_lang["active"] == true;
			$proj_export["myCapActive"][$key] = $this_lang["mycap-active"] == true;
		}
		foreach ($settings as $key => $val) {
			switch ($key) {
				case "langs":
				case "projectId":
				case "excludedSettings":
				case "excludedFields":
				case "excludedAlerts":
				case "alertSources":
				case "asiSources":
					// Skip
					break;
				default: 
					$proj_export[$key] = $val;
			}
		}
		// Form-based stuff
		foreach ($pmd["forms"] as $form => $form_data) {
			// dataEntryEnabled
			foreach ($settings["langs"] as $lang_id => $this_lang) {
				$proj_export["dataEntryEnabled"][$form][$lang_id] = isset($this_lang["dd"]["form-active"][$form]);
			}
			if ($form_data["isSurvey"]) {
				// surveyEnabled
				foreach ($settings["langs"] as $lang_id => $this_lang) {
					$proj_export["surveyEnabled"][$form][$lang_id] = isset($this_lang["dd"]["survey-active"][$form]);
				}
				// excludedSettings
				foreach (self::$excludeableSurveySettings as $ss) {
					$proj_export["excludedSettings"][$form][$ss] = (isset($settings["excludedSettings"][$form][$ss]) && $settings["excludedSettings"][$form][$ss] == true);
				}
			}
		}
		// Field-based stuff
		// excludedFields
		foreach ($pmd["fields"] as $field => $field_data) {
			if ($field_data["isPROMIS"]) continue; // Skip PROMIS
			$proj_export["excludedFields"][$field] = (isset($settings["excludedFields"][$field]) && $settings["excludedFields"][$field] == true);
		}
		// Alert-based stuff
		foreach ($pmd["alerts"] as $alert_id => $alert_data) {
			// excludedAlerts
			$proj_export["excludedAlerts"][$alert_data["uniqueId"]] = (isset($settings["excludedAlerts"][$alert_id]) && $settings["excludedAlerts"][$alert_id] == true);
			// alertSources
			$proj_export["alertSources"][$alert_data["uniqueId"]] = $settings["alertSources"][$alert_id] ?? "field";
		}
		// ASI-based stuff
		foreach ($pmd["asis"] as $_=> $asi_data) {
			// asiSources
			$proj_export["asiSources"][$asi_data["form"]] = $settings["asiSources"][$asi_data["form"]] ?? "field";
		}

		return [$name, $proj_export];
	}

	/**
	 * Deletes a snapshot
	 * @param mixed $project_id 
	 * @param mixed $user 
	 * @param mixed $snapshot_id 
	 * @return array 
	 */
	public static function deleteSnapshot($project_id, $user, $snapshot_id) {
		$settings = self::getProjectSettings($project_id);
		if ($settings["disabled"]) {
			return self::response(false, RCView::getLangStringByKey("multilang_546")); //= Multi-Language Management is disabled for this project.
		}
		$user_info= User::getUserInfo($user);
		$ui_id = $user_info["ui_id"];
		$snapshot_id = $snapshot_id * 1;
		try {
			// Get the snapshot row to extract the edoc_id
			$snapshot_raw = self::readSnapshotRaw($snapshot_id);
			if ($snapshot_raw === false || $snapshot_raw["project_id"] != $project_id) {
				throw new Exception(RCView::getLangStringByKey("multilang_548")); //= This snapshot does not exist!
			}
			// Delete the snapshot file
			$edoc_id = $snapshot_raw["edoc_id"];
			$deleted = Files::deleteFileByDocId($edoc_id, $project_id);
			if ($deleted == false) {
				throw new Exception(RCView::getLangStringByKey("multilang_549")); //= Failed to delete snapshot file from edocs.
			}
			// Set as deleted in the snapshots table
			if (self::updateSnapshotEntry($snapshot_id, $ui_id) === false) {
				throw new Exception(RCView::getLangStringByKey("multilang_550")); //= Failed to update the snapshots table.
			}
			// Log action
			self::log("Delete snapshot", "The snapshot [{$snapshot_id}] with edoc id [{$edoc_id}] has been deleted.", $user, $project_id);
			// Get updated entry
			$snapshot = self::readSnapshots($project_id, $snapshot_id)[$snapshot_id];
			// And set as response
			$reponse = self::response(true, "", array(
				"snapshot" => $snapshot,
			));
		}
		catch(Throwable $ex) {
			$reponse = self::exceptionResponse($ex);
		}
		return $reponse;
	}

	#endregion

	#region Export General Settings

	/**
	 * Renders a data structure for exporting a language (final output will
	 * be prepared in the browser, including file format - json or csv)
	 * @param mixed $project_id 
	 * @param mixed $user 
	 * @param mixed $data 
	 * @return array 
	 */
	public static function getGeneralSettingsExportFile($project_id, $user) {
		// Log
		self::log("Export", "General MLM Settings were exported.", $user, $project_id);
		// Create export
		$timestamp = date("Ymd-His");

		list($name, $general_settings) = self::getProjectGeneralSettings($project_id, $timestamp);

		// Return response
		return self::response(true, "", [
			"name" => $name,
			"content" => $general_settings,
		]);
	}

	#endregion

	#region Usage Statistics

	/**
	 * Gets MLM usage statistics
	 * @return array 
	 */
	public static function getUsageStats() {
		$stats = array();
		$sql = "SELECT proj.project_id 
					, GROUP_CONCAT(CASE WHEN kcols.name='display' THEN CONCAT(kcols.lang_id, ',', kcols.value) END SEPARATOR '#=X=#') AS 'all_langs'
					, GROUP_CONCAT(DISTINCT active.lang_id) AS 'active_keys'
					, MAX(CASE WHEN mlm.name='debug' THEN 1 END) AS 'debug'
					, MAX(CASE WHEN mlm.name='disabled' THEN 1 END) AS 'disabled'
					, MAX(CASE WHEN mlm.name='admin-enabled' THEN 1 END) AS 'admin_enabled'
					, MAX(CASE WHEN mlm.name='admin-disabled' THEN 1 END) AS 'admin_disabled'
					, proj.app_title
					, proj.status
					, proj.completed_time
					, proj.project_language
				FROM redcap_projects AS proj
				LEFT JOIN redcap_multilanguage_config AS mlm
					ON mlm.project_id = proj.project_id
					AND mlm.name IN ('key','admin-disabled','admin-enabled','debug','disabled') 
				LEFT JOIN redcap_multilanguage_config AS kcols
					ON mlm.project_id = kcols.project_id 
						AND mlm.value = kcols.lang_id
				LEFT JOIN redcap_multilanguage_config AS active
					ON mlm.project_id = active.project_id 
						AND mlm.value = active.lang_id 
						AND active.name='active'
				WHERE ISNULL(proj.date_deleted)
				GROUP BY proj.project_id
				ORDER BY proj.project_id";
		$result = db_query($sql);
		while ($row = db_fetch_assoc($result)) {
			$proj_status = "development";
			if ($row["date_completed"]) {
				$proj_status = "completed";
			}
			else if ($row["status"] == "2") {
				$proj_status = "analysis";
			}
			else if ($row["status"] == "1") {
				$proj_status = "production";
			}
			$not_empty = function($s) { return !empty($s); };
			$active_keys = array_filter(explode(",", $row["active_keys"] ?? ""), $not_empty);
			// Sort out languages
			$lang_kv_pairs = explode("#=X=#", $row["all_langs"] ?? "");
			$langs = [];
			foreach ($lang_kv_pairs as $lang_kv) {
				if (!empty($lang_kv)) {
					$kv = explode(",", $lang_kv, 2);
					$langs[$kv[0]] = RCView::escape($kv[1], true);
				}
			}
			$n_langs = count($langs);
			$n_active_langs = count($active_keys);
			$active_langs = array();
			foreach ($active_keys as $key) {
				$active_langs[$key] = $langs[$key];
			}

			$stat = [
				"projectTitle" => strip_tags(str_replace(array("<br>","<br/>","<br />"), array(" "," "," "), html_entity_decode($row['app_title'], ENT_QUOTES))),
				"projectId" => $row["project_id"],
				"projectStatus" => $proj_status,
				"projectLanguage" => $row["project_language"] == "English" ? "" : RCView::escape($row["project_language"], true),
				"nAllLangs" => $n_langs,
				"nActiveLangs" => $n_active_langs,
				"allLangs" => $langs,
				"activeLangs" => $active_langs,
				"mlmDisabled" => $row["disabled"] == 1,
				"mlmDebug" => $row["debug"] == 1,
				"mlmAdminDisabled" => $row["admin_disabled"] == 1,
				"mlmAdminEnabled" => $row["admin_enabled"] == 1
			];
			$stat["hasMlm"] = $stat["mlmDisabled"] || $stat["mlmDebug"] || $stat["mlmAdminDisabled"] || $stat["mlmAdminEnabled"] || $n_langs > 0;
			$stats[] = $stat;
		}
		return self::response(true, "", ["usageStats" => $stats]);
	}

	#endregion

	#region Public Helpers

	/**
	 * Gets a data dictionary translation
	 * @param Context $context 
	 * @param string $type 
	 * @param string $name 
	 * @param string $index 
	 * @param mixed $fallback_override When set to a value other than `false`, this will be returned in case there is no translation 
	 * @return string The translated value 
	 */
	public static function getDDTranslation(Context $context, $type, $name, $index = "", $fallback_override = false) {
		$lang_id = self::getCurrentLanguage($context);
		$meta = self::getProjectMetadata($context->project_id);
		$type_prefix = explode("-", $type, 2)[0];
		$field_type = $type_prefix == "field" ? $meta["fields"][$name]["type"] : "";
		if ($field_type == "yesno" && $index != "") {
			$ref_value = $GLOBALS["lang"][$index == 1 ? "design_100" : "design_99"];
		}
		else if ($field_type == "truefalse" && $index != "") {
			$ref_value = $GLOBALS["lang"][$index == 1 ? "design_186" : "design_187"];
		}
		else {
			$ref_value = $meta[self::$ref_map[$type_prefix]][$name][$type]["reference"] ?? "";
		}
		if ($lang_id === false) {
			return $ref_value;
		}
		$settings = self::getProjectSettings($context->project_id);
		$lang = $settings["langs"][$lang_id];
		$fallback_lang = $settings["langs"][$settings["fallbackLang"]];
		$is_ref_lang = $lang_id == $settings["refLang"];
		if ($field_type == "yesno" && $index != "") {
			$translation = $index == "1" 
				? ($lang["ui"]["design_100"]["translation"] ?? "") // Yes
				: ($lang["ui"]["design_99"]["translation"] ?? ""); // No
		}
		else if ($field_type == "truefalse" && $index != "") {
			$translation = $index == "1" 
				? ($lang["ui"]["design_186"]["translation"] ?? "")  // True
				: ($lang["ui"]["design_187"]["translation"] ?? ""); // False
		}
		else {
			$translation = $lang["dd"][$type][$name][$index]["translation"] ?? "";
		}
		if (self::isEmpty($translation) && $fallback_override !== false) {
			return $fallback_override;
		}
		if (self::isEmpty($translation) && !$is_ref_lang) {
			if ($field_type == "yesno") {
				$translation = $index == "1" 
					? ($fallback_lang["ui"]["design_100"]["translation"] ?? "") // Yes
					: ($fallback_lang["ui"]["design_99"]["translation"] ?? ""); // No
			}
			else if ($field_type == "truefalse") {
				$translation = $index == "1" 
					? ($fallback_lang["ui"]["design_186"]["translation"] ?? "")  // True
					: ($fallback_lang["ui"]["design_187"]["translation"] ?? ""); // False
			}
			else {
				$translation = $fallback_lang["dd"][$type][$name][$index]["translation"] ?? "";
			}
		}
		if (self::isEmpty($translation)) {
			$translation = $ref_value;
		}
		return $translation;
	}
	/**
	 * A mapper for accessing reference values (used by getDDTranslation())
	 * @var string[]
	 */
	private static $ref_map = array(
		"alert" => "alerts",
		"asi" => "asis",
		"event" => "events",
		"field" => "fields",
		"form" => "forms",
		"matrix" => "matrixGroups",
		"sq" => "surveyQueue",
		"survey" => "surveys",
		"pdf" => "pdfCustomizations",
		"protmail" => "protectedMail",
	);

	/**
	 * Gets the translation of a choice label (used for dropdown replacement, see 
	 * DataEntry/piping_dropdown_replace.php). The language is determined automatically.
	 * @param Context $context
	 * @param string $field_name 
	 * @param string $code 
	 * @return string 
	 */
	public static function getChoiceLabelTranslation(Context $context, $field_name, $code) {
		$lang_id = self::getCurrentLanguage($context);
		$settings = self::getProjectSettings($context->project_id);
		$is_ref_lang = $lang_id == $settings["refLang"];
		$Proj = new Project($context->project_id);
		$Proj_metadata = $Proj->getMetadata();
		// Yes/No or True/False?
		if ($Proj_metadata[$field_name]["element_type"] == "yesno") {
			return self::getDDTranslation($context, "field-enum", $field_name, $code);
		}
		else if ($Proj_metadata[$field_name]["element_type"] == "truefalse") {
			return self::getDDTranslation($context, "field-enum", $field_name, $code);
		}
		// Determine enum type (matrix or field enum)
		if ($Proj_metadata[$field_name]["grid_name"] != null) {
			$enum_type = "matrix-enum";
			$enum_name = $Proj_metadata[$field_name]["grid_name"];
			$ref_type = "matrixGroups";
		}
		else {
			$enum_type = "field-enum";
			$enum_name = $field_name;
			$ref_type = "fields";
		}
		$translation = $settings["langs"][$lang_id]["dd"][$enum_type][$enum_name][$code]["translation"] ?? "";
		// Need to use fallback language?
		if (self::isEmpty($translation) && !$is_ref_lang) {
			$lang_id = $settings["fallbackLang"];
			$translation = $settings["langs"][$lang_id]["dd"][$enum_type][$enum_name][$code]["translation"];
		}
		// Need to use ref lang?
		if (self::isEmpty($translation)) {
			$ref = self::getProjectMetadata($context->project_id);
			$translation = $ref[$ref_type][$enum_name][$enum_type][$code]["reference"] ?? "";
		}
		return $translation;
	}

	/**
	 * Gets the current language, taking into account designated fields, and cookies
	 * @param Context|null $context A context, if available
	 * @param boolean $do_not_use_ultimate_fallbacks When true, does not use refLang or first active as fallback (defaults to false)
	 * @return string|false A language id, or an empty string if no language preference can be determined, or false, if there are no languages at all 
	 */
	public static function getCurrentLanguage(?Context $context = null, $do_not_use_ultimate_fallbacks = false) {
		$active_langs = self::getActiveLangs($context);
		if (count($active_langs) == 0) {
			// No active langs - signal with false
			return false;
		}
		// In surveys with a "magic" URL parameter, use this if it matches an available language
		if ($context->is_survey && isset($_GET[self::LANG_SURVEY_URL_OVERRIDE]) && in_array($_GET[self::LANG_SURVEY_URL_OVERRIDE], $active_langs, true)) {
			return "".$_GET[self::LANG_SURVEY_URL_OVERRIDE];
		}
		// Unless in data entry context, default to the cookie value, if set
		$lang_id = $context !== null && $context->is_dataentry ? "" : ($_COOKIE[self::SURVEY_COOKIE] ?? "");
		// Determine based on context
		if ($context == null) {
			if ($lang_id == "") {
				// Use initial language
				$settings = self::getSystemSettings();
				$lang_id = $settings["initialLang"];
			}
		}
		else if ($context != null) do {
			// Does the context already include a language preference? Does it exist?
			// If so, the context lang takes precedence over the cookie
			if ($context->lang_id && in_array($context->lang_id, $active_langs)) {
				$lang_id = $context->lang_id;
				break;
			}
			if ($lang_id == "") {
				// Only if there is no cookie value, check alternative sources
				// Does the context include a project id?
				if ($context->project_id) {
					$settings = self::getProjectSettings($context->project_id);
					// Data entry? Use from UIState
					if ($context->is_dataentry || ($context->is_pdf && !$context->is_survey && $context->user_id != null) || ($context->user_id != null && !System::isSurveyRespondent($context->user_id))) {
						$lang_id = UIState::getUIStateValue($context->project_id, self::UISTATE_OBJECT, self::UISTATE_LANGPREF) ?? $settings["refLang"];
						break;
					}
					else {
						// Check the designated field - if set it takes precedence
						$desginated = self::getDesignatedFieldValue($context);
						if (!self::isEmpty($desginated) && in_array($desginated, $active_langs)) {
							$lang_id = $desginated;
							break;
						}
					}
					// Set to default language
					if ($lang_id == "" && !$do_not_use_ultimate_fallbacks) {
						$lang_id = $settings["refLang"];
						break;
					}
				}
				else {
					// Set to initial systen language
					$settings = self::getSystemSettings();
					$lang_id = $settings["initialLang"];
					break;
				}
			}
		} while (false);
		// Ensure that the value is a valid active lang
		if (!in_array($lang_id, $active_langs)) {
			$lang_id = "";
		}
		// Use first active lang as fallback
		if ($lang_id == "" && !$do_not_use_ultimate_fallbacks) {
			$lang_id = $active_langs[0];
		}
		// Ensure that lang is in active langs or use first active as fallback
		return "$lang_id";
	}

	/**
	 * Gets the default language set for the project
	 * @param Context $context A context
	 * @return string|null A language id or NULL if MLM is not active
	 */
	public static function getDefaultLanguage(Context $context) {
		$active_langs = self::getActiveLangs($context);
		if (count($active_langs) == 0) {
			// No active langs
			return null;
		}
		$lang_id = null;
		if ($context->project_id) {
			$settings = self::getProjectSettings($context->project_id);
			$lang_id = $settings["refLang"];
		}
		return $lang_id;
	}

	/**
	 * Gets the user's preferred language (data entry only)
	 * @param Context|null $context 
	 * @return string|false A language id, or an empty string if no language preference can be determined, or false, if there are no languages at all 
	 */
	public static function getUserPreferredLanguage(?Context $context = null) {
		// Must have a data entry context
		if ($context == null || !$context->project_id || !($context->is_dataentry || ($context->is_pdf && !$context->is_survey && $context->user_id != null) || ($context->user_id != null && !System::isSurveyRespondent($context->user_id)))) return false;
		$active_langs = self::getActiveLangs($context);
		if (count($active_langs) == 0) {
			// No active langs - signal with false
			return false;
		}
		// Read from UI state
		$lang_id = UIState::getUIStateValue($context->project_id, self::UISTATE_OBJECT, self::UISTATE_LANGPREF);
		// Ensure that lang is in active langs
		return in_array($lang_id, $active_langs) ? $lang_id : null;
	}

	/**
	 * Gets the UI item translation for the specified language file key for the given context
	 * @param Context $context
	 * @param string $key Language file key
	 * @return string The translation or default (or "LANGUAGE KEY 'X' IS NOT DEFINED!")
	 */
	public static function getUITranslation(Context $context, $key) {
		$lang_id = self::getCurrentLanguage($context);
		if ($lang_id !== false) {
			$settings = self::getProjectSettings($context->project_id);
			$translation = $settings["langs"][$lang_id]["ui"][$key]["translation"] ?? "";
			if (self::isEmpty($translation) && $lang_id != $settings["refLang"]) {
				$translation = $settings["langs"][$settings["fallbackLang"]]["ui"][$key]["translation"] ?? "";
			}
			if (self::isEmpty($translation)) {
				$translation = $settings["langs"][$settings["refLang"]]["ui"][$key]["translation"] ?? "";
			}
			if (!self::isEmpty($translation)) {
				return $translation;
			}
		}
		$entry = $GLOBALS["lang"][$key] ?? "LANGUAGE KEY '{$key}' IS NOT DEFINED!";
		return $entry;
	}

	/**
	 * Indicates whether the project has multilanguage enabled in the project AND 
	 * at least one language defined (not necessarily active).
	 * @param string|int $project_id 
	 * @return bool 
	 */
	public static function hasLanguages($project_id) {
		if ($project_id) {
			$proj_settings = self::getProjectSettings($project_id);
			return !$proj_settings["disabled"] && count($proj_settings["langs"]);
		}
		return false;
	}

	/**
	 * Gets the Right-to-left status of a language (in the given project or system)
	 * @param null|string|int $project_id 
	 * @param string $lang_id 
	 * @return bool|null (null when the language does not exist)
	 */
	public static function isRtl($project_id, $lang_id) {
		if ($project_id) {
			$proj_settings = self::getProjectSettings($project_id);
			if (isset($proj_settings["langs"][$lang_id])) {
				return $proj_settings["langs"][$lang_id]["rtl"];
			}
		}
		else {
			$system_settings = self::getSystemSettings();
			if (isset($system_settings["langs"][$lang_id])) {
				return $system_settings["langs"][$lang_id]["rtl"];
			}
		}
		return null;
	}

	/**
	 * Indicates whether multilanguage is enabeled on the system and/or in the project
	 * (if it's off on the system level, false will be returned)
	 * @param mixed $project_id ("SYSTEM" or null for system level)
	 * @return bool 
	 */
	public static function isActive($project_id = null) {
		$system_settings = self::getSystemSettings();
		if ($project_id == null || $project_id == "SYSTEM") {
			return !$system_settings["disabled"];
		}
		$proj_settings = self::getProjectSettings($project_id);
		if ($system_settings["disabled"]) {
			// Inactive when turned off system-wide
			return false;
		}
		if ($proj_settings["disabled"]) {
			// Disabled by the project user
			return false;
		}
		if ($system_settings["require-admin-activation"]) {
			if ($proj_settings["admin-disabled"]) {
				// Disabled by the admin
				return false;
			}
			if ($proj_settings["admin-enabled"] == false && count($proj_settings["langs"]) == 0) {
				// Not enabled and no languages
				return false;
			}
		}
		// It must be enabled
		return true; 
	}

	/**
	 * Indicates whether the Multi-Language Management project menu item should be shown
	 * @param mixed $project_id
	 * @return bool 
	 */
	public static function showProjectMenuItem($project_id) {
		$system_settings = self::getSystemSettings();
		// Do not show when disabled system-wide
		if ($system_settings["disabled"] == true) return false;
		// Always show for admins (unless impersonating)
		if (UserRights::isSuperUserNotImpersonator()) return true;
		// Check project
		$proj_settings = self::getProjectSettings($project_id);
		// Disabled by an admin? Then do not show
		if ($proj_settings["admin-disabled"]) return false;
		// Admin activation required?
		if ($system_settings["require-admin-activation"]) {
			// Show when enabled by an admin OR some languages are already configured
			if ($proj_settings["admin-enabled"] || count($proj_settings["langs"]) > 0) return true;
			// Otherwise, do not show
			return false;
		}
		// We should never get here .. when in doubt ...
		return true;
	}

	/**
	 * Indicates whether MLM must be activated by an admin in each project
	 * @return bool 
	 */
	public static function isAdminActivationRequired() {
		$system_settings = self::getSystemSettings();
		return $system_settings["require-admin-activation"] == true;
	}

	/**
	 * Indicates whether MLM has been disabled in a project by an admin
	 * @return bool 
	 */
	public static function isAdminDisabled($project_id) {
		$project_settings = self::getProjectSettings($project_id);
		return $project_settings["admin-disabled"] == true;
	}

	/**
	 * Gets a list of available system languages
	 * @return array Array [key => [key, display] ]
	 */
	public static function getSystemLanguages() {
		$settings = self::getSystemSettings();
		$langs = self::sortLanguages($settings["langs"]);
		$sys_langs = array();
		foreach ($langs as $lang_id) {
			$this_lang = $settings["langs"][$lang_id];
			if (empty($this_lang["guid"])) {
				// Retrospectively add a GUID to each system language
				$this_lang["guid"] = self::addSystemLanguageGuid($this_lang["key"]);
			}
			if ($this_lang["visible"]) {
				$sys_langs[$this_lang["key"]] = array(
					"key" => $this_lang["key"],
					"display" => $this_lang["display"],
					"guid" => $this_lang["guid"]
				);
			}
		}
		return $sys_langs;
	}

	/**
	 * Gets the system language key for the given GUID (or null if not found)
	 * @param string $guid 
	 * @return string|null 
	 */
	public static function getSystemLanguageKeyFromGuid($guid) {
		$sys_langs = self::getSystemLanguages();
		foreach ($sys_langs as $key => $data) {
			if ($data["guid"] == $guid) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * Adds exception information to an AJAX response (for super users only!)
	 * @param Throwable $ex 
	 * @return (false|string)[]|(false|string|(string|int)[])[] 
	 */
	public static function exceptionResponse(\Throwable $ex) {
		$response = array(
			"success" => false,
			"error" => ensureUTF(RCView::tt_strip_tags("multilang_547")), //= An unspecified error has occurred.
		);
		if (UserRights::isSuperUserOrImpersonator()) {
			$response["exception"] = array (
				"msg" => ensureUTF($ex->getMessage()),
				"file" => ensureUTF($ex->getFile()),
				"line" => ensureUTF($ex->getLine()),
				"trace" => ensureUTF($ex->getTraceAsString()),
			);
		}
		return $response;
	}

	#endregion

	#region Helper Maps

	/**
	 * List of possible global settings with metadata (PROJECT)
	 * @var (string[]|(string|false)[])[]
	 */
	private static $global_settings_project = array(
		"debug" =>                     [ "type" => "bool",   "default" => false, "admin" => true  ],
		"admin-enabled" =>             [ "type" => "bool",   "default" => false, "admin" => true  ],
		"admin-disabled" =>            [ "type" => "bool",   "default" => false, "admin" => true  ],
		"allow-from-file" =>           [ "type" => "bool",   "default" => false, "admin" => true  ],
		"allow-from-scratch" =>        [ "type" => "bool",   "default" => false, "admin" => true  ],
		"optional-subscription" =>     [ "type" => "bool",   "default" => false, "admin" => true  ],
		"allow-ui-overrides" =>        [ "type" => "bool",   "default" => false, "admin" => true  ],
		"refLang" =>                   [ "type" => "string", "default" => ""   , "admin" => false ] ,
		"fallbackLang" =>              [ "type" => "string", "default" => ""   , "admin" => false ] ,
		"disabled" =>                  [ "type" => "bool",   "default" => false, "admin" => false ],
		"highlightMissingDataentry" => [ "type" => "bool",   "default" => false, "admin" => false ],
		"highlightMissingSurvey" =>    [ "type" => "bool",   "default" => false, "admin" => false ],
		"autoDetectBrowserLang" =>     [ "type" => "bool",   "default" => false, "admin" => false ],
		"alertSources" =>              [ "type" => "json",   "default" => ""   , "admin" => false ],
		"asiSources" =>                [ "type" => "json",   "default" => ""   , "admin" => false ],
		"excludedAlerts" =>            [ "type" => "list",   "default" => ""   , "admin" => false ],
		"excludedFields" =>            [ "type" => "list",   "default" => ""   , "admin" => false ],
		"excludedSettings" =>          [ "type" => "json",   "default" => ""   , "admin" => false ],
		"designatedField" =>           [ "type" => "string", "default" => ""   , "admin" => false ],
	);

	/**
	 * List of possible global settings with metadata (SYSTEM)
	 * @var (string[]|(string|false)[])[]
	 */
	private static $global_settings_system = array(
		"debug" =>                     [ "type" => "bool",   "default" => false ],
		"require-admin-activation" =>  [ "type" => "bool",   "default" => false ],
		"disabled" =>                  [ "type" => "bool",   "default" => false ],
		"disable-from-file" =>         [ "type" => "bool",   "default" => false ],
		"disable-from-scratch" =>      [ "type" => "bool",   "default" => false ],
		"force-subscription" =>        [ "type" => "bool",   "default" => false ],
		"disable-ui-overrides" =>      [ "type" => "bool",   "default" => false ],
		"highlightMissing" =>          [ "type" => "bool",   "default" => false ],
		"refLang" =>                   [ "type" => "string", "default" => "" ],
		"initialLang" =>               [ "type" => "string", "default" => "" ],
	);

	/**
	 * List of possible language-specific settings with metadata (PROJECT)
	 * @var (string[]|(string|false)[])[]
	 */
	private static $lang_settings_project = array(
		"key" =>                       [ "type" => "string", "default" => "" ],
		"display" =>                   [ "type" => "string", "default" => "" ],
		"notes" =>                     [ "type" => "string", "default" => "" ],
		"sort" =>                      [ "type" => "string", "default" => "" ],
		"rtl" =>                       [ "type" => "bool",   "default" => false ],
		"active" =>                    [ "type" => "bool",   "default" => false ],
		"mycap-active" =>              [ "type" => "bool",   "default" => false ],
		"subscribed" =>                [ "type" => "bool",   "default" => false ],
		"syslang" =>                   [ "type" => "string", "default" => "" ],
		"recaptcha-lang" =>            [ "type" => "string", "default" => "" ],
	);

	/**
	 * List of possible language-specific settings with metadata (SYSTEM)
	 * @var (string[]|(string|false)[])[]
	 */
	private static $lang_settings_system = array(
		"key" =>                       [ "type" => "string", "default" => "" ],
		"guid" =>                      [ "type" => "string", "default" => "" ],
		"display" =>                   [ "type" => "string", "default" => "" ],
		"notes" =>                     [ "type" => "string", "default" => "" ],
		"sort" =>                      [ "type" => "string", "default" => "" ],
		"rtl" =>                       [ "type" => "bool",   "default" => false ],
		"active" =>                    [ "type" => "bool",   "default" => false ],
		"visible" =>                   [ "type" => "bool",   "default" => false ],
	);

	#endregion

	#region Misc Helpers 

	/**
	 * Determines whether another user is on the MLM setup page. If so, returns the other user's username.
	 * @return array{user_id:string,full_name:string,email:string,timer:string}|boolean User name or false
	 */
	public static function checkSimultaneousUsers() {
		global $autologout_timer;
		// Need to use autologout timer value to determine span of time to evaluate
		if (empty($autologout_timer) || $autologout_timer == 0 || !is_numeric($autologout_timer)) return false;
		// If for some reason there is no session, then assume the other user won't have a session, which negates checking here.
		if (!Session::sessionId()) return false;
		// Check sessions table using log_view table session_id values
		// Set window of time after which the user should have been logged out (based on system-wide parameter)
		$bufferTime = 3; // X minutes of buffer time (2 minute auto-logout warning + 1 minute buffer for lag, slow page load, etc.)
		$logoutWindow = date("Y-m-d H:i:s", mktime(date("H"), date("i") - (\Authentication::AUTO_LOGOUT_RESET_TIME + $bufferTime), date("s"), date("m"), date("d"), date("Y")));
		// For better performance of the big query below, first get minimum log_view_id
		$sql = "SELECT MIN(log_view_id) FROM redcap_log_view WHERE ts >= '$logoutWindow'";
		$q = db_query($sql);
		$logoutWindowLogViewId = db_result($q, 0);
		if ($logoutWindowLogViewId == '') $logoutWindowLogViewId = '0';
		$user = db_escape(USERID);
		// Check latest log_view listing in past [MaxLogoutTime] minutes for MLM setup
		// Separate SQL for project and control center setup page
		$project_id = defined("PROJECT_ID") ? PROJECT_ID : null;
		if ($project_id != null) {
			$sql = "SELECT a.log_view_id, a.session_id, a.user, a.page
					FROM redcap_log_view a
					INNER JOIN redcap_user_rights u ON a.user = u.username AND u.project_id = a.project_id
					INNER JOIN redcap_user_information i ON i.username = u.username
					LEFT JOIN redcap_user_roles ur ON u.role_id = ur.role_id
					WHERE a.project_id = $project_id
						AND a.log_view_id >= $logoutWindowLogViewId
						AND a.user != '$user'
						AND (
							a.page = 'MultiLanguageController:projectSetup'
							OR
							(a.page = 'MultiLanguageController:ajax' and a.form_name = 'proj')
						)
						AND (
							(u.design = 1 AND ur.role_id IS NULL)
							OR
							(ur.design = 1 AND ur.role_id IS NOT NULL)
							OR
							i.super_user = 1
						)
						AND a.log_view_id = (
							SELECT b.log_view_id 
							FROM redcap_log_view b 
							WHERE b.user = a.user 
								AND (
									(b.page NOT LIKE '%ajax%' AND b.page != 'ProjectGeneral/keep_alive.php')
									OR 
									(b.page = 'MultiLanguageController:ajax' AND b.form_name = 'proj')
								)
								AND ISNULL(b.miscellaneous)
							ORDER BY b.log_view_id DESC 
							LIMIT 1
						)
					ORDER BY a.log_view_id DESC 
					LIMIT 1";
		}
		else {
			$sql = "SELECT a.log_view_id, a.session_id, a.user, a.page
					FROM redcap_log_view a
					WHERE ISNULL(a.project_id)
						AND a.log_view_id >= $logoutWindowLogViewId
						AND a.user != '$user'
						AND (
							a.page = 'MultiLanguageController:systemConfig'
							OR
							(a.page = 'MultiLanguageController:ajax' and a.form_name = 'sys')
						)
						AND a.log_view_id = (
							SELECT b.log_view_id 
							FROM redcap_log_view b 
							WHERE b.user = a.user 
								AND (
									b.page NOT LIKE '%ajax%' 
									OR 
									(b.page = 'MultiLanguageController:ajax' AND b.form_name = 'sys')
								)
								AND ISNULL(b.miscellaneous)
							ORDER BY b.log_view_id DESC 
							LIMIT 1
						)
					ORDER BY a.log_view_id DESC 
					LIMIT 1";
		}
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			// Now use the session_id from log_view table to see if they're still logged in (check sessions table)
			$log_view_id = db_result($q, 0, "log_view_id");
			$session_id = db_result($q, 0, "session_id");
			$other_user = db_result($q, 0, "user");
			$page = db_escape(db_result($q, 0, "page"));
			$sql = "SELECT 1 
					FROM redcap_sessions 
					WHERE session_id = '$session_id' AND session_expiration >= '$logoutWindow' 
					LIMIT 1";
			$q = db_query($sql);
			if (db_num_rows($q) > 0)
			{
				// We have 2 users on same form/record. Prevent loading of page.
				// First remove the new row just made in log_view table (otherwise, can simply refresh page to gain access)
				$sql = "UPDATE redcap_log_view 
						SET `miscellaneous` = '// Simultaneous user detected on MLM setup page' 
						WHERE `log_view_id` > $log_view_id
						AND `user` = '$user'
						AND `page` = '$page'";
				$q = db_query($sql);
				// Obtain other user's email/name for display
				$sql = "SELECT * 
						FROM redcap_user_information 
						WHERE username = '" . db_escape($other_user) . "'";
				$q = db_query($sql);
				$other_user_email = db_result($q, 0, "user_email");
				$other_user_name = db_result($q, 0, "user_firstname") . " " . db_result($q, 0, "user_lastname");
				$other_is_super_user = db_result($q, 0, "super_user");
				// Check MLM status in projects
				if ($project_id != null) {
					$system_settings = MultiLanguage::getSystemSettings();
					$project_settings = MultiLanguage::getProjectSettings($project_id, true);
					if (defined("SUPER_USER") && SUPER_USER && $system_settings["require-admin-activation"]) {
						// Check whether there is no access to the project MLM page (unless the other user is also a super user)
						if (!$other_is_super_user && ((!$project_settings["admin-enabled"] && count($project_settings["langs"]) == 0) || $project_settings["admin-disabled"])) {
							// Ignore simultaneous use - the other user is on an access denied page
							return false;
						}
					} 
				}
				// Return user info of the user already on the page
				return array(
					"user_id" => $other_user,
					"full_name" => $other_user_name,
					"email" => $other_user_email,
					"timer" => $autologout_timer,
				);
			}
		}
		return false;
	}

	/**
	 * Creates the JSON for MLM JavaScript initialization.
	 * @param mixed $arr The data that needs to be converted to JSON
	 * @return string JSON 
	 */
	public static function convertAssocArrayToJSON($arr) {
		// Note: JSON_FORCE_OBJECT is essential - the JS implemetation relies on objects only!
		$json = json_encode($arr, JSON_FORCE_OBJECT);
		if (empty($json)) {
			throw new Exception("Failed to encode JSON: " . json_last_error_msg());
		}
		return $json;
	}

	/**
	 * A logging helper
	 * @param mixed $cat Category, e.g. Create snapshot
	 * @param mixed $message The detailed message
	 * @param mixed $user 
	 * @param mixed $pid 
	 * @return void 
	 */
	private static function log($cat, $message, $user, $pid) {
		$desc = "Multi-Language Management: " . $message;
		Logging::logEvent("", "redcap_multilanguage_config", "MANAGE", null, $cat, $desc, "", $user, $pid);
	}

	/**
	 * Gets a response object to return for ajax requests
	 * @param bool $success 
	 * @param string $error 
	 * @param array $data 
	 * @return array 
	 */
	private static function response($success = true, $error = "", $data = array()) {
		$response = array(
			"success" => $success,
			"error" => ensureUTF($error),
		);
		foreach ($data as $key => $item) {
			$response[ensureUTF($key)] = ensureUTF($item);
		}
		return $response;
	}

	/**
	 * Checks whether the combination of type, name, and index is valid
	 * @param Array $pmd Project metadata
	 * @param Array $pmd_map Project metadata map
	 * @param string $type 
	 * @param string $name 
	 * @param string $index 
	 * @return bool 
	 */
	private static function checkTypeNameIndex($pmd, $pmd_map, $type, $name, $index) {
		// Disallow all types that are not defined as allowed
		if (!(array_key_exists($type, $pmd_map) || in_array($type, self::$allowedMetaDataTypes))) return false;

		// Check that type/name/index combination is compatible with project metadata
		if (!isset($pmd_map[$type][$name][$index]) || $pmd_map[$type][$name][$index] !== true) {
			return false;
		}

		// Check if field- types are consistent with metadata
		if (starts_with($type, "field-")) {
			$fmd = $pmd["fields"][$name];
			if (array_key_exists($type, $fmd)) {
				// Only allow saving if there is a non-null reference value
				if ($fmd[$type] === null) return false;
				if ($type == "field-enum" || $type == "field-actiontag") {
					if (is_array($fmd[$type][$index]) && $fmd[$type][$index]["reference"] === null) return false;
				}
				else {
					if (is_array($fmd[$type]) && $fmd[$type]["reference"] === null) return false;
				}
			}
		}
		// Check if matrix- types are consistent with metadata
		if (starts_with($type, "matrix-")) {
			$mmd = $pmd["matrixGroups"][$name];
			if (array_key_exists($type, $mmd)) {
				// Only allow saving if there is a non-null reference value
				if ($mmd[$type] === null) return false;
				if ($type == "matrix-enum") {
					if (is_array($mmd[$type][$index]) && $mmd[$type][$index]["reference"] === null) return false;
				}
				else {
					if (is_array($mmd[$type]) && $mmd[$type]["reference"] === null) return false;
				}
			}
		}

		// More checking?

		return true;
	}

	/**
	 * Determines whether a value is empty. Empty values in the sense of a 
	 * value that should be entered into the database is one that is not 
	 * null, false, or an empty string.
	 * @param mixed $val 
	 * @return bool 
	 */
	private static function isEmpty($val) {
		return $val === null || $val === "" || $val === false;
	}

	/**
	 * Gets the reference to a survey in $Proj.
	 * @param string $form_name The name of the form.
	 * @return array The survey array (by reference).
	 */
	private static function & getSurveyRef($form_name) {
		global $Proj;
		foreach ($Proj->surveys as $id => $s) 
			if ($s["form_name"] == $form_name)
				return $Proj->surveys[$id];
		return null;
	}

	/**
	 * Encodes an enum-formatted value into REDCap's slider enum format
	 * @param array $enum [left => , middle => , right => ]
	 * @return string 
	 */
	private static function encodeSliderEnum($enum) {
		$encoded = trim("{$enum["left"]} | {$enum["middle"]} | {$enum["right"]}");
		if ($encoded == "|  |") $encoded = "";
		return $encoded;
	}

	/**
	 * Gets the active languages
	 * @param Context $context 
	 * @return array List of language ids
	 */
	private static function getActiveLangs(?Context $context = null) {
		$active_langs = array();
		$system_settings = self::getSystemSettings();
		// Globally disabled?
		if ($system_settings["disabled"]) {
			// Do nothing
		}
		// System Context
		else if ($context == null || $context->project_id == null) {
			foreach ($system_settings["langs"] as $lang_id => $this_lang) {
				if ($this_lang["active"]) {
					$active_langs[] = $lang_id;
				}
			}
			$active_langs = self::sortLanguages($system_settings["langs"], $active_langs);
		}
		// Project Context (context must not be null and must have a project id)
		else if ($context && $context->project_id) {
			$project_settings = self::getProjectSettings($context->project_id);
			if (!($project_settings["disabled"] || $project_settings["admin-disabled"])) {
				foreach($project_settings["langs"] as $lang_id => $this_lang) {
					$active = true;
					// Depending on context, check survey/data entry state
					if ($context->instrument) {
						if ($context->is_survey) {
							$active = $this_lang["dd"]["survey-active"][$context->instrument] ?? false;
						}
						else if ($context->is_dataentry) {
							$active = $this_lang["dd"]["form-active"][$context->instrument] ?? false;
						}
					}
					// Enabled for MyCap?
					if ($context->is_mycap) {
						$active = $this_lang["mycap-active"] ?? false;
					}
					if ($this_lang["active"] && $active) {
						$active_langs[] = $lang_id;
					}
				}
			}
			$active_langs = self::sortLanguages($project_settings["langs"], $active_langs);
		}
		return $active_langs;
	}

	/**
	 * Sorts languages
	 * @param array $lang_data An associative array [lang_id => data] of languages
	 * @param array|null $subset (Optional) A subset to sort (as array of lang keys)
	 * @return array An array of language keys sorted according to language data
	 */
	public static function sortLanguages($lang_data, $subset = null) {
		if ($subset === null) {
			$subset = array_keys($lang_data);
		}
		$map = array();
		$suffix = 0;
		foreach ($subset as $lang_id) {
			$suffix++;
			$this_lang = $lang_data[$lang_id];
			$sort_by = strtoupper(empty($this_lang["sort"]) ? $this_lang["display"] : $this_lang["sort"])."-{$suffix}";
			$map[$sort_by] = $lang_id;
		}
		$unsorted = array_keys($map);
		sort($unsorted);
		$sorted = array();
		foreach ($unsorted as $map_key) {
			$sorted[] = $map[$map_key];
		}
		return $sorted;
	}

	/**
	 * Helper function: Performs field embedding and piping of special variables and fields
	 * @param Context $context 
	 * @param string $text 
	 * @param bool $embed 
	 * @param string $lang_id The language id 
	 * @param bool $wrap Wrap piped values in span elements (default: true)
	 * @return string 
	 */
	private static function performPiping($context, $text, $embed, $lang_id, $wrap = true) {
		// Field embedding
		if ($embed) {
			$text = Piping::replaceEmbedVariablesInLabel($text, $context->project_id, $context->instrument);
			$text = is_array($text) ? $text[0] : $text;
		}
		// Piping
		$text = Piping::pipeSpecialTags($text, $context->project_id, $context->record, $context->event_id, $context->instance, null, false, null, $context->instrument, false);
		$text = Piping::replaceVariablesInLabel($text, $context->record, $context->event_id, $context->instance, array(), true, $context->project_id, $wrap, "", 1, false, false, $context->instrument, null, false, false, false, false, false, $lang_id);
		return $text;
	}

	/**
	 * Converts the type-name-index-[hash|value] structure to a name-type-index-[hash|value] 
	 * structure and adds it back to the language (dd_swap)
	 * @param Array $mlm_lang A language
	 * @return Array 
	 */
	private static function swapLangDdTypeName($mlm_lang) {
		$swapped = array();
		foreach ($mlm_lang["dd"] as $type => $td) {
			foreach($td as $name => $data) {
				$swapped[$name][$type] = $data;
			}
		}
		$mlm_lang["dd_swap"] = $swapped;
		return $mlm_lang;
	}

	/**
	 * Splits a comma-separate list into individual items, arranged as [ item-name => true ]
	 * @param mixed $raw 
	 * @return array Associative array [ item1 => true, item2 => true, ... ]
	 */
	private static function splitList($raw) {
		$a = array();
		foreach (explode(",", $raw) as $item) {
			$item = trim($item);
			if (strlen($item)) {
				$a[$item] = true;
			}
		}
		return $a;
	}

	/**
	 * Gets a field's translatable action tags
	 * Translatable action tags are:
	 *   @PLACEHOLDER
	 * @param string $misc
	 * @param string $type The field type
	 * @return array Translatable action tags [ "tag" => "@ACTIONTAG", "reference" => "value" ]
	 */
	private static function getTranslatableActionTags($misc, $type) {
		// https://regex101.com/r/b2nQEz/1
		$re = '/(?\'tag\'@PLACEHOLDER)\s*=\s*([\'|"])(?\'value\'.+)\2/sU';
		preg_match_all($re, $misc??"", $matches, PREG_SET_ORDER, 0);
		$tags = array();
		$counters = array();
		foreach ($matches as $match) {
			// In order to be able to map translations later, an id is stored as the key. 
			// The id is the name of the action tag and an incrementing counter (per kind)
			$tag = $match["tag"];
			$val = $match["value"];
			$counters[$tag] = isset($counters[$tag]) ? $counters[$tag] + 1 : 0;
			$hash = self::getChangeTrackHash($val);
			$tag_id = "{$tag}.{$counters[$tag]}";
			$tags[$tag_id] = array (
				"tag" => $tag,
				"reference" => $val,
				"refHash" => $hash,
			);
		}
		return $tags;
	}

	/**
	 * Gets the value of the @LANGUAGE-FORCE action tag
	 * Note: When there are multiple action tags - the last one wins (provided it matches the 
	 * context type, survey or data entry form)
	 * @param Context $context
	 * @return string|null The action tag parameter (raw) or null, if there is no action tag
	 */
	private static function getLanguageForceActionTags($context) {
		// https://regex101.com/r/y19GxP/1
		$re = '/@LANGUAGE-FORCE(-(?\'fs\'FORM|SURVEY))?\s*=\s*([\'|"])(?\'value\'[^@|.]+)\3/m';
		$Proj = new Project($context->project_id);
		$return = null;

		foreach ($context->page_fields as $_ => $field_name) {
			$field = $Proj->metadata[$field_name];
			$misc = $field["misc"] ?? "";
			preg_match_all($re, $misc, $matches, PREG_SET_ORDER, 0);
			foreach ($matches as $match) {
				// In order to be able to map translations later, an id is stored as the key. 
				// The id is the name of the action tag and an incrementing counter (per kind)
				// as well as the first 6 characters of the sha1 hash.
				$fs = $match["fs"];
				$val = $match["value"];
				if (self::isEmpty($fs) || ($fs == "SURVEY" && $context->is_survey) || ($fs == "FORM" && $context->is_dataentry)) {
					$return = $val;
				}
			}
		}
		return $return;
	}

	/**
	 * Gets a hash value used for change tracking
	 * @param mixed $val 
	 * @return string 
	 */
	private static function getChangeTrackHash($val) {
		return substr(sha1($val ?? ""), 0, 10);
	}

	/**
	 * Parses enums for choices and sliders (and filters calc and sql)
	 * @param string $raw 
	 * @param string $type 
	 * @return Array [ code => display ]
	 */
	private static function formatEnum($raw, $type) {
		if ($raw != null) {
			switch ($type) {
				case "calc": 
				case "sql":
					// Translation not possible
					return null;
				case "slider":
					$enum = \Form::parseSliderLabels($raw);
					return $enum;
				default:
					return parseEnum($raw);
			}
		}
		return $raw;
	}

	/**
	 * Processes a settings value according to type
	 * @param mixed $type 
	 * @param mixed $value 
	 * @return mixed 
	 */
	private static function processConfigValue($type, $value) {
		if ($type == "bool") {
			$value = $value === "1";
		}
		else if ($type == "list") {
			$value = self::splitList($value);
		}
		else if ($type == "json") {
			$value = self::isEmpty($value) ? array() : json_decode($value, true);
		}
		return $value;
	}

	/**
	 * Is draft mode enable for a project?
	 * @param mixed $project_id 
	 * @return bool 
	 */
	public static function inDraftMode($project_id)
	{
		if (!isinteger($project_id)) return false;
		$Proj = new Project($project_id);
		return ($Proj->project['status'] > 0 && $Proj->project['draft_mode'] > 0);
	}

	/**
	 * Is the project in DEVELOPMENT mode?
	 * @param mixed $project_id 
	 * @return bool 
	 */
	public static function inDevelopmentMode($project_id)
	{
		if (!isinteger($project_id)) return false;
		$Proj = new Project($project_id);
		return $Proj->project['status'] == 0;
	}

	/**
	 * Is a project in production mode?
	 * @param mixed $project_id 
	 * @return bool 
	 */
	public static function inProductionMode($project_id)
	{
		if (!isinteger($project_id)) return false;
		$Proj = new Project($project_id);
		return $Proj->project['status'] > 0;
	}

	/**
	 * Returns the name of the config table (redcap_multilanguage_config_temp or redcap_multilanguage_config),
	 * depending on project status/draft mode
	 * @param mixed $project_id 
	 * @param bool $tryDraftMode 
	 * @return string 
	 */
	public static function getConfigTable($project_id, $tryDraftMode=true)
	{
		return (!$tryDraftMode || !isinteger($project_id) || !self::inDraftMode($project_id)) ? "redcap_multilanguage_config" : "redcap_multilanguage_config_temp";
	}

	/**
	 * Returns the name of the metadata table (redcap_multilanguage_metadata_temp or redcap_multilanguage_metadata),
	 * depending on project status/draft mode
	 * @param mixed $project_id 
	 * @param bool $tryDraftMode 
	 * @return string 
	 */
	public static function getMetadataTable($project_id, $tryDraftMode=true)
	{
		return (!$tryDraftMode || !isinteger($project_id) || !self::inDraftMode($project_id)) ? "redcap_multilanguage_metadata" : "redcap_multilanguage_metadata_temp";
	}

	/**
	 * Returns the name of the metadata table (redcap_multilanguage_ui_temp or redcap_multilanguage_ui),
	 * depending on project status/draft mode
	 * @param mixed $project_id 
	 * @param bool $tryDraftMode 
	 * @return string 
	 */
	public static function getUITable($project_id, $tryDraftMode=true)
	{
		return (!$tryDraftMode || !isinteger($project_id) || !self::inDraftMode($project_id)) ? "redcap_multilanguage_ui" : "redcap_multilanguage_ui_temp";
	}

	#endregion

	#region Database Readers

	/**
	 * Gets the number of (active, i.e. not deleted, not archived) projects that subscribe to
	 * the given language
	 * Cave: This does not take into consideration any subscriptions added in draft mode!
	 * @param string $guid The GUID
	 * @return Array 
	 */
	public static function getSubscribedToStatus($guid) {
		$counts = [
			"total" => 0,
			"active" => 0,
			"dev" => 0,
			"prod" => 0,
			"cleanup" => 0,
			"completed" => 0,
			"deleted" => 0
		];
		$guid = db_escape($guid);
		$sql = "SELECT 
				p.status, p.completed_time, p.date_deleted
				FROM
				redcap_multilanguage_config AS syslang
				JOIN redcap_multilanguage_config AS subscribed 
					ON syslang.project_id = subscribed.project_id AND syslang.lang_id = subscribed.lang_id
				JOIN redcap_projects AS p 
					ON p.project_id = syslang.project_id
				WHERE 
				syslang.value = '$guid'
				AND subscribed.name = 'subscribed'
				AND subscribed.value = '1'
		";
		$q = db_query($sql);
		// Categorize and tally
		while ($row = db_fetch_assoc($q)) {
			$counts["total"]++;
			if ($row["date_deleted"] != null) {
				$counts["deleted"]++;
			}
			else if ($row["completed_time"] != null) {
				$counts["completed"]++;
			}
			else if ($row["status"] == "2") {
				$counts["cleanup"]++;
			}
			else {
				$counts["active"]++;
				$counts["dev"] += ($row["status"] == "0" ? 1 : 0);
				$counts["prod"] += ($row["status"] == "1" ? 1 : 0);
			}
		}
		return $counts;
	}

	/**
	 * Gets the forms that were downloaded from the REDCap Shared Library
	 * @param int|string $project_id 
	 * @return array 
	 */
	private static function getSharedLibraryForms($project_id) {
		$project_id = $project_id * 1;
		if (!is_integer($project_id) || !($project_id > 0)) return [];
		$sql = "SELECT form_name 
				FROM redcap_library_map 
				WHERE `type` = 1 AND ISNULL(`promis_key`) AND `project_id` = $project_id";
		$result = db_query($sql);
		$a = array();
		while ($row = db_fetch_assoc($result)) {
			$a[] = $row["form_name"];
		}
		return $a;
	}
	/**
	 * Executes a DB query against the config table
	 * @param int|string $project_id The project id. Use 'SYSTEM' to specify the system context (Control Center)
	 * @param string|boolean|null $lang (Optional) The language to query. Use TRUE to get all settings (including language data); use NULL, FALSE, or empty string to get only project (or system) settings.
	 * @param string|boolean $tryDraftMode (Optional) If TRUE and project is in draft mode, then use the _temp db table. Otherwise, use normal db table.
	 * @return Array [lang_id => [name => value]]
	 */
	public static function readConfig($project_id, $lang=NULL, $tryDraftMode=false) {
		if ($project_id == "SYSTEM") {
			$pid_clause = "`project_id` IS NULL";
		}
		else {
			$pid = $project_id * 1;
			$pid_clause = "`project_id`= {$pid}";
		}
		$sql = "SELECT `lang_id`, `name`, `value` FROM " . self::getConfigTable($project_id, $tryDraftMode) . " WHERE {$pid_clause}";
		if ($lang === true) {
			// add nothing
		}
		else if (strlen(trim($lang??"")) > 0) {
			$sql .= " AND `lang_id` = '" . db_escape($lang??"") . "'";
		}
		else {
			$sql .= " AND `lang_id` IS NULL";
		}
		$result = db_query($sql);
		$a = array("" => array()); // Initialize with at least a nested array on empty key
		while ($row = db_fetch_assoc($result)) {
			$val = ensureUTF($row["value"]);
			$lang_id = ensureUTF($row["lang_id"]);
			$name = ensureUTF($row["name"]);
			$a[$lang_id][$name] = $val;
		}
		return $a;
	}

	/**
	 * Executes a DB query against the ui translation table
	 * @param int $project_id
	 * @param string $lang_id The language id to query.
	 * @return Array [item => translation]
	 */
	private static function readUITranslations($project_id, $lang_id, $tryDraftMode=false) {
		$is_system = $project_id == "SYSTEM";
		if ($is_system) {
			$pid_clause = "`project_id` IS NULL";
		}
		else {
			$pid = $project_id * 1;
			$pid_clause = "`project_id` = {$pid}";
		}
		$sql = "SELECT `item`, `hash`, `translation` FROM " . self::getUITable($project_id, $tryDraftMode) . " WHERE {$pid_clause} ".
			"AND `lang_id` = '" . db_escape($lang_id) . "'";
		$result = db_query($sql);
		$a = array();
		while ($row = db_fetch_assoc($result)) {
			$val = ensureUTF($row["translation"]);
			$hash = ensureUTF($row["hash"]);
			$item = ensureUTF($row["item"]);
			$a[$item] = array(
				"translation" => $val,
				"refHash" => $hash,
			);
		}
		return $a;
	}

	/**
	 * Executes a DB query against the metadata table
	 * @param int $project_id
	 * @param string $lang_id The language id to query
	 * @param string $type (Optional) When supplied, limits the query to the specified type
	 * @param bool $tryDraftMode Indicates whether the project is in draft mode (defaults to false)
	 * @return Array [type => [name => [index => [translation, refHash]]]]
	 */
	private static function readMetadataTranslations($project_id, $lang_id, $type = null, $tryDraftMode=false) {
		$pid = $project_id * 1;
		$sql = "SELECT `type`, `name`, `index`, `hash`, `value` FROM " . self::getMetadataTable($project_id, $tryDraftMode) . " WHERE project_id = {$pid} ".
			"AND `lang_id` = '" . db_escape($lang_id) . "'";
		if ($type !== null && strlen($type)) {
			$sql .= " AND `type` = '" . db_escape($type) . "'";
		}
		$result = db_query($sql);
		$a = array();
		while ($row = db_fetch_assoc($result)) {
			$type = ensureUTF($row["type"]);
			$name = ensureUTF($row["name"]);
			$index = ensureUTF($row["index"]);
			$val = ensureUTF($row["value"]);
			$hash = ensureUTF($row["hash"]);
			$a[$type][$name][$index] = array(
				"translation" => $val,
				"refHash" => $hash,
			);
		}
		return $a;
	}

	/**
	 * Executes a DB query againt the redcap_surveys_scheduler table to retrive all ASIs
	 * @param array $survey_ids List of survey ids
	 * @param array $valid_event_ids List of valid event ids
	 * @return array ["survey_id", "event_id", "email_subject", "email_content", "email_sender_display"]
	 */
	private static function readASIData($survey_ids, $valid_event_ids) {
		$sql = "SELECT `survey_id`, `event_id`, `email_subject`, `email_content`, `email_sender_display` 
				FROM redcap_surveys_scheduler 
				WHERE `survey_id` IN (" . join(",", array_keys($survey_ids)) . ")";
		$result = db_query($sql);
		$asis = array();
		while ($row = db_fetch_assoc($result)) {
			if (!empty($row["event_id"] && in_array($row["event_id"], $valid_event_ids, true))) { // Skip entries that are not associated with a valid event
				$utf_row = array();
				foreach ($row as $k => $v) {
					$utf_row[ensureUTF($k)] = ensureUTF($v);
				}
				$asis[] = $utf_row;
			}
		}
		return $asis;
	}


	private static function readEDocsMetadata($doc_id) {
		$doc_id = $doc_id * 1;
		$sql = "SELECT * FROM `redcap_edocs_metadata` WHERE `doc_id` = {$doc_id} LIMIT 1";
		$q = db_query($sql);
		if ($q && db_num_rows($q) > 0) {
			$data = db_fetch_assoc($q);
			$utf_data = array();
			foreach ($data as $k => $v) {
				$utf_data[ensureUTF($k)] = ensureUTF($v);
			}
			return $utf_data;
		}
		return false;
	}

	/**
	 * Executes a DB query against multiple tables to get snapshot information
	 * @param int|string $project_id 
	 * @param int|null $snapshot_id When set, results will be limited to this snapshot
	 * @return array Associative array with snapshot_id as key
	 */
	private static function readSnapshots($project_id, $snapshot_id = null) {
		$pid = $project_id * 1;
		$snapshot_id = $snapshot_id * 1;
		$limit = $snapshot_id ? "AND s.`snapshot_id` = {$snapshot_id}" : "";
		$snapshots = array();
		$sql =
			"SELECT 
				s.`snapshot_id`, 
				s.`edoc_id`, 
				concat(uc.`username`, ' (', uc.`user_firstname`, ' ', uc.`user_lastname`, ')') AS 'created_by',
				e.`stored_date` as 'created_ts',
				concat(ud.`username`, ' (', ud.`user_firstname`, ' ', ud.`user_lastname`, ')') AS 'deleted_by',
				e.`delete_date` AS 'deleted_ts'
			FROM `redcap_multilanguage_snapshots` AS s 
			INNER JOIN `redcap_edocs_metadata` AS e ON e.`doc_id` = s.`edoc_id` 
			INNER JOIN `redcap_user_information` AS uc ON uc.`ui_id` = s.`created_by`
			LEFT JOIN `redcap_user_information` AS ud ON ud.`ui_id` = s.`deleted_by`
			WHERE s.`project_id` = {$pid} {$limit}
			ORDER BY s.`snapshot_id` DESC";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$snapshots[$row["snapshot_id"]] = array(
				"id" => $row["snapshot_id"] * 1, // Convert to number
				"created_ts" => ensureUTF($row["created_ts"]),
				"created_by" => ensureUTF($row["created_by"]),
				"deleted_ts" => ensureUTF($row["deleted_ts"]),
				"deleted_by" => ensureUTF($row["deleted_by"]),
			);
		}
		return $snapshots;
	}

	/**
	 * Executes a DB query against the redcap_multilanguage_snapshot table
	 * @param int $snapshot_id 
	 * @return array The raw row data
	 */
	private static function readSnapshotRaw($snapshot_id) {
		$snapshot_id = $snapshot_id * 1;
		$sql = 
			"SELECT * 
			FROM `redcap_multilanguage_snapshots` 
			WHERE `snapshot_id` = {$snapshot_id}
			LIMIT 1";
		$q = db_query($sql);
		if ($q && db_num_rows($q) > 0) {
			$data = db_fetch_assoc($q);
			$utf_data = array();
			foreach ($data as $k => $v) {
				$utf_data[ensureUTF($k)] = ensureUTF($v);
			}
			return $utf_data;
		}
		return false;
	}

	/**
	 * Executes a DB query against the redcap_validation_types table
	 * @return array [validation_name:string[label:string,enabled:bool]]
	 */
	private static function readValidationTypes() {
		$sql = 
			"SELECT `validation_name`, `validation_label`, `visible`
			FROM `redcap_validation_types`
			";
		$q = db_query($sql);
		$validations = array();
		while ($row = db_fetch_assoc($q)) {
			$validations[$row["validation_name"]] = array(
				"label" => $row["validation_label"],
				"visible" => $row["visible"] == "1",
			);
		}
		return $validations;
	}

	#endregion

	#region Database Writers

	/**
	 * Creates an entry in the redcap_multilanguage_snapshots table
	 * @param mixed $project_id 
	 * @param mixed $edoc_id 
	 * @param mixed $ui_id 
	 * @return int|false Returns the snapshot_id or false in case of failure
	 */
	private static function createSnapshotEntry($project_id, $edoc_id, $ui_id) {
		$project_id = $project_id * 1;
		$edoc_id = $edoc_id * 1;
		$ui_id = $ui_id * 1;
		$sql = "INSERT INTO `redcap_multilanguage_snapshots` (`project_id`, `edoc_id`, `created_by`) VALUES ({$project_id}, {$edoc_id}, {$ui_id})";
		if (db_query($sql)) {
			return db_insert_id();
		}
		return false;
	}

	/**
	 * Updates a snapshot entry by setting the ui_id of the user who deleted the snapshot
	 * @param mixed $snapshot_id 
	 * @param mixed $deleted_by A user's ui_id
	 * @return mysqli_result|bool 
	 */
	private static function updateSnapshotEntry($snapshot_id, $deleted_by) {
		$snapshot_id = $snapshot_id * 1;
		$deleted_by = $deleted_by * 1;
		$sql = 
			"UPDATE `redcap_multilanguage_snapshots` 
			SET `deleted_by` = {$deleted_by}
			WHERE `snapshot_id` = {$snapshot_id}";
		return db_query($sql);
	}

	public static function getGuid() {
		return \Crypto::getGuid();
	}

	private static function addSystemLanguageGuid($lang_id) {
		$guid = self::getGuid();
		$sql = "INSERT INTO `redcap_multilanguage_config` (`project_id`, `lang_id`, `name`, `value`) ".
			"VALUES (NULL, '".db_escape($lang_id)."', 'guid', '{$guid}')";
		$result = db_query($sql);
		return $guid;
	}

	/**
	 * Stores settings in the redcap_multilanguage_config table
	 * @param bool $update Whether to use UPDATE (true) or INSERT (false)
	 * @param int $project_id
	 * @param string|null $lang_id The language ID
	 * @param string $name The name of the setting
	 * @param mixed $value The value
	 * @return void 
	 */
	private static function storeConfig($update, $project_id, $lang_id, $name, $value) {
		if ($project_id == "SYSTEM") {
			$pid = "NULL";
			$pid_clause = "`project_id` IS NULL";
		}
		else {
			$pid = $project_id * 1;
			$pid_clause = "`project_id` = {$pid}";
		}
		$sql = "";
		if ($update) {
			$lid = "`lang_id` " . ($lang_id == NULL ? "IS NULL" : "= '" . db_escape($lang_id) . "'");
			$sql .= "UPDATE " . self::getConfigTable($project_id) . " ";
			$sql .= "SET `value` = '" . db_escape($value) . "' ";
			$sql .= "WHERE {$pid_clause} AND `name` = '" . db_escape($name) . "' AND {$lid}";
		}
		else {
			$lid = $lang_id == NULL ? "NULL" : "'" . db_escape($lang_id) . "'";
			$sql .= "INSERT INTO " . self::getConfigTable($project_id) . " (`project_id`, `lang_id`, `name`, `value`) ";
			$sql .= "VALUES ({$pid}, {$lid}, '".db_escape($name)."', '".db_escape($value)."')";
		}
		$result = db_query($sql);
	}

	/**
	 * Deletes a setting entry from the redcap_multilanguage_config table
	 * @param int $project_id
	 * @param string|null $lang_id The language ID
	 * @param string $name The name of the setting
	 * @return void 
	 */
	private static function removeConfig($project_id, $lang_id, $name) {
		if ($project_id == "SYSTEM") {
			$pid_clause = "`project_id` IS NULL";
		}
		else {
			$pid = $project_id * 1;
			$pid_clause = "`project_id` = {$pid}";
		}
		$lid = "`lang_id` " . ($lang_id == null ? "IS NULL" : "= '" . db_escape($lang_id) . "'");
		$sql = "DELETE FROM " . self::getConfigTable($project_id) . " WHERE {$pid_clause} " .
			"AND {$lid} " .
			"AND `name` = '" . db_escape($name) . "'";
		$result = db_query($sql);
	}

	/**
	 * Stores a UI translation in the redcap_multilanguage_ui table
	 * @param bool $update Whether to use UPDATE (true) or INSERT (false)
	 * @param int $project_id
	 * @param string|null $lang_id The language ID
	 * @param string $item The name of the item
	 * @param string $translation The translation
	 * @param string $hash The reference hash value for change tracking
	 * @return void 
	 */
	private static function storeUITranslation($update, $project_id, $lang_id, $item, $translation, $hash) {
		if ($project_id == "SYSTEM") {
			$pid = "NULL";
			$pid_clause = "`project_id` IS NULL";
		}
		else {
			$pid = $project_id * 1;
			$pid_clause = "`project_id` = {$pid}";
		}
		$lang_id = "'".db_escape($lang_id)."'";
		$item = "'".db_escape($item)."'";
		$hash = self::isEmpty($hash) ? "NULL" : "'".db_escape($hash)."'";
		$translation = "'".db_escape($translation)."'";
		$sql = "";
		if ($update) {
			$sql .= "UPDATE " . self::getUITable($project_id) . " ";
			$sql .= "SET `translation` = {$translation}, `hash` = {$hash} ";
			$sql .= "WHERE {$pid_clause} ";
			$sql .= "AND `lang_id` = {$lang_id} "; 
			$sql .= "AND `item` = {$item} ";
		}
		else {
			$sql .= "INSERT INTO " . self::getUITable($project_id) . " (`project_id`, `lang_id`, `item`, `hash`, `translation`) ";
			$sql .= "VALUES ({$pid}, {$lang_id}, {$item}, {$hash}, {$translation})";
		}
		$result = db_query($sql);
	}

	/**
	 * Deletes an entry from the redcap_multilanguage_ui table
	 * @param int $project_id
	 * @param string|null $lang_id The language ID
	 * @param string $item The name of the item
	 * @return void 
	 */
	private static function removeUITranslation($project_id, $lang_id, $item) {
		if ($project_id == "SYSTEM") {
			$pid_clause = "`project_id` IS NULL";
		}
		else {
			$pid = $project_id * 1;
			$pid_clause = "`project_id` = {$pid}";
		}
		$lang_id = "`lang_id` = '" . db_escape($lang_id) . "'";
		$item = "`item` = '" . db_escape($item) . "'";
		$sql = "DELETE FROM " . self::getUITable($project_id) . " WHERE {$pid_clause} AND {$lang_id} AND {$item}";
		$result = db_query($sql);
	}

	/**
	 * Stores a UI translation in the redcap_multilanguage_ui table
	 * @param bool $update Whether to use UPDATE (true) or INSERT (false)
	 * @param int $project_id
	 * @param string|null $lang_id The language ID
	 * @param string $type The type of the item
	 * @param string $name The name of the item
	 * @param string $index The index (e.g. code of enums) of the item
	 * @param string $value The (translated) value
	 * @param string $hash The corresponding reference hash (for change tracking)
	 * @return void 
	 */
	private static function storeMetadataTranslation($update, $project_id, $lang_id, $type, $name, $index, $value, $hash) {
		if (
			!is_numeric($project_id) ||
			self::isEmpty($lang_id) ||
			self::isEmpty($type) ||
			self::isEmpty($value)
		) {
			throw new Exception("Project ID, Language ID, Type, and Value MUST be set!");
		}
		$pid = $project_id * 1;
		$lang_id = "'".db_escape($lang_id)."'";
		$type = "'".db_escape($type)."'";
		$name = self::isEmpty($name) ? "NULL" : "'".db_escape($name)."'";
		$index = self::isEmpty($index) ? "NULL" : "'".db_escape($index)."'";
		$hash = self::isEmpty($hash) ? "NULL" : "'".db_escape($hash)."'";
		$value = "'".db_escape($value)."'";

		$sql = "";
		if ($update) {
			$sql .= "UPDATE " . self::getMetadataTable($project_id) . " ";
			$sql .= "SET `value` = {$value}, `hash` = {$hash} ";
			$sql .= "WHERE `project_id` = {$pid} ";
			$sql .= "AND `lang_id` = {$lang_id} ";
			$sql .= "AND `type` = {$type} ";
			$sql .= "AND `name` " . ($name == "NULL" ? "IS NULL " : "= {$name} ");
			$sql .= "AND `index` " . ($index == "NULL" ? "IS NULL " : "= {$index} ");
		}
		else {
			$sql .= "INSERT INTO " . self::getMetadataTable($project_id) . " (`project_id`, `lang_id`, `type`, `name`, `index`, `hash`, `value`) ";
			$sql .= "VALUES ({$pid}, {$lang_id}, {$type}, {$name}, {$index}, {$hash}, {$value})";
		}
		$result = db_query($sql);
	}

	/**
	 * Deletes an entry from the redcap_multilanguage_metadata table
	 * @param int $project_id
	 * @param string|null $lang_id The language ID
	 * @param string $name The name of the item
	 * @param string $type The type of the item
	 * @return void 
	 */
	private static function removeMetadataTranslation($project_id, $lang_id, $type, $name, $index) {
		if (
			!is_numeric($project_id) ||
			self::isEmpty($lang_id) ||
			self::isEmpty($type)
		) {
			throw new Exception("Project ID, Language ID, and Type MUST be specified!");
		}
		$pid = $project_id * 1;
		$pid = "`project_id` = {$pid}";
		$lang_id = "`lang_id` = '".db_escape($lang_id)."'";
		$type = "`type` = '".db_escape($type)."'";
		$name = "`name` " . (self::isEmpty($name) ? "IS NULL" : "= '".db_escape($name)."'");
		$index = "`index` " . (self::isEmpty($index) ? "IS NULL" : "= '".db_escape($index)."'");
		$sql = "DELETE FROM " . self::getMetadataTable($project_id) . " WHERE {$pid} AND {$lang_id} AND {$type} AND {$name} AND {$index}";
		$result = db_query($sql);
	}

	/**
	 * Deletes all language data from the redcap_multilanguage_(config|metadata|ui) tables
	 * @param int|string $project_id
	 * @param string $lang_id The language id
	 * @return void 
	 */
	private static function purgeLanguage($project_id, $lang_id) {
		if ($project_id == "SYSTEM") {
			$pid_clause = "`project_id` IS NULL";
		}
		else {
			$pid = $project_id * 1;
			$pid_clause = "`project_id` = {$pid}";
		}
		$lang_id = "`lang_id` = '" . db_escape($lang_id) . "'";
		$tables = array(
			self::getConfigTable($project_id),
			self::getUITable($project_id)
		);
		if ($project_id != "SYSTEM") {
			$tables[] = self::getMetadataTable($project_id);
		}
		foreach ($tables as $table) {
			$sql = "DELETE FROM {$table} WHERE {$pid_clause} AND {$lang_id}";
			$result = db_query($sql);
		}
	}

	/**
	 * Deletes all references to the system language identified by the given GUID
	 * (in both, regular and draft mode tables)
	 * @param mixed $guid 
	 * @return void 
	 */
	private static function purgeSysLangLink($guid) {
		$guid = db_escape($guid);
		$tables = [ "redcap_multilanguage_config", "redcap_multilanguage_config_temp" ];
		foreach ($tables as $table) {
			$sql = "DELETE FROM $table 
					WHERE 
					`project_id` IS NOT NULL 
					AND `name` = 'syslang'
					AND `value` = '$guid'";
			$result = db_query($sql);
		}
	}

	/**
	 * Construcs an IN clause for SQL queries
	 * @param Array $arr An array of items to add to the IN clause
	 * @return string 
	 */
	private static function getInClauseStr($arr) {
		$escape = function($in) {
			return "'".db_escape($in)."'";
		};
		$out = join(", ", array_map($escape, $arr));
		return "IN({$out})";
	}

	/**
	 * Updates a form's name when being changed while the project is in DEVELOPMENT mode
	 * @param mixed $project_id 
	 * @param mixed $old_name 
	 * @param mixed $new_name 
	 * @return void 
	 */
	public static function updateFormNameDuringDevelopment($project_id, $old_name, $new_name) {
		$pid = $project_id * 1;
		// Form/Survey-level items
		$db_new = db_escape($new_name);
		$db_old = db_escape($old_name);
		$in = self::getInClauseStr(self::$formMetaDataTypes);
		$sql = "UPDATE redcap_multilanguage_metadata SET `name` = '{$db_new}' WHERE `name` = '{$db_old}' AND `type` {$in} AND `project_id` = {$pid}";
		$result = db_query($sql);

		// Form complete field
		$db_new = db_escape("{$new_name}_complete");
		$db_old = db_escape("{$old_name}_complete");
		$in = self::getInClauseStr(self::$formCompleteMetaDataTypes);
		$sql = "UPDATE redcap_multilanguage_metadata SET `name` = '{$db_new}' WHERE `name` = '{$db_old}' AND SUBSTRING_INDEX(`type`, ',', 1) {$in} AND `project_id` = {$pid}";
		$result = db_query($sql);
	}

	/**
	 * Updates a field's name when being changed while the project is in DEVELOPMENT mode
	 * @param mixed $project_id 
	 * @param mixed $old_name 
	 * @param mixed $new_name 
	 * @return void 
	 */
	public static function updateFieldNameDuringDevelopment($project_id, $old_name, $new_name) {
		$pid = $project_id * 1;
		// Field-level items
		$db_new = db_escape($new_name);
		$db_old = db_escape($old_name);
		$in = self::getInClauseStr(self::$fieldMetaDataTypes);
		$sql = "UPDATE redcap_multilanguage_metadata SET `name` = '{$db_new}' WHERE `name` = '{$db_old}' AND `type` {$in} AND `project_id` = {$pid}";
		$result = db_query($sql);
	}

	/**
	 * Moves translations of a section header
	 * @param int|string $project_id 
	 * @param string $prev_field 
	 * @param string $new_field 
	 * @param int $merge 0, 1, or 2 (0 = no merge, 1 = new-prev, 2 = prev-new)
	 * @return void 
	 */
	public static function moveSectionHeader($project_id, $prev_field, $new_field, $merge = 1) {
		$pid = $project_id * 1;
		$db_new = db_escape($new_field);
		$db_prev = db_escape($prev_field);
		$langs = self::getActiveLangs(Context::Builder()->project_id($project_id)->Build());
		foreach ($langs as $lang) {
			$db_lang = db_escape($lang);
			$where = "`type` = 'field-header' AND `project_id` = {$pid} AND `lang_id` = '{$db_lang}'";
			// Get the section header translation of the previous field
			$sql = "SELECT `value` FROM redcap_multilanguage_metadata WHERE `name` = '{$db_prev}' AND {$where}";
			$prev_value = db_result(db_query($sql), 0);
			// If there is no translation, then nothing needs to be done
			if ($prev_value == null || !strlen($prev_value)) continue;
			// Is there a section header translation in the destination field?
			$sql = "SELECT `value` FROM redcap_multilanguage_metadata WHERE `name` = '{$db_new}' AND {$where}";
			$dest_value = db_result(db_query($sql), 0);
			if ($dest_value != null && strlen($dest_value)) {
				// Need to merge
				$val = $dest_value;
				if ($merge == 1) {
					$val = "{$dest_value}<br><br>{$prev_value}";
				}
				else if ($merge == 2) {
					$val = "{$prev_value}<br><br>{$dest_value}";
				}
				$db_val = db_escape($val);
				// And update the translation in the destination (reset the hash)
				$sql = "UPDATE redcap_multilanguage_metadata SET `value` = '{$db_val}', `hash` = 'NoHash' WHERE `name` = '{$db_new}' AND {$where}";
				$result = db_query($sql);
				// Delete old
				$sql = "DELETE FROM redcap_multilanguage_metadata WHERE `name` = '{$db_prev}' AND {$where} LIMIT 1";
				$result = db_query($sql);
			}
			else {
				// Update field name
				$sql = "UPDATE redcap_multilanguage_metadata SET `name` = '{$db_new}' WHERE `name` = '{$db_prev}' AND {$where}";
				$result = db_query($sql);
			}
		}
	}

	/**
	 * Get UI and field translations for given language of a project (MyCap Mobile App)
	 * @param int $project_id
	 * @param string $lang_id The language id
	 * @return array
	 */
	public static function exportMyCapTranslations($project_id, $lang_id) {
		$Proj = new Project($project_id);
		$myCapProj = new MyCap($project_id);
		$ret_val = ["success" => false,
					"errors" => array(),
					"translations" => "{}"];

		$context = Context::Builder()->project_id($project_id)->lang_id($lang_id)->is_mycap()->Build();
		$mycap_langs = self::getActiveLangs($context);

		if (!in_array($lang_id, $mycap_langs)) {
			// Set $lang_id to REDCap stored lang code if different from MyCap supported language in param
			foreach ($mycap_langs as $code) {
				if (strtok($code, '-') === strtok($lang_id, '-')) {
					$lang_id = $code;
					break;
				}
			}
		}

		// Check if Language ID is valid
		if (in_array($lang_id, $mycap_langs)) {
			$settings = self::getProjectSettings($project_id);

			// Get MyCap App data translations
			$myCapUITranslations = [];
			// Get user interface metadata
			$ui_meta = self::getUIMetadata(false);

			foreach ($ui_meta as $ui_key => $ui_data) {
				if (str_starts_with($ui_key, 'mycapui_')) { // Check only for mycapui_* fields
					$myCapUITranslations[$ui_key] = self::getUITranslation($context, $ui_key);

				}
			}

			// Get fields label, note, choices translations
			$proj_meta = self::getProjectMetadata($project_id);
			$dd = $settings["langs"][$lang_id]["dd"];
			$fieldAnnotationIgnore = [
				"@HIDDEN", // This is a REDCap annotation that indicates that a field should be hidden in the instrument
				\Vanderbilt\REDCap\Classes\MyCap\Annotation::FIELD_HIDDEN,
				\Vanderbilt\REDCap\Classes\MyCap\Annotation::TASK_UUID,
				\Vanderbilt\REDCap\Classes\MyCap\Annotation::TASK_STARTDATE,
				\Vanderbilt\REDCap\Classes\MyCap\Annotation::TASK_ENDDATE,
				\Vanderbilt\REDCap\Classes\MyCap\Annotation::TASK_SCHEDULEDATE,
				\Vanderbilt\REDCap\Classes\MyCap\Annotation::TASK_ISDELETED,
				\Vanderbilt\REDCap\Classes\MyCap\Annotation::TASK_STATUS,
				\Vanderbilt\REDCap\Classes\MyCap\Annotation::TASK_SUPPLEMENTALDATA,
				\Vanderbilt\REDCap\Classes\MyCap\Annotation::TASK_SERIALIZEDRESULT
			];
			$fieldTypeUnsupported = ['calc', 'sql'];
			$ret_val = [];
			$mycap_tasks = [];
			foreach ($proj_meta['fields'] as $field => $attr) {
				$skip = false;
				$formName = $attr['formName'];

				if ($proj_meta['forms'][$formName]['myCapTaskId'] == ''
					|| $field == $formName."_complete"
					|| in_array($attr['type'], $fieldTypeUnsupported)
				) {
					$skip = true;
				} else {
					// A REDCap instrument may contain fields that we do not want to capture via the mobile app. Exclude them.
					$field_annotation = strtoupper($Proj->metadata[$field]["misc"]);
					if ($field_annotation != '') {
						$annotations = explode(
							" ",
							$field_annotation
						);
						foreach ($annotations as $annotation) {
							if (in_array(
								$annotation,
								$fieldAnnotationIgnore
							)) {
								$skip = true;
							}
						}
					}
				}

				if ($skip == false) {
					if (!isset($return[$formName])) {
						$return[$formName] = [];
					}
					$return[$formName][] = self::getFieldTranslationsForMyCap($field, $dd, $attr);
				}
			}
			$fieldTranslations = $return;

			// Get MyCap tasks translations
			$tasks_details = [];
			foreach ($proj_meta['myCap']['taskToForm'] as $taskId => $formName) {
				$tasks_details[$taskId] = [];
				$tasks_details[$taskId]['identifier'] = $formName;
				$titleRefHash = $proj_meta['forms'][$formName]['myCapTaskItems']['task_title']['refHash'];
				if (isset($dd['task-task_title'][$taskId][""]['refHash']) && $dd['task-task_title'][$taskId][""]['refHash'] == $titleRefHash) {
					$tasks_details[$taskId]['title'] = $dd['task-task_title'][$taskId][""]["translation"];
				} else {
					$tasks_details[$taskId]['title'] = $proj_meta['forms'][$formName]['myCapTaskItems']['task_title']['reference'];
				}

				$isActive = ($myCapProj->tasks[$formName]['is_active_task'] == 1);
				if ($isActive) {
					$extendedConfigs = $proj_meta['forms'][$formName]['myCapTaskItems'];
					foreach ($extendedConfigs as $key => $config) {
						if (starts_with($key, "extendedConfig_")) {
							$configKey = substr($key, 15);
							$configRefHash = $config['refHash'];
							if (isset($dd['task-'.$key][$taskId][""]['refHash']) && $dd['task-'.$key][$taskId][""]['refHash'] == $configRefHash) {
								$extendedConfigArr[$configKey] = $dd['task-'.$key][$taskId][""]["translation"];
							} else {
								$extendedConfigArr[$configKey] = $config['reference'];
							}
						}
						$tasks_details[$taskId]['extendedConfigJson'] = json_encode($extendedConfigArr);
					}
				} else {
					unset($tasks_details[$taskId]['extendedConfigJson']);
				}
				foreach ($proj_meta['forms'][$formName]['myCapTaskItems'] as $event_task_id => $myCapTaskItem) {
					if ($event_task_id != 'task_title') {
						$isActive = ($myCapProj->tasks[$formName]['is_active_task'] == 1);
						list ($eventId, $tsId) = explode("-", $event_task_id);
						if ($isActive) {
							unset($tasks_details[$tsId][$eventId]['instructionStep'], $tasks_details[$tsId][$eventId]['completionStep']);

						} else {
							$stepTitleRefHash = $proj_meta['forms'][$formName]['myCapTaskItems'][$event_task_id]['instruction_step_title']['refHash'];
							if (isset($dd['task-instruction_step_title'][$event_task_id][""]['refHash']) && $dd['task-instruction_step_title'][$event_task_id][""]['refHash'] == $stepTitleRefHash) {
								$tasks_details[$tsId][$eventId]['instructionStep']['title'] = $dd['task-instruction_step_title'][$event_task_id][""]["translation"];
							} else {
								$tasks_details[$tsId][$eventId]['instructionStep']['title'] = $proj_meta['forms'][$formName]['myCapTaskItems'][$event_task_id]['instruction_step_title']['reference'];
							}

							$stepContentRefHash = $proj_meta['forms'][$formName]['myCapTaskItems'][$event_task_id]['instruction_step_content']['refHash'];
							if (isset($dd['task-instruction_step_content'][$event_task_id][""]['refHash']) && $dd['task-instruction_step_content'][$event_task_id][""]['refHash'] == $stepContentRefHash) {
								$tasks_details[$tsId][$eventId]['instructionStep']['content'] = $dd['task-instruction_step_content'][$event_task_id][""]["translation"];
							} else {
								$tasks_details[$tsId][$eventId]['instructionStep']['content'] = $proj_meta['forms'][$formName]['myCapTaskItems'][$event_task_id]['instruction_step_content']['reference'];
							}

							$cStepTitleRefHash = $proj_meta['forms'][$formName]['myCapTaskItems'][$event_task_id]['completion_step_title']['refHash'];
							if (isset($dd['task-completion_step_title'][$event_task_id][""]['refHash']) && $dd['task-completion_step_title'][$event_task_id][""]['refHash'] == $cStepTitleRefHash) {
								$tasks_details[$tsId][$eventId]['completionStep']['title'] = $dd['task-completion_step_title'][$event_task_id][""]["translation"];
							} else {
								$tasks_details[$tsId][$eventId]['completionStep']['title'] = $proj_meta['forms'][$formName]['myCapTaskItems'][$event_task_id]['completion_step_title']['reference'];
							}

							$cStepContentRefHash = $proj_meta['forms'][$formName]['myCapTaskItems'][$event_task_id]['completion_step_content']['refHash'];
							if (isset($dd['task-completion_step_content'][$event_task_id][""]['refHash']) && $dd['task-completion_step_content'][$event_task_id][""]['refHash'] == $cStepContentRefHash) {
								$tasks_details[$tsId][$eventId]['completionStep']['content'] = $dd['task-completion_step_content'][$event_task_id][""]["translation"];
							} else {
								$tasks_details[$tsId][$eventId]['completionStep']['content'] = $proj_meta['forms'][$formName]['myCapTaskItems'][$event_task_id]['completion_step_content']['reference'];
							}
						}
					}
				}
			}

			foreach ($tasks_details as $tsId => $tasks_detail) {
				$result = $task_arr = [];
				$schedules = Task::getTaskSchedules($tsId, '', $project_id);
				$tasks['identifier'] = $tasks_detail['identifier'];
				$tasks['title'] = $tasks_detail['title'];
				$tasks['extendedConfigJson'] = $tasks_detail['extendedConfigJson'];
				if ($Proj->longitudinal) {
					$tasks['longitudinalSchedule'] = [];
					foreach ($schedules as $eventId => $schedule) {
						if (isset($tasks_detail[$eventId]['instructionStep'])) {
							$task_arr['instructionStep'] = $tasks_detail[$eventId]['instructionStep'];
						}
						if (isset($tasks_detail[$eventId]['completionStep'])) {
							$task_arr['completionStep'] = $tasks_detail[$eventId]['completionStep'];
						}
						$result = $task_arr;
						unset($task_arr['instructionStep'], $task_arr['completionStep']);
						$result['identifier'] = $eventId;
						$tasks['longitudinalSchedule'][] = $result;
					}
				} else {
					unset($tasks['instructionStep'], $tasks['completionStep']);
					if (isset($tasks_detail[$Proj->firstEventId]['instructionStep'])) {
						$tasks['instructionStep'] = $tasks_detail[$Proj->firstEventId]['instructionStep'];
					}
					if (isset($tasks_detail[$Proj->firstEventId]['completionStep'])) {
						$tasks['completionStep'] = $tasks_detail[$Proj->firstEventId]['completionStep'];
					}
				}
				$ts_arr[] = $tasks;
			}

			$refHash = $proj_meta['myCap']["mycap-app_title"]['refHash'];
			if (isset($dd['mycap-app_title'][""][""]['refHash']) && $dd['mycap-app_title'][""][""]['refHash'] == $refHash) {
				$app_title = $dd['mycap-app_title'][""][""]['translation'];
			} else {
				$app_title = $proj_meta['myCap']['mycap-app_title']['reference'];
			}

			// Get MyCap About pages data translations
			$pagePrefix = 'mycap-about-';
			$pages = [];
			foreach ($proj_meta['myCap']['pages'] as $pageId => $pageArr) {
				$page_details['identifier'] = $pageArr['identifier'];
				$titleRefHash = $pageArr[$pagePrefix.'page_title']['refHash'];
				if (isset($dd[$pagePrefix.'page_title'][$pageId][""]['refHash']) && $dd[$pagePrefix.'page_title'][$pageId][""]['refHash'] == $titleRefHash) {
					$page_details['title'] = $dd[$pagePrefix.'page_title'][$pageId][""]["translation"];
				} else {
					$page_details['title'] = $pageArr[$pagePrefix.'page_title']['reference'];
				}

				$contentRefHash = $pageArr[$pagePrefix.'page_content']['refHash'];
				if (isset($dd[$pagePrefix.'page_content'][$pageId][""]['refHash']) && $dd[$pagePrefix.'page_content'][$pageId][""]['refHash'] == $contentRefHash) {
					$page_details['content'] = $dd[$pagePrefix.'page_content'][$pageId][""]["translation"];
				} else {
					$page_details['content'] = $pageArr[$pagePrefix.'page_content']['reference'];
				}
				$pages[] = $page_details;
			}

			// Get MyCap Contacts data translations
			$cPrefix = 'mycap-contact-';
			$contacts = [];
			foreach ($proj_meta['myCap']['contacts'] as $contactId => $contactArr) {
				$contact_details['identifier'] = $contactArr['identifier'];
				$headerRefHash = $contactArr[$cPrefix.'contact_header']['refHash'];
				if (isset($dd[$cPrefix.'contact_header'][$contactId][""]['refHash']) && $dd[$cPrefix.'contact_header'][$contactId][""]['refHash'] == $headerRefHash) {
					$contact_details['name'] = $dd[$cPrefix.'contact_header'][$contactId][""]["translation"];
				} else {
					$contact_details['name'] = $contactArr[$cPrefix.'contact_header']['reference'];
				}
				$titleRefHash = $contactArr[$cPrefix.'contact_title']['refHash'];
				if (isset($dd[$cPrefix.'contact_title'][$contactId][""]['refHash']) && $dd[$cPrefix.'contact_title'][$contactId][""]['refHash'] == $titleRefHash) {
					$contact_details['title'] = $dd[$cPrefix.'contact_title'][$contactId][""]["translation"];
				} else {
					$contact_details['title'] = $contactArr[$cPrefix.'contact_title']['reference'];
				}

				$phoneRefHash = $contactArr[$cPrefix.'phone_number']['refHash'];
				if (isset($dd[$cPrefix.'phone_number'][$contactId][""]['refHash']) && $dd[$cPrefix.'phone_number'][$contactId][""]['refHash'] == $phoneRefHash) {
					$contact_details['phone'] = $dd[$cPrefix.'phone_number'][$contactId][""]["translation"];
				} else {
					$contact_details['phone'] = $contactArr[$cPrefix.'phone_number']['reference'];
				}

				$emailRefHash = $contactArr[$cPrefix.'email']['refHash'];
				if (isset($dd[$cPrefix.'email'][$contactId][""]['refHash']) && $dd[$cPrefix.'email'][$contactId][""]['refHash'] == $emailRefHash) {
					$contact_details['email'] = $dd[$cPrefix.'email'][$contactId][""]["translation"];
				} else {
					$contact_details['email'] = $contactArr[$cPrefix.'email']['reference'];
				}

				$websiteRefHash = $contactArr[$cPrefix.'website']['refHash'];
				if (isset($dd[$cPrefix.'website'][$contactId][""]['refHash']) && $dd[$cPrefix.'website'][$contactId][""]['refHash'] == $websiteRefHash) {
					$contact_details['website'] = $dd[$cPrefix.'website'][$contactId][""]["translation"];
				} else {
					$contact_details['website'] = $contactArr[$cPrefix.'website']['reference'];
				}

				$websiteRefHash = $contactArr[$cPrefix.'additional_info']['refHash'];
				if (isset($dd[$cPrefix.'additional_info'][$contactId][""]['refHash']) && $dd[$cPrefix.'additional_info'][$contactId][""]['refHash'] == $websiteRefHash) {
					$contact_details['notes'] = $dd[$cPrefix.'additional_info'][$contactId][""]["translation"];
				} else {
					$contact_details['notes'] = $contactArr[$cPrefix.'additional_info']['reference'];
				}

				$contacts[] = $contact_details;
			}

			// Get MyCap Links data translations
			$linkPrefix = 'mycap-link-';
			$links = [];
			foreach ($proj_meta['myCap']['links'] as $linkId => $linkArr) {
				$link_details['identifier'] = $linkArr['identifier'];
				$titleRefHash = $linkArr[$linkPrefix.'link_name']['refHash'];
				if (isset($dd[$linkPrefix.'link_name'][$linkId][""]['refHash']) && $dd[$linkPrefix.'link_name'][$linkId][""]['refHash'] == $titleRefHash) {
					$link_details['name'] = $dd[$linkPrefix.'link_name'][$linkId][""]["translation"];
				} else {
					$link_details['name'] = $linkArr[$linkPrefix.'link_name']['reference'];
				}

				$linkRefHash = $linkArr[$linkPrefix.'link_url']['refHash'];
				if (isset($dd[$linkPrefix.'link_url'][$linkId][""]['refHash']) && $dd[$linkPrefix.'link_url'][$linkId][""]['refHash'] == $linkRefHash) {
					$link_details['url'] = $dd[$linkPrefix.'link_url'][$linkId][""]["translation"];
				} else {
					$link_details['url'] = $linkArr[$linkPrefix.'link_url']['reference'];
				}

				$links[] = $link_details;
			}
			if (ZeroDateTask::baselineDateEnabled($project_id)) {
				$baselineTitleRefHash = $proj_meta['myCap']["mycap-baseline_task"]["mycap-baseline_title"]['refHash'];
				if (isset($dd['mycap-baseline_task']["mycap-baseline_title"][""]['refHash']) && $dd['mycap-baseline_task']["mycap-baseline_title"][""]['refHash'] == $baselineTitleRefHash) {
					$zeroDateTask['title'] = $dd['mycap-baseline_task']["mycap-baseline_title"][""]['translation'];
				} else {
					$zeroDateTask['title'] = $proj_meta['myCap']['mycap-baseline_task']['mycap-baseline_title']['reference'];
				}
				$question1RefHash = $proj_meta['myCap']["mycap-baseline_task"]["mycap-baseline_question1"]['refHash'];
				if (isset($dd['mycap-baseline_task']["mycap-baseline_question1"][""]['refHash']) && $dd['mycap-baseline_task']["mycap-baseline_question1"][""]['refHash'] == $question1RefHash) {
					$zeroDateTask['question1'] = $dd['mycap-baseline_task']["mycap-baseline_question1"][""]['translation'];
				} else {
					$zeroDateTask['question1'] = $proj_meta['myCap']['mycap-baseline_task']['mycap-baseline_question1']['reference'];
				}

				$question2RefHash = $proj_meta['myCap']["mycap-baseline_task"]["mycap-baseline_question2"]['refHash'];
				if (isset($dd['mycap-baseline_task']["mycap-baseline_question2"][""]['refHash']) && $dd['mycap-baseline_task']["mycap-baseline_question2"][""]['refHash'] == $question2RefHash) {
					$zeroDateTask['question2'] = $dd['mycap-baseline_task']["mycap-baseline_question2"][""]['translation'];
				} else {
					$zeroDateTask['question2'] = $proj_meta['myCap']['mycap-baseline_task']['mycap-baseline_question2']['reference'];
				}

				$titleRefHash = $proj_meta['myCap']["mycap-baseline_task"]["mycap-baseline_instr_title"]['refHash'];
				if (isset($dd['mycap-baseline_task']["mycap-baseline_instr_title"][""]['refHash']) && $dd['mycap-baseline_task']["mycap-baseline_instr_title"][""]['refHash'] == $titleRefHash) {
					$zeroDateTask['instructionStep']['title'] = $dd['mycap-baseline_task']["mycap-baseline_instr_title"][""]['translation'];
				} else {
					$zeroDateTask['instructionStep']['title'] = $proj_meta['myCap']['mycap-baseline_task']['mycap-baseline_instr_title']['reference'];
				}
				$contentRefHash = $proj_meta['myCap']["mycap-baseline_task"]["mycap-baseline_instr_content"]['refHash'];
				if (isset($dd['mycap-baseline_task']["mycap-baseline_instr_content"][""]['refHash']) && $dd['mycap-baseline_task']["mycap-baseline_instr_content"][""]['refHash'] == $contentRefHash) {
					$zeroDateTask['instructionStep']['content'] = $dd['mycap-baseline_task']["mycap-baseline_instr_content"][""]['translation'];
				} else {
					$zeroDateTask['instructionStep']['content'] = $proj_meta['myCap']['mycap-baseline_task']['mycap-baseline_instr_content']['reference'];
				}
			}

			$ret_val['translations'] = ['uiTranslations' => $myCapUITranslations,
										'fieldTranslations' => $fieldTranslations,
										'appDataTranslation' => ['title' => $app_title,
																'tasks' => $ts_arr,
																'pages' => $pages,
																'contacts' => $contacts,
																'links' => $links,
																'zeroDateTask' => $zeroDateTask]
										];
			$ret_val["success"] = true;
		} else {
			// Return Invalid Language in response
			$ret_val["success"] = false;
			$ret_val["errors"] = array(RCView::tt_i_strip_tags("multilang_102", [$lang_id])); // The language '{0}' is not available.
		}
		return $ret_val;
	}

	/**
	 * Get field translations of a project (MyCap Moblile App)
	 * @param string $field
	 * @param array $dd
	 * @param array $attr
	 * @return array
	 */
	public static function getFieldTranslationsForMyCap($field, $dd, $attr) {
		$fieldTranslations['identifier'] = $field;
		if ($attr['field-label'] != '') {
			if (isset($dd["field-label"][$field][""]["refHash"]) && $dd["field-label"][$field][""]["refHash"] != '') {
				$fieldTranslations['title'] = $dd["field-label"][$field][""]["translation"];
			} else {
				$fieldTranslations['title'] = $attr["field-label"]["reference"];
			}
		}
		if ($attr['field-note']["reference"] != '') {
			if (isset($dd["field-note"][$field][""]["refHash"]) && $dd["field-note"][$field][""]["refHash"] != '') {
				$fieldTranslations['fieldNote'] = $dd["field-note"][$field][""]["translation"];
			} else {
				$fieldTranslations['fieldNote'] = $attr["field-note"]["reference"];
			}
		}

		if ($attr['field-header']["reference"] != '') {
			if (isset($dd["field-header"][$field][""]["refHash"]) && $dd["field-header"][$field][""]["refHash"] != '') {
				$fieldTranslations['sectionTitle'] = $dd["field-header"][$field][""]["translation"];
			} else {
				$fieldTranslations['sectionTitle'] = $attr["field-header"]["reference"];
			}
		}

		if (in_array($attr['type'], ['checkbox', 'select', 'radio', 'slider'])) {
			if (!is_null($attr['field-enum'])) {
				$select_choices_or_calculations = $attr['field-enum'];
				// Some PROMIS measures (NIH TB Hearing Handicap Ages 18-64) have empty length list of choices
				if (count($select_choices_or_calculations) > 0) {
					$choiceOptions = [];
					foreach ($select_choices_or_calculations as $value => $choice) {
						if (!empty($choice)) {
							$c['value'] = trim($value);
							if (isset($dd["field-enum"][$field][$value]["refHash"]) && $dd["field-enum"][$field][$value]["refHash"] != '') {
								$text = $dd["field-enum"][$field][$value]["translation"];
							} else {
								$text = $attr["field-enum"][$value]["reference"];
							}

							$c['text'] = trim($text);
							$choiceOptions[] = $c;
						}
					}

					if ($attr['type'] == 'slider') {
						foreach ($choiceOptions as $choice) {
							if ($choice['value'] == 'left') {
								$labelTexts['minimumDescription'] = $choice['text'];
							}
							if ($choice['value'] == 'middle') {
								$labelTexts['middleDescription'] = $choice['text'];
							}
							if ($choice['value'] == 'right') {
								$labelTexts['maximumDescription'] = $choice['text'];
							}
						}
						$fieldTranslations["scaleField"] = $labelTexts;
					} else {
						$fieldTranslations["textChoices"] = $choiceOptions;
					}
				}
			}
		}

		return $fieldTranslations;
	}

	/**
	 * Get table of languages list utilized by the MyCap app
	 * @param boolean $isClickable
	 * @return string
	 */
	public static function getMyCapAppLanguageList($isClickable = true) {
		$mcLangs = self::MYCAP_SUPPORTED_LANGS;
		$i = 0;
		$html = '<table width="100%" cellpadding="7" cellspacing="0"><tr>';
		if ($isClickable) {
			$html .= '<td colspan="2" style="color:#A00000;" class="cc_info">'.RCView::tt('multilang_776').'</td></tr><tr>';
		}
		foreach ($mcLangs as $langCode => $langText) {
			$i++;
			if ($i == 1 || $i == 10) { // Convert to 2 tables of equal rows
				$html .= '<td width="50%"><table width="100%" cellpadding="3" cellspacing="3" border="1" class="ReportTableWithBorder"><tr><td><b>ID</b></td><td><b>Display Name</b></td></tr>';
			}
			if ($isClickable) {
				$langCodeHTML = '<a href="javascript:;" onclick="populateLanguageID(\''.$langCode.'\', \''.$langText.'\'); " style="text-decoration:none;padding:0;margin:0 0 0 2px;font-size:11px; color:#0d6efd;">'.$langCode.'</a>';
			} else {
				$langCodeHTML = $langCode;
			}
			// Add entry to table
			$html .= '<tr valign="top"><td class="mt-5 boldish fs14 text-dangerrc">'.$langCodeHTML.'</td><td style="padding-left: 5px;">'.$langText.'</td></tr>';
			if ($i == 9 || $i == 18) {
				$html .= '</table></td>';
			}

		}
		$html .= '</tr></table>';
		return $html;
	}

	public static function getAITranslatorActionHTML() {
		$aiDetailsSet = \AI::isServiceDetailsSet();

		$html = '';
		if ($aiDetailsSet) { // Will be "TRUE" if details set at system-level and function called from control center::MLM OR details set at project-level and function called from project specific MLM pages
			$html = '<div class="mlm-ai-translate-tool round chklist text-center" style="background-color: #f5f5f5; width: 420px; margin: 5px; float: right; padding-top: 10px;">
				<label id="mlm-ui-translate-box-label">'.RCView::tt('openai_091').'</label>
				'.RCView::button(array('class'=>'btn btn-xs fs13 btn-defaultrc ms-1', 'style'=>'color:#d31d90;', 'data-mlm-action' => 'ai-translate'), RCView::fa('fa-solid fa-wand-sparkles mr-1').RCView::tt('openai_092')).'
			</div><div class="clear"></div>';
			$html .= addLangToJS(['openai_095', 'openai_096', 'openai_138'], false);
		}

		return $html;
	}

	#endregion

	#region Data Type Lists

	/**
	 * A list of types that rely on a form's name as name parameter (used for 
	 * updating the metadata table after changing a form's name while a project
	 * is still in development mode) or during draft mode
	 * @var string[]
	 */
	private static $formMetaDataTypes = array (
		"form-active", 
		"survey-active",
		"form-name",
		"survey-acknowledgement",
		"survey-confirmation_email_content",
		"survey-confirmation_email_from_display",
		"survey-confirmation_email_subject",
		"survey-end_survey_redirect_url",
		"survey-instructions",
		"survey-logo_alt_text",
		"survey-offline_instructions",
		"survey-repeat_survey_btn_text",
		"survey-response_limit_custom_text",
		"survey-stop_action_acknowledgement",
		"survey-survey_btn_text_next_page",
		"survey-survey_btn_text_prev_page",
		"survey-survey_btn_text_submit",
		"survey-text_to_speech",
		"survey-text_to_speech_language",
		"survey-title",
		"asi-email_content",
		"asi-email_sender_display",
		"asi-email_subject",
	);

	/**
	 * A list of types that rely on a field's name as name parameter (used for 
	 * updating the metadata table after changing a field's name while a project
	 * is still in development mode)
	 * @var string[]
	 */
	private static $fieldMetaDataTypes = array (
		"field-complete",
		"field-header",
		"field-label",
		"field-note", 
		"field-video_url",
		"field-enum",
		"field-actiontag",
	);

	/**
	 * A list of types that can be on a form's "_complete" field (used for 
	 * updating the metadata table after changing a form's name while a project
	 * is still in develompent mode)
	 * @var string[]
	 */
	private static $formCompleteMetaDataTypes = array (
		"field-complete",
		"field-header",
		"field-label",
		"field-enum",
		"task-complete",
	);

	/**
	 * A list of types that are allowed in the metadata table
	 * @var string[]
	 */
	private static $allowedMetaDataTypes = array (
		// Form/Survey Status
		"form-active", 
		"survey-active",
		// Forms
		"form-name",
		// MyCap Tasks
		"task-complete",
		// Events
		"event-name",
		"event-custom_event_label",
		// Fields
		"field-complete",
		"field-header",
		"field-label",
		"field-note", 
		"field-video_url",
		"field-enum",
		"field-actiontag",
		// Matrix Groups
		"matrix-complete",
		"matrix-header",
		"matrix-enum",
		// Survey Settings
		"survey-acknowledgement",
		"survey-confirmation_email_content",
		"survey-confirmation_email_from_display",
		"survey-confirmation_email_subject",
		"survey-end_survey_redirect_url",
		"survey-instructions",
		"survey-logo_alt_text",
		"survey-offline_instructions",
		"survey-repeat_survey_btn_text",
		"survey-response_limit_custom_text",
		"survey-stop_action_acknowledgement",
		"survey-survey_btn_text_next_page",
		"survey-survey_btn_text_prev_page",
		"survey-survey_btn_text_submit",
		"survey-text_to_speech",
		"survey-text_to_speech_language",
		"survey-title",
		// Survey Queue
		"sq-survey_auth_custom_message",
		"sq-survey_queue_custom_text",
		// ASI
		"asi-email_content",
		"asi-email_sender_display",
		"asi-email_subject",
		// Alerts
		"alert-excluded",
		"alert-email_from_display",
		"alert-email_subject",
		"alert-alert_message",
		"alert-sendgrid_template_data",
		// Missing Data Codes
		"mdc-label",
		// PDF Customizations
		"pdf-pdf_custom_header_text",
		// Protected Mail (REDCap Secure Messaging)
		"protmail-protected_email_mode_custom_text",
		// MyCap
		"mycap-app_title",
		"mycap-baseline_title",
		"mycap-baseline_question1",
		"mycap-baseline_question2",
		"mycap-baseline_instr_title",
		"mycap-baseline_instr_content",
		"mycap-about-page_title",
		"mycap-about-page_content",
		"mycap-contact-additional_info",
		"mycap-contact-contact_header",
		"mycap-contact-contact_title",
		"mycap-contact-email",
		"mycap-contact-phone_number",
		"mycap-contact-website",
		"mycap-link-link_name",
		"mycap-link-link_url",
		// Descriptive Popups
		"descriptive-popup",
		"descriptive-popup-complete",
	);

	/**
	 * A list of survey settings that are excludeable
	 * @var string[]
	 */
	private static $excludeableSurveySettings = array (
		"survey-acknowledgement",
		"survey-confirmation_email_content",
		"survey-confirmation_email_from_display",
		"survey-confirmation_email_subject",
		"survey-end_survey_redirect_url",
		"survey-instructions",
		"survey-logo_alt_text",
		"survey-offline_instructions",
		"survey-repeat_survey_btn_text",
		"survey-response_limit_custom_text",
		"survey-stop_action_acknowledgement",
		"survey-survey_btn_text_next_page",
		"survey-survey_btn_text_prev_page",
		"survey-survey_btn_text_submit",
		"survey-text_to_speech",
		"survey-text_to_speech_language",
		"survey-title",
	);

	#endregion

}
