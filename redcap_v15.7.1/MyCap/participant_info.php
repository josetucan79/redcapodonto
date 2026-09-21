<?php
use Vanderbilt\REDCap\Classes\MyCap\Participant;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\MyCapConfiguration;

if (isset($_GET['action']) && $_GET['action'] == 'displayParticipantQrCode')
{
    // Disable REDCap's authentication
    defined("NOAUTH") or define("NOAUTH", true);
    // Convert pid=[project-id] on the fly for the preview mode to allow an image to be seen
    if (isset($_GET['preview_pid']) && isset($_GET['pid']) && $_GET['pid'] == '[project-id]') {
        $_GET['pid'] = $_GET['preview_pid'];
    }
}

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Include the QR Code class
require_once APP_PATH_LIBRARIES . "phpqrcode/lib/full/qrlib.php";

if (isset($_GET['action']) && $_GET['action'] == 'displayParticipantQrCode') {
    // Display Participant QR Code Image
    $par_code_placeholder = '[mycap-participant-code]';
    $par_code = $_GET['par_code'];
    $emptyPngPath = APP_PATH_DOCROOT.'Resources'.DS.'images'.DS.'qr_placeholder.png';
    // REDCap alerts and notifications may pass an empty participant code
    // in the WYSIWYG editor. Show a placeholder image in this case.
    if ((!isset($par_code) || empty($par_code) || $par_code === $par_code_placeholder) && file_exists($emptyPngPath)) {
        $imgInfo = getimagesize($emptyPngPath);
        header("Content-type: image/png");
        readfile($emptyPngPath);
        exit();
    }

    $myCapProj = new MyCap($_GET['pid']);
    $project_code = $myCapProj->project['code'];
    if ($project_code != '' && $_GET['par_code'] != '') {
        $qrcode = Participant::makeParticipantImage(
            MyCapConfiguration::ENDPOINT,
            $project_code,
            $_GET['par_code'],
            APP_PATH_DOCROOT.'Resources/images/mycap_qr_overlay.png'
        );
    }

    header("Content-type: image/gif");
    echo base64_decode($qrcode);
    exit();
} else if (isset($_GET['action']) && $_GET['action'] == 'setIdentifier') {
    global $myCapProj;
    // Set dialog title
    $title = RCView::span(array('style'=>'margin-left:3px;vertical-align:middle;'),
        "<i class='fas fa-tag'></i> ".$lang['mycap_mobile_app_357']);

    $participant_custom_field = $myCapProj->project['participant_custom_field'];
    $participant_custom_label = $myCapProj->project['participant_custom_label'];

    $contents = '<div>
                    <div class="mb-3 fs13" style="line-height: 1.4;">'.$lang['mycap_mobile_app_358'].'</div>
                        <div class="round chklist" style="padding:10px 20px;max-width:900px;">
                            <form id="setuplabelsform" action="'.APP_PATH_WEBROOT .'ProjectGeneral/edit_project_settings.php?pid='.PROJECT_ID.'" method="post">
                                <table style="width:100%;" cellspacing=0>
                                    <tr>
                                        <td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
                                            <i class="fas fa-tags" style="text-indent: 0;"></i>
                                            <b style=""><u>'.$lang['mycap_mobile_app_359'].'</u></b><br>
                                            '.$lang['mycap_mobile_app_361'].'
                                            <div id="participant_id_div" style="text-indent: 0em; padding: 10px; '.(($participant_custom_field == '' && $participant_custom_label != '')? 'opacity: 0.3;' : '').'">
                                                '.Participant::renderParticipantDisplayLabelDropDown("participant_custom_field", "participant_custom_field", $participant_custom_field, (($participant_custom_field == '' && $participant_custom_label != '')? 'disabled="disabled"' : '')).'
                                            </div>
                                            <div style="padding:5px 0;font-weight:normal;color:#777;">&mdash; '.$lang['global_46'].' &mdash;</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:0px 5px 10px 40px;">
                                            <div style="margin:5px; padding-top: 10px; font-weight: bold;">
                                                <input type="checkbox" name="participant_id_custom_chk" id="participant_id_custom_chk" '.($participant_custom_label != '' ? 'checked="checked"' : '').'>
                                                <i class="fas fa-tag" style="text-indent: 0;"></i>
                                                <b style=""><u>'.$lang['mycap_mobile_app_362'].'</u></b>
                                            </div>                                        
                                            <div id="participant_id_custom_div" style="text-indent:0em; '.($participant_custom_label == ''? 'opacity: 0.3;' : '').'">
                                                <input type="text" class="x-form-text x-form-field" style="width:300px;" id="participant_custom_label" name="participant_custom_label" '.($participant_custom_label == ''? 'disabled="disabled"' : '').' value="'.str_replace('"', '&quot;', $participant_custom_label??"").'"><br>
                                                <!-- Piping link -->
                                                <div style="padding:8px 0px 2px;color:#555;font-size:11px;">
                                                    '.$lang['mycap_mobile_app_777'].' 
                                                    <button class="btn btn-xs btn-rcpurple btn-rcpurple-light" style="margin-left:3px;margin-right:2px;font-size:11px;padding:0px 3px 1px;line-height: 14px;" onclick="pipingExplanation();return false;"><img src="'.APP_PATH_IMAGES.'pipe.png" style="width:12px;position:relative;top:-1px;margin-right:2px;">'.$lang["info_41"].'</button>
                                                    '.$lang["global_43"].'
                                                    <button class="btn btn-xs btn-rcgreen btn-rcgreen-light" style="margin-left:3px;font-size:11px;padding:0px 3px 1px;line-height:14px;"  onclick="smartVariableExplainPopup();return false;">[<i class="fas fa-bolt fa-xs" style="margin:0 1px;"></i>] '.$lang["global_146"].'</button>
                                                    <div style="margin-top:8px;color:#999;font-size:11px;font-family:verdana;">
                                                        '.$lang['mycap_mobile_app_379'].' '.$lang['mycap_mobile_app_778'].'                                                        
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                        </div>   
                   </div>';
} else if (isset($_GET['action']) && $_GET['action'] == 'getHTML') {
    // Get HTML Message templates
    // Set dialog title
    $title = RCView::span(array('style'=>'margin-left:3px;vertical-align:middle;'),
            "<i class='fas fa-user-plus'></i> ".$lang['mycap_mobile_app_383']);

    // Set dialog content
    $contents = loadJS('MyCapProject.js', false);

    $type = (isset($_GET['type'])) ? $_GET['type'] : 'qr';
    $text = Participant::getTemplateMessage($type, !(isset($_GET['record']) && !empty($_GET['record'])));

    $qrChecked = ($type == 'qr') ? "checked" : "";
    $urlChecked = ($type == 'url') ? "checked" : "";
    $bothChecked = ($type == 'both') ? "checked" : "";

    $templateOptions =  RCView::div(array('class' => 'd-inline-block', 'style' => 'margin: 5px;'),
                            RCView::label(array('style' => 'display:inline;font-weight:normal;color:#A00000;margin-bottom:2px;'),
                                RCView::radio(array('name'=>'template-type', 'value'=>'qr', 'id' => 'qr', $qrChecked => $qrChecked)) .' '. $lang['mycap_mobile_app_384']
                            ) .
                            RCView::label(array('style' => 'display:inline;font-weight:normal;color:#A00000;margin-bottom:2px;padding-left:10px;'),
                                RCView::radio(array('name'=>'template-type', 'value'=>'url', 'id' => 'url', $urlChecked => $urlChecked)) .' '. $lang['mycap_mobile_app_385']
                            ) .
                            RCView::label(array('style' => 'display:inline;font-weight:normal;color:#A00000;margin-bottom:2px;padding-left:10px;'),
                                RCView::radio(array('name'=>'template-type', 'value'=>'both', 'id' => 'both', $bothChecked => $bothChecked)) .' '. $lang['mycap_mobile_app_386']
                            )
                        ) .
                        RCView::div(array('class' => 'clear'),'');
    $templateOptions .= RCView::hidden(array('id' => 'recordVal', 'value' => $_GET['record']));
    $templateOptions .= RCView::hidden(array('id' => 'eventVal', 'value' => $_GET['event_id']));

    $copyTextHtml = "<div class='' style='width:99%; box-sizing:border-box;'>
                        <div class='clear'></div>                       
                        <div id='textboxTemplate' class='text-block wrap-long-url staticInput' style='border:1px solid #ccc; padding: 4px;'>$text</div>
                     </div>"
                    . '<div><textarea id="html-message-generated" class="staticInput fs15" readonly style="display:none;color:#e83e8c;white-space:pre-wrap!important;height:200px;width:98%;font-family:SFMono-Regular,Menlo,Monaco,Consolas,\'Liberation Mono\',\'Courier New\',monospace" onclick="this.select();">'.$text.'</textarea></div>'
                    . '';

    // Give warning if we're on Config Version 0
    $zeroVersionWarning = "";
    if ($myCapProj->getConfigVersion() == 0) {
        $zeroVersionWarning = RCView::div(['class'=>'red my-4'], RCView::b('<i class="fa-solid fa-circle-exclamation"></i> ' . $lang['global_48'].$lang['colon']) . " " .$lang['mycap_mobile_app_676']);
    }

    $contents .= '<div>
                        <div class="mb-3 fs13" style="line-height: 1.2;">'.$lang['mycap_mobile_app_766'].'</div>
                        '.$zeroVersionWarning. $transitionToFlutterNotice.'                        
                        <!-- Step 1: Choose Template format -->
                        <div class="font-weight-bold ms-1 fs16" style="color:#C00000;">'.$lang['mycap_mobile_app_378'].'</div>
                        <div class="mt-2" style="background-color:#f5f5f5;padding:8px;border:1px solid #ccc;">
                            '.$templateOptions.'
                        </div>
                        
                        <!-- Step 2 -->
                        <div class="mt-4 mb-4">
                            <div class="clearfix">
                                <div class="font-weight-bold ms-1 fs16 float-start" style="color:#C00000;">'.$lang['mycap_mobile_app_380'].'</div>
                                <button id=\'change\' class=\'btn btn-xs btn-defaultrc float-end me-2 mb-1\'><i class=\'fas fa-code\'></i> '.RCView::tt('mycap_mobile_app_901').'</button>
                                <button class="btn btn-primaryrc btn-xs btn-clipboard float-end me-2 mb-1" onclick="return false;" title="'.js_escape2($lang['global_137']).'" data-clipboard-target="#textboxTemplate"><i class="fas fa-paste"></i> '.$lang['global_137'].'</button>
                            </div>
                            '.$copyTextHtml.'
                        </div>
                        
                        <!-- Step 3 -->
                        <div class="font-weight-bold ms-1 fs16" style="color:#C00000;">'.$lang['mycap_mobile_app_381'].'</div>
                        <div class="mt-2" style="background-color:#f5f5f5;padding:8px;border:1px solid #ccc;">
                            <div class="p mt-1">
                                '.$lang['mycap_mobile_app_770'].'
                                <ul>
                                    <li class="mt-1">'.$lang['mycap_mobile_app_771'].'</li>
                                    <li class="mt-1">'.$lang['mycap_mobile_app_772'].'</li>
                                </ul>
                            </div>
                            <div class="p mb-1">
                                '.$lang['mycap_mobile_app_409'].'<br>'.$lang['mycap_mobile_app_410'].'
                            </div>
                        </div>';

    $contents .= '</div>';

} elseif (isset($_GET['action']) && $_GET['action'] == 'getHTMLByType') {
    print Participant::getTemplateMessage($_GET['type'], !(isset($_GET['record']) && !empty($_GET['record'])));
    exit;
} else if (isset($_GET['action']) && $_GET['action'] == 'getChangeStatusHTML') {
    $contents = RCView::tt('mycap_mobile_app_934');
    $title = RCView::tt('mycap_mobile_app_933');
    $defaultMsgText = RCView::tt_js2('mycap_mobile_app_940');
    if ($_GET['flag'] == 'enable') {
        $contents = RCView::tt('mycap_mobile_app_931');
        $title = RCView::tt('mycap_mobile_app_932');
        $defaultMsgText = RCView::tt_js2('mycap_mobile_app_941');
    }


    if ($_GET['joined'] == 1) { // Show "Notify Participant" section ONLY if participant joined from app
        $contents .= '<div style="padding-top: 5px;overflow:hidden;">
                        <div style="color:green;font-size:11px;">'.RCView::tt('mycap_mobile_app_938').'</div>
                        <div style="color:#A00000;background-color:#f7f7f7;border:1px solid #ddd;padding-top: 5px;">
                            <div style="margin:5px; font-weight: bold;">
                                <input type="checkbox" id="notify_participant" name="notify_participant"> <label for="notify_participant"><i class="fas fa-bell"></i> <b><u>'.RCView::tt('mycap_mobile_app_939').'</u></b></label>
                            </div>
                            <div id="par-message-box" style="padding-top: 5px;margin:10px;opacity: 0.6;" class="mc-form-control-custom">
                                <textarea disabled name="message" placeholder="'.RCView::tt_js2('messaging_110').'" style="max-width:95%; height: 100px; resize: both;">'.$defaultMsgText.'</textarea></div>
                        </div>
                    </div>';
    }

} else {
    // Check record
    if (!isset($_GET['record'])) exit('0');
    $record = $_GET['record'];
    $event_id = $_GET['event_id'];

    global $myCapProj;
    // Get mycap participant code
    $sql = "SELECT code FROM redcap_mycap_participants WHERE record = '".db_escape($record)."' AND project_id = '".PROJECT_ID."'";
    if ($event_id != '') {
        $sql .= " AND event_id = '".db_escape($event_id)."'";
    }
    $q = db_query($sql);
    $par_code = db_result($q, 0);

    $participant = Participant::getParticipantIdentifier($record, PROJECT_ID, null, $event_id);
    if ($par_code != '') {
        $qrcode = Participant::makeParticipantImage(
                            MyCapConfiguration::ENDPOINT,
                                    $myCapProj->project['code']??"",
                                    $par_code,
                                    APP_PATH_DOCROOT.'Resources/images/mycap_qr_overlay.png'
                                );
    }

    $participant_link = Participant::makeParticipantmakeJoinUrl(
                            MyCapConfiguration::ENDPOINT,
                                    $myCapProj->project['code']??"",
                                    $par_code
                                );
    // Set dialog title
    $title = RCView::img(array('src'=>'access_qr_code.gif','style'=>'vertical-align:middle;')) .
        RCView::span(array('style'=>'margin-left:3px;vertical-align:middle;'),
            (gd2_enabled()
                ? $lang['mycap_mobile_app_387']
                : $lang['mycap_mobile_app_388'])
        );

    // Give warning if we're on Config Version 0
    $zeroVersionWarning = "";
    if ($myCapProj->getConfigVersion() == 0) {
        $zeroVersionWarning = RCView::div(['class'=>'red my-4'], RCView::b('<i class="fa-solid fa-circle-exclamation"></i> ' . $lang['global_48'].$lang['colon']) . " " .$lang['mycap_mobile_app_676']);
    }

    $convertedToFlutter = $myCapProj->project['converted_to_flutter'];
    $participantQRSteps = '<b>'.$lang['mycap_mobile_app_790'].'</b>' .
                        '<ol>
                            <li>'.(($convertedToFlutter == 1) ? $lang['mycap_mobile_app_809'] : $lang['mycap_mobile_app_815']).'</li>
                            <li>'.(($convertedToFlutter == 1) ? $lang['mycap_mobile_app_955'] : $lang['mycap_mobile_app_813']).'</li>
                            <li>'.$lang['mycap_mobile_app_814'].'</li>
                            <li>'.$lang['mycap_mobile_app_951'].'</li>
                            <li>'.$lang['mycap_mobile_app_956'].'</li>
                         </ol>';

    $participantURLSteps = '<b>'.$lang['mycap_mobile_app_790'].'</b>' .
                        '<ol>
                            <li>'.$lang['mycap_mobile_app_952'].'</li>
                            <li>'.(($convertedToFlutter == 1) ? $lang['mycap_mobile_app_953'] : $lang['mycap_mobile_app_801']).'</li>'.
                            (($convertedToFlutter == 1) ?
                                '<li>'.$lang['mycap_mobile_app_955'].'</li>
                                 <li>'.$lang['mycap_mobile_app_954'].'</li>'
                                : '<li>'.$lang['mycap_mobile_app_814'].'</li>').
                         '</ol>';

    // Set dialog content
    $contents = RCView::div(array('style'=>'font-size:'.($isAjax ? '14px' : '16px').';color:#800000;'),
            $lang['mycap_mobile_app_357'] . $lang['colon'] . " \"" . RCView::b(RCView::escape($participant)) . "\" (".$lang['global_49']." \"" . RCView::escape($record) . "\")"
        ) .
        ($isAjax ? RCView::div(array('style'=>'font-size:14px;margin:15px 0 20px;'),
            $lang['mycap_mobile_app_788'] . $zeroVersionWarning
        ) : '').
        RCView::table(array('style'=>'table-layout:fixed;border-top:1px solid #ccc;padding-top:10px;width:100%;'),
            RCView::tr(array(),
                (!gd2_enabled() ? '' :
                    RCView::td(array('id'=>'qrcode-info', 'valign'=>'top', 'style'=>'padding-right:20px;padding-top:10px; width: 50%;'),
                        ## QR CODE
                        RCView::div(array('style'=>'color:#800000;font-weight:bold;font-size:15px;margin-bottom:10px;'),
                            RCView::span(array('style'=>'vertical-align:middle;'), '<i class="fas fa-qrcode"></i> '.$lang['mycap_mobile_app_789'])
                        ) .
                        ($par_code != '' ?
                            RCView::div(array('style'=>''),
                                ($isAjax ? $participantQRSteps : $lang['mycap_mobile_app_395'])
                            ) .
                            RCView::div(array('style'=>'text-align:center;margin-top:20px; margin-bottom: 20px;'),
                                "<img height='365px' src='data:image/png;base64,".$qrcode."'>"
                            ) : RCView::div(array('style'=>'margin-bottom:2px;', 'class'=>'error'), $lang['mycap_mobile_app_392'])
                        ) .
                        (($isAjax && $par_code != '') ?
                            RCView::div(array('class'=>'error mt-3 mb-1'), RCView::button(array('class'=>'btn btn-xs btn-primaryrc fs12', 'onclick'=>"openEmailTemplatePopup('".$record."', '".$event_id."', 'qr');return false;"),
                                RCView::fa('fas fa-code mr-1').' '.$lang['mycap_mobile_app_396'])
                            ) .
                            RCView::div(array('class'=>'error mt-3 mb-1'), RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs12', 'onclick'=>"printQRCode('".$record."');return false;"),
                                RCView::fa('fas fa-print me-1').' '.$lang['mycap_mobile_app_397'])
                            ) : '')
                    )
                ) .
                ($isAjax ? RCView::td(array('valign'=>'top', 'style'=>'width:325px;padding-left:20px;padding-top:10px;border-left:1px solid #ccc;'),
                    ## JOIN BY URL
                    RCView::div(array('style'=>'color:#800000;font-weight:bold;font-size:15px;margin-bottom:10px;'),
                        RCView::span(array('style'=>'vertical-align:middle;'), '<i class="fas fa-link"></i> '.$lang['mycap_mobile_app_390'])
                    ) .
                    ($par_code != '' ?
                        RCView::div(array('style'=>''),
                            $participantURLSteps
                        ) .
                        RCView::div(array('class' => 'wrap-long-url', 'style'=>'color:#e83e8c;white-space:wrap!important;width:97%;margin:10px;'),
                            $participant_link
                        )  .
                        RCView::div(array('class'=>'error mt-3 mb-1'),
                            RCView::button(array('class'=>'btn btn-xs btn-primaryrc fs12', 'onclick'=>"openEmailTemplatePopup('".$record."', '".$event_id."', 'url');return false;"),
                                '<i class="fas fa-code mr-1"></i> '.$lang['mycap_mobile_app_393']
                            )
                        )
                        : RCView::div(array('style'=>'margin-bottom:2px;', 'class'=>'error'), $lang['mycap_mobile_app_392'])
                    )
                ) : '')
            )
        ) .
        ($isAjax ? '' :
            // Dialog
            RCView::div(array('class'=>'d-print-none', 'style'=>'text-align:center;margin:50px 0 0;'),
                RCView::button(array('style'=>'color:#800000;font-size:15px;padding:4px 8px;', 'onclick'=>"var currentWin = window.self;currentWin.close();"),
                    $lang['data_export_tool_160']
                )
            )
        );
}

// Output JSON if AJAX
if ($isAjax) {
	print json_encode_rc(array('content'=>$contents, 'title'=>$title));
} else {
	// Displaying on the "print" page
	print $contents;
}