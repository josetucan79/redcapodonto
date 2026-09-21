<?php

// TODO: Replace any hardcoded color values with CSS variables

/**
 *  --online-designer-branchinglogic-color: #008000;
 *  --online-designer-edit-color: #ca861f;
 *  --online-designer-delete-color: #c03b3b;
 *  --online-designer-move-color: #258ae7;
 *  --online-designer-copy-color: #2b2f32;
 *  --online-designer-convert-color: #337ab7;
 *  --online-designer-identifier-color: #f3ac12;
 *  --online-designer-stopaction-color: #dc3545;
 *  --online-designer-required-color: #dc3545;
 *  --online-designer-modify-selection-color: #41a741;
 *  --online-designer-surveyqueue-color: #800000;
 *  --online-designer-survey-color: #0f7b0f;
 *  --color-rc-green: #41a741
 */

/**
 * REDCap Icon is a class of static functions that build icon HTML elements.
 * (based on FontAwesome icons)
 */
class RCIcon
{
	public static function Manual($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-book $extra_classes"), $style);
	}
	public static function Palette($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-palette $extra_classes"), $style);
	}
	public static function CSS($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-brands fa-css $extra_classes"), $style);
	}
	public static function RepeatingIndicator($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-repeat $extra_classes"), $style);
	}
	public static function NotificationLogDelete($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-xmark $extra_classes"), $style);
	}
	public static function NotificationLogSent($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-circle-check $extra_classes"), $style);
	}
	public static function NotificationLogScheduled($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-clock $extra_classes"), $style);
	}
	public static function NotificationLogPaused($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-circle-pause $extra_classes"), $style);
	}
	public static function NotificationLogFailed($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-circle-exclamation $extra_classes"), $style);
	}

	public static function CollapseUp($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-chevron-up $extra_classes"), $style);
	}
	public static function UncollapseDown($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-chevron-down $extra_classes"), $style);
	}
	public static function Close($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-times $extra_classes"), $style);
	}
	public static function ESigned($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-file-signature $extra_classes"), $style);
	}
	public static function Locked($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-lock $extra_classes"), $style);
	}
	public static function Unlock($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-unlock $extra_classes"), $style);
	}
	public static function Unlocked($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-lock-open $extra_classes"), $style);
	}
	public static function Hint($extra_classes = "", $style = "") {
		$style = "color:#ffc107;$style"; // --bs-warning
		return RCView::fa(trim("fa-regular fa-lightbulb $extra_classes"), $style);
	}
	public static function SurveyQueue($extra_classes = "", $style = "") {
		$style = "color:#800000;$style"; // --online-designer-surveyqueue-color
		return RCView::fa(trim("fa-solid fa-list $extra_classes"), $style);
	}
	public static function SurveyRepeat($extra_classes = "", $style = "") {
		$style = "color:#0f7b0f;$style"; // --online-designer-survey-color
		return RCView::fa(trim("fa-solid fa-rotate-right fa-rotate-180 $extra_classes"), $style);
	}
	public static function SurveyAutoContinue($extra_classes = "", $style = "") {
		$style = "color:#0f7b0f;$style"; // --online-designer-survey-color
		return RCView::fa(trim("fa-solid fa-arrow-down $extra_classes"), $style);
	}
	public static function Paste($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-paste $extra_classes"), $style);
	}
	public static function CheckMark($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-check $extra_classes"), $style);
	}
	public static function CheckMarkCircle($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-circle-check $extra_classes"), $style);
	}
    public static function CrossCircle($extra_classes = "", $style = "") {
        return RCView::fa(trim("fa-solid fa-circle-xmark $extra_classes"), $style);
    }
	public static function MinusCircle($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-minus-circle $extra_classes"), $style);
	}
	public static function ProgressSpinner($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-spinner fa-spin-pulse $extra_classes"), $style);
	} 
	public static function TakeATour($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-user-graduate $extra_classes"), $style);
	} 
	public static function Video($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-film $extra_classes"), $style);
	}
	public static function Survey($extra_classes = "", $style = "") {
		$style = "color:#0f7b0f;$style"; // --online-designer-survey-color
		return RCView::fa(trim("fa-solid fa-clipboard-user $extra_classes"), $style);
	}
	public static function MyCapTask($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-list-check text-primaryrc $extra_classes"), $style);
	}
	public static function SmartVariables($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-bolt $extra_classes"), $style);
	}
	public static function SurveyStopAction($extra_classes = "", $style = "") {
		$style = "color:#dc3545;$style"; // --online-designer-stopaction-color
		return RCView::fa(trim("fa-solid fa-hand $extra_classes"), $style);
	}
	public static function BranchingLogic($extra_classes = "", $style = "") {
		$style = "color:#008000;$style"; // --online-designer-branchinglogic-color
		return RCView::fa(trim("fa-solid fa-arrows-split-up-and-left fa-rotate-180 $extra_classes"), $style);
	}
	public static function ActionTags($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-at $extra_classes"), $style);
	}
	public static function OnlineDesignerEdit($extra_classes = "", $style = "") {
		$style = "color:#ca861f;$style"; // --online-designer-edit-color
		return RCView::fa(trim("fa-solid fa-pencil $extra_classes"), $style);
	}
	public static function OnlineDesignerDelete($extra_classes = "", $style = "") {
		$style = "color:#c03b3b;$style"; // --online-designer-delete-color
		return RCView::fa(trim("fa-solid fa-trash-can $extra_classes"), $style);
	}
	public static function OnlineDesignerMove($extra_classes = "", $style = "") {
		$style = "color:#258ae7;$style"; // --online-designer-move-color
		return RCView::fa(trim("fa-solid fa-up-down-left-right $extra_classes"), $style);
	}
	public static function OnlineDesignerCopy($extra_classes = "", $style = "") {
		$style = "color:#2b2f32;$style"; // --online-designer-copy-color
		return RCView::fa(trim("fa-solid fa-clone $extra_classes"), $style);
	}
	public static function OnlineDesignerConvertMatrix($extra_classes = "", $style = "") {
		$style = "color:#337ab7;$style"; // --online-designer-convert-color
		return RCView::fa(trim("fa-solid fa-table-cells $extra_classes"), $style);
	}
	public static function OnlineDesignerEditChoices($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-list-ol $extra_classes"), $style);
	}
	public static function OnlineDesignerIdentifier($extra_classes = "", $style = "") {
		$style = "color:#f3ac12;$style"; // --online-designer-identifier-color
		return RCView::fa(trim("fa-solid fa-user $extra_classes"), $style);
	}
	public static function OnlineDesignerIdentifierOff($extra_classes = "", $style = "") {
		$style = "color:#c03b3b;$style"; // --online-designer-delete-color
		return RCView::fa(trim("fa-solid fa-user-slash $extra_classes"), $style);
	}
	public static function OnlineDesignerRequired($extra_classes = "", $style = "") {
		$style = "color:#dc3545;$style"; // --online-designer-required-color
		return RCView::fa(trim("fa-solid fa-asterisk $extra_classes"), $style);
	}
	public static function OnlineDesignerRequiredOff($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-asterisk fa-xs $extra_classes"), $style);
	}
	public static function OnlineDesignerCustomAlignment($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-align-left $extra_classes"), $style);
	}
	public static function OnlineDesignerTextBoxValidation($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-pen-to-square $extra_classes"), $style);
	}
	public static function OnlineDesignerEditSlider($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-sliders $extra_classes"), $style);
	}
	public static function OnlineDesignerEditFieldNote($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-note-sticky $extra_classes"), $style);
	}
	public static function OnlineDesignerRadioField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-circle-dot $extra_classes"), $style);
	}
	public static function OnlineDesignerYesNoField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-thumbs-up $extra_classes"), $style);
	}
	public static function OnlineDesignerTrueFalseField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-circle-check $extra_classes"), $style);
	}
	public static function OnlineDesignerDropdownField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-square-caret-down $extra_classes"), $style);
	}
	public static function OnlineDesignerCheckboxField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-square-check $extra_classes"), $style);
	}
	public static function OnlineDesignerTextBoxField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-square $extra_classes"), $style);
	}
	public static function OnlineDesignerTextBoxField_Numeric($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-hashtag $extra_classes"), $style);
	}
	public static function OnlineDesignerTextBoxField_Date($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-calendar-days $extra_classes"), $style);
	}
	public static function OnlineDesignerTextBoxField_Email($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-envelope $extra_classes"), $style);
	}
	public static function OnlineDesignerTextBoxField_Phone($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-phone $extra_classes"), $style);
	}
	public static function OnlineDesignerNotesBoxField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-note-sticky $extra_classes"), $style);
	}
	public static function OnlineDesignerSignatureField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-signature $extra_classes"), $style);
	}
	public static function OnlineDesignerSliderField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-sliders $extra_classes"), $style);
	}
	public static function OnlineDesignerFileUploadField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-file $extra_classes"), $style);
	}
	public static function OnlineDesignerDescriptiveField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-circle-info $extra_classes"), $style);
	}
	public static function OnlineDesignerCalcField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-square-root-variable $extra_classes"), $style);
	}
	public static function OnlineDesignerSqlField($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-database $extra_classes"), $style);
	}
	public static function OnlineDesignerDownload($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-download $extra_classes"), $style);
	}
	public static function OnlineDesignerUpload($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-upload $extra_classes"), $style);
	}
	public static function OnlineDesignerExpand($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-up-right-and-down-left-from-center $extra_classes"), $style);
	}
	public static function OnlineDesignerReplace($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-arrow-right-arrow-left $extra_classes"), $style);
	}
	public static function OnlineDesignerModifySelection($extra_classes = "", $style = "") {
		$style = "color:#41a741;$style"; // --online-designer-modify-selection-color
		return RCView::fa(trim("fa-solid fa-expand $extra_classes"), $style);
	}
	public static function OnlineDesignerUpUpDownDownToLine($extra_classes = "", $style = "", $fa_extra_classes = "") {
		$attr = [];
		if (!empty($style)) $attr["style"] = $style;
		if (!empty($extra_classes)) $attr["class"] = $extra_classes;
		return RCView::span($attr, 
			RCView::fa(trim("fa-solid fa-arrows-left-right-to-line fa-rotate-90 fa-sm $fa_extra_classes"), "margin-left:-2px;margin-top:2px;") .
			RCView::fa(trim("fa-solid fa-arrows-left-right-to-line fa-rotate-90 fa-sm $fa_extra_classes"), "margin-left:-7px;margin-top:2px;")
		);
	}
	public static function OnlineDesignerPDF($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-circle-down $extra_classes"), $style);
	}
	public static function OnlineDesignerPDFSnapshot($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-camera $extra_classes"), $style);
	}
	public static function OnlineDesignerEConsent($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-user-pen $extra_classes"), $style);
	}
	public static function OnlineDesignerFDL($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-eye-slash $extra_classes"), $style);
	}
	public static function OnlineDesignerUpUpToLine($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-arrows-up-to-line $extra_classes"), $style);
	}
	public static function OnlineDesignerDownDownToLine($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-arrows-down-to-line $extra_classes"), $style);
	}
	public static function OnlineDesignerUpDown($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-up-down $extra_classes"), $style);
	}
	public static function OnlineDesignerTurnUp($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-arrow-turn-up $extra_classes"), $style);
	}
	public static function OnlineDesignerTurnDown($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-arrow-turn-down $extra_classes"), $style);
	}
	public static function OnlineDesignerFieldNavigator($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-compass $extra_classes"), $style);
	}
	public static function OnlineDesignerSurveyLogin($extra_classes = "", $style = "") {
		$style = "color:#ab8900;$style";
		return RCView::fa(trim("fa-solid fa-key $extra_classes"), $style);
	}
	public static function OnlineDesignerSurveyQueue($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-list $extra_classes"), $style);
	}
	public static function OnlineDesignerReevaluateASIs($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-redo $extra_classes"), $style);
	}
	public static function OnlineDesignerSurveyNotifications($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-mail-bulk $extra_classes"), $style);
	}
	public static function OnlineDesignerAnalyzeSMS($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-comment-sms $extra_classes"), $style);
	}
	public static function OnlineDesignerAutoInvitationOptions($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-gears $extra_classes"), $style);
	}
	public static function OnlineDesignerMobileDevice($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-mobile-screen-button $extra_classes"), $style);
	}
	public static function OnlineDesignerViewDetails($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-magnifying-glass $extra_classes"), $style);
	}
	public static function OnlineDesignerBaselineDate($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-calendar-day $extra_classes"), $style);
	}
	public static function OnlineDesignerCustomMyCapSettings($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-user-tag $extra_classes"), $style);
	}
	public static function ErrorNotificationTriangle($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-triangle-exclamation $extra_classes"), $style);
	}
	public static function ErrorNotificationCircle($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-solid fa-circle-exclamation $extra_classes"), $style);
	}
	public static function DescriptivePopups($extra_classes = "", $style = "") {
		return RCView::fa(trim("fa-regular fa-comment-dots $extra_classes"), $style);
	}
}
