<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Validate request
if (!(isset($_GET['page']) && isset($Proj->forms[$_GET['page']])
	&& isset($_GET['survey_id']) && Survey::checkSurveyProject($_GET['survey_id']))) exit("ERROR!");

// Output the event list
$chooseEventRows = Design::getEventsAutomatedInvitesForForm($_GET['page']);

// Display each event as a separate row
$html = "";
$isRepeatingSurvey = false;
foreach ($chooseEventRows as $this_event_id=>$attr)
{
    $class = '';
	if ($attr['active'] == '1') {
		// Add check icon with green text
		$img = RCIcon::CheckMarkCircle("button-checked");
        $class = 'checked';
		$color = '#0f7b0f ';
		$btnTxt = $lang['design_169'];
	} elseif ($attr['active'] == '0') {
		// Add check icon with green text
        $img = RCIcon::MinusCircle("button-not-checked");
		$color = '#800000';
		$btnTxt = $lang['design_169'];
	} else {
		// Add + with gray black text
		$img = RCView::span(array('style'=>'margin-right:2px;'), "+");
		$color = '#555';
		$btnTxt = $lang['design_387'];
	}
    // Set button and text
    $btn1 = RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs11 '.$class,'style'=>"position:relative;margin-right:5px;color:$color;",
        'onclick'=>"setUpConditionalInvites({$_GET['survey_id']}, $this_event_id, '{$_GET['page']}')"),
        $img . $btnTxt
    );
    $postText = $longitudinal ? $attr['name'] : "";
	// Output event as row
    $html .= RCView::div(array('style'=>'padding:5px 0;font-size:12px;'),
        $btn1 . $postText
		  );
}
// Output the HTML
print $html;