<?php

use MultiLanguageManagement\MultiLanguage;
use REDCap\Context;

class PDF
{

	private static $survey_mode = false;
	private static $hideAllHiddenAndHiddenSurveyActionTagFields = false;
	private static $page_num_template = "Page {0}";
	private static $custom_header_text = "";
	public static $contains_completed_consent = false;

	//Set the character limit per line for questions (left column) and answers (right column)
	const char_limit_q = 54; //question char limit per line
	const char_limit_a = 51; //answer char limit per line
	const char_limit_slider = 18; //slider char limit per line
	//Set column width and row height
	const col_width_a = 105; //left column width
	const col_width_b = 75;  //right column width
	const sigil_width = 4;
	const atext_width = 70;
	const row_height = 4;
	//Set other widths
	const page_width = 190;
	const matrix_label_width = 55;
	//Indentation string
	const indent_q = "     ";
	const indent_a = "";
	//Parameters for determining page breaks
	const est_char_per_line = 110;
	const y_units_per_line = 4.5;
	const bottom_of_page = 275;
	// Slider parameters
	const rect_width = 1.5;
	const num_rect = 50;
	// Set max width of an entire line in the PDF
	const max_line_width = 190;
    // Set variable in order to output a subset of specific events/forms instead of either all or single event/form
    static public $selected_forms_events_array = []; // Array keys are event_ids with sub-array values as instrument names

	// Main endpoint function
	public static function output($survey_mode = false, $hideAllHiddenAndHiddenSurveyActionTagFields = false,
								  $selected_forms_events_array = array(), $returnString=false, $bypassFormExportRights=false)
	{
		PDF::$survey_mode = $survey_mode;
        PDF::$contains_completed_consent = false;
		PDF::$hideAllHiddenAndHiddenSurveyActionTagFields = $hideAllHiddenAndHiddenSurveyActionTagFields;
        // Set flag in case we need to output a specific subset of all forms/events
        PDF::$selected_forms_events_array = $selected_forms_events_array;

		// Flatten the selected forms events array to an array with form names as keys
		$selected_forms_array = [];
		if (is_array($selected_forms_events_array) && count($selected_forms_events_array) > 0) {
			foreach ($selected_forms_events_array as $_ => $forms) {
				foreach ($forms as $selected_form) {
					$selected_forms_array[$selected_form] = true; 
				}
			}
		}

		extract($GLOBALS);

        $draftMode = false;
        if (isset($_GET['page'])) {
            // Check if we should get metadata for draft mode or not
            $draftMode = ($Proj->project['status'] > 0 && isset($_GET['draftmode']));
        }

        // In case we need to output the Draft Mode version of the PDF, set $Proj object attributes as global vars
        global $ProjMetadata, $ProjForms;
        if ($draftMode) {
            $ProjMetadata = $Proj->metadata_temp;
            $ProjForms = $Proj->forms_temp;
            $ProjMatrixGroupNames = $Proj->matrixGroupNamesTemp;
        } else {
            $ProjMetadata = $Proj->getMetadata();
            $ProjForms = $Proj->getForms();
            $ProjMatrixGroupNames = $Proj->getMatrixGroupNames();
        }

		// Check if coming from survey or authenticated form
		if (isset($_GET['s']) && !empty($_GET['s']))
		{
			// Call config_functions before config file in this case since we need some setup before calling config
			require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
			// Validate and clean the survey hash, while also returning if a legacy hash
			$hash = $_GET['s'] = Survey::checkSurveyHash();
			// Set all survey attributes as global variables
			Survey::setSurveyVals($hash);
            // Make sure the hash matches the id/event_id/form and that this is not a public survey (never remove this check - it protects critical vulnerability)
            $sql = "select 1 from redcap_surveys_participants p, redcap_surveys_response r
                    where r.participant_id = p.participant_id and p.participant_email is not null 
                    and p.survey_id = '" . db_escape($ProjForms[$_GET['page']]['survey_id']) . "' and p.event_id = '" . db_escape($_GET['event_id']) . "' 
                    and r.record = '" . db_escape($_GET['id']) . "' and p.hash = '" . db_escape($hash) . "' limit 1";
            if (!db_num_rows(db_query($sql))) exit("ERROR");
			// Now set $_GET['pid'] before calling config
			$_GET['pid'] = $project_id;
			defined("NOAUTH") or define("NOAUTH", true);
			PDF::$survey_mode = true;
		}

		require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

		// GLOBAL VAR WORKAROUND: If we're including this file from INSIDE a method (e.g., REDCap::getPDF),
		// the global variables used in that file will not be defined, so we need to loop through ALL global variables to make them
		// local variables in this scope. Use crude method to detect if in global scope.
		if (isset($GLOBALS['system_offline']) && (!isset($system_offline) || $GLOBALS['system_offline'] != $system_offline)) {
			foreach (array_keys($GLOBALS) as $key) {
				if (strpos($key, '_') === 0 || $key == 'GLOBALS') continue;
				$$key = $GLOBALS[$key];
			}
		}

		$Proj = new Project($project_id);
		$project_encoding = $Proj->project['project_encoding'];
		$table_pk = $Proj->table_pk;
		global $project_encoding;

		// If a survey response, get record, event, form instance
		if (isset($_GET['s']) & isset($_GET['return_code']))
		{
			// Obtain required variables
			$participant_id = Survey::getParticipantIdFromHash($hash);
			$partArray = Survey::getRecordFromPartId(array($participant_id));
			$_GET['id'] = $partArray[$participant_id];
			$_GET['event_id'] = $event_id;
			$_GET['page'] = $form_name;
			$return_code = Survey::getSurveyReturnCode($_GET['id'], $_GET['page'], $_GET['event_id'], $_GET['instance'], true);
			// Verify the return code
			if ($_GET['return_code'] != $return_code) exit("ERROR!");
			// We are sure that this must be survey mode
			PDF::$survey_mode = true;
			// Download the PDF!
		}

		// Must have PHP extention "mbstring" installed in order to render UTF-8 characters properly AND also the PDF unicode fonts installed
		if (function_exists('mb_convert_encoding')) {
			// Define the UTF-8 PDF fonts' path
            defined("FPDF_FONTPATH")   or define("FPDF_FONTPATH",   APP_PATH_DOCROOT . "Resources" . DS . "pdf" . DS . "font" . DS);
            defined("_SYSTEM_TTFONTS") or define("_SYSTEM_TTFONTS", APP_PATH_DOCROOT . "Resources" . DS . "pdf" . DS . "font" . DS);
			// Set constant
            defined("USE_UTF8") or define("USE_UTF8", true);
			// Use tFPDF class for UTF-8 by default
			if ($project_encoding == 'chinese_utf8' || $project_encoding == 'chinese_utf8_traditional') {
				require_once APP_PATH_LIBRARIES . "PDF_Unicode.php";
			} else {
				require_once APP_PATH_LIBRARIES . "tFPDF.php";
			}
		} else {
			// Set contant
            defined("USE_UTF8") or define("USE_UTF8", false);
			// Use normal FPDF class
			require_once APP_PATH_LIBRARIES . "FPDF.php";
		}
		// If using language 'Japanese', then use MBFPDF class for multi-byte string rendering
		if ($project_encoding == 'japanese_sjis')
		{
			require_once APP_PATH_LIBRARIES . "MBFPDF.php"; // Japanese
			// Make sure mbstring is installed
			if (!function_exists('mb_convert_encoding'))
			{
				exit("ERROR: In order for multi-byte encoded text to render correctly in the PDF, you must have the PHP extention \"mbstring\" installed on your web server.");
			}
		}

		// Save fields into metadata array
		if (isset($_GET['page'])) {
			// Check if we should get metadata for draft mode or not
			$metadata_table = ($draftMode) ? "redcap_metadata_temp" : $Proj->getMetadataTable();
			// Make sure form exists first
			if ((!$draftMode && !isset(($Proj->getForms())[$_GET['page']])) || ($draftMode && !isset($Proj->forms_temp[$_GET['page']]))) {
				exit('ERROR!');
			}
			$Query = "select * from $metadata_table where project_id = $project_id and ((form_name = '{$_GET['page']}'
				  and field_name != concat(form_name,'_complete')) or field_name = '$table_pk') order by field_order";
		} else {
			$metadata_table = $Proj->getMetadataTable();
			$Query = "select * from $metadata_table where project_id = $project_id and
				  (field_name != concat(form_name,'_complete') or field_name = '$table_pk') order by field_order";
		}
		$QQuery = db_query($Query);
		$metadata = array();
		while ($row = db_fetch_assoc($QQuery))
		{
			// Skip if field is not on a selected form
			if (count($selected_forms_array) && !array_key_exists($row['form_name'], $selected_forms_array)) continue;
			// If field is an "sql" field type, then retrieve enum from query result
			if ($row['element_type'] == "sql") {
				$row['element_enum'] = getSqlFieldEnum($row['element_enum'], PROJECT_ID, $_GET['id']??null, $_GET['event_id']??null, $_GET['instance']??null, null, null, $_GET['page']??null);
			}
			// If PK field...
			if ($row['field_name'] == $table_pk) {
				// Ensure PK field is a text field
				$row['element_type'] = 'text';
				// When pulling a single form other than the first form, change PK form_name to prevent it being on its own page
				if (isset($_GET['page'])) {
					$row['form_name'] = $_GET['page'];
				}
			}
			// Store metadata in array
			$metadata[] = $row;
		}

		// Initialize values
		$data = array();
		$logging_description = "Download data entry form as PDF" . (isset($_GET['id']) ? " (with data)" : "");


		// Check export rights
		if (
			// No export rights at all
			((isset($_GET['id']) || isset($_GET['allrecords'])) && $user_rights['data_export_tool'] == '0')
			// No export rights to this form if exporting only this form
			|| (isset($_GET['id']) && isset($_GET['page']) && isset($user_rights['forms_export'][$_GET['page']]) && $user_rights['forms_export'][$_GET['page']] == '0')
		) {
			exit(RCView::tt("data_entry_233")); // ERROR: You are not allowed to download PDFs containing data because currently you have 'No Access' data export rights.
		}


		// GET SINGLE RECORD'S DATA (ALL FORMS for ALL EVENTS or SINGLE EVENT if event_id provided) OR specific forms/events (via PDF::$selected_forms_events_array)
		if (isset($_GET['id']) && !isset($_GET['page']))
		{
			// Set logging description
			$logging_description = "Download all data entry forms as PDF (with data)";
            // If outputting a subset of all forms/events, pull only data for this subset instead of all the data for the record
            if (is_array(PDF::$selected_forms_events_array) && !empty(PDF::$selected_forms_events_array)) {
                $getDataEvents = [];
                $getDataFields = [$Proj->table_pk];
                foreach (PDF::$selected_forms_events_array as $this_event_id=>$these_forms) {
                    $getDataEvents[] = $this_event_id;
                    foreach ($these_forms as $this_form) {
                        $getDataFields = array_merge($getDataFields, array_keys($ProjForms[$this_form]['fields']));
                    }
                }
            } else {
                $getDataEvents = $_GET['event_id'] ?? [];
                $getDataFields = [];
            }
			// Get all data for this record
			$getDataParams = [
				'records' => $_GET['id'],
				'events' => $getDataEvents,
				'fields' => $getDataFields,
				'groups' => $user_rights['group_id'],
				'removeMissingDataCodes' => true,
				'decimalCharacter' => '.',
			];
			$data = Records::getData($getDataParams);
			if (!isset($data[$_GET['id']])) {
                $data = array();
            }
            // If instance is provided for a single form PDF (e.g., e-Consent response), then return only that instance of data
            elseif (isset($_GET['id']) && isset($_GET['event_id']) && isset($_GET['instance']) && isset(PDF::$selected_forms_events_array[$_GET['event_id']])
                    && count(PDF::$selected_forms_events_array[$_GET['event_id']]) === 1
            ) {
                $currentFormName = PDF::$selected_forms_events_array[$_GET['event_id']][0];
                if (isset($ProjForms[$currentFormName]) && $Proj->isRepeatingFormOrEvent($_GET['event_id'], $currentFormName))
                {
                    $repeatingFormName = $Proj->isRepeatingEvent($_GET['event_id']) ? '' : $currentFormName;
                    if (isset($data[$_GET['id']][$_GET['event_id']])) unset($data[$_GET['id']][$_GET['event_id']]);
                    foreach (array_keys($data[$_GET['id']]['repeat_instances'][$_GET['event_id']][$repeatingFormName]) as $repeat_instance) {
                        if ($repeat_instance == $_GET['instance']) continue;
                        unset($data[$_GET['id']]['repeat_instances'][$_GET['event_id']][$repeatingFormName][$repeat_instance]);
                    }
                }
            }
		}

		// GET SINGLE RECORD'S DATA (SINGLE FORM ONLY)
		elseif (isset($_GET['id']) && isset($_GET['page']))
		{
			$id = trim($_GET['id']);
			// Ensure the event_id belongs to this project, and additionally if longitudinal, can be used with this form
			if (isset($_GET['event_id'])) {
				if (!$Proj->validateEventId($_GET['event_id'])
					// Check if form has been designated for this event
					|| !$Proj->validateFormEvent($_GET['page'], $_GET['event_id'])
					|| ($id == "") )
				{
					if ($longitudinal) {
						redirect(APP_PATH_WEBROOT . "DataEntry/record_home.php?pid=" . PROJECT_ID);
					} else {
						redirect(APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID . "&page=" . $_GET['page']);
					}
				}
			}
			// Get all data for this record
			$getDataParams = [
				'records' => $id,
				'fields' => array_merge(array($table_pk), array_keys($ProjForms[$_GET['page']]['fields'])),
				'events' => (isset($_GET['event_id']) ? $_GET['event_id'] : array()),
				'groups' => $user_rights['group_id'],
				'removeMissingDataCodes' => true,
				'decimalCharacter' => '.',
			];
			$data = Records::getData($getDataParams);
			if (!isset($data[$id])) $data = array();
			// Repeating forms only: Remove all other instances of this form to leave only the current instance
			if (isset($data[$id]['repeat_instances']) && (
					($Proj->isRepeatingForm($_GET['event_id'], $_GET['page']) && count($data[$id]['repeat_instances'][$_GET['event_id']][$_GET['page']] ?? []) > 1)
					|| ($Proj->isRepeatingEvent($_GET['event_id']) && count($data[$id]['repeat_instances'][$_GET['event_id']]['']) > 1)
				)) {
				$repeatingFormName = $Proj->isRepeatingEvent($_GET['event_id']) ? '' : $_GET['page'];
				foreach (array_keys($data[$id]['repeat_instances'][$_GET['event_id']][$repeatingFormName]) as $repeat_instance) {
					if ($repeat_instance == $_GET['instance']) continue;
					unset($data[$id]['repeat_instances'][$_GET['event_id']][$repeatingFormName][$repeat_instance]);
				}
			}
		}

		// GET ALL RECORDS' DATA
		elseif (isset($_GET['allrecords']))
		{
			// Set logging description
			$logging_description = "Download all data entry forms as PDF (all records)";
			// Get all data for this record
			$getDataParams = [
				'groups' => $user_rights['group_id'],
				'removeMissingDataCodes' => true,
				'decimalCharacter' => '.',
			];
			$data = Records::getData($getDataParams);
			// If project contains zero records, then the PDF will be blank. So return a message to user about this.
			if (empty($data)) {
				include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
				print RCView::tt("data_export_tool_220"); //= NOTICE: No records exist yet in this project, so there is no PDF file to download.
				print RCView::div(array('style'=>'padding:20px 0;'),
					renderPrevPageBtn("DataExport/index.php?other_export_options=1&pid=$project_id", RCView::tt("global_77"), false) //= Return to previous page
				);
				include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
				exit;
			}
		}

		// BLANK PDF FOR SINGLE FORM OR ALL FORMS
		else
		{
			$data[''][''] = null;
			// Set logging description
			if (isset($_GET['page'])) {
				$logging_description = "Download data entry form as PDF";
			} else {
				$logging_description = "Download all data entry forms as PDF";
			}
		}

		// Multilanguage support
		$context_builder = Context::Builder()
			->project_id($_GET["pid"])
			->survey_hash($_GET["s"] ?? null)
			->instrument($_GET["page"] ?? "")
			->page_fields(array_keys($ProjMetadata)) // all fields
			->event_id($_GET["event_id"] ?? null)
			->instance($_GET["instance"])
			->record($_GET["id"] ?? null)
			->lang_id(isset($_GET[MultiLanguage::LANG_GET_NAME]) ? $_GET[MultiLanguage::LANG_GET_NAME] : null)
			// ->group_id($params["groups"])
			->user_id(defined("USERID") ? USERID : null)
			->is_pdf();
		if ($survey_mode || isset($_GET["s"]) || isset($_GET[MultiLanguage::LANG_GET_ECONSENT_FLAG])) {
			$context_builder->is_survey();
		}
		else if (!isset($_GET[MultiLanguage::LANG_PDF_FORCE])) {
			$context_builder->is_dataentry();
		}
		global $context;
		$context = $context_builder->Build();

		## REFORMAT DATES AND/OR REMOVE DATA VALUES FOR DE-ID RIGHTS.
		## ALSO, ONTOLOGY AUTO-SUGGEST: Obtain labels for the raw notation values.
		if (!isset($data['']) && !empty($data))
		{
			// Get all validation types to use for converting DMY and MDY date formats
			$valTypes = getValTypes();
			$dateTimeFields = $dateTimeValTypes = array();
			foreach ($valTypes as $valtype=>$attr) {
				if (in_array($attr['data_type'], array('date', 'datetime', 'datetime_seconds'))) {
					$dateTimeValTypes[] = $valtype;
				}
			}

			// Create array of MDY and DMY date/time fields
			// and also create array of fields used for ontology auto-suggest
			$field_names = array();
			$ontology_auto_suggest_fields = $ontology_auto_suggest_cats = $ontology_auto_suggest_labels = array();
			foreach ($metadata as $attr) {
				$field_names[] = $attr['field_name'];
				$this_field_enum = $attr['element_enum'];
				// If Text field with ontology auto-suggest
				if ($attr['element_type'] == 'text' && $this_field_enum != '' && strpos($this_field_enum, ":") !== false) {
					// Get the name of the name of the web service API and the category (ontology) name
					list ($this_autosuggest_service, $this_autosuggest_cat) = explode(":", $this_field_enum, 2);
					// Add to arrays
					$ontology_auto_suggest_fields[$attr['field_name']] = array('service'=>$this_autosuggest_service, 'category'=>$this_autosuggest_cat);
					$ontology_auto_suggest_cats[$this_autosuggest_service][$this_autosuggest_cat] = true;
				}
				// If has date/time validation
				elseif (in_array($attr['element_validation_type'], $dateTimeValTypes)) {
					$dateFormat = substr($attr['element_validation_type'], -3);
					if ($dateFormat == 'mdy' || $dateFormat == 'dmy') {
						$dateTimeFields[$attr['field_name']] = $dateFormat;
					}
				}
			}

			// GET CACHED LABELS AUTO-SUGGEST ONTOLOGIES
			if (!empty($ontology_auto_suggest_fields)) {
				// Obtain all the cached labels for these ontologies used
				$subsql = array();
				foreach ($ontology_auto_suggest_cats as $this_service=>$these_cats) {
					$subsql[] = "(service = '".db_escape($this_service)."' and category in (".prep_implode(array_keys($these_cats))."))";
				}
				$sql = "select service, category, value, label from redcap_web_service_cache
					where project_id = $project_id and (" . implode(" or ", $subsql) . ")";
				$q = db_query($sql);
				while ($row = db_fetch_assoc($q)) {
					$ontology_auto_suggest_labels[$row['service']][$row['category']][$row['value']] = $row['label'];
				}
				// Remove unneeded variable
				unset($ontology_auto_suggest_cats);
			}

			// If user has de-id rights, then get list of fields
			$deidFieldsToRemove = ($user_rights['data_export_tool'] > 0 && !$bypassFormExportRights)
				? DataExport::deidFieldsToRemove($project_id, $field_names, $user_rights['forms_export'])
				: array();
			$deidFieldsToRemove = array_fill_keys($deidFieldsToRemove, true);
			unset($field_names);
			// Set flags
			$checkDateTimeFields = !empty($dateTimeFields);
			$checkDeidFieldsToRemove = !empty($deidFieldsToRemove);

			// LOOP THROUGH ALL DATA VALUES
			if (!empty($ontology_auto_suggest_fields) || $checkDateTimeFields || $checkDeidFieldsToRemove) {
				foreach ($data as $this_record=>&$event_data) {
					foreach ($event_data as $this_event_id1=>&$field_data) {
						if ($this_event_id1 == 'repeat_instances') {
							$eventNormalized = $event_data['repeat_instances'];
						} else {
							$eventNormalized = array();
							$eventNormalized[$this_event_id1][""][0] = $event_data[$this_event_id1];
						}
						foreach ($eventNormalized as $this_event_id=>$data1) {
							foreach ($data1 as $repeat_instrument=>$data2) {
								foreach ($data2 as $instance=>$data3) {
									foreach ($data3 as $this_field=>$this_value) {
										// If value is not blank
										if ($this_value != '') {
											// When outputting labels for TEXT fields with ONTOLOGY AUTO-SUGGEST, replace value with cached label
											if (isset($ontology_auto_suggest_fields[$this_field])) {
												// Replace value with label
												if ($ontology_auto_suggest_labels[$ontology_auto_suggest_fields[$this_field]['service']][$ontology_auto_suggest_fields[$this_field]['category']][$this_value]) {
													$this_value = $ontology_auto_suggest_labels[$ontology_auto_suggest_fields[$this_field]['service']][$ontology_auto_suggest_fields[$this_field]['category']][$this_value] . " ($this_value)";
												}
												if ($instance == '0') {
													$data[$this_record][$this_event_id][$this_field] = $this_value;
												} else {
													$data[$this_record][$this_event_id1][$this_event_id][$repeat_instrument][$instance][$this_field] = $this_value;
												}
											}
											// If a DMY or MDY datetime field, then convert value
											elseif ($checkDeidFieldsToRemove && isset($deidFieldsToRemove[$this_field])) {
												// If this is the Record ID field, then merely hash it IF the user has de-id or remove identifiers export rights
												if ($this_field == $Proj->table_pk) {
													if ($Proj->table_pk_phi) {
														if ($instance == '0') {
															$data[$this_record][$this_event_id][$this_field] = md5($salt . $this_record . $__SALT__);
														} else {
															$data[$this_record][$this_event_id1][$this_event_id][$repeat_instrument][$instance][$this_field] = md5($salt . $this_record . $__SALT__);
														}
													}
												} else {
													if ($instance == '0') {
														$data[$this_record][$this_event_id][$this_field] = MultiLanguage::getUITranslation($context, "data_entry_540");
													} else {
														$data[$this_record][$this_event_id1][$this_event_id][$repeat_instrument][$instance][$this_field] = MultiLanguage::getUITranslation($context, "data_entry_540");
													}
												}
											}
											// If a DMY or MDY datetime field, then convert value
											elseif ($checkDateTimeFields && isset($dateTimeFields[$this_field])) {
												if ($instance == '0') {
													$data[$this_record][$this_event_id][$this_field] = DateTimeRC::datetimeConvert($this_value, 'ymd', $dateTimeFields[$this_field]);;
												} else {
													$data[$this_record][$this_event_id1][$this_event_id][$repeat_instrument][$instance][$this_field] = DateTimeRC::datetimeConvert($this_value, 'ymd', $dateTimeFields[$this_field]);;
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}

		// If form was downloaded from Shared Library and has an Acknowledgement, render it here
		$acknowledgement = SharedLibrary::getAcknowledgement($project_id, isset($_GET['page']) ? $_GET['page'] : '');

		// Loop through metadata and replace any &nbsp; character codes with spaces.
		$pdfHasData = !isset($data['']);
		foreach ($metadata as &$attr) {
			$attr['element_label'] = str_replace('&nbsp;', ' ', $attr['element_label'] ?? "");
			$attr['element_enum'] = str_replace('&nbsp;', ' ', $attr['element_enum'] ?? "");
			$attr['element_note'] = str_replace('&nbsp;', ' ', $attr['element_note'] ?? "");
			$attr['element_preceding_header'] = str_replace('&nbsp;', ' ', $attr['element_preceding_header'] ?? "");
			// Also replace any embedded fields {field} and {field:icons} with square bracket counterpart [field] to force data to be piped to simulate Field Embedding.
			$attr['element_label'] = Piping::replaceEmbedVariablesInLabel($attr['element_label'] ?? "", $project_id, $attr['form_name'], $pdfHasData, !$pdfHasData);
			$attr['element_enum'] = Piping::replaceEmbedVariablesInLabel($attr['element_enum'] ?? "", $project_id, $attr['form_name'], $pdfHasData, !$pdfHasData);
			$attr['element_note'] = Piping::replaceEmbedVariablesInLabel($attr['element_note'] ?? "", $project_id, $attr['form_name'], $pdfHasData, !$pdfHasData);
			$attr['element_preceding_header'] = Piping::replaceEmbedVariablesInLabel($attr['element_preceding_header'] ?? "", $project_id, $attr['form_name'], $pdfHasData, !$pdfHasData);
		}

		// Logging (but don't do it if this script is being called via the API or via a plugin)
		if (!defined("API") && !defined("PLUGIN") && !(isset($_GET['s']) && !empty($_GET['s'])) && !isset($_GET['__noLogPDFSave'])) {
			$page = isset($_GET['page']) ? $_GET['page'] : '';
			Logging::logEvent("", "redcap_metadata", "MANAGE", (isset($_GET['id']) ? $_GET['id'] : ""), "form_name = $page", $logging_description);
		}

		$project_name = strip_tags(label_decode($Proj->project['app_title']));

		// Multilanguage support
		$pdf_translations = MultiLanguage::translatePDF($context, $metadata, $acknowledgement, $project_name, $data);

        // Set original language id, in case gets changed on the fly for e-Consent surveys
        $GLOBALS['orig_lang_id'] = MultiLanguage::getCurrentLanguage($context);
        if (isset($_GET['appendToFooter'])) $_GET['appendToFooterOrig'] = $_GET['appendToFooter'];
        if (isset($_GET['appendToHeader'])) $_GET['appendToHeaderOrig'] = $_GET['appendToHeader'];

		// Call the PDF hook
		$hookReturn = Hooks::call('redcap_pdf', array($project_id, $metadata, $data, (isset($_GET['page']) ? $_GET['page'] : ""), (isset($_GET['id']) ? $_GET['id'] : ""), (isset($_GET['event_id']) ? $_GET['event_id'] : ""), $_GET['instance']));
		if (isset($hookReturn['metadata']) && is_array($hookReturn['metadata'])) {
			// Overwrite $metadata if hook manipulated it
			$metadata = $hookReturn['metadata'];
		}
		if (isset($hookReturn['data']) && is_array($hookReturn['data'])) {
			// Overwrite $data if hook manipulated it
			$data = $hookReturn['data'];
		}

		// Render the PDF
        if ($returnString) {
            return PDF::renderPDF($metadata, $acknowledgement, $project_name, $data, isset($_GET['compact']), $pdf_translations, ($deidFieldsToRemove ?? null), $returnString, $bypassFormExportRights);
        } else {
            PDF::renderPDF($metadata, $acknowledgement, $project_name, $data, isset($_GET['compact']), $pdf_translations, ($deidFieldsToRemove ?? null), $returnString, $bypassFormExportRights);
        }
	}

	// Append custom text to header
	public static function appendToHeader($pdf)
	{
        if (isset($_GET['appendToHeaderEconsent']) && $_GET['appendToHeaderEconsent'] != "") {
            $pdf->SetFont(FONT,'B',8);
            $pdf->Cell(0,2,rawurldecode(urldecode($_GET['appendToHeaderEconsent'])),0,1,'R');
            $pdf->Ln();
        } elseif (isset($_GET['appendToHeader']) && $_GET['appendToHeader'] != "") {
			$pdf->SetFont(FONT,'B',8);
			$pdf->Cell(0,2,rawurldecode(urldecode($_GET['appendToHeader'])),0,1,'R');
			$pdf->Ln();
		}
		return $pdf;
	}

	//Check if need to start a new page with this question
	public static function new_page_check($num_lines, $pdf, $study_id_event, $forceNewPage=false)
	{
		if ($forceNewPage || ((self::y_units_per_line * $num_lines) + $pdf->GetY() > self::bottom_of_page))
        {
			$pdf->AddPage();
			// Set logo at bottom
			PDF::setFooterImage($pdf);
			// Set "Confidential" text at top
			$pdf = PDF::confidentialText($pdf);
			// If appending text to header
			$pdf = PDF::appendToHeader($pdf);
			// Add record name (for user-facing PDFs only)
			if ($study_id_event != "" && !PDF::$survey_mode) {
				$pdf->SetFont(FONT,'BI',8);
				$pdf->Cell(0,2,$study_id_event,0,1,'R');
				$pdf->Ln();
			}
            $pdf->SetFont(FONT,'I',8);
            // Add instance number for repeating instances
            if (isset($_GET['__pdf_instance'])) {
                $pdf->Cell(0, 2, '#'.$_GET['__pdf_instance'], 0, 1, 'R');
                $pdf->Ln();
            }
            // Add page number
			$pdf->Cell(0, 3, RCView::interpolateLanguageString(self::$page_num_template, [$pdf->PageNo()]), 0, 1, 'R');
			// Line break and reset font
			$pdf->Ln();
			$pdf->SetFont(FONT,'',10);
		}
		return $pdf;
	}

	// Add survey custom question number, if applicable
	public static function addQuestionNumber($pdf, $question_num, $isSurvey, $customQuesNum, $num)
	{
		if ($isSurvey)
		{
			if ($customQuesNum && $question_num != "") {
				// Custom numbered
				$currentXPos = $pdf->GetX();
				$pdf->SetX(2);
				$pdf->Cell(0,self::row_height,$question_num);
				$pdf->SetX($currentXPos);
			} elseif (!$customQuesNum && is_numeric($num)) {
				// Auto numbered
				$currentXPos = $pdf->GetX();
				$pdf->SetX(2);
				$pdf->Cell(0,self::row_height,$num.")");
				$pdf->SetX($currentXPos);
			}
		}
		return $pdf;
	}

	// Set "Confidential" text at top
	public static function confidentialText($pdf)
	{
		global $Proj, $project_encoding;
		// Do not display this for survey respondents
		if (PDF::$survey_mode) {
			return $pdf;
		}
        // Set header text to display
        if (PDF::$custom_header_text != "") {
            $headerText = PDF::$custom_header_text;
        } else {
            $headerText = (isset($Proj->project['pdf_custom_header_text']) && $Proj->project['pdf_custom_header_text'] !== null) ? $Proj->project['pdf_custom_header_text'] : RCView::getLangStringByKey("global_237"); //= Confidential
        }
		if ($project_encoding == 'japanese_sjis') {
			$headerText = mb_convert_encoding($headerText, "SJIS", "UTF-8"); // Japanese
		}
		// Get current position, so we can reset it back later
		$y = $pdf->GetY();
		$x = $pdf->GetX();
		// Set new position
		$pdf->SetY(3);
		$pdf->SetX(0);
		// Add text
		$pdf->SetFont(FONT,'I',12);
		$pdf->Cell(0,0,$headerText,0,1,'L');
		// Reset font and positions
		$pdf->SetFont(FONT,'',10);
		$pdf->SetY($y);
		$pdf->SetX($x);
		return $pdf;
	}

	//Set the footer with the URL for the consortium website and the REDCap logo
	public static function setFooterImage($pdf)
	{
		global $Proj, $project_encoding;
		// Determine if we should display REDCap logo and URL
		$displayLogoUrl = !(isset($Proj->project['pdf_show_logo_url']) && $Proj->project['pdf_show_logo_url'] == '0');
		// Set position and font
		$pdf->SetY(-4);
		$pdf->SetFont(FONT,'',8);
		// Set the current date/time as the left-hand footer
		$pdf->Cell(40,0,DateTimeRC::format_ts_from_ymd(NOW));
		// Append custom text to footer
		$buffer = 0;
		if (isset($_GET['appendToFooter'])) {
			$stringToPdfCell = trim(rawurldecode(urldecode(ltrim($_GET['appendToFooter'],','))));
			if ($project_encoding == 'japanese_sjis') {
				$stringToPdfCell = mb_convert_encoding($stringToPdfCell, "SJIS", "UTF-8"); // Japanese
			}
			$pdf->Cell(40,0,$stringToPdfCell);
			$pdf->Cell(10,0,'');
			$buffer = -50;
		}
		if ($displayLogoUrl) {
			// Set REDCap Consortium URL as right-hand footer
			$pdf->Cell(95+$buffer,0,'');
			$pdf->Cell(0,0,'projectredcap.org',0,0,'L',false,'https://projectredcap.org');
			$pdf->Image(LOGO_PATH . "redcap-logo-small.png", 176, 289, 24, 7);
		} else {
			// Set "Powered by REDCap" text
			$pdf->Cell(123+$buffer,0,'');
			$pdf->SetTextColor(128,128,128);
			$pdf->Cell(0,0,System::powered_by_redcap);
			$pdf->SetTextColor(0,0,0);
		}
		//Reset position to begin the page
		$pdf->SetY(6);
	}

	// Format the min, mid, and max labels for Sliders
	public static function slider_label($this_text) {
		global $project_encoding;
		$this_text = strip_tags(str_replace(["<br>", "<br/>", "<br />"], " ", $this_text));
		$this_text = str_replace("  ", " ", $this_text)." ";
		$slider_lines = array();
		$start_pos = 0;
		// Deal with 2-byte characters in strings
		if ($project_encoding != '') {
			if ($project_encoding == 'japanese_sjis') {
				$this_text = mb_convert_encoding($this_text, "SJIS", "UTF-8"); //Japanese
			}
			foreach (str_split(trim($this_text), self::char_limit_slider) as $newline) {
				if ($newline == '') continue;
				$slider_lines[] = trim($newline);
			}
			return $slider_lines;
		}
		// Normal processing
		do {
			$this_line = substr($this_text,$start_pos,self::char_limit_slider);
			$end_pos = strrpos($this_line," ");
			if ($end_pos === false) $end_pos = strlen($this_line);
			$slider_lines[] = substr($this_line,0,$end_pos);
			$start_pos = $start_pos + $end_pos + 1;
		} while ($start_pos < strlen($this_text));
		return $slider_lines;
	}

	//Format question text for questions with vertically-rendered answers
	public static function qtext_vertical($row) {
		$this_string = $row['element_label']; // We've already done stripping/replacing: strip_tags(br2nl(str_replace(array("\r\n","\r"), array("\n","\n"), label_decode($row['element_label']))));
		$lines = explode("\n", $this_string);
		$lines2 = array();
		foreach ($lines as $key=>$line) {
			$lines2[] = wordwrap($line, self::char_limit_q, "\n", true);
		}
		return explode("\n", implode("\n", $lines2));
	}

	public static function backwardStrpos($haystack, $needle, $offset = 0){
		$length = strlen($haystack);
		$offset = ($offset > 0)?($length - $offset):abs($offset);
		$pos = strpos(strrev($haystack), strrev($needle), $offset);
		return ($pos === false)?false:( $length - $pos - strlen($needle) );
	}

	public static function text_vertical($this_string) {
		$this_string = str_replace(array("\r\n","\r"), array("\n","\n"), html_entity_decode($this_string, ENT_QUOTES));
		$lines = explode("\n", $this_string);
		// Go through each line and place \n to break up into segments based on $char_limit value
		$lines2 = array();
		foreach ($lines as $key=>$line) {
			$lines2[] = wordwrap($line, self::char_limit_a, "\n", true);
		}
		return explode("\n", implode("\n", $lines2));
	}

	//Format answer text for questions with vertically-rendered answers
	public static function atext_vertical_mc($row, $dataNormalized, $project_language, $compactDisplay=false)
	{
		global $project_encoding;

		$atext = array();
		$line = array();

		// Set char limit as a little shorter for non-latin chars
        $local_char_limit_a = self::char_limit_a - ($project_encoding == '' ? 0 : 4);

        $row['element_enum'] = strip_tags(label_decode($row['element_enum']));
		if ($project_encoding == 'japanese_sjis') $row['element_enum'] = mb_convert_encoding($row['element_enum'], "SJIS", "UTF-8");

		// Loop through each choice for this field
		foreach (parseEnum($row['element_enum']) as $this_code=>$this_choice)
		{
			$this_code = $this_code."";
			if ($compactDisplay && $row['element_type'] != 'descriptive') {
				if (is_array($dataNormalized[$row['field_name']])) {
					// Checkbox with no choices checked
					if ($dataNormalized[$row['field_name']][$this_code] == 0) {
						continue;
					}
				} else {
					// Normal field type with no value
					if ($dataNormalized[$row['field_name']] != $this_code) {
						continue;
					}
				}
			}

			// Default: checkbox is unchecked
			$chosen = false;

			// Determine if this row's checkbox needs to be checked (i.e. it has data)
			if (isset($dataNormalized[$row['field_name']])) {
				if (is_array($dataNormalized[$row['field_name']])) {
					// Checkbox fields
					if (isset($dataNormalized[$row['field_name']][$this_code]) && $dataNormalized[$row['field_name']][$this_code] == "1") {
						$chosen = true;
					}
				} elseif ($dataNormalized[$row['field_name']]."" === $this_code."") {
					// Regular fields
					$chosen = true;
				}
			}

			$this_string = trim($this_choice);

			// Deal with 2-byte characters in strings
			if ($project_encoding != '') {
				$indent_this = false;
				foreach (str_split($this_string, $local_char_limit_a) as $newline) {
					$newline = trim($newline);
					if ($newline == '') continue;
					// Set values for this line of text
					$atext[] = array('chosen'=>$chosen, 'sigil'=>!$indent_this, 'line'=>($indent_this ? self::indent_a : '') . $newline);
					$indent_this = true;
				}
			}
			// Latin character language processing
			else {
				$start_pos = 0;
				do {
					$indent_this = false;
					if ($start_pos + $local_char_limit_a >= strlen($this_string)) {
						if ($start_pos == 0) {
							$this_line = substr($this_string,$start_pos,$local_char_limit_a); //if only one line of text
						} else {
							$this_line = self::indent_a . substr($this_string,$start_pos,$local_char_limit_a); //for last line of text
							$indent_this = true;
						}
						$end_pos = strlen($this_line);
					} else {
						if ($start_pos == 0) {
							$this_line = substr($this_string,$start_pos,$local_char_limit_a);
						} else {
							$this_line = self::indent_a . substr($this_string,$start_pos,$local_char_limit_a); //indent all lines after first line
							$indent_this = true;
						}
						$end_pos = strrpos($this_line," "); //for all lines of text except last
					}
					// Set values for this line of text
					$line = array('chosen'=>$chosen, 'sigil'=>true, 'line'=>substr($this_line,0,$end_pos));
					// If secondary line for same choice, then indent and do not display checkbox
					if ($indent_this) {
						$line['sigil'] = false;
						$end_pos = $end_pos - strlen(self::indent_a);
					}
					// Add line of text to array
					$atext[] = $line;
					// Set start position for next loop
					$start_pos = $start_pos + $end_pos + 1;
				} while ($start_pos <= strlen($this_string));
			}
		}

		return $atext;
	}

	// If all questions in the previous section were hidden, then manually remove the SH from the $pdf object
	public static function removeSectionHeader($pdf)
	{
		global $pdfLastSH;
		if ($pdfLastSH !== null) $pdf = clone $pdfLastSH;
		return $pdf;
	}

	// If all questions in the previous matrix were hidden, then manually remove the matrix header from the $pdf object
	public static function removeMatrixHeader($pdf)
	{
		global $pdfLastMH;
		if ($pdfLastMH !== null) $pdf = clone $pdfLastMH;
		return $pdf;
	}

	/**
	 * Build and render the PDF
	 */
	public static function renderPDF($metadata, $acknowledgement, $project_name = "", $Data = array(), $compactDisplay=false,
									 $translations = array(), $deid_fields = array(), $returnString = false, $bypassFormExportRights=false)
	{
		global $Proj, $table_pk, $table_pk_label, $longitudinal, $surveys_enabled,
			   $salt, $__SALT__, $user_rights, $ProjMetadata, $ProjForms, $project_encoding, $context, $study_id_event;

		self::$page_num_template = RCView::getLangStringByKey("survey_1365"); //= Page {0}
		// Set repeating instrument/event var
		$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
        // Check selected forms/events?
        $check_selected_forms_events = !empty(PDF::$selected_forms_events_array);
		// Set flag meaning that we're current in the PDF render method
        $_GET['__methodRenderPDF'] = 1;
		// Increase memory limit in case needed for intensive processing
		System::increaseMemory(2048);

		// Collect $pdf object in temporary form for section headers and matrix headers
		// in case we have to roll back the PDF if hiding those headers due to branching logic.
		global $pdfLastSH, $pdfLastMH;
		$pdfLastSH = $pdfLastMH = null;

		// Create array of event_ids=>event names
		$events = array();
		if (isset($Proj))
		{
			foreach ($Proj->events as $this_arm_num=>$this_arm)
			{
				foreach ($this_arm['events'] as $this_event_id=>$event_attr)
				{
					$events[$this_event_id] = strip_tags(label_decode($event_attr['descrip']));
				}
			}
		}
		else {
			$events[1] = 'Event 1';
		}

		// Determine if in Consortium website or REDCap core
		if (!defined("PROJECT_ID"))
		{
			// We are in Consortium website
			$project_language = 'English'; // set as default (English)
			$project_encoding = '';
            defined("LOGO_PATH") or define("LOGO_PATH", APP_PATH_DOCROOT . "resources/img/");
			// Set font constant
            defined("FONT") or define("FONT", "Arial");
		}
		else
		{
			// We are in REDCap core
			global $project_language;
            defined("LOGO_PATH") or define("LOGO_PATH", APP_PATH_DOCROOT . "Resources/images/");
			// Set font constant
			if ($project_encoding == 'japanese_sjis')
			{
				// Japanese
                defined("FONT") or define("FONT", KOZMIN);
			}
			else
			{
				// If using UTF-8 encoding, include other fonts
				if (USE_UTF8) {
					if ($project_encoding == 'chinese_utf8') {
						// Chinese Simplified
                        defined("FONT") or define("FONT", 'uGB');
					} elseif ($project_encoding == 'chinese_utf8_traditional') {
						// Chinese Traditional
                        defined("FONT") or define("FONT", 'uni');
					} else {
						// Normal UTF-8 (add-on package)
                        defined("FONT") or define("FONT", "DejaVu");
					}
				} else {
					// Default installation
                    defined("FONT") or define("FONT", "Arial");
				}
			}
		}

		//Begin creating PDF
		if ($project_encoding == 'japanese_sjis')
		{
			//Japanese
			$pdf = new FPDF_HTML();
			$pdf->AddMBFont(FONT ,'SJIS');
			$project_name = mb_convert_encoding($project_name, "SJIS", "UTF-8");
		}
		elseif ($project_encoding == 'chinese_utf8' || $project_encoding == 'chinese_utf8_traditional')
		{
			// Chinese
			$pdf = new FPDF_HTML();
			if (USE_UTF8) {
				// using adobe fonts
				if ($project_encoding == 'chinese_utf8') {
					// Chinese Simplified
					$pdf->AddUniGBhwFont();
				} elseif ($project_encoding == 'chinese_utf8_traditional') {
					// Chinese Traditional
					$pdf->AddUniCNSFont();
				}
			}
		}
		else
		{
			// Normal
			$pdf = new FPDF_HTML();
			// If using UTF-8 encoding, include other fonts
			if (USE_UTF8) {
				$pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
				$pdf->AddFont('DejaVu','B','DejaVuSansCondensed-Bold.ttf',true);
				$pdf->AddFont('DejaVu','I','DejaVuSansCondensed-Oblique.ttf',true);
				$pdf->AddFont('DejaVu','BI','DejaVuSansCondensed-BoldOblique.ttf',true);
			}
		}

		// Set paging settings
		$pdf->SetAutoPageBreak('auto'); # on by default with 2cm margin
		/*
		$pdf->AliasNbPages(); # defines a string which is substituted with total number of pages when the
							  # document is closed: '{nb}' by default.
		*/

		// Obtain custom record label & secondary unique field labels for all relevant records.
		$extra_record_labels = array();
		if (!isset($Data['']) && isset($Proj->project['pdf_hide_secondary_field']) && $Proj->project['pdf_hide_secondary_field'] == '0') {
			// Only get the extra labels if we have some data in $Data
			$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords(array_keys($Data), true);
		}

		// PIPING: Obtain all saved data for all records involved (just in case we need to pipe any into a label)
		$piping_record_data = (isset($Data[''])) ? array() : Records::getData([
			'records' => array_keys($Data),
			'returnEmptyEvents' => true,
			'decimalCharacter' => '.',
			'returnBlankForGrayFormStatus' => true,
		]);

		## BRANCHING LOGIC
		// Loop through all fields with branching logic, validate the syntax, and if valid add to array so we can eval on a per record basis.
		// This eliminates any branching with incorrect syntax (i.e. has javascript) that would cause a field to be hidden.
		// If the syntax is invalid, we'll always show the field in the PDF
		$branchingLogicValid = array();
		if (!isset($Data[''])) {
			// Loop through all fields
			foreach ($metadata as $row) {
				if ($row['branching_logic'] != '' && LogicTester::isValid($row['branching_logic'])) {
					$branchingLogicValid[$row['field_name']] = true;
				}
			}
		}

		// Get all embedded fields in the project
		$embeddedFields = Piping::getEmbeddedVariables($Proj->project_id);

		## LOOP THROUGH ALL EVENTS/RECORDS
		$pdfHasData = !isset($Data['']);
		foreach ($Data as $record=>$event_array)
		{
			// Reset for new record
			$pdfLastSH = $pdfLastMH = null;
			// Loop through records within the event
			foreach (array_keys($event_array) as $event_id)
			{
				if ($event_id == 'repeat_instances') {
					$eventNormalized = $event_array['repeat_instances'];
				} else {
					$eventNormalized = array();
					$eventNormalized[$event_id][""][0] = $event_array[$event_id];
				}

				// Loop through the normalized structure for consistency even with repeating instances
				foreach ($eventNormalized as $event_id=>$nattr1)
				{
                    // If outputting a subset of all forms/events, then skip any event/form not in PDF::$selected_forms_events_array
                    if ($record != '' && $check_selected_forms_events && !isset(PDF::$selected_forms_events_array[$event_id])) continue;

					// Display event name if longitudinal
					$event_name = (isset($events[$event_id]) ? $events[$event_id] : "");

					// Get record name to display (top right of pdf), if displaying data for a record
					$study_id_event = "";
					if ($record != '')
					{
						// Is PK an identifier? If so, then hash it
						$pk_display_val = ($ProjMetadata[$table_pk]['field_phi'] && $user_rights['forms_export'][$Proj->firstForm] > 1 && $Proj->table_pk_phi) ? md5($salt . $record . $__SALT__) : $record;
						// Set top-left corner display labels
						if(isset($Proj->project['pdf_hide_record_id']) && $Proj->project['pdf_hide_record_id'] == '0') {
							$study_id_event = "$table_pk_label $pk_display_val" .
								strip_tags(isset($extra_record_labels[$record]) ? " ".$extra_record_labels[$record] : "");
							// Display event name if longitudinal
							if (isset($longitudinal) && $longitudinal) {
								$study_id_event .= " ($event_name)";
							}
							if ($project_encoding == 'japanese_sjis') {
								$study_id_event = mb_convert_encoding($study_id_event, "SJIS", "UTF-8"); //Japanese
							}
						}
						// Add survey identifier to $study_id_event, if identifier exists and user does not have de-id rights
						$removeIdentifierFields = (!isset($user_rights['data_export_tool']) || ($user_rights['data_export_tool'] == '3' || $user_rights['data_export_tool'] == '2'));
						$response_time_text = array();
						if (isset($surveys_enabled) && $surveys_enabled && !isset($_GET['hideSurveyTimestamp']) && !$removeIdentifierFields)
						{
							$sql = "select p.participant_identifier, r.first_submit_time, r.completion_time, s.form_name, r.instance
									from redcap_surveys s, redcap_surveys_participants p, redcap_events_metadata e,
									redcap_surveys_response r where s.project_id = ".PROJECT_ID."
									and e.event_id = p.event_id and e.event_id = $event_id and s.survey_id = p.survey_id
									and p.participant_id = r.participant_id and r.record = '".db_escape($record)."'
									order by r.completion_time, r.first_submit_time";
							$q = db_query($sql);
							while ($rowsu = db_fetch_assoc($q)) {
								$added_template = isset($translations[$rowsu["form_name"]]) ? $translations[$rowsu["form_name"]]["data_entry_535"] : RCView::getLangStringByKey("data_entry_535"); //= Response was added on {0:Timestamp}.
								// Append identifier
								if ($rowsu['participant_identifier'] != "") {
									$study_id_event .= " - " . $rowsu['participant_identifier'];
								}
								// Set response time also
								if ($rowsu['completion_time'] == "" && $rowsu['first_submit_time'] != "") {
									// Partial
									$partial_text = isset($translations[$rowsu["form_name"]]) ? $translations[$rowsu["form_name"]]["data_entry_101"] : RCView::getLangStringByKey("data_entry_101"); //= Response is only partial and is not complete.
									$response_time_text[$rowsu['form_name']][$rowsu['instance']] = $partial_text." ".strip_tags(RCView::interpolateLanguageString($added_template, array(
										DateTimeRC::format_ts_from_ymd($rowsu['first_submit_time'])
									)));
								} 
								elseif ($rowsu['completion_time'] != "") {
									// Complete
									$response_time_text[$rowsu['form_name']][$rowsu['instance']] = strip_tags(RCView::interpolateLanguageString($added_template, array(
										DateTimeRC::format_ts_from_ymd($rowsu['completion_time'])
									)));
								}
							}
						}
					}

					$last_repeat_instrument = $last_repeat_instance = null;
					foreach ($nattr1 as $repeat_instrument=>$nattr2)
					{
						foreach ($nattr2 as $repeat_instance=>$dataNormalized)
						{
							// Set temp metadata array for this record/event
							$this_metadata = $metadata;

                            // Set instance number for PDF header, if applicable
                            if (isset($_GET['__pdf_instance'])) unset($_GET['__pdf_instance']);
                            if ($repeat_instance > 0 || $repeat_instrument != '') {
                                $_GET['__pdf_instance'] = $repeat_instance;
                            }

							// print "<hr>$record, $event_id, $repeat_instrument, $repeat_instance";
							// print_array($dataNormalized);

							// Skip if this is a repeating instrument but currently on base instance (or if a repeating instance and not a field on repeating form)

							/*
							 *   ATTENTION: WE MUST NOT DO THIS - otherwise MLM translations 
							 *   will not work the way this is set up here (copying based on row_idx
							 *   from the original metadata ==> row_idx will point to another field!!)
							 *   What we can (should) do to optimize, is to get rid of all fields
							 *   that are not on forms set in PDF::selected_forms_events_array!
							 */

							// if ($hasRepeatingFormsEvents) {
							// 	foreach ($this_metadata as $key=>$row) {
							// 		$isRepeatingForm = $Proj->isRepeatingForm($event_id, $row['form_name']);
							// 		$isRepeatingEvent = $Proj->isRepeatingEvent($event_id);
							// 		if (($repeat_instance == 0 && $isRepeatingForm)
							// 			|| ($repeat_instance != 0 && !$isRepeatingForm && !$isRepeatingEvent)
							// 			|| ($repeat_instance != 0 && $isRepeatingForm && $repeat_instrument != $row['form_name'])
							// 			|| ($repeat_instance != 0 && $isRepeatingEvent && !(isset($Proj->eventsForms[$event_id]) && is_array($Proj->eventsForms[$event_id]) && in_array($row['form_name'], $Proj->eventsForms[$event_id])))
							// 		) {
							// 			// Remove non-relevant field for this instance
							// 			unset($this_metadata[$key]);
							// 		}
							// 	}
							// }


							// PIPING: If exporting a PDF with data, then any labels, notes, choices that have field_names in them for piping, replace out with data.
							$mlm_lang = isset($context) ? $context->lang_id ?? false : false;
                            $this_metadata = self::pipeMetadata($this_metadata, $Proj->project_id, $record, $event_id, $row["form_name"], $repeat_instance, $piping_record_data, $pdfHasData, $mlm_lang);
							// Loop through each field to create row in PDF
							$num = 1;
							$last_form = "";
							$num_rows = count($this_metadata);
                            $econsentEnabledDescriptiveFieldLabel = null;
                            $econsentEnabledDescriptiveFieldDocId = null;
							for ($row_idx = 0; $row_idx < $num_rows; $row_idx++)
								// foreach ($this_metadata as $row)
							{
								$row = $this_metadata[$row_idx];
								$next_row = $row_idx < $num_rows - 1 ? $this_metadata[$row_idx + 1] : null;
								$instrument_about_to_change = $next_row != null && $row["form_name"] != $next_row["form_name"];

								// If longitudinal, make sure this form is designated for this event (if not, skip this loop and continue)
								if ($longitudinal && $record != '' && isset($Proj->eventsForms[$event_id]) && is_array($Proj->eventsForms[$event_id]) && !in_array($row['form_name'], $Proj->eventsForms[$event_id]))
								{
									continue;
								}

                                // If outputting a subset of all forms/events, then skip any event/form not in PDF::$selected_forms_events_array
                                if ($record != '' && $check_selected_forms_events && isset(PDF::$selected_forms_events_array[$event_id]) && !in_array($row['form_name'], PDF::$selected_forms_events_array[$event_id])) {
                                    continue;
                                }

                                // Evaluate @IF action tag, if exists
                                if ($row['misc'] !== null && strpos($row['misc'], "@IF") !== false) {
                                    $row['misc'] = Form::replaceIfActionTag($row['misc'], $Proj->project_id, $record, $event_id, $row['form_name'], $repeat_instance);
                                }

								// @HIDDEN-PDF action tags
								if (Form::hasHiddenPdfActionTag($row['misc'], $Proj->project_id, $record, $event_id, $row['form_name'], $repeat_instance)) {
									continue;
								}

								// If a survey respondent is downloading the PDF, hide all fields with @HIDDEN and @HIDDEN-SURVEY action tags
								if (PDF::$hideAllHiddenAndHiddenSurveyActionTagFields && Form::hasHiddenOrHiddenSurveyActionTag($row['misc'], $Proj->project_id, $record, $event_id, $row['form_name'], $repeat_instance)) {
									continue;
								}

								// If field is embedded in another field, then skip
								if (in_array($row['field_name'], $embeddedFields)) continue;

                                // If this is a repeating form, skip instance 0
                                if ($record != '' && $repeat_instance == '0' && $Proj->isRepeatingFormOrEvent($event_id, $row['form_name'])) {
                                    continue;
                                }
                                // If this is NOT a repeating form/event, skip instances > 0
                                if ($record != '' && $repeat_instance > 0 && !$Proj->isRepeatingFormOrEvent($event_id, $row['form_name'])) {
                                    continue;
                                }
                                // If this is repeating form, skip all fields NOT on this instrument
                                if ($record != '' && $repeat_instance > 0 && $repeat_instrument != '' && $row['form_name'] != $repeat_instrument && $Proj->isRepeatingForm($event_id, $row['form_name'])) {
                                    continue;
                                }

								// Compact display
								if ($compactDisplay && $record != '' && $row['element_type'] != 'descriptive') {
									if (is_array($dataNormalized[$row['field_name']])) {
										// Checkbox with no choices checked
										if (array_sum($dataNormalized[$row['field_name']]) == 0) {
											$num++; // Advance question number for auto-numbering (if needed)
											continue;
										}
									} else {
										// Normal field type with no value
										if ($dataNormalized[$row['field_name']] == '') {
											$num++; // Advance question number for auto-numbering (if needed)
											continue;
										}
									}
								}

								//print "<hr>$record, $event_id, {$row['field_name']}, {$row['form_name']}, $repeat_instrument, $repeat_instance, ";
								// var_dump($Proj->isRepeatingForm($event_id, $row['form_name']));

								// Check if starting new form or instance
								if ($last_form != $row['form_name']) // || $last_repeat_instrument != $repeat_instrument || $last_repeat_instance != $repeat_instance)
								{
                                    // Deal with hidden section headers and matrix headers
                                    if ($record != '')
                                    {
                                        // Check if all questions in previous matrix were hidden. If so, hide matrix header.
                                        if (isset($prev_grid_name) && $prev_grid_name != "" && $fieldsDisplayedInMatrix === 0)
                                        {
                                            $pdf = PDF::removeMatrixHeader($pdf);
                                        }
                                        // Check if all questions in previous section were hidden
                                        if (isset($fieldsDisplayedInSection) && $fieldsDisplayedInSection === 0) {
                                            $pdf = PDF::removeSectionHeader($pdf);
                                        }
                                    }

                                    // Initialize values for this instrument
                                    $econsentEnabledDescriptiveFieldLabel = null;
                                    $econsentEnabledDescriptiveFieldDocId = null;
                                    if (isset($_GET['appendEconsentFooter'])) {
                                        unset($_GET['appendEconsentFooter']);
                                    }
                                    if (isset($_GET['appendToFooterOrig'])) {
                                        $_GET['appendToFooter'] = $_GET['appendToFooterOrig'];
                                    } elseif (!isset($_GET['appendToFooterOrig']) && isset($_GET['appendToFooter'])) {
                                        unset($_GET['appendToFooter']);
                                    }
                                    if (isset($_GET['appendToHeaderOrig'])) {
                                        $_GET['appendToHeader'] = $_GET['appendToHeaderOrig'];
                                    } elseif (!isset($_GET['appendToHeaderOrig']) && isset($_GET['appendToHeader'])) {
                                        unset($_GET['appendToHeader']);
                                    }
                                    if (isset($_GET['appendToHeaderEconsent'])) {
                                        unset($_GET['appendToHeaderEconsent']);
                                    }
                                    // Reset the language back to language at beginning of PDF generation, if was changed temporarily to apply e-Consent participant language
                                    $lang_id = MultiLanguage::getCurrentLanguage($GLOBALS['context']); // global $consent already set in PDF::output()
                                    if ($GLOBALS['orig_lang_id'] != $lang_id) {
                                        $lang_id = $GLOBALS['orig_lang_id'];
                                        $GLOBALS['context'] = Context::Builder($GLOBALS['context'])->lang_id($lang_id)->Build();
                                        MultiLanguage::translatePDF($GLOBALS['context'], $metadata, $acknowledgement, $project_name, $Data);
                                        $this_metadata = self::pipeMetadata($metadata, $Proj->project_id, $record, $event_id, $row["form_name"], $repeat_instance, $piping_record_data, $pdfHasData, $lang_id);
                                        $row = $this_metadata[$row_idx]; // Set for this loop
                                    }
                                    // If Participant-facing e-Consent is enabled for this instrument, see if a consent form should be displayed below a Descriptive field
                                    if (isset($ProjForms[$row["form_name"]]["survey_id"]) && Econsent::econsentEnabledForSurvey($ProjForms[$row["form_name"]]['survey_id']))
                                    {
                                        // Record in a DAG?
                                        $dag_id = ($record == null) ? null : Records::getRecordGroupId($Proj->project_id, $record);
                                        // Get the e-Consent settings for this instrument
                                        $eConsentSettings = Econsent::getEconsentSurveySettings($ProjForms[$row["form_name"]]["survey_id"], $dag_id, $lang_id);
                                        // If this form has e-Consent enabled AND has already had the participant consent, get the lang_id of the ORIGINAL participant when they consented
                                        if (Survey::isResponseCompleted($ProjForms[$row["form_name"]]["survey_id"], $record, $event_id, ($repeat_instance == '0' ? '1' : $repeat_instance))) // Survey has been completed
                                        {
                                            // Set flag to denote that PDF contains a completed e-Consent survey response
                                            PDF::$contains_completed_consent = true;
                                            // Set flag to force display e-Consent footer
                                            $_GET['appendEconsentFooter'] = 1;
                                            // Get the original participant's language when they consented
                                            $sql = "select f.consent_form_filter_lang_id, f.consent_form_pdf_doc_id, f.consent_form_richtext
                                                    from redcap_surveys_pdf_archive p, redcap_econsent_forms f 
                                                    where f.consent_form_id = p.consent_form_id and p.consent_id = ? and p.record = ?
                                                    and p.event_id = ? and p.survey_id = ? and p.instance = ? 
                                                    order by p.doc_id desc limit 1";
                                            $params = [$eConsentSettings['consent_id'], $record, $event_id, $ProjForms[$row["form_name"]]["survey_id"], ($repeat_instance == '0' ? '1' : $repeat_instance)];
                                            $q = db_query($sql, $params);
                                            if (db_num_rows($q)) {
                                                $temp_lang_id = db_result($q, 0, 'consent_form_filter_lang_id');
                                                if ($temp_lang_id != null) {
                                                    // Re-fetch eConsentSettings based on the new lang_id
                                                    $lang_id = $temp_lang_id;
                                                    $eConsentSettings = Econsent::getEconsentSurveySettings($ProjForms[$row["form_name"]]["survey_id"], $dag_id, $lang_id);
                                                    // Reset language
                                                    $GLOBALS['context'] = Context::Builder($GLOBALS['context'])->lang_id($lang_id)->Build();
                                                    MultiLanguage::translatePDF($GLOBALS['context'], $metadata, $acknowledgement, $project_name, $Data);
                                                    $this_metadata = self::pipeMetadata($metadata, $Proj->project_id, $record, $event_id, $row["form_name"], $repeat_instance, $piping_record_data, $pdfHasData, $lang_id);
                                                    $row = $this_metadata[$row_idx]; // Set for this loop
                                                }
                                                // Since the e-Consent response is completed, we should output the consent form seen when the survey was completed (which might not be the current one)
                                                $eConsentSettings['consent_form_pdf_doc_id'] = db_result($q, 0, 'consent_form_pdf_doc_id');
                                                $eConsentSettings['consent_form_richtext'] = db_result($q, 0, 'consent_form_richtext');
                                            }
                                        }

                                        // Rich text or inline PDF?
                                        $econsentEnabledDescriptiveField = $eConsentSettings['consent_form_location_field'];
                                        if ($econsentEnabledDescriptiveField != null) {
                                            if ($eConsentSettings['consent_form_pdf_doc_id'] != '') {
                                                // Rendering inline PDF inside a PDF
                                                if (PDF::canConvertPdfToImages()) {
                                                    $econsentEnabledDescriptiveFieldDocId = $eConsentSettings['consent_form_pdf_doc_id'];
                                                } else {
                                                    $econsentEnabledDescriptiveFieldLabel = "[".RCView::tt('design_205','')." \"".Files::getEdocName($eConsentSettings['consent_form_pdf_doc_id'])."\"]";
                                                }
                                            } else {
                                                // Rich text
                                                $econsentEnabledDescriptiveFieldLabel = filter_tags($eConsentSettings['consent_form_richtext']);
                                            }
                                        }

										// Note if we need to remove identifier field data for users with de-id export rights
										$removeIdentifiers = (defined("PAGE") && PAGE == "PdfController:index" && defined("DEID_TEXT") && isset($user_rights)
															&& ($user_rights['forms_export'][$row["form_name"]] == '2' || $user_rights['forms_export'][$row["form_name"]] == '3'));

                                        // Update footer text: Append e-Consent footer info?
										if (isset($_GET['appendEconsentFooter']))
                                        {
											list ($nameDobText, $versionText, $typeText) = Econsent::getEconsentOptionsData($Proj->project_id, $_GET['id'], $row["form_name"], $dag_id, $lang_id, $removeIdentifiers);
                                            $versionTypeTextArray = array();
                                            if ($nameDobText != '') {
                                                $versionTypeTextArray[] = $nameDobText;
                                            }
                                            if ($versionText != '') {
                                                $versionTypeTextArray[] = (isset($translations[$row["form_name"]]) ? $translations[$row["form_name"]]["data_entry_428"] : RCView::getLangStringByKey("data_entry_428"))." ".$versionText; //= Version:
                                            }
                                            if ($typeText != '') {
                                                $versionTypeTextArray[] = $typeText; //= Type:
                                            }
                                            // Set as flag to add this text to footer
                                            $_GET['appendToFooter'] = implode(', ', $versionTypeTextArray);
                                        }
                                        // Get custom consent PDF header
                                        $ec = new Econsent();
                                        $consent_id = isset($ProjForms[$row["form_name"]]['survey_id']) ? ($ec->getEconsentBySurveyId($ProjForms[$row["form_name"]]['survey_id'])['consent_id'] ?? null) : null;
                                        $_GET["appendToHeaderEconsent"] = ($consent_id == null) ? "" : $ec->getCustomEconsentLabel($Proj->project_id, $record, $event_id, $row["form_name"], $repeat_instance, $consent_id, $removeIdentifiers);
                                    }

									// Update PDF custom header text and page number template
									PDF::$custom_header_text = $Proj->project["pdf_custom_header_text"] ?? RCView::getLangStringByKey("global_237");
									if (isset($translations[$row["form_name"]])) {
                                        PDF::$custom_header_text = $Proj->project["pdf_custom_header_text"] = $translations[$row["form_name"]]["__PDF_CUSTOM_HEADER_TEXT"];
										self::$page_num_template = $translations[$row["form_name"]]["survey_1365"];
									}

									// For compact displays where form has never had any data entered, skip it in the pdf export
									if ($compactDisplay && $record != '' && $dataNormalized[$row['form_name']."_complete"] == '0')
									{
										// Loop through all values to see if they're blank
										$dataFieldThisForm = array_intersect_key($dataNormalized, $ProjForms[$row['form_name']]['fields']);
										$formHasData = false;
										foreach ($dataFieldThisForm as $this_field2=>$this_val2) {
											if ($this_field2 == $row['form_name']."_complete") continue;
											if ((!is_array($this_val2) && $this_val2 != '') || (is_array($this_val2) && array_sum($this_val2) > 0)) {
												$formHasData = true;
												break;
											}
										}
										unset($dataFieldThisForm);
										// If all fields are blank, then skip this form
										if (!$formHasData) {
											continue;
										}
									}

									// If user has "no access" export rights for this form, skip this form (but only if we are exporting a form with data)
									if (isset($user_rights['forms_export']) && isset($user_rights['forms_export'][$row['form_name']])
										&& $user_rights['forms_export'][$row['form_name']] == '0'
                                        // Only skip form if we are exporting a form with data
                                        && !isset($Data[''])
										// If not bypassing this via PDF Snapshot generation
										&& !$bypassFormExportRights
									) {
										continue;
									}

									if ($last_form != "") {
										PDF::displayLockingEsig($pdf, $record, $event_id, $last_form, $repeat_instance, $study_id_event);
									}
									//print "<br>New page! field: {$row['field_name']}, $last_form != {$row['form_name']} || $last_repeat_instrument != $repeat_instrument || $last_repeat_instance != $repeat_instance";
									// Set flags to denote beginning of new form/page
									$fieldsDisplayedInSection = $fieldsDisplayedInMatrix = 0;
									$prev_grid_name = "";
									// Set flag for first SH encountered
									$encounteredFirstSH = false;

									// Set form/survey values
									if (isset($Proj) && is_array($ProjForms) && isset($ProjForms[$row['form_name']]['survey_id'])) {
										// Survey
										$survey_id = $ProjForms[$row['form_name']]['survey_id'];
										$isSurvey = true;
										$newPageOnSH = $Proj->surveys[$survey_id]['question_by_section'];
										$customQuesNum = !$Proj->surveys[$survey_id]['question_auto_numbering'];
										$survey_instructions = strip_tags(PDF::formatText(Piping::replaceVariablesInLabel(label_decode(label_decode($Proj->surveys[$survey_id]['instructions'])), $record, $event_id, $repeat_instance, $piping_record_data, true, null, false, "", 1, false, true, $row['form_name'], null, false, false, false, true)));
                                        $form_title = strip_tags(label_decode($Proj->surveys[$survey_id]['title']));
									} elseif (isset($Proj) && is_array($ProjForms)) {
										// Form
										$survey_instructions = "";
										$isSurvey = false;
										$newPageOnSH = false;
										$customQuesNum = false;
										$form_title = strip_tags(label_decode($ProjForms[$row['form_name']]['menu']));
									} else {
										// Shared Library defaults
										$form_title = $project_name;
										$customQuesNum = false;
										$isSurvey = false;
									}

									if ($project_encoding == 'japanese_sjis') {
										$form_title = mb_convert_encoding($form_title, "SJIS", "UTF-8"); //Japanese
										$survey_instructions = mb_convert_encoding($survey_instructions, "SJIS", "UTF-8"); //Japanese
									}

									// For surveys only, skip participant_id field
									if (isset($isSurvey) && $isSurvey && $row['field_name'] == $table_pk) {
										$atSurveyPkField = true;
										continue;
									}

									// Begin new page
									$pdf->AddPage();
									// Set REDCap logo at bottom right
									PDF::setFooterImage($pdf);
									// Set "Confidential" text at top
									$pdf = PDF::confidentialText($pdf);
									//Display project name (top right)
									$pdf->SetFillColor(0,0,0); # Set fill color (when used) to black
									$pdf->SetFont(FONT,'I',8); # retained from page to page. #  'I' for italic, 8 for size in points.
									if (!$isSurvey) {
										$pdf->Cell(0,2,$project_name,0,1,'R');
										$pdf->Ln();
									}
									// If appending text to header
									$pdf = PDF::appendToHeader($pdf);
									//Display record name (top right), if displaying data for a record
									if ($study_id_event != "" && !PDF::$survey_mode) {
										$pdf->SetFont(FONT,'BI',8);
										$pdf->Cell(0,2,$study_id_event,0,1,'R');
										$pdf->Ln();
										$pdf->SetFont(FONT,'I',8);
									}
                                    // Add instance number for repeating instances
                                    if (isset($_GET['__pdf_instance'])) {
                                        $pdf->Cell(0, 2, '#'.$_GET['__pdf_instance'], 0, 1, 'R');
                                        $pdf->Ln();
                                    }
									//Initial page number
									$pdf->SetFont(FONT,'I',8);
									$pdf->Cell(0, 3, RCView::interpolateLanguageString(self::$page_num_template, [$pdf->PageNo()]), 0, 1, 'R');
									//Display form title as page header
									$pdf->SetFont(FONT,'B',18);
									$pdf->MultiCell(0,8,$form_title,0);
									$pdf->SetFont(FONT,'',10);
									// Survey instructions, if a survey
									if (isset($isSurvey) && $isSurvey)
									{
										$has_instructions = trim($survey_instructions) != "";
										if ($has_instructions) {
											$pdf->MultiCell(0,self::row_height,"\n".$survey_instructions,0);
										}
										// Set the survey response time
										$repeat_instance_norm = ($repeat_instance < 1) ? 1 : $repeat_instance;
										$display_survey_timestamp = isset($response_time_text[$row['form_name']][$repeat_instance_norm]);
										if ($display_survey_timestamp) {
											$pdf->Ln();
											// Display timestamp for surveys
											$pdf->SetFont(FONT,'',10);
											$pdf->SetTextColor(255,255,255);
											$pdf->MultiCell(0,6,$response_time_text[$row['form_name']][$repeat_instance_norm],1,'L',1);
											$pdf->SetTextColor(0,0,0);
											$pdf->SetFont(FONT,'',10);

										}
										if ($has_instructions || $display_survey_timestamp) {
											$pdf->Ln();
										}
									}
									// Set as default for next loop
									$atSurveyPkField = false;
								}

								// If the current value is blank, then remove it from $Data to correct interpretation issues (due to change of old code)
								if (isset($dataNormalized[$row['field_name']]) && $dataNormalized[$row['field_name']] == '') {
									unset($dataNormalized[$row['field_name']]);
								} elseif (isset($dataNormalized[$row['field_name']]) && !is_array($dataNormalized[$row['field_name']])) {
									// Replace tabs with 5 spaces for text because otherwise it will display them as square box characters
									$dataNormalized[$row['field_name']] = str_replace("\t", "     ", $dataNormalized[$row['field_name']]);
								}

								//Set default font
								$pdf->SetFont(FONT,'',10);
								$q_lines = array();
								$a_lines = array();

								## MATRIX QUESTION GROUPS
								$matrixGroupPosition = ''; //default
								$grid_name = $row['grid_name'];
								$matrixHeight = null;
								// Just ended a grid, so give a little extra space
								if ($grid_name == "" && $prev_grid_name != $grid_name)
								{
									$pdf->Ln();
								}
								// Beginning a new grid
								elseif ($grid_name != "" && $prev_grid_name != $grid_name)
								{
									// Set that field is the first field in matrix group
									$matrixGroupPosition = '1';
									// Get total matrix group height, including SH, so check if we need a page break invoked below
									$matrixHeight = self::row_height * PDF::getMatrixHeight($pdf, $row['field_name']);
								}
								// Continuing an existing grid
								elseif ($grid_name != "" && $prev_grid_name == $grid_name)
								{
									// Set that field is *not* the first field in matrix group
									$matrixGroupPosition = 'X';
								}


								// If just ended a matrix in the previous loop, then check if all questions in previous matrix were hidden.
								// If so, hide matrix header.
								if ($record != '') {
									if ($prev_grid_name != "" && $prev_grid_name != $grid_name && $fieldsDisplayedInMatrix !== 0)
									{
										// Set to 0 if we have a matrix back-to-back and this is the start of the second matrix
										$fieldsDisplayedInMatrix = 0;
									}
									elseif ($prev_grid_name != "" && $prev_grid_name != $grid_name && $fieldsDisplayedInMatrix === 0)
									{
										$pdf = PDF::removeMatrixHeader($pdf);
									}
								}

								// REMOVE SH: Check if all questions in previous section were hidden. Skip if this is field 2 on form 1 (field right after table_pk).
								if (($row['element_preceding_header'] != "" || $instrument_about_to_change) && $record != '' && $fieldsDisplayedInSection === 0 && $encounteredFirstSH && $row['field_order'] != '2') {
									$pdf = PDF::removeSectionHeader($pdf);
								}

								$row['element_label'] = label_decode($row['element_label']);
								$row['element_preceding_header'] = label_decode($row['element_preceding_header']);
								// Pre-format any field labels created with the rich text editor
								if (strpos($row['element_label'], '"rich-text-field-label"') || strpos($row['element_label'], "'rich-text-field-label'")) {
									$row['element_label'] = PDF::formatText($row['element_label']);
								}
								if (strpos($row['element_preceding_header'], '"rich-text-field-label"') || strpos($row['element_preceding_header'], "'rich-text-field-label'")) {
									$row['element_preceding_header'] = PDF::formatText($row['element_preceding_header']);
								}
								// Remove HTML tags from field labels and field notes
								$row['element_label'] = trim(strip_tags(br2nl($row['element_label'])));
								$row['element_preceding_header'] = trim($row['element_preceding_header']);
								// For all fields (except Descriptive), remove line breaks
								if ($row['element_type'] != 'descriptive') {
									$row['element_label'] = str_replace(array("\r\n","\r"), array("\n","\n"), $row['element_label']);
								}
								if ($project_encoding == 'japanese_sjis') $row['element_label'] = mb_convert_encoding($row['element_label'], "SJIS", "UTF-8"); //Japanese
								if ($row['element_note'] != "") {
									$row['element_note'] = strip_tags(label_decode($row['element_note']));
									if ($project_encoding == 'japanese_sjis') $row['element_note'] = mb_convert_encoding($row['element_note'], "SJIS", "UTF-8"); //Japanese
								}

								// If a Matrix AND whole matrix will exceed length of page
								$matrixExceedPage = ($matrixGroupPosition == '1' && ($pdf->GetY()+$matrixHeight) > (self::bottom_of_page-20) && $pdf->PageNo() > 1);
								// If Section Header AND (starting new page OR close to the bottom)
								$headerExceedPage = ($row['element_preceding_header'] != "" && ((isset($isSurvey) && $isSurvey && $newPageOnSH && $num != 1) || ($pdf->GetY() > self::bottom_of_page-50)));

								// Check pagebreak for Section Header OR Matrix
								if ($matrixExceedPage || $headerExceedPage) {
									// Cache the current $pdf object in case we have to hide this header later
									if ($matrixExceedPage) $pdfLastMH = clone $pdf;
									// Cache the current $pdf object in case we have to hide this header later
									if ($headerExceedPage) $pdfLastSH = clone $pdf;
									// Begin new page
									$pdf->AddPage();
									PDF::setFooterImage($pdf);
									// Set "Confidential" text at top
									$pdf = PDF::confidentialText($pdf);
									// If appending text to header
									$pdf = PDF::appendToHeader($pdf);
									//Display record name (top right), if displaying data for a record
									if ($study_id_event != "" && !PDF::$survey_mode) {
										$pdf->SetFont(FONT,'BI',8);
										$pdf->Cell(0,2,$study_id_event,0,1,'R');
										$pdf->Ln();
									}
									$pdf->SetFont(FONT,'I',8);
                                    // Add instance number for repeating instances
                                    if (isset($_GET['__pdf_instance'])) {
                                        $pdf->Cell(0, 2, '#'.$_GET['__pdf_instance'], 0, 1, 'R');
                                        $pdf->Ln();
                                    }
									$pdf->Cell(0, 3, RCView::interpolateLanguageString(self::$page_num_template, [$pdf->PageNo()]), 0, 1, 'R');
								}

								// Section header
								if ($row['element_preceding_header'] != "")
								{
									// Cache the current $pdf object in case we have to hide this header later
									if (!$headerExceedPage) $pdfLastSH = clone $pdf;

									// Render section header
									$shHeightBefore = $pdf->GetY();
									$pdf->Ln();
									// $pdf->MultiCell(0,0,'','B'); $pdf->Ln();
									// $pdf->MultiCell(0,0,'','T'); $pdf->Ln();
									$pdf->SetFont(FONT,'B',11);
									$row['element_preceding_header'] = strip_tags(br2nl($row['element_preceding_header']));
									if ($project_encoding == 'japanese_sjis') $row['element_preceding_header'] = mb_convert_encoding($row['element_preceding_header'], "SJIS", "UTF-8"); //Japanese
									$pdf->SetFillColor(225,225,225);
									$pdf->MultiCell(0,6,$row['element_preceding_header'],'T','J',true);
									//$pdf->Ln();
									$pdf->SetFont(FONT,'',10);
									// Set flag to denote a new section
									//print " <b>".$row['element_preceding_header']."</b><br>";
									$fieldsDisplayedInSection = 0;
									// Set flag
									$encounteredFirstSH = true;
									// Calculate the total height of this SH
									$heightPrevSH = $pdf->GetY() - $shHeightBefore;
								}

								// APPLY BRANCHING LOGIC
								$displayField = true; //default
								if ($record != '')
								{
									// If field has data, then show it regardless (may have data but is trying to be hidden)
									if (!(isset($dataNormalized[$row['field_name']]) && !is_array($dataNormalized[$row['field_name']])
										&& $dataNormalized[$row['field_name']] != ''))
									{
										// Check logic, if applicable
										if (isset($branchingLogicValid[$row['field_name']])) {
											// If longitudinal, then inject the unique event names into logic (if missing)
											// in order to specific the current event.
											if ($longitudinal) {
												$row['branching_logic'] = LogicTester::logicPrependEventName($row['branching_logic'], $Proj->getUniqueEventNames($event_id), $Proj);
											}
											$row['branching_logic'] = Piping::pipeSpecialTags($row['branching_logic'], $Proj->project_id, $record, $event_id, $repeat_instance, null, true, null, $row['form_name'], false, false, false, true, false, false, true);
											if ($Proj->hasRepeatingFormsEvents()) {
												$row['branching_logic'] = LogicTester::logicAppendInstance($row['branching_logic'], $Proj, $event_id, $row['form_name'], $repeat_instance);
											}
											$displayField = LogicTester::apply($row['branching_logic'], $piping_record_data[$record]);
											// If field should be hidden, then skip it here
											if (!$displayField) {
												// Set last_form for next loop
												$last_form = $row['form_name'];
												// Set value for next loop
												$prev_grid_name = $grid_name;
												// If beginning a matrix of fields here, then go ahead and render this matrix header row (we will remove later if needed)
												if ($matrixGroupPosition == '1') {
													$mhHeightBefore = $pdf->GetY();
													if ($project_encoding == 'japanese_sjis') {
														$thisEnum = parseEnum(mb_convert_encoding($row['element_enum'], "SJIS", "UTF-8")); //Japanese
													} else {
														$thisEnum = parseEnum($row['element_enum']);
													}
													// Cache the current $pdf object in case we have to hide this header later
													if (!$matrixExceedPage) $pdfLastMH = clone $pdf;
													$pdf = PDF::renderMatrixHeaderRow($pdf, $thisEnum);
													// Calculate the total height of this matrix header
													$heightPrevMH = $pdf->GetY() - $mhHeightBefore;
												}
												// Stop and begin next loop
												continue;
											}
										}
									}
									// Set flag to denote that field was displayed
									$fieldsDisplayedInSection++;
								}

								// Beginning a new grid
								if ($matrixGroupPosition == '1') {
									$fieldsDisplayedInMatrix = 1;
								}
								// Continuing an existing grid
								elseif ($matrixGroupPosition == 'X') {
									$fieldsDisplayedInMatrix++;
								}
								// Just ended a grid, so give a little extra space
								else {
									$fieldsDisplayedInMatrix = 0;
								}

								// DON'T DO THIS YET. SHOULD WE EVER DO THIS? MIGHT GO AGAINST WHAT PEOPLE HAVE ALREADY DONE.
								// If any drop-down fields are RH alignment, set as RV to emulate webpage appearance better since they get rendered out as radios here.
								// if (($row['element_type'] == "select" || $row['element_type'] == "sql") && $row['custom_alignment'] == 'RH') {
								// $row['custom_alignment'] = 'RV';
								// }

								// If a multiple choice field had its data removed via De-Id rights, then don't display its choices
								if (isset($dataNormalized[$row['field_name']]) && isset($deid_fields[$row['field_name']]) && $deid_fields[$row['field_name']]) {
									$row['element_type'] = "text";
								}

								//Drop-downs & Radio buttons
								if ($row['element_type'] == "yesno" || $row['element_type'] == "truefalse" || $row['element_type'] == "radio" || $row['element_type'] == "select" || $row['element_type'] == "advcheckbox" || $row['element_type'] == "checkbox" || $row['element_type'] == "sql")
								{
									//If AdvCheckbox, render as Yes/No radio buttons
									if ($row['element_type'] == "advcheckbox") {
										$row['element_enum'] = "1, ";
									}
									//If Yes/No, manually set options
									elseif ($row['element_type'] == "yesno") {
										// $row['element_enum'] = YN_ENUM;
										$row['element_enum'] = "1, ".MultiLanguage::getUITranslation($context, "design_100")." \\n 0, ".MultiLanguage::getUITranslation($context, "design_99");
									}
									//If True/False, manually set options
									elseif ($row['element_type'] == "truefalse") {
										// $row['element_enum'] = TF_ENUM;
										$row['element_enum'] = "1, ".MultiLanguage::getUITranslation($context, "design_186")." \\n 0, ".MultiLanguage::getUITranslation($context, "design_187");
									}

									if ($row['element_note'] != "") $row['element_note'] = "(".$row['element_note'].")";

									// If a Matrix formatted field
									if ($row['grid_name'] != '') {
										// Parse choices into an array
										$enum = parseEnum($row['element_enum']);
										// Render this matrix header row
										if ($matrixGroupPosition == '1') {
											$mhHeightBefore = $pdf->GetY();
											if ($project_encoding == 'japanese_sjis') {
												$enum = parseEnum(mb_convert_encoding($row['element_enum'], "SJIS", "UTF-8")); //Japanese
											} else {
												$enum = parseEnum($row['element_enum']);
											}
											// Cache the current $pdf object in case we have to hide this header later
											if (!$matrixExceedPage) $pdfLastMH = clone $pdf;
											$pdf = PDF::renderMatrixHeaderRow($pdf, $enum);
											// Calculate the total height of this matrix header
											$heightPrevMH = $pdf->GetY() - $mhHeightBefore;
										}
										// Determine if this row's checkbox needs to be checked (i.e. it has data)
										$enumData = array();
										if (isset($dataNormalized[$row['field_name']]))
										{
											// Field DOES have data, so loop through EVERY choice and put in array
											foreach (array_keys($enum) as $this_code) {
												$this_code = $this_code."";
												if (is_array($dataNormalized[$row['field_name']])) {
													// Checkbox fields
													if (isset($dataNormalized[$row['field_name']][$this_code]) && $dataNormalized[$row['field_name']][$this_code] == "1") {
														$enumData[$this_code] = '1';
													}
												} elseif ($dataNormalized[$row['field_name']] == $this_code) {
													// Regular fields
													$enumData[$this_code] = '1';
												}
											}
										}
										$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], $isSurvey, $customQuesNum, $num);
										// Render the matrix row for this field
										$pdf = PDF::renderMatrixRow($pdf, $row['element_label'], $enum, $enumData,$study_id_event,($row['element_type'] == "advcheckbox" || $row['element_type'] == "checkbox"));
									}
									// LV, LH, RH Alignment
									elseif ($row['custom_alignment'] == 'LV' || $row['custom_alignment'] == 'LH' || $row['custom_alignment'] == 'RH')
									{
										// Set begin position of new line
										$xStartPos = 10;
										if ($row['custom_alignment'] == 'RH') {
											$xStartPos = 115;
										}

										// Place enums in array while trying to judge general line count of all choices
										$row['element_enum'] = strip_tags(label_decode($row['element_enum']));
										if ($project_encoding == 'japanese_sjis') $row['element_enum'] = mb_convert_encoding($row['element_enum'], "SJIS", "UTF-8");
										$enum = array();
										foreach (parseEnum($row['element_enum']) as $this_code=>$line)
										{
											$this_code = $this_code."";
											if ($compactDisplay && $record != '' && $row['element_type'] != 'descriptive') {
												if (is_array($dataNormalized[$row['field_name']])) {
													// Checkbox with no choices checked
													if ($dataNormalized[$row['field_name']][$this_code] == 0) {
														continue;
													}
												} else {
													// Normal field type with no value
													if ($dataNormalized[$row['field_name']] != $this_code) {
														continue;
													}
												}
											}
											// Add to array
											$enum[$this_code] = strip_tags(label_decode($line));
										}

										// Field label text
										if ($row['custom_alignment'] == 'RH') {
											// Right-horizontal aligned
											$q_lines = PDF::qtext_vertical($row);
											//print_array($q_lines);
											$counter = (count($q_lines) >= count($enum)) ? count($q_lines) : count($enum);
											$pdf = PDF::new_page_check($counter, $pdf, $study_id_event);
											$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
											$yStartPos = $pdf->GetY();
											// If a survey and using custom question numbering, then render question number
											$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], $isSurvey, $customQuesNum, $num);
											for ($i = 0; $i < count($q_lines); $i++) {
												$pdf->Cell(self::col_width_a,self::row_height,$q_lines[$i],0,1);
											}
											$yPosAfterLabel = $pdf->GetY();
											$pdf->SetY($yStartPos);
										} else {
											// Left aligned
											$counter = ceil($pdf->GetStringWidth($row['element_label']."\n".$row['element_note'])/self::max_line_width)+2+count($enum);
											$pdf = PDF::new_page_check($counter, $pdf, $study_id_event);
											$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
											// If a survey and using custom question numbering, then render question number
											$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], $isSurvey, $customQuesNum, $num);
											$pdf->MultiCell(0,self::row_height,$row['element_label']."\n".$row['element_note']);
											$pdf->Ln();
										}

										// Set initial x-position on line
										$pdf->SetX($xStartPos);

										// Render choices
										foreach ($enum as $this_code=>$line)
										{
											$this_code = $this_code."";
											if ($compactDisplay && $record != '' && $row['element_type'] != 'descriptive') {
												if (is_array($dataNormalized[$row['field_name']])) {
													// Checkbox with no choices checked
													if ((string)$dataNormalized[$row['field_name']][$this_code] === '0') {
														continue;
													}
												} else {
													// Normal field type with no value
													if ((string)$dataNormalized[$row['field_name']] !== $this_code) {
														continue;
													}
												}
											}
											// Check if we need to start new line to prevent text run-off
											if ($pdf->GetX() > (self::max_line_width - self::sigil_width - 30)) {
												$pdf->Ln();
												$pdf->SetX($xStartPos);
											}
											// Draw checkboxes
											$pdf->Cell(1,self::row_height,'');
											$pdf->Cell(self::sigil_width,self::row_height,'',0,0,'L',false);
											$x = array($pdf->GetX()-self::sigil_width+.5,0);
											$x[1] = $x[0] + self::row_height-1;
											$y = array($pdf->GetY()+.5,0);
											$y[1] = $y[0] + self::row_height-1;
											if ($row['element_type'] == "advcheckbox" || $row['element_type'] == "checkbox") {
												$pdf->Rect($x[0],$y[0],self::row_height-1,self::row_height-1);
												$crosslineoffset = 0;
											} else {
												$pdf = PDF::Circle($pdf, $x[0]+1.5, $y[0]+1.5, 1.6);
												$crosslineoffset = 0.5;
											}
											// Determine if checkbox needs to be checked (if has data)
											$hasData = false; // Default
											// Determine if this row's checkbox needs to be checked (i.e. it has data)
											if (isset($dataNormalized[$row['field_name']])) {
												if (is_array($dataNormalized[$row['field_name']])) {
													// Checkbox fields
													if (isset($dataNormalized[$row['field_name']][$this_code]) && (string)$dataNormalized[$row['field_name']][$this_code] === "1") {
														$hasData = true;
													}
												} elseif ((string)$dataNormalized[$row['field_name']] === $this_code) {
													// Regular fields
													$hasData = true;
												}
											}
											if ($hasData) {
												// X marks the spot
												$pdf->Line($x[0]+$crosslineoffset,$y[0]+$crosslineoffset,$x[1]-$crosslineoffset,$y[1]-$crosslineoffset);
												$pdf->Line($x[0]+$crosslineoffset,$y[1]-$crosslineoffset,$x[1]-$crosslineoffset,$y[0]+$crosslineoffset);
											}
											// Before printing label, first check if we need to start new line to prevent text run-off
											while (strlen($line) > 0)
											{
												//print "<br>Xpos: ".$pdf->GetX().", Line: $line";
												if (($pdf->GetX() + $pdf->GetStringWidth($line)) >= self::max_line_width)
												{
													// If text will produce run-off, cut off and repeat in next loop to split up onto multiple lines
													$cutoff = self::max_line_width - $pdf->GetX();
													// Since cutoff is in FPDF width, we need to find it's length in characters by going one character at a time
													$last_space_pos = 0; // Note the position of last space (for cutting off purposes)
													for ($i = 1; $i <= strlen($line); $i++) {
														// Check length of string segment
														$segment_width = $pdf->GetStringWidth(substr($line, 0, $i));
														// Check if current character is a space
														if (substr($line, $i, 1) == " ") $last_space_pos = $i;
														// If we found the cutoff, get the character count
														if ($segment_width >= $cutoff) {
															// Obtain length of segment and set segment value
															$segment_char_length = ($last_space_pos != 0) ? $last_space_pos : $i;
															$thisline = substr($line, 0, $segment_char_length);
															break;
														} else {
															$segment_char_length = strlen($line);
															$thisline = $line;
														}
													}
													// Print this segment of the line
													$thisline = trim($thisline);
													$pdf->Cell($pdf->GetStringWidth($thisline)+2,self::row_height,$thisline);
													// Set text for next loop on next line
													$line = substr($line, $segment_char_length);
													// Now set new line with slight indentation (if another line is needed)
													if (strlen($line) > 0) {
														$pdf->Ln();
														$pdf->SetX($xStartPos+(($row['custom_alignment'] == 'LV') ? self::sigil_width : 0));
														$pdf->Cell(1,self::row_height,'');
													}
												} else {
													// Text fits easily on one line
													$line = trim($line);
													$pdf->Cell($pdf->GetStringWidth($line)+4,self::row_height,$line);
													// Reset to prevent further looping
													$line = "";
												}
											}
											// Insert line break if left-vertical alignment
											if ($row['custom_alignment'] == 'LV') {
												$pdf->Ln();
											}
										}
										// For RH aligned with element note...
										if ($row['custom_alignment'] == 'RH' && $row['element_note']) {
											$a_lines_note = PDF::text_vertical($row['element_note']);
											foreach ($a_lines_note as $row2) {
												$pdf->Ln();
												$pdf->SetX($xStartPos);
												$pdf->Cell(self::col_width_a,self::row_height,$row2);
											}
										}
										// For RH aligned, reset y-position if field label has more lines than choices
										if ($row['custom_alignment'] == 'RH' && $yPosAfterLabel > $pdf->GetY()) {
											$pdf->SetY($yPosAfterLabel);
										}
										// Insert line break if NOT left-vertical alignment (because was just added on last loop)
										else if ($row['custom_alignment'] != 'LV') {
											$pdf->Ln();
										}
									}
									// RV Alignment
									else
									{
										$q_lines = PDF::qtext_vertical($row);
										$a_lines = PDF::atext_vertical_mc($row, $dataNormalized, $project_language, $compactDisplay);
										if ($row['element_note'] != "") {
											$a_lines_note = PDF::text_vertical($row['element_note']);
											foreach ($a_lines_note as $row2) {
												$a_lines[] = $row2;
											}
										}
										$counter = (count($q_lines) >= count($a_lines)) ? count($q_lines) : count($a_lines);
										$pdf = PDF::new_page_check($counter, $pdf, $study_id_event);
										$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
										// If a survey and using custom question numbering, then render question number
										$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, $num);
										for ($i = 0; $i < $counter; $i++) {
											$pdf->Cell(self::col_width_a,self::row_height,(isset($q_lines[$i]) ? $q_lines[$i] : ""),0,0,'L',false);
											// Advances X without drawing anything
											if (isset($a_lines[$i]['sigil']) && is_array($a_lines[$i]) && $a_lines[$i]['sigil'] == "1") {
												$pdf->Cell(self::sigil_width,self::row_height,'',0,0,'L',false);
												$x = array($pdf->GetX()-self::sigil_width+.5,0); $x[1] = $x[0] + self::row_height-1;
												$y = array($pdf->GetY()+.5,0); $y[1] = $y[0] + self::row_height-1;
												if ($row['element_type'] == "advcheckbox" || $row['element_type'] == "checkbox") {
													$pdf->Rect($x[0],$y[0],self::row_height-1,self::row_height-1);
													$crosslineoffset = 0;
												} else {
													$pdf = PDF::Circle($pdf, $x[0]+1.5, $y[0]+1.5, 1.6);
													$crosslineoffset = 0.5;
												}
												if ($a_lines[$i]['chosen']){
													// X marks the spot
													$pdf->Line($x[0]+$crosslineoffset,$y[0]+$crosslineoffset,$x[1]-$crosslineoffset,$y[1]-$crosslineoffset);
													$pdf->Line($x[0]+$crosslineoffset,$y[1]-$crosslineoffset,$x[1]-$crosslineoffset,$y[0]+$crosslineoffset);
												}
												$pdf->Cell(self::atext_width,self::row_height,$a_lines[$i]['line'],0,0,'L',false);
												$pdf->Ln();
											} else {
												if (isset($a_lines[$i]) && is_array($a_lines[$i])) {
													// If a choice (and not an element note), then indent for checkbox/radio box
													$pdf->Cell(self::sigil_width,self::row_height,'',0,0,'C',false);
												}
												$pdf->Cell(self::atext_width,self::row_height,((isset($a_lines[$i]) && is_array($a_lines[$i])) ? $a_lines[$i]['line'] : (isset($a_lines[$i]) ? $a_lines[$i] : "")),0,0,'L',false);
												$pdf->Ln();
											}
										}
									}
									//print "<br>{$row['field_name']} \$pdf->GetY() = {$pdf->GetY()}";
									$num++;

									// Descriptive
								} elseif ($row['element_type'] == "descriptive") {
                                    $this_string = "";
                                    // INLINE CONSENT FORM: If field used for Participant e-Consent, display consent form as inline PDF or rich text below the label
									if (isset($econsentEnabledDescriptiveField) && $econsentEnabledDescriptiveField == $row['field_name'] && $econsentEnabledDescriptiveFieldDocId == null) {
										$this_string .= "\n\n".self::strip_html_preserving_angles(PDF::formatText($econsentEnabledDescriptiveFieldLabel));
									}
									//Show notice of image/attachment
									$pdf_to_image = false;
                                    if (is_numeric($row['edoc_id']) || !defined("PROJECT_ID"))
									{
										$display_inline_image = false;
										if (!defined("PROJECT_ID")) {
											// Shared Library
											if ($row['edoc_display_img'] == '1') {
												$this_string .= "\n\n[".RCView::tt('econsent_179','')." {$row['edoc_id']}]";
											} elseif ($row['edoc_display_img'] == '0') {
												$this_string .= "\n\n[".RCView::tt('design_205','')." {$row['edoc_id']}]";
											}
										} else {
											// REDCap project
											$sql = "select doc_name from redcap_edocs_metadata where project_id = " . PROJECT_ID . "
													and delete_date is null and doc_id = ".$row['edoc_id']." limit 1";
											$q = db_query($sql);
											$fname = (db_num_rows($q) < 1) ? "" : label_decode(db_result($q, 0));
											$fnameDisplay = (db_num_rows($q) < 1) ? "Not found" : "\"$fname\"";
											if ($row['edoc_display_img']) {
												$display_inline_image = true;
                                                if (getFileExt(strtolower($fname)) == 'pdf') {
                                                    if (self::canConvertPdfToImages()) {
                                                        $pdf_to_image = true;
                                                    } else {
                                                        $display_inline_image = false;
                                                        $this_string .= "\n\n[".RCView::tt('design_205','')." $fnameDisplay]";
                                                    }
                                                }
                                            } else {
                                                $this_string .= "\n\n[".RCView::tt('design_205','')." $fnameDisplay]";
                                            }
										}
									}
									## DISPLAY INLINE IMAGE/PDF ATTACHMENT
									if (isset($display_inline_image) && $display_inline_image) {
                                        // IF we have a PDF - try to display it as included images
                                        if ($pdf_to_image)
                                        {
                                            $pdf_file_paths = PDF::convertPdfToImages($row['edoc_id'], $Proj->project_id);
                                            $pdf_to_image = !empty($pdf_file_paths);
                                            if ($pdf_to_image) {
                                                $firstPagePdf = true;
                                                $totalPdfPages = count($pdf_file_paths);
                                                $currentPdfPageNum = 0;
                                                foreach ($pdf_file_paths as $img_file_with_path)
                                                {
                                                    $currentPdfPageNum++;
                                                    $imgfile_size = getimagesize($img_file_with_path);
                                                    $img_width = ceil($imgfile_size[0] / 4);
                                                    $img_height = $img_height_orig = ceil($imgfile_size[1] / 4);
                                                    // If image is too big, then scale it down
                                                    $this_page_width = self::page_width - 4;
                                                    $resized_width = false;
                                                    if ($img_width > $this_page_width) {
                                                        $scale_ratio = ($img_width / $this_page_width);
                                                        $img_width = $this_page_width;
                                                        $img_height = ceil($img_height / $scale_ratio);
                                                        $resized_width = true;
                                                    }
                                                    // New page check (due to label length + image)
                                                    $counter = ceil($pdf->GetStringWidth($row['element_label']) / self::max_line_width);
                                                    $label_img_at_top = false;
                                                    // $label_img_at_top = (($img_height + (self::y_units_per_line * $counter) + $pdf->GetY()) > self::bottom_of_page);
                                                    if ($firstPagePdf) {
                                                        $pdf = PDF::new_page_check(1 + $counter, $pdf, $study_id_event);
                                                        $pdf->MultiCell(0, 1, '', 'T');
                                                        $pdf->Ln();
                                                        if (trim($row['element_label'] . $this_string) != '') {
                                                            // If a survey and using custom question numbering, then render question number
                                                            $pdf = PDF::addQuestionNumber($pdf, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, $num);
                                                            // Label
                                                            $pdf->MultiCell(0, self::row_height, $row['element_label'] . $this_string, 0);
                                                        }
                                                        // Start first inline PDF page on a new page with field label on previous page
                                                        $pdf = PDF::new_page_check(1, $pdf, $study_id_event, true);
                                                        // Set flag for next loop
                                                        $firstPagePdf = false;
                                                    }
                                                    // Check if we need to start a new page with this image (but not if we just started a new page due to long label length)
                                                    $resized_height = false;
                                                    if ($label_img_at_top) {
                                                        // Label and image are at top of page
                                                        // Make sure image+label height is not taller than entire page. If so, make its height the height of the page minus the label height
                                                        if (($img_height + $pdf->GetY()) > self::bottom_of_page) {
                                                            $scale_ratio = $img_height / (self::bottom_of_page - 20 - $pdf->GetY());
                                                            $img_height = ceil($img_height / $scale_ratio);
                                                            $img_width = ceil($img_width / $scale_ratio);
                                                            $resized_height = true;
                                                        }
                                                    }
                                                    // Get current position, so we can reset it back later
                                                    $y = $pdf->GetY();
                                                    $x = $pdf->GetX();
                                                    // Set the image
                                                    $pdf->Image($img_file_with_path, $x + 1, $y + 2, $img_width, $img_height);
                                                    // Now we can delete the image from temp
                                                    unlink($img_file_with_path);
                                                    // The PDF should fill to the end of the page, so start a new page (but not if this is the last page of the PDF *and* the last field being displayed)
                                                    $isLastField = ($next_row === null);
                                                    $isLastImgInLoop = ($currentPdfPageNum == $totalPdfPages);
                                                    if (!($isLastField && $isLastImgInLoop)) {
                                                        $pdf = PDF::new_page_check(1, $pdf, $study_id_event, true);
                                                    }
                                                }
                                            }
                                        }
                                        // Copy file to temp directory
										if (!$pdf_to_image) $pdf_file_path = Files::copyEdocToTemp($row['edoc_id'], true);
										if (!$pdf_to_image && $pdf_file_path !== false) {
                                            // Double-check the image's file extension to make sure it matches the mime type (if not, could crash or time out)
                                            if (Files::mime_content_type($pdf_file_path) != mime_content_type($pdf_file_path)) {
                                                // Rename the file with appropriate file extension
                                                $pdf_file_path2 = $pdf_file_path.".".Files::get_file_extension_by_mime_type(mime_content_type($pdf_file_path));
                                                if (@rename($pdf_file_path, $pdf_file_path2)) {
                                                    $pdf_file_path = $pdf_file_path2;
                                                } elseif (file_put_contents($pdf_file_path2, file_get_contents($pdf_file_path))) {
                                                    unlink($pdf_file_path);
                                                    $pdf_file_path = $pdf_file_path2;
                                                }
                                            }
											// Get image size
											$imgfile_size = getimagesize($pdf_file_path);
											$img_width = ceil($imgfile_size[0]/4);
											$img_height = $img_height_orig = ceil($imgfile_size[1]/4);
											// If image is too big, then scale it down
											$this_page_width = self::page_width - 4;
											$resized_width = false;
											if ($img_width > $this_page_width) {
												$scale_ratio = ($img_width / $this_page_width);
												$img_width = $this_page_width;
												$img_height = ceil($img_height / $scale_ratio);
												$resized_width = true;
											}
											// New page check (due to label length + image)
											$counter = ceil($pdf->GetStringWidth($row['element_label'])/self::max_line_width);
											$label_img_at_top = (($img_height + (self::y_units_per_line * $counter) + $pdf->GetY()) > self::bottom_of_page);
											$pdf = PDF::new_page_check(($img_height/self::y_units_per_line)+$counter, $pdf, $study_id_event);
											$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
											// If a survey and using custom question numbering, then render question number
											$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, "");
											// Label
											$pdf->MultiCell(0,self::row_height,$row['element_label'].$this_string,0);
											// Check if we need to start a new page with this image (but not if we just started a new page due to long label length)
											$resized_height = false;
											if ($label_img_at_top) {
												// Label and image are at top of page
												// Make sure image+label height is not taller than entire page. If so, make its height the height of the page minus the label height
												if (($img_height + $pdf->GetY()) > self::bottom_of_page) {
													$scale_ratio = $img_height / (self::bottom_of_page - 20 - $pdf->GetY());
													$img_height = ceil($img_height / $scale_ratio);
													$img_width = ceil($img_width / $scale_ratio);
													$resized_height = true;
												}
											}
											//print "<hr>\self::page_width: self::page_width<br>\self::bottom_of_page: self::bottom_of_page<br>w: $img_width, h: $img_height";
											// Get current position, so we can reset it back later
											$y = $pdf->GetY();
											$x = $pdf->GetX();
											// Set the image
											$pdf->Image($pdf_file_path, $x+1, $y+2, $img_width);
											// Now we can delete the image from temp
											unlink($pdf_file_path);
											// Reset Y position to right below the image
											$pdf->SetY($y+3+$img_height);
										} elseif (!$pdf_to_image) {
											$display_inline_image = false;
										}
									}
									if (!isset($display_inline_image) || !$display_inline_image) {
										## No inline image
										// New page check
										$counter = ceil($pdf->GetStringWidth($row['element_label'])/self::max_line_width);
										$pdf = PDF::new_page_check($counter, $pdf, $study_id_event);
										$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
										// If a survey and using custom question numbering, then render question number
										$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, "");
										// Label
										$pdf->MultiCell(0,self::row_height,$row['element_label'].$this_string,0);
                                    }
                                    // INLINE CONSENT FORM: If field used for Participant e-Consent, display consent form as inline PDF or rich text below the label
                                    if (isset($econsentEnabledDescriptiveField) && $econsentEnabledDescriptiveField == $row['field_name'] && $econsentEnabledDescriptiveFieldDocId != null)
                                    {
                                        $pdf->Ln();
                                        // Inline PDF via iMagick
                                        $pdf_file_paths = PDF::convertPdfToImages($econsentEnabledDescriptiveFieldDocId, $Proj->project_id);
                                        if (!empty($pdf_file_paths)) {
                                            $firstPagePdf = true;
                                            $totalPdfPages = count($pdf_file_paths);
                                            $currentPdfPageNum = 0;
                                            foreach ($pdf_file_paths as $img_file_with_path)
                                            {
                                                $currentPdfPageNum++;
                                                $imgfile_size = getimagesize($img_file_with_path);
                                                $img_width = ceil($imgfile_size[0] / 4);
                                                $img_height = $img_height_orig = ceil($imgfile_size[1] / 4);
                                                // If image is too big, then scale it down
                                                $this_page_width = self::page_width - 4;
                                                $resized_width = false;
                                                if ($img_width > $this_page_width) {
                                                    $scale_ratio = ($img_width / $this_page_width);
                                                    $img_width = $this_page_width;
                                                    $img_height = ceil($img_height / $scale_ratio);
                                                    $resized_width = true;
                                                }
                                                // New page check (due to label length + image)
                                                $counter = 0;
                                                $label_img_at_top = (($img_height + (self::y_units_per_line * $counter) + $pdf->GetY()) > self::bottom_of_page);
                                                if ($firstPagePdf) {
                                                    $pdf = PDF::new_page_check(1, $pdf, $study_id_event, true);
                                                    $pdf->MultiCell(0, 1, '', 'T');
                                                    $pdf->Ln();
                                                    // Set flag for next loop
                                                    $firstPagePdf = false;
                                                }
                                                // Check if we need to start a new page with this image (but not if we just started a new page due to long label length)
                                                $resized_height = false;
                                                if ($label_img_at_top) {
                                                    // Label and image are at top of page
                                                    // Make sure image+label height is not taller than entire page. If so, make its height the height of the page minus the label height
                                                    if (($img_height + $pdf->GetY()) > self::bottom_of_page) {
                                                        $scale_ratio = $img_height / (self::bottom_of_page - 20 - $pdf->GetY());
                                                        $img_height = ceil($img_height / $scale_ratio);
                                                        //REMOVED - BUG $img_width = ceil($img_width / $scale_ratio);
                                                        $resized_height = true;
                                                    }
                                                }
                                                // Get current position, so we can reset it back later
                                                $y = $pdf->GetY();
                                                $x = $pdf->GetX();
                                                // Set the image
                                                $pdf->Image($img_file_with_path, $x + 1, $y + 2, $img_width);
                                                // Now we can delete the image from temp
                                                unlink($img_file_with_path);
                                                // The PDF should fill to the end of the page, so start a new page (but not if this is the last page of the PDF *and* the last field being displayed)
                                                $isLastField = ($next_row === null);
                                                $isLastImgInLoop = ($currentPdfPageNum == $totalPdfPages);
                                                if (!($isLastField && $isLastImgInLoop)) {
                                                    $pdf = PDF::new_page_check(1, $pdf, $study_id_event, true);
                                                }
                                            }
                                        }
                                    }
                                // Slider
								} elseif ($row['element_type'] == "slider") {

									// Parse the slider labels
									$slider_labels = Form::parseSliderLabels($row['element_enum']);
									$slider_min = PDF::slider_label($slider_labels['left']);
									$slider_mid = PDF::slider_label($slider_labels['middle']);
									$slider_max = PDF::slider_label($slider_labels['right']);
                                    // Get numerical value to display in box, if have "number" validation
                                    $sliderValOriginal = $dataNormalized[$row['field_name']] ?? "";
									// Normalize value to 0-100 scale for display
									if (isset($dataNormalized[$row['field_name']]) && ($row['element_validation_min'].$row['element_validation_max'] != "")
										&& ($row['element_validation_min']."" !== '0' || $row['element_validation_max']."" !== '100'))
									{
										if (!isinteger($row['element_validation_min'])) $row['element_validation_min'] = 0;
										if (!isinteger($row['element_validation_max'])) $row['element_validation_max'] = 100;
										if (($row['element_validation_max'] - $row['element_validation_min']) == 0) {
											// Prevent DivisionByZeroError
											$dataNormalized[$row['field_name']] = 0;
										} else {
											$dataNormalized[$row['field_name']] = round(($dataNormalized[$row['field_name']]-$row['element_validation_min']) * 100 / ($row['element_validation_max'] - $row['element_validation_min']));
										}
									}

									if ($row['custom_alignment'] == 'LV' || $row['custom_alignment'] == 'LH') {
										//Display left-aligned
										$this_string = $row['element_label'] . "\n\n";
										$slider_rows = array(count($slider_min), count($slider_mid), count($slider_max));
										$counter = max($slider_rows);
										$pdf = PDF::new_page_check($counter, $pdf, $study_id_event);
										$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
										// If a survey and using custom question numbering, then render question number
										$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], $isSurvey, $customQuesNum, $num);
										while (count($slider_min) < $counter) array_unshift($slider_min,"");
										while (count($slider_mid) < $counter) array_unshift($slider_mid,"");
										while (count($slider_max) < $counter) array_unshift($slider_max,"");
										$pdf->MultiCell(0,self::row_height,$this_string);
										$pdf->SetFont(FONT,'',8);
										for ($i = 0; $i < $counter; $i++) {
											$pdf->Cell(6,self::row_height,"",0,0);
											$pdf->Cell(((self::num_rect*self::rect_width)/3)+2,self::row_height,$slider_min[$i],0,0,'L');
											$pdf->Cell(((self::num_rect*self::rect_width)/3)+2,self::row_height,$slider_mid[$i],0,0,'C');
											$pdf->Cell(((self::num_rect*self::rect_width)/3)+2,self::row_height,$slider_max[$i],0,1,'R');
										}
										$x_pos = 20;
										$pdf->MultiCell(0,2,"",0);
										for ($i = 1; $i <= self::num_rect; $i++) {
											$emptyRect = true;
											if (isset($dataNormalized[$row['field_name']]))
											{
												// If slider has value 0, fudge it to 1 so that it appears (otherwise looks empty)
												$sliderDisplayVal = ($dataNormalized[$row['field_name']] < 1) ? 1 : $dataNormalized[$row['field_name']];
												// Set empty rectangle
												if (is_numeric($sliderDisplayVal) && round($sliderDisplayVal*self::num_rect/100) == $i) {
													$emptyRect = false;
												}
											}
											if ($emptyRect) {
												$pdf->Rect($x_pos,$pdf->GetY(),self::rect_width,1);
											} else {
												$pdf->Rect($x_pos,$pdf->GetY(),self::rect_width,3,'F');
											}
											$x_pos = $x_pos + self::rect_width;
										}
										if ($row['element_validation_type'] == "number" && isset($dataNormalized[$row['field_name']])) {
											$pdf->SetX($x_pos+2);
											$pdf->Cell(6,self::row_height,$sliderValOriginal,1,0);
										}
										$pdf->MultiCell(0,self::row_height,"",0);
										$pdf->SetFont(FONT,'I',7);
										if (!isset($dataNormalized[$row['field_name']])) {
											$slider_prompt = MultiLanguage::getUITranslation($context, "data_entry_609");
											$space_len = $pdf->GetStringWidth(" ");
											$slider_prompt_len = $pdf->GetStringWidth($slider_prompt);
											$slider_prompt_spaces = $slider_prompt_len / $space_len;
											// Magic-numbered: slider left = 13, right = 120, width = 107 spaces
											if ($slider_prompt_spaces < 107) {
												// Move to the right
												$spaces = intval(13 + (107 - $slider_prompt_spaces) / 2);
											}
											else {
												// Move to the left
												$spaces = max(0, intval(13 - ($slider_prompt_spaces - 107) / 2));
											}
											$pdf->MultiCell(0, 3, str_repeat(" ", $spaces) . $slider_prompt, 0);
										}
									} else {
										//Display right-aligned
										$q_lines = PDF::qtext_vertical($row);
										$slider_rows = array(count($q_lines), count($slider_min), count($slider_mid), count($slider_max));
										$counter = max($slider_rows);
										$pdf = PDF::new_page_check($counter, $pdf, $study_id_event);
										$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
										// If a survey and using custom question numbering, then render question number
										$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], $isSurvey, $customQuesNum, $num);
										while (count($slider_min) < $counter) array_unshift($slider_min,"");
										while (count($slider_mid) < $counter) array_unshift($slider_mid,"");
										while (count($slider_max) < $counter) array_unshift($slider_max,"");
										$x_pos = 120;
										for ($i = 0; $i < $counter; $i++) {
											$pdf->SetFont(FONT,'',10);
											$pdf->Cell(self::col_width_a,self::row_height,$q_lines[$i],0,0);
											$pdf->SetFont(FONT,'',8);
											$pdf->Cell(1,self::row_height,"",0,0);
											$pdf->Cell(((self::num_rect*self::rect_width)/3)+2,self::row_height,$slider_min[$i],0,0,'L');
											$pdf->Cell(((self::num_rect*self::rect_width)/3)+2,self::row_height,$slider_mid[$i],0,0,'C');
											$pdf->Cell(((self::num_rect*self::rect_width)/3)+2,self::row_height,$slider_max[$i],0,1,'R');
										}
										$pdf->MultiCell(0,2,"",0);
										for ($i = 1; $i <= self::num_rect; $i++) {
											$emptyRect = true;
											if (isset($dataNormalized[$row['field_name']])) {
												// If slider has value 0, fudge it to 1 so that it appears (otherwise looks empty)
												$sliderDisplayVal = ($dataNormalized[$row['field_name']] < 1) ? 1 : $dataNormalized[$row['field_name']];
												// Set empty rectangle
												if (is_numeric($sliderDisplayVal) && round($sliderDisplayVal*self::num_rect/100) == $i) {
													$emptyRect = false;
												}
											}
											if ($emptyRect) {
												$pdf->Rect($x_pos,$pdf->GetY(),self::rect_width,1);
											} else {
												$pdf->Rect($x_pos,$pdf->GetY(),self::rect_width,3,'F');
											}
											$x_pos = $x_pos + self::rect_width;
										}
										if ($row['element_validation_type'] == "number" && isset($dataNormalized[$row['field_name']])) {
											$pdf->SetX($x_pos+2);
											$pdf->Cell(6,self::row_height,$sliderValOriginal,1,0);
										}
										$pdf->MultiCell(0,self::row_height,"",0);
										$pdf->SetFont(FONT,'I',7);
										if (!isset($dataNormalized[$row['field_name']])) {
											$slider_prompt = MultiLanguage::getUITranslation($context, "data_entry_609");
											$space_len = $pdf->GetStringWidth(" ");
											$slider_prompt_len = $pdf->GetStringWidth($slider_prompt);
											$slider_prompt_spaces = $slider_prompt_len / $space_len;
											// Magic-numbered: slider left = 112, right = 5, width = 107 spaces
											if ($slider_prompt_spaces < 107) {
												// Move to the left
												$spaces = intval(5 + (107 - $slider_prompt_spaces) / 2);
											}
											else {
												// Move to the right
												$spaces = max(0, intval(5 - ($slider_prompt_spaces - 107) / 2));
											}
											$pdf->MultiCell(0, 3, $slider_prompt . str_repeat(" ", $spaces), 0, "R");
										}
									}
									$num++;

									// Text, Notes, Calcs, and File Upload fields
								} elseif ($row['element_type'] == "textarea" || $row['element_type'] == "text"
									|| $row['element_type'] == "calc" || $row['element_type'] == "file") {

									// If field note exists, format it first
									if ($row['element_note'] != "") {
										$row['element_note'] = "\n(".$row['element_note'].")";
									}

                                    if ($row['element_type'] == "textarea" && isset($dataNormalized[$row['field_name']]) && strpos($Proj->metadata[$row['field_name']]['misc'] ?? "", "@RICHTEXT") !== false) {
                                        $dataNormalized[$row['field_name']] = strip_tags(PDF::formatText($dataNormalized[$row['field_name']]));
                                    }

									// If a File Upload field *with* data, just display [document]. If no data, display nothing.
									if ($row['element_type'] == "file" && $row['element_validation_type'] != "signature") {
                                        $dataNormalized[$row['field_name']] = (isset($dataNormalized[$row['field_name']]))
											? "[".RCView::getLangStringByKey("data_export_tool_248")." ".truncateTextMiddle(Files::getEdocName($dataNormalized[$row['field_name']]), 40, 10)."]" : ''; //= FILE:
                                    }

									if ($row['custom_alignment'] == 'LV' || $row['custom_alignment'] == 'LH')
									{
										if ($row['element_type'] == "textarea") {
											$row['element_label'] .= $row['element_note'] . "\n \n";
										} else {
											$row['element_label'] .= "\n \n";
										}

										// Left-aligned
										if (isset($dataNormalized[$row['field_name']])) {
											// Unescape text for Text and Notes fields (somehow not getting unescaped for left alignment)
											$dataNormalized[$row['field_name']] = label_decode($dataNormalized[$row['field_name']]);
											//Has data
											if ($project_encoding == 'japanese_sjis') {
												$row['element_label'] .= mb_convert_encoding($dataNormalized[$row['field_name']], "SJIS", "UTF-8"); // Japanese
											} elseif (!($row['element_type'] == "file" && $row['element_validation_type'] == "signature")) {
												$row['element_label'] .= $dataNormalized[$row['field_name']];
											}
											if ($row['element_type'] != "textarea" && !($row['element_type'] == "file" && $row['element_validation_type'] == "signature")) {
												$row['element_label'] .= $row['element_note'];
											}
										} else {
											if ($row['element_type'] == "textarea") {
												$row['element_label'] .= "\n \n \n";
											} else {
												if ($row['element_type'] == "file" && $row['element_validation_type'] == "signature") {
													$row['element_label'] .= "\n\n__________________________________________" . $row['element_note'];
												} else {
													$row['element_label'] .= "\n__________________________________" . $row['element_note'];
												}
											}
										}
										// New page check
										$counter = ceil($pdf->GetStringWidth($row['element_label'])/self::max_line_width);
										$pdf = PDF::new_page_check($counter, $pdf, $study_id_event);
										$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
										// If a survey and using custom question numbering, then render question number
										$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], $isSurvey, $customQuesNum, $num);
										$pdf->MultiCell(0,self::row_height,$row['element_label'],0);
										## DISPLAY SIGNATURE IMAGE
										if (isset($dataNormalized[$row['field_name']]) && $row['element_type'] == "file" && $row['element_validation_type'] == "signature") {
											// Cannot display PNG signature w/o Zlib extension enabled
											if (!USE_UTF8) {
												// Need about 2 lines worth of height for this label
												$pdf = PDF::new_page_check(2, $pdf, $study_id_event);
												$pdf->Ln();
												$pdf->MultiCell(0,self::row_height,RCView::getLangStringByKey("data_entry_248"),0);
											} else {
												// Need about 6 lines worth of height for this image
												$pdf = PDF::new_page_check(7, $pdf, $study_id_event);
												// Get current position, so we can reset it back later
												$y = $pdf->GetY();
												$x = $pdf->GetX();
												// Copy file to temp directory
												$sigfile_path = Files::copyEdocToTemp($dataNormalized[$row['field_name']], true);
												if ($sigfile_path !== false) {
													// Get image size
													// $sigfile_size = getimagesize($sigfile_path);
													$sigfile_size = [460, 115];
													// Set the image
													$pdf->Image($sigfile_path, $x, $y, round($sigfile_size[0]/6));
													// Now we can delete the image from temp
													unlink($sigfile_path);
													// Set new position
													$pdf->SetY($y+round($sigfile_size[1]/6));
												}
											}
											// Add field note, if applicable
											if ($row['element_note'] != '') {
												if (substr($row['element_note'], 0, 1) == "\n") $row['element_note'] = substr($row['element_note'], 1);
												$pdf->MultiCell(0,self::row_height,$row['element_note'],0);
											}
										}
									}
									else
									{
										// Right-aligned
										if ($row['element_type'] == "textarea") {
											//$row['element_label'] .= $row['element_note'];
											$row['element_label'] = str_replace(array("\r\n","\r"), array("\n","\n"), html_entity_decode($row['element_label'], ENT_QUOTES));
											
											$q_lines = PDF::qtext_vertical($row);
										} else {
											$q_lines = PDF::qtext_vertical($row);
										}
										if (isset($dataNormalized[$row['field_name']])) {
											//Has data
											$this_textv = $dataNormalized[$row['field_name']];
											if ($project_encoding == 'japanese_sjis') {
												$this_textv = mb_convert_encoding($this_textv, "SJIS", "UTF-8") . $row['element_note']; // Japanese - field note is already encoded
											} else {
												$this_textv .= $row['element_note'];
											}
											$a_lines = PDF::text_vertical($this_textv);
										} else {
											if ($row['element_type'] == "textarea") {
												$a_lines = PDF::text_vertical("\n \n__________________________________________" . $row['element_note']);
											} else {
												if ($row['element_type'] == "file" && $row['element_validation_type'] == "signature") {
													$a_lines = PDF::text_vertical("\n\n\n__________________________________________" . $row['element_note']);
												} else {
													$a_lines = PDF::text_vertical("\n__________________________________" . $row['element_note']);
												}
											}
										}

										## DISPLAY SIGNATURE IMAGE
										if (isset($dataNormalized[$row['field_name']]) && $row['element_type'] == "file" && $row['element_validation_type'] == "signature") {
											// Cannot display PNG signature w/o Zlib extension enabled
											$sigMinLines = 9;
											if (count($q_lines) < $sigMinLines) {
												$linesAdd = $sigMinLines - count($q_lines);
												for ($i=0; $i<$linesAdd; $i++) {
													$q_lines[] = "";
												}
											}
											if (!USE_UTF8) {
												$counter = count($q_lines);
												$pdf = PDF::new_page_check($counter, $pdf, $study_id_event);
												// If a survey and using custom question numbering, then render question number
												$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, $num);
												// Display text first
												for ($i = 0; $i < $counter; $i++) {
													$pdf->Cell(self::col_width_a,self::row_height,$q_lines[$i],0,0);
													$pdf->Cell(self::col_width_b,self::row_height,($i == 0 ? RCView::getLangStringByKey("data_entry_248") : ""),0,1);
												}
											} else {
												// Check for new page
												//$counter = max(count($q_lines), 7);
												$counter = count($q_lines);
												$pdf = PDF::new_page_check($counter, $pdf, $study_id_event);
												// If a survey and using custom question numbering, then render question number
												$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, $num);
												// Get current position, so we can reset it back later
												$y = $pdf->GetY();
												$x = $pdf->GetX();
												// Display text first
												for ($i = 0; $i < $counter; $i++) {
													$pdf->Cell(self::col_width_a,self::row_height,$q_lines[$i],0,0);
													$pdf->Cell(self::col_width_b,self::row_height,"",0,1);
												}
												$y2 = $pdf->GetY();
												// Copy file to temp directory
												$sigfile_path = Files::copyEdocToTemp($dataNormalized[$row['field_name']], true);
												if ($sigfile_path !== false) {
													// Get image size
													// $sigfile_size = getimagesize($sigfile_path);
													$sigfile_size = [460, 115];
													// 460 /115
													// Set the image
													$pdf->Image($sigfile_path, self::col_width_a+5, $y, round($sigfile_size[0]/6));
													// Now we can delete the image from temp
													unlink($sigfile_path);
													// Set new position
													if ($y+round($sigfile_size[1]/6) > $y2) $y2 = round($sigfile_size[1]/6);
													$pdf->SetY($y2);
												}
											}
											// Add field note, if applicable
											if ($row['element_note'] != '') {
												if (substr($row['element_note'], 0, 1) == "\n") $row['element_note'] = substr($row['element_note'], 1);
												$a_lines = PDF::text_vertical($row['element_note']);
												for ($i = 0; $i < count($a_lines); $i++) {
													$pdf->Cell(self::col_width_a,self::row_height,'',0,0);
													$pdf->Cell(self::col_width_b,self::row_height,$a_lines[$i],0,1);
												}
											}
										} else {
											$counter = (count($q_lines) >= count($a_lines)) ? count($q_lines) : count($a_lines);
											$pdf = PDF::new_page_check($counter, $pdf, $study_id_event);
											$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
											// If a survey and using custom question numbering, then render question number
											$pdf = PDF::addQuestionNumber($pdf, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, $num);
											for ($i = 0; $i < $counter; $i++) {
												if (!isset($q_lines[$i])) $q_lines[$i] = "";
												if (!isset($a_lines[$i])) $a_lines[$i] = "";
												$pdf->Cell(self::col_width_a,self::row_height,$q_lines[$i],0,0);
												$pdf->Cell(self::col_width_b,self::row_height,$a_lines[$i],0,1);
											}
										}
									}

									// Loop num
									$num++;

								}
								$pdf->Ln();
								// Save this form_name for the next loop
								$last_form = $row['form_name'];
								// Set value for next loop
								$prev_grid_name = $grid_name;
							}

							PDF::displayLockingEsig($pdf, $record, $event_id, $last_form, $repeat_instance, $study_id_event);


							if ($record != '')
							{
								// Check if all questions in previous matrix were hidden. If so, hide matrix header.
								if (isset($prev_grid_name) && $prev_grid_name != "" && $fieldsDisplayedInMatrix === 0)
								{
									$pdf = PDF::removeMatrixHeader($pdf);
								}
								// Check if all questions in previous section were hidden
								if (isset($fieldsDisplayedInSection) && $fieldsDisplayedInSection === 0) {
									$pdf = PDF::removeSectionHeader($pdf);
								}
							}


							// If form has an Acknowledgement, render it here
							if ($acknowledgement != "") {
								// Calculate how many lines will be needed for text to check if new page is needed
								$num_lines = ceil(strlen(strip_tags($acknowledgement))/self::est_char_per_line)+substr_count($acknowledgement, "\n");
								$pdf = PDF::new_page_check($num_lines, $pdf, $study_id_event);
								$pdf->MultiCell(0,20,'');
								$pdf->MultiCell(0,1,'','B');
								$pdf->WriteHTML(nl2br($acknowledgement));
							}
							$last_repeat_instance = $repeat_instance;
						}
						$last_repeat_instrument = $repeat_instrument;
					}
				}
			}
		}

		// Remove special characters from title for using as filename
		$filename = "";
		if (isset($_GET['page']) && isset($form_title)) {
			$filename .= str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9]/", " ", $form_title))) . "_";
		}
		$filename .= str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9]/", " ", $project_name)));
		// Make sure filename is not too long
		if (strlen($filename) > 30) {
			$filename = substr($filename, 0, 30);
		}
		// Add timestamp if data in PDF
		if (isset($_GET['id']) || isset($_GET['allrecords'])) {
			$filename .= date("_Y-m-d_Hi");
		}

        // Remove flag meaning that we're current in the PDF render method
        unset($_GET['__methodRenderPDF']);

		// Don't output PDF if some text has been printed to page already
		if (strlen(ob_get_contents()) > 0) {
			exit("<br><br>ERROR: PDF cannot be output because some content has already been output to the buffer.");
		}

        // Output PDF in various ways (as file or string, depending on context)
        if ($returnString) {
            // Return as string
            return $pdf->Output("$filename.pdf", 'S');
        } elseif (isset($_GET['display']) && $_GET['display'] == 'inline') {
			// Inline display
			print $pdf->Output("$filename.pdf", 'I');
		} elseif (PAGE != "PdfController:index" && !isset($_GET['s'])) {
			// Output as string
			print $pdf->Output("$filename.pdf", 'S');
		} else {
			// If we're downloading ALL records, then also add the file to the File Repository
			if (isset($_GET['allrecords']) && PAGE == "PdfController:index")
			{
				// Get PDF string
				$pdfString = $pdf->Output("$filename.pdf", 'S');
				$docs_size = strlen($pdfString);
				$mime_type = 'application/pdf';
				$docs_comment = "Data export file created by " . USERID . " on " . date("Y-m-d-H-i-s");

				// Temporarily store file in temp
				$pdf_filename = APP_PATH_TEMP . "pdf_allrecords_pid" . $Proj->project_id . "_" . date('Y-m-d_His_') . substr(sha1(random_int(0,(int)999999)), 0, 10) . ".pdf";
				file_put_contents($pdf_filename, $pdfString);
				// Add PDF to edocs_metadata table
				$pdfFile = array('name'=>basename($pdf_filename), 'type'=>'application/pdf', 'size'=>filesize($pdf_filename), 'tmp_name'=>$pdf_filename);
				$edoc_id = Files::uploadFile($pdfFile);
				// Save to File Repository
				$sql = "INSERT INTO redcap_docs (project_id, docs_name, docs_file, docs_date, docs_size, docs_comment, docs_type,
						docs_rights, export_file, temp) VALUES (" . PROJECT_ID . ", '" . db_escape("$filename.pdf") . "', NULL, '" . TODAY . "',
						'$docs_size', '" . db_escape($docs_comment). "', '$mime_type', null, 1, 0)";
				if (db_query($sql)) {
					$docs_id = db_insert_id();
					// Add to redcap_docs_to_edocs also
					$sql = "insert into redcap_docs_to_edocs (docs_id, doc_id) values ($docs_id, $edoc_id)";
					db_query($sql);
				} else {
					// Could not store in table, so remove from edocs_metadata also
					db_query("delete from redcap_edocs_metadata where doc_id = $edoc_id");
					return false;
				}

				// Output as file download
				header('Pragma: anytextexeptno-cache', true);
				if (ob_get_contents()) exit('Some data has already been output, can\'t send PDF file');
				if(isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'],'MSIE'))
					header('Content-Type: application/force-download');
				else
					header('Content-Type: application/octet-stream');
				if (headers_sent()) exit('Some data has already been output to browser, can\'t send PDF file');
				header('Content-Length: '.$docs_size);
				header('Content-disposition: attachment; filename="'.$filename.'.pdf"');
				echo $pdfString;
			}
			// Output as file download
			else
			{
				$pdf->Output("$filename.pdf", 'D');
			}
		}
	}

	//Computes the number of lines a MultiCell of width w will take
	public static function NbLines($pdf, $w, $txt)
	{
		global $project_encoding;
		// Manually break up the lines into fragments of <=$w width to determine the true line count
		$txt = strip_tags(str_replace(["\r\n", "\r", "&nbsp;"], ["\n", "\n", " "], br2nl(label_decode($txt))));
		$this_text = $txt;
		if ($this_text == "") return 1;

		// Deal with 2-byte characters in strings
		if ($project_encoding != '') {
			//if ($project_encoding == 'japanese_sjis') $this_text = mb_convert_encoding($this_text, "SJIS", "UTF-8");
			return ceil($pdf->GetStringWidth($this_text)/$w);
		}

        // Break into separate lines first
        if (strpos($this_text, "\n") === false) {
            $this_text_array = [$this_text];
            $num_lines = 1;
        } else {
            $this_text_array = explode("\n", $this_text);
            foreach ($this_text_array as &$val) $val = trim($val);
            $num_lines = count($this_text_array);
        }

        $num_loop = 1;
		foreach ($this_text_array as $this_text) {
            while ($this_text != "") {
                // Get last space before cutoff
                $this_text_width = $pdf->GetStringWidth($this_text);
                if ($this_text_width <= $w) {
                    // Only one line
                    $this_text = "";
                } else {
                    // Multiple lines: Split it by max length < $w by spaces
                    $this_line_num_chars = floor(($w / $this_text_width) * mb_strlen($this_text));
                    $this_line_last_space_pos = strrpos(mb_substr($this_text, 0, $this_line_num_chars-1), " ");
                    // If $this_line_last_space_pos is FALSE, then just get first space and cut it off there
                    if ($this_line_last_space_pos === false) {
                        if (strpos($this_text, " ") !== false) {
                            list ($nothing, $this_text) = explode(" ", $this_text, 2);
                        }
                    } else {
                        $this_text = substr($this_text, $this_line_last_space_pos);
                    }
                    $this_text = trim($this_text??"");
                    $num_lines++;
                }
                // Increment loop
                $num_loop++;
                // If we're stuck in an infinite loop, then get out using legacy method to determine line count
                if ($num_loop > 100) {
                    return ceil($pdf->GetStringWidth($txt)/$w);
                }
            }
        }
		// Return line count
		return $num_lines;
	}

	// Draw circle
	public static function Circle($pdf, $x, $y, $r, $style='')
	{
		return PDF::Ellipse($pdf, $x, $y, $r, $r, $style);
	}

	public static function Ellipse($pdf, $x, $y, $rx, $ry, $style='D')
	{
		if($style=='F')
			$op='f';
		elseif($style=='FD' or $style=='DF')
			$op='B';
		else
			$op='S';
		$lx=4/3*(M_SQRT2-1)*$rx;
		$ly=4/3*(M_SQRT2-1)*$ry;
		$k=$pdf->k;
		$h=$pdf->h;
		$pdf->_out(sprintf('%.2f %.2f m %.2f %.2f %.2f %.2f %.2f %.2f c',
			($x+$rx)*$k, ($h-$y)*$k,
			($x+$rx)*$k, ($h-($y-$ly))*$k,
			($x+$lx)*$k, ($h-($y-$ry))*$k,
			$x*$k, ($h-($y-$ry))*$k));
		$pdf->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
			($x-$lx)*$k, ($h-($y-$ry))*$k,
			($x-$rx)*$k, ($h-($y-$ly))*$k,
			($x-$rx)*$k, ($h-$y)*$k));
		$pdf->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
			($x-$rx)*$k, ($h-($y+$ly))*$k,
			($x-$lx)*$k, ($h-($y+$ry))*$k,
			$x*$k, ($h-($y+$ry))*$k));
		$pdf->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c %s',
			($x+$lx)*$k, ($h-($y+$ry))*$k,
			($x+$rx)*$k, ($h-($y+$ly))*$k,
			($x+$rx)*$k, ($h-$y)*$k,
			$op));
		return $pdf;
	}

	// Generate a matrix header row of multicells
	public static function renderMatrixHeaderRow($pdf,$hdrs)
	{
		// Construct row-specific parameters
		$mtx_hdr_width = round((self::page_width - self::matrix_label_width)/count($hdrs));
		$widths = array(self::matrix_label_width); // Default for field label
		$data = array("");
		foreach ($hdrs as $hdr) {
			$widths[] = $mtx_hdr_width;
			$data[] = $hdr;
		}
		//Calculate the height of the row
		$nb=0;
		for($i=0;$i<count($data);$i++)
			$nb=max($nb, PDF::NbLines($pdf, $widths[$i], $data[$i]));
		$h=5*$nb;
		//If the height h would cause an overflow, add a new page immediately
		if($pdf->GetY()+$h>$pdf->PageBreakTrigger) {
			$pdf->AddPage($pdf->CurOrientation);
		}
		$pdf->SetFont(FONT,'',9);
		//Draw the cells of the row
		for($i=0;$i<count($data);$i++)
		{
			$w=$widths[$i];
			//$a=isset($pdf->aligns[$i]) ? $pdf->aligns[$i] : 'L';
			$a=$i==0 ? 'L' : 'C';
			//Save the current position
			$x=$pdf->GetX();
			$y=$pdf->GetY();
			//Draw the border
			//$pdf->Rect($x, $y, $w, $h);
			//Print the text
			$pdf->MultiCell($w, 4, strip_tags(label_decode($data[$i])), 'T', $a);
			//Put the position to the right of the cell
			$pdf->SetXY($x+$w, $y);
		}
		// Set Y
		$pdf->SetY($pdf->GetY()+$h);
		//Go to the next line
		//$pdf->Ln();
		// Reset font back to earlier value
		$pdf->SetFont(FONT,'',10);
		return $pdf;
	}

	// Generate a matrix field row of multicells
	public static function renderMatrixRow($pdf,$label,$hdrs,$enumData,$study_id_event,$isCheckbox)
	{
		$chkbx_width = self::row_height-1;
		// Construct row-specific parameters
		$mtx_hdr_width = round((self::page_width - self::matrix_label_width)/count($hdrs));
		$widths = array(self::matrix_label_width); // Default for field label
		$data = array('Label-Key'=>$label);
		foreach ($hdrs as $key=>$hdr) {
			$widths[] = $mtx_hdr_width;
			$data[$key] = (isset($enumData[$key])); // checked value for each checkbox/radio button
		}
		//print_array($data);print "<br>";
		//Calculate the height of the row
		$nb = PDF::NbLines($pdf, self::matrix_label_width, $label);
		//Issue a page break first if needed
		//$pdf->CheckPageBreak($h);
		//If the height h would cause an overflow, add a new page immediately
		// if($pdf->GetY()+$h>$pdf->PageBreakTrigger) {
		// $pdf->AddPage($pdf->CurOrientation);
		// }
		if ($pdf->GetY()+($nb*self::row_height) > (self::bottom_of_page-20)) {
			$pdf->AddPage();
			// Set logo at bottom
			PDF::setFooterImage($pdf);
			// Set "Confidential" text at top
			$pdf = PDF::confidentialText($pdf);
			// If appending text to header
			$pdf = PDF::appendToHeader($pdf);
			// Add page number
			if ($study_id_event != "" && !PDF::$survey_mode) {
				$pdf->SetFont(FONT,'BI',8);
				$pdf->Cell(0,2,$study_id_event,0,1,'R');
				$pdf->Ln();
			}
			$pdf->SetFont(FONT,'I',8);
            // Add instance number for repeating instances
            if (isset($_GET['__pdf_instance'])) {
                $pdf->Cell(0, 2, '#'.$_GET['__pdf_instance'], 0, 1, 'R');
                $pdf->Ln();
            }
			$pdf->Cell(0, 3, RCView::interpolateLanguageString(self::$page_num_template, [$pdf->PageNo()]), 0, 1, 'R');
			// Line break and reset font
			$pdf->Ln();
			$pdf->SetFont(FONT,'',10);
		}
		//Draw the cells of the row
		$i = 0;
		foreach ($data as $key=>$isChecked)
		{
			$w=$widths[$i];
			//Save the current position
			$x=$pdf->GetX();
			$y=$pdf->GetY();
			if($i!=0) {
				// Draw checkbox/radio
				$xboxpos = $x-1+floor($mtx_hdr_width/2);
				if ($isCheckbox) {
					$pdf->Rect($xboxpos, $y, $chkbx_width, $chkbx_width);
					$crosslineoffset = 0;
				} else {
					$pdf = PDF::Circle($pdf, $xboxpos+1.5, $y+1.5, 1.6);
					$crosslineoffset = 0.5;
				}
				// Positions of line 1
				$line1_x0 = $xboxpos;
				$line1_y0 = $y;
				$line1_x1 = $line1_x0+$chkbx_width;
				$line1_y1 = $line1_y0+$chkbx_width;
				// Positions of line 2
				$line2_x0 = $xboxpos;
				$line2_y0 = $y+$chkbx_width;
				$line2_x1 = $line2_x0+$chkbx_width;
				$line2_y1 = $y;
				// If checked, then X marks the spot
				if ($isChecked) {
					$pdf->Line($line1_x0+$crosslineoffset,$line1_y0+$crosslineoffset,$line1_x1-$crosslineoffset,$line1_y1-$crosslineoffset);
					$pdf->Line($line2_x0+$crosslineoffset,$line2_y0-$crosslineoffset,$line2_x1-$crosslineoffset,$line2_y1+$crosslineoffset);
				}
			} else {
				//Print the label
				$pdf->MultiCell($w, self::row_height, strip_tags(label_decode($label)), 0, 'L');
				$yLabel = $y+(($nb-1)*self::row_height*1.3);
			}
			//Put the position to the right of the cell
			$pdf->SetXY($x+$w, $y);
			// Increment counter
			$i++;
		}
		//Go to the next line
		if ($nb > 1) {
			// Set Y
			$pdf->SetY($yLabel);
		}
		$pdf->Ln(2);
		return $pdf;
	}

	// Get total matrix group height, including SH, so check if we need a page break invoked below
	public static function getMatrixHeight($pdf, $field)
	{
		global $Proj, $ProjMetadata, $ProjMatrixGroupNames;
		if (!is_array($ProjMatrixGroupNames)) $ProjMatrixGroupNames = array();
		// Set initial line count
		$lines = 0;
		// Get count of total lines for SH (adding 2 extra lines for spacing and double lines)
		$SH = $ProjMetadata[$field]['element_preceding_header'];
		$lines += ($SH == '' ? 0 : 2) + PDF::NbLines($pdf, self::page_width, $SH);
		// Get max line count over all matrix headers
		$hdrs = parseEnum($ProjMetadata[$field]['element_enum']);
		$mtx_hdr_width = round((self::page_width - self::matrix_label_width)/count($hdrs));
		$widths = array(self::matrix_label_width); // Default for field label
		$data = array("");
		foreach ($hdrs as $hdr) {
			$widths[] = $mtx_hdr_width;
			$data[] = $hdr;
		}
		$nb=0;
		for($i=0;$i<count($data);$i++)
			$nb=max($nb, PDF::NbLines($pdf, $widths[$i], $data[$i]));
		$lines += $nb;
		// Get count of EACH field in the matrix
		$grid_name = $ProjMetadata[$field]['grid_name'];
		if (isset($ProjMatrixGroupNames[$grid_name])) {
			foreach ($ProjMatrixGroupNames[$grid_name] as $thisfield) {
				// Get label for each
				$thislabel = $ProjMetadata[$thisfield]['element_label'];
				// Get line count for this field
				$lines += PDF::NbLines($pdf, self::matrix_label_width, $thislabel);
			}
		}
		// Return height
		return $lines;
	}

	public static function ImageCreateFromBMP($filename)
	{
		//Ouverture du fichier en mode binaire
		if (! $f1 = fopen($filename,"rb")) return FALSE;

		//1 : Chargement des FICHIER
		$FILE = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($f1,14));
		if ($FILE['file_type'] != 19778) return FALSE;

		//2 : Chargement des BMP
		$BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel'.
			'/Vcompression/Vsize_bitmap/Vhoriz_resolution'.
			'/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1,40));
		$BMP['colors'] = pow(2,$BMP['bits_per_pixel']);
		if ($BMP['size_bitmap'] == 0) $BMP['size_bitmap'] = $FILE['file_size'] - $FILE['bitmap_offset'];
		$BMP['bytes_per_pixel'] = $BMP['bits_per_pixel']/8;
		$BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
		$BMP['decal'] = ($BMP['width']*$BMP['bytes_per_pixel']/4);
		$BMP['decal'] -= floor($BMP['width']*$BMP['bytes_per_pixel']/4);
		$BMP['decal'] = 4-(4*$BMP['decal']);
		if ($BMP['decal'] == 4) $BMP['decal'] = 0;

		//3 : Chargement des couleurs de la palette
		$PALETTE = array();
		if ($BMP['colors'] < 16777216)
		{
			$PALETTE = unpack('V'.$BMP['colors'], fread($f1,$BMP['colors']*4));
		}

		//4 : de l'image
		$IMG = fread($f1,$BMP['size_bitmap']);
		$VIDE = chr(0);

		$res = imagecreatetruecolor($BMP['width'],$BMP['height']);
		$P = 0;
		$Y = $BMP['height']-1;
		while ($Y >= 0)
		{
			$X=0;
			while ($X < $BMP['width'])
			{
				if ($BMP['bits_per_pixel'] == 24)
					$COLOR = unpack("V",substr($IMG,$P,3).$VIDE);
				elseif ($BMP['bits_per_pixel'] == 16)
				{
					$COLOR = unpack("n",substr($IMG,$P,2));
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				elseif ($BMP['bits_per_pixel'] == 8)
				{
					$COLOR = unpack("n",$VIDE.substr($IMG,$P,1));
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				elseif ($BMP['bits_per_pixel'] == 4)
				{
					$COLOR = unpack("n",$VIDE.substr($IMG,floor($P),1));
					if (($P*2)%2 == 0) $COLOR[1] = ($COLOR[1] >> 4) ; else $COLOR[1] = ($COLOR[1] & 0x0F);
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				elseif ($BMP['bits_per_pixel'] == 1)
				{
					$COLOR = unpack("n",$VIDE.substr($IMG,floor($P),1));
					if     (($P*8)%8 == 0) $COLOR[1] =  $COLOR[1]        >>7;
					elseif (($P*8)%8 == 1) $COLOR[1] = ($COLOR[1] & 0x40)>>6;
					elseif (($P*8)%8 == 2) $COLOR[1] = ($COLOR[1] & 0x20)>>5;
					elseif (($P*8)%8 == 3) $COLOR[1] = ($COLOR[1] & 0x10)>>4;
					elseif (($P*8)%8 == 4) $COLOR[1] = ($COLOR[1] & 0x8)>>3;
					elseif (($P*8)%8 == 5) $COLOR[1] = ($COLOR[1] & 0x4)>>2;
					elseif (($P*8)%8 == 6) $COLOR[1] = ($COLOR[1] & 0x2)>>1;
					elseif (($P*8)%8 == 7) $COLOR[1] = ($COLOR[1] & 0x1);
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				else
					return FALSE;
				imagesetpixel($res,$X,$Y,$COLOR[1]);
				$X++;
				$P += $BMP['bytes_per_pixel'];
			}
			$Y--;
			$P+=$BMP['decal'];
		}

		//Fermeture du fichier
		fclose($f1);

		return $res;
	}

	// LOCKING & E-SIGNATURE: Check if this form has been locked and/or e-signed, when viewing PDF with data
	public static function displayLockingEsig(&$pdf, $record, $event_id, $form, $instance, $study_id_event)
	{
		if ($record == '') return;
		if (!is_numeric($instance) || $instance < 1) $instance = 1;

		// Check if need to display this info at all
		$sql = "select display, display_esignature, label from redcap_locking_labels where project_id = " . PROJECT_ID . "
				and form_name = '" . db_escape($form) . "' limit 1";
		$q = db_query($sql);
		// If it is NOT in the table OR if it IS in table with display=1, then show locking/e-signature
		$displayLocking		= (!db_num_rows($q) || (db_num_rows($q) && db_result($q, 0, "display") == "1"));
		$displayEsignature  = (db_num_rows($q) && db_result($q, 0, "display_esignature") == "1");

		// LOCKING
		if ($displayLocking)
		{
			// Set customized locking label (i.e affidavit text for e-signatures)
			$custom_lock_label = db_num_rows($q) ? trim(label_decode(db_result($q, 0, "label"))) : "";
			if ($custom_lock_label != '') $custom_lock_label .= "\n\n";
			// Check if locked
			$sql = "select l.username, l.timestamp, u.user_firstname, u.user_lastname from redcap_locking_data l, redcap_user_information u
					where l.project_id = " . PROJECT_ID . " and l.username = u.username
					and l.record = '" . db_escape($record) . "' and l.event_id = '" . db_escape($event_id) . "'
					and l.form_name = '" . db_escape($form) . "' and l.instance = '" . db_escape($instance) . "' limit 1";
			$q = db_query($sql);
			if (db_num_rows($q))
			{
				$form_locked = db_fetch_assoc($q);
				// Set string to capture lock text
				$locking_ts = DateTimeRC::format_ts_from_ymd($form_locked['timestamp']);
				$lock_string = $form_locked['username'] != "" 
					? RCView::tt_i_strip_tags("form_renderer_42", array( //= <b>Locked by {0}</b> ({1}) on {2}
						$form_locked['username'],
						"{$form_locked['user_firstname']} {$form_locked['user_lastname']}",
						$locking_ts
					)) 
					: RCView::tt_i_strip_tags("form_renderer_41", array( //= <b>Locked</b> on {0}
						$locking_ts
					));

				// E-SIGNATURE
				if ($displayEsignature)
				{
					// Check if e-signed
					$sql = "select e.username, e.timestamp, u.user_firstname, u.user_lastname from redcap_esignatures e, redcap_user_information u
							where e.project_id = " . PROJECT_ID . " and e.username = u.username and e.record = '" . db_escape($record) . "'
							and e.event_id = '" . db_escape($event_id) . "' and e.form_name = '" . db_escape($form) . "' 
							and e.instance = '" . db_escape($instance) . "' limit 1";
					$q = db_query($sql);
					if (db_num_rows($q))
					{
						$form_esigned = db_fetch_assoc($q);
						// Set string to capture lock text
						$lock_string = RCView::tt_i_strip_tags("form_renderer_62", array( //= E-signed by {0} on {1}
							"{$form_esigned['username']} ({$form_esigned['user_firstname']} {$form_esigned['user_lastname']})",
							DateTimeRC::format_ts_from_ymd($form_esigned['timestamp'])
						)) . "\n" . $lock_string;
					}
				}

				// Now add custom locking text, if was set (will have blank value if not set)
				$lock_string = $custom_lock_label . $lock_string;

				// Render the lock record and e-signature text
				$num_lines = ceil(strlen(strip_tags($lock_string))/self::est_char_per_line)+substr_count($lock_string, "\n");
				$pdf = PDF::new_page_check($num_lines, $pdf, $study_id_event);
				$pdf->MultiCell(0,5,'');
				$pdf->MultiCell(0,5,$lock_string,1);
			}
		}
	}

    // Return boolean regarding whether the iMagick PHP extension is installed
    public static function iMagickInstalled()
    {
        // return (isDev() || class_exists('Imagick'));
        return class_exists('Imagick');
    }

    // Return boolean if web server has ability to convert PDFs to images (via iMagick PHP extension).
    // Also, the global setting must be enabled too.
    public static function canConvertPdfToImages()
    {
        return (isset($GLOBALS['display_inline_pdf_in_pdf']) && $GLOBALS['display_inline_pdf_in_pdf'] == '1' && self::iMagickInstalled());
    }

    // Use iMagick to convert a PDF (via doc_id) to an array of paths of PNG images stored in REDCap Temp.
    // Note: The image files that are created in the temp folder are NOT deleted, by default,
    // so you may wish to manually delete them from temp after calling this method.
    private static $pdfImageCacheExpirationDays = 30;
    public static function convertPdfToImages($pdf_doc_id, $project_id=null, $removeAlphaChannel=true)
    {
        if (!PDF::iMagickInstalled()) return [];
        // First, check if PDF images are stored in the redcap_pdf_image_cache table
        $img_files = self::getCachedImages($pdf_doc_id);
        if (!empty($img_files)) return $img_files;
        // If $img_files is empty, then there are no cached image files, so instead convert PDF to images in real time and also cache them
        $pdf_file_path = Files::copyEdocToTemp($pdf_doc_id, true);
        if ($pdf_file_path !== false) {
            try {
                // Convert PDF to one or more images
                $imagick = new Imagick();
                $imagick->setResolution(200,200); // Resolution set to 200dpi
                $imagick->readImage($pdf_file_path);
                // Check if PDF has an alpha channel, and if so, remove it before converting to PNG images
                if ($removeAlphaChannel && self::hasAlphaChannel($pdf_file_path)) {
                    foreach ($imagick as $page) {
                        $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                        $page->setImageFormat('png');
                    }
                    $alphaChannelRemoved = true;
                } else {
                    $imagick->setImageFormat('png');
                    $alphaChannelRemoved = false;
                }
                $tmp_fname = date("YmdHis_").uniqid('pngtopdf');
				foreach ($imagick as $i => $page) {
					$page->setImageFormat('png');
					$outputPath = APP_PATH_TEMP.$tmp_fname."-".$i.".png";
					$page->stripImage();
					$page->writeImage($outputPath);
				}
                // $imagick->writeImages(APP_PATH_TEMP.$tmp_fname.'.png', false);
                $imgCount = $imagick->count();
                $imagick->clear();
                // Set expiration time as X days in the future
                $expiration = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")+self::$pdfImageCacheExpirationDays,date("Y")));
                // Loop through each page/image
                for ($pdf_c = 0; $pdf_c < $imgCount; $pdf_c++) {
                    // Get the path of the PNG image that was just created for this PDF page
                    $this_file_path = APP_PATH_TEMP.$tmp_fname.'-'.$pdf_c.'.png';
                    // Check if image still has an alpha channel
                    if ($alphaChannelRemoved && self::hasAlphaChannel($this_file_path)) {
                        // The alpha channel wasn't removed, which means the image may be corrupted now,
                        // so make a recursive call to reconvert this PDF without removing the alpha channel.
                        return self::convertPdfToImages($pdf_doc_id, $project_id, false);
                    }
                    // Add file path to array
                    $img_files[] = $this_file_path;
                    // Add file to edocs folder and also to redcap_pdf_image_cache table
                    $img_doc_id = REDCap::storeFile($this_file_path, $project_id);
                    $this_page = $pdf_c+1;
                    // Note: We need to replace into the table since alpha channel detection could switch mid pages
                    // and the recursive nature would lead to a prepared statement error when the same image is added twice.
                    // This would result in seemingly truncated images.
                    $sql = "REPLACE INTO redcap_pdf_image_cache (`pdf_doc_id`, `page`, `num_pages`, `image_doc_id`, `expiration`) 
                            VALUES (?, ?, ?, ?, ?)";
                    $q = db_query($sql, [$pdf_doc_id, $this_page, $imgCount, $img_doc_id, $expiration]);
                }
                return $img_files;
            } catch (Throwable $e) { }
        }
        return [];
    }

    // Return array of filepaths of cached images in the redcap_pdf_image_cache table stored locally in the temp folder
    public static function getCachedImages($pdf_doc_id)
    {
        $sql = "SELECT `image_doc_id`, `num_pages` FROM redcap_pdf_image_cache 
                WHERE `pdf_doc_id` = ?
                ORDER BY `page`";
        $q = db_query($sql, $pdf_doc_id);
		$num_pages = db_num_rows($q);
        $img_files = [];
        while ($row = db_fetch_assoc($q)) {
			if ($row['num_pages'] != $num_pages) return [];
            $img_files[] = Files::copyEdocToTemp($row['image_doc_id'], true);
        }
        return $img_files;
    }

    // Return boolean if the PDF or PNG contains an alpha channel, which can prevent correct rendering via iMagick
    public static function hasAlphaChannel($file_path)
    {
        try {
            $image = new Imagick($file_path);
            // Imagick::getImageAlphaChannel() not very reliable in detecting whether there acutally is an alpha channel
            // that is used. Therefore, we use the getImageChannelRange() method instead and check the minima/maxima reported
            // for the alpha channel. When no alpha channel is present, the maximal will be reported as a negative number.
            // Identical minima and maxima indicates that the alpha channel is not used.
            $channels = $image->getImageChannelRange(Imagick::CHANNEL_ALPHA);
            $hasAlphaChannel = $channels['maxima'] >= 0 && (($channels['maxima'] - $channels['minima']) > 0);
            if (!$hasAlphaChannel && $channels['maxima'] > 0 && ends_with($file_path, '.png') && function_exists('imagecreatefrompng') && function_exists('imagepng')) {
                // Remove irrelevant alpha channel by re-saving with GD (removing the alpha channel with Imagick doesn't work!)
                $png = imagecreatefrompng($file_path);
                imagepng($png, $file_path);
                unset($png);
            }
            return $hasAlphaChannel;
        } catch (Exception $e) {
            return false;
        }
    }

    // Return PDF-formatted string after passing HTML
    public static function formatText($string)
    {
        if ($string === null || !is_string($string)) return '';
        $orig = ["\r\n", "\r", "\t", "</p> <p>", "</p>\n", "</p>", "</tr>", "<br>\n", "<br/>\n", "<br>", "<br/>", "\n<ol>\n", "\n<ul>\n", "<ol>\n", "<ul>\n", "</li> <li>", "</li>\n<li>", "<li>", "</h1>\n", "</h2>\n", "</h3>\n", "</h4>\n", "</h5>\n"];
        $repl = ["\n", "\n", "     ", "</p><p>", "</p>", "</p>\n\n", "</tr>\n", "\n", "\n", "\n", "\n", "<ol>\n", "<ul>\n", "<ol>", "<ul>", "</li><li>", "</li><li>", "\n - ", "</h1>\n\n", "</h2>\n\n", "</h3>\n\n", "</h4>\n\n", "</h5>\n\n"];
        return str_replace($orig, $repl, $string);
    }

    // Return HTML div container for rendering an inline PDF on the page via PDFObject
    public static function renderInlinePdfContainer($src, $object_unique_id=null): string
    {
        if ($object_unique_id == null) $object_unique_id = Files::getFileUniqueId();
        return RCView::div(["class"=>"inline-pdf-viewer", "id"=>$object_unique_id, "src"=>$src]);
    }

    // PIPING: If exporting a PDF with data, then any labels, notes, choices that have field_names in them for piping, replace out with data.
    public static function pipeMetadata($this_metadata, $project_id, $record, $event_id, $form_name, $repeat_instance, $piping_record_data, $pdfHasData, $mlm_lang = false)
    {
        // Set array of field attributes to look at in $metadata
        $elements_attr_to_replace = array('element_label', 'element_enum', 'element_preceding_header', 'element_note');
        // Loop through all fields to be displayed on this form/survey
        foreach ($this_metadata as $key=>$attr)
        {
            // Loop through all relevant field attributes for this field
            foreach ($elements_attr_to_replace as $this_attr_type)
            {
                // Get value for the current attribute
                $this_string = $attr[$this_attr_type];
                // If a field, check field label
                if ($this_string != '')
                {
                    if ($record != '') {
                        // Do piping only if there is data
                        $this_string = Piping::replaceVariablesInLabel($this_string, $record, $event_id, $repeat_instance, $piping_record_data, true, null, false, "", 1,
                            false, true, $form_name, null, false, false, false, true, false, $mlm_lang);
                        // In case we have nested embedding, try running the embedded field replacement again to see if we get something different
                        do {
                            $this_string2 = $this_string;
                            if (strpos($this_string2, "{") === false || strpos($this_string2, "}") === false) break;
                            $this_string2 = Piping::replaceEmbedVariablesInLabel($this_string2, $project_id, 'ALL', $pdfHasData, !$pdfHasData);
                            $this_string2 = Piping::replaceVariablesInLabel($this_string2, $record, $event_id, $repeat_instance, $piping_record_data, true, null, false, "", 1,
                                false, true, $form_name, null, false, false, false, true, false, $mlm_lang);
                            if ($this_string === $this_string2) break;
                            $this_string = $this_string2;
                        } while (1 != 2);
                        // Set value after piping
                        $this_metadata[$key][$this_attr_type] = $this_string2;
                    }
                    // Perform string replacement
                    $this_metadata[$key][$this_attr_type] = str_replace(array("\t","&nbsp;"), array(" "," "), label_decode($this_metadata[$key][$this_attr_type]));
                }
            }
        }
        return array_values($this_metadata);
    }

	/**
	 * Strip HTML tags reliably while preserving literal < and > in text.
	 * - Converts <br> to "\n"
	 * - Drops all other tags
	 * - Optionally drops script/style content
	 */
	public static function strip_html_preserving_angles(string $html, bool $dropScriptStyle = true): string
	{
		// Regex to capture real HTML-ish tags/comments/doctype/cdata
		$tagRx = '~(<!--.*?--\s*>|<!\[CDATA\[.*?\]\]>|<!DOCTYPE.*?>|</?[A-Za-z][A-Za-z0-9:-]*(?:\s+[^<>]*?)?>)~si';

		$parts = preg_split($tagRx, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
		if ($parts === false) return $html; // fallback

		$out = '';
		$skipUntil = null; // 'script' or 'style' if we’re dropping their contents

		foreach ($parts as $i => $part) {
			$isTag = $i % 2 === 1;

			if (!$isTag) {
				// Text chunk
				if ($skipUntil === null) {
					$out .= $part; // keep literal text, including any < or >
				}
				continue;
			}

			// Tag chunk: decide what to do
			if (preg_match('~^<\s*br\s*/?\s*>$~i', $part)) {
				if ($skipUntil === null) $out .= "\n";
				continue;
			}

			// Optionally nuke script/style content entirely
			if ($dropScriptStyle) {
				if (preg_match('~^<\s*script\b~i', $part)) { $skipUntil = 'script'; continue; }
				if (preg_match('~^<\s*style\b~i',  $part)) { $skipUntil = 'style';  continue; }
				if ($skipUntil === 'script' && preg_match('~^<\s*/\s*script\s*>$~i', $part)) { $skipUntil = null; continue; }
				if ($skipUntil === 'style'  && preg_match('~^<\s*/\s*style\s*>$~i',  $part)) { $skipUntil = null; continue; }
				if ($skipUntil !== null) continue; // while skipping, drop everything
			}

			// For any other tag: drop it
		}

		// Decode entities but keep literal < and > as-is (we never encoded them)
		// If you want entities decoded: use ENT_QUOTES | ENT_HTML5
		$out = html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// Optional tidy-up
		$out = preg_replace("/\r\n?/", "\n", $out);      // normalize EOL
		$out = preg_replace("/[ \t]+\n/", "\n", $out);   // trim line-end spaces
		return trim($out);
	}

}