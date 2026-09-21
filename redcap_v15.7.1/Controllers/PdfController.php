<?php

use MultiLanguageManagement\MultiLanguage;
use REDCap\Context;

class PdfController extends Controller
{
	// Render the PDF
	public function index()
	{
		$GLOBALS["draft_preview_enabled"] = Design::isDraftPreview() && (($_GET["draft-preview"] ?? "0") == "1");
		// MLM - Inject user preferred language, but only for individual instruments
		// (multi-instrument downloads from the Record Home Page or multi-record downloads
		// from Other Export Options will use the default language)
		if (isset($_GET["page"]) && !empty($_GET["page"])) {
			// Get record name if not in URL but survey hash is in URL
			$isSurvey = false;
			if (isset($_GET["s"]) && !empty($_GET["s"]) && !isset($_GET["id"])) {
				$_GET["id"] = Survey::getRecordFromParticipantId(Survey::getParticipantIdFromHash($_GET["s"]));
				$isSurvey = true;
			}
			// Build MLM context
			$context_builder = Context::Builder()
				->project_id(defined("PROJECT_ID") ? PROJECT_ID : null)
				->user_id(defined("USERID") ? USERID : null)
				->instrument(isset($_GET["page"]) ? $_GET["page"] : null)
				->instance(isset($_GET["instance"]) && is_integer($_GET["instance"]) ? $_GET["instance"] : null)
				->event_id(isset($_GET["event_id"]) && is_integer($_GET["event_id"]) ? $_GET["event_id"] : null)
				->record(isset($_GET["id"]) ? $_GET["id"] : null)
				->lang_id(isset($_GET[MultiLanguage::LANG_GET_NAME]) ? $_GET[MultiLanguage::LANG_GET_NAME] : null);
			if ($isSurvey) {
				$context_builder->is_pdf();
			} 
			else {
				$context_builder->is_dataentry();
			}
			// Set lang for PDF (if not already set)
			if (!isset($_GET[MultiLanguage::LANG_GET_NAME])) {
				$_GET[MultiLanguage::LANG_GET_NAME] = MultiLanguage::getUserPreferredLanguage($context_builder->Build());
			}
		}
		// Output the PDF
		PDF::output(false, isset($_GET['s']) && !empty($_GET['s']));
	}
}