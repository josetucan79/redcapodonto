<?php

use Vanderbilt\REDCap\Classes\MyCap\MyCapConfiguration;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\MiscHandler;

// Header
include 'header.php';

if (!ACCESS_CONTROL_CENTER && !System::isCI()) redirect(APP_PATH_WEBROOT);
if (!ACCESS_SYSTEM_CONFIG) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); });</script>";

?>
<style type="text/css">
    .test-title {
        background-color: #5cb860;
        border: 1px solid green;
        border-top: 1px solid green;
        border-left:0;
        color: #fff;
        padding: 3px 6px;
        font-size: 14px;
        display: inline;
        border-radius: 0 0 calc(.25rem - 1px) 0 !important;
    }
    #executing {
        position: absolute;
        border: 2px solid #AAAAAA;
        background-color: white;
        overflow: auto;
        z-index:9999;
        padding:8px 18px 8px 8px;
        text-align:center;
        top:40%;
        left:40%;
        width:650px;
        font-size:18px;
        font-weight:bold;
        color:#666;
    }
</style>
<script type="text/javascript">
var langErrorColon = '<?php echo js_escape($lang['global_01'].$lang['colon']) ?>';
var project_missing = '<?php echo js_escape($lang['mycap_mobile_app_486']); ?>';
var participant_missing = '<?php echo js_escape($lang['mycap_mobile_app_487']); ?>';
var processing_message1 = '<?php echo js_escape($lang['control_center_4851']); ?>';
var processing_message2 = '<?php echo js_escape($lang['control_center_4852']); ?>';

$(document).ready(function(){
    $("#sel-mycap-project").change(function(){
        var selected_project_code = $(this).val();

        $.ajax({ url: app_path_webroot+'ControlCenter/mycap_check_ajax.php', data: {action: 'listParticipants', selected_project : selected_project_code}, dataType: 'json',
            success:function(response){
                var len = response.length;

                $("#sel-mycap-participant").empty();
                for( var i = 0; i<len; i++){
                    var id = response[i]['id'];
                    var name = response[i]['name'];

                    $("#sel-mycap-participant").append("<option value='"+id+"'>"+name+"</option>");
                }
            }
        });
    });

});


//Display "Working" div as progress indicator
function showExecuteApiProgress(noOfTests, show,ms) {
    // Set default time for fade-in/fade-out
    if (ms == null) ms = 500;
    if (!$("#executing").length) 	$('body').append('<div id="executing"><img alt="Executing..." src="'+app_path_images+'downloading.gif">' +
        '<br>'+ processing_message1 +' '+noOfTests+' '+ processing_message2 +'</div>');
    if (!$("#fade").length) 	$('body').append('<div id="fade"></div>');
    if (show == 1) { // In Process
        $('#fade').addClass('black_overlay').show();
        $('#executing').center().fadeIn(ms);
    } else if (show == 0) { // Success
        setTimeout(function(){
            $("#fade").removeClass('black_overlay').hide();
            $("#executing").fadeOut(ms);
        },ms);
    }
}

function executeAPITests(noOfTests) {
    if (validateAPITestsForm()) {
        showExecuteApiProgress(noOfTests, 1);
        $("#apiTestResult").html('');
        var project_code = $("#sel-mycap-project").val();
        var par_code = $("#sel-mycap-participant").val();
        $.get(app_path_webroot+'ControlCenter/mycap_check_ajax.php', { project_code: project_code, par_code: par_code }, function(data){
            $("#apiTestResult").html(data);
            showExecuteApiProgress(noOfTests, 0);
        });
    }
}

function validateAPITestsForm() {
    var errors = "";
    if ($('#sel-mycap-project').val() == "") {
        errors = "&bull;&nbsp;&nbsp;"+ project_missing +"<br>";
    }
    if ($('#sel-mycap-participant').val() == "") {
        errors += "&bull;&nbsp;&nbsp;"+ participant_missing;
    }
    if (errors != "") {
        simpleDialog(errors, langErrorColon);
        return false;
    } else {
        return true;
    }
}
</script>
<?php

############################################################################################

//PAGE HEADER
print RCView::h4(array('style'=>'margin-top:0;'), "<img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:35px;position:relative;top:-2px;margin-right:1px;'>&nbsp;" . $lang['mycap_mobile_app_101'] . " " . $lang['control_center_443']);
print  "<p>".$lang['control_center_4853']."</p>";

$testApiUrl = MyCapConfiguration::ENDPOINT . '&action=' . MiscHandler::$actions['TEST_ENDPOINT'];

// Enable an auto-appearing button to allow users to scroll to top of page
outputButtonScrollToTop();

$test = MyCapConfiguration::$tests[0];
list($result, $http_status) = MyCapConfiguration::makeApiCall($test);

## Basic tests
print "<p style='padding-top:10px;color:#800000;font-weight:bold;font-family:verdana;font-size:13px;'>".$lang['control_center_4854']."</p>";

$opacityProp = '';
$disableAPI = '';

// Validate response
$validJsonResponse = false;
$resArr = json_decode($result, true);
if (json_last_error() === JSON_ERROR_NONE) {
    if ($resArr['success'] == true) {
        $validJsonResponse = true;
    }
}

if ($test['expectCode'] == $http_status) {
    // Check if response is a valid/correct JSON or not
    if ($validJsonResponse == false) {
        $testInitMsg = "<img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['control_center_4949'];
        print RCView::div(array('class' => 'red'), $testInitMsg);
        $opacityProp = 'opacity: 0.65;';
        $disableAPI = 'disabled';
    } else {
        $testInitMsg =  $lang['control_center_4855'].
            "<div class='mt-2 mb-3'><a href='".$testApiUrl."' target='_blank'>".$testApiUrl."</a></div>
                    <img src='" . APP_PATH_IMAGES . "tick.png'> ".$lang['control_center_4856'];
        print RCView::div(array('class' => 'darkgreen', 'style' => 'color:green;'), $testInitMsg);
    }
} else {
    $testInitMsg = "<img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['control_center_4857'];
    print RCView::div(array('class' => 'red'), $testInitMsg);
    $opacityProp = 'opacity: 0.65;';
    $disableAPI = 'disabled';
}

// Check if Server supports TLS version 1.2
try {
    $isTls1Point2Supported = MyCapConfiguration::isTLSVersionSupported();
    $tlsCheckError = '';
} catch (Exception $e) {
    $isTls1Point2Supported = false;
    $tlsCheckError = $e->getMessage();
}

if ($tlsCheckError) {
    $testInitMsg = "<img src='".APP_PATH_IMAGES."exclamation_orange.png'> ".$lang['control_center_4835'];
    print RCView::div(array('class' => 'yellow'), $testInitMsg);
} else if ($isTls1Point2Supported) {
    $testInitMsg = "<b>".$lang['control_center_4836']."</b><br><br><img src='" . APP_PATH_IMAGES . "tick.png'> ".$lang['control_center_4837'];
    print RCView::div(array('class' => 'darkgreen', 'style' => 'color:green;'), $testInitMsg);
} else {
    $testInitMsg = "<img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['control_center_4838']." <a href='https://redcap.vumc.org/community/post.php?id=51635' target='_blank'>".$lang['control_center_4839']."</a> ".$lang['control_center_4840'];
    print RCView::div(array('class' => 'red'), $testInitMsg);
}

// Test communication with MyCap Central
try {
    $canCommunicateMyCapCentral = MyCapConfiguration::checkMyCapCentralCommunication();
    $myCapCentralCheckError = '';
} catch (Exception $e) {
    $canCommunicateMyCapCentral = false;
    $myCapCentralCheckError = $e->getMessage();
}

if ($myCapCentralCheckError) {
    $testInitMsg = RCView::div(array(), "<img src='".APP_PATH_IMAGES."exclamation_orange.png'> <b>".RCView::tt('control_center_4940')."</b>");
    $testInitMsg .= RCView::div(array('style' => 'padding-left: 20px;'), RCView::tt('control_center_4941'));
    $testInitMsg .= RCView::div(array('style' => 'padding-left: 20px;'),
        RCView::ul(array(), implode('', array(
            RCView::li(array(), $lang['control_center_4942']),
            RCView::li(array(), $lang['control_center_4943'])
    ))));
    print RCView::div(array('class' => 'yellow'), $testInitMsg);
} else if ($canCommunicateMyCapCentral) {
    $testInitMsg = "<b>".$lang['control_center_4842']."</b><br><br><img src='" . APP_PATH_IMAGES . "tick.png'> ".$lang['control_center_4843'];
    print RCView::div(array('class' => 'darkgreen', 'style' => 'color:green;'), $testInitMsg);
} else {
    $testInitMsg = "<img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['control_center_4844'];
    print RCView::div(array('class' => 'red'), $testInitMsg);
}

/**
 * SECONDARY TESTS
 */
$allProjects = array('' => '--'.$lang['extres_39'].'--');
$sql = "SELECT p.project_id, p.app_title, m.code
        FROM redcap_projects p, redcap_mycap_projects m 
        WHERE m.project_id = p.project_id AND m.status = 1 AND p.date_deleted is NULL ORDER BY m.project_id";
$q = db_query($sql);
while ($row = db_fetch_assoc($q))
{
    $allProjects[$row['code']] = $row['app_title']. " [".$row['code']."] [PID ".$row['project_id']."]";
}

$allParticipants = array('' => $lang['mycap_mobile_app_485']);

$noOfTests = count(MyCapConfiguration::$tests);
print "<p style='padding-top:15px;color:#800000;font-weight:bold;font-family:verdana;font-size:13px;".$opacityProp."'>".$lang['control_center_4850']."</p>";

print '<div style="margin-bottom:20px;padding:10px 15px;border:1px solid #d0d0d0;background-color:#f5f5f5;max-width:800px;'.$opacityProp.'">
            <div id="user-search-criteria" class="browse-users-search-box">
                <i class="fas fa-laptop-code"></i>
                <b>'.$lang['control_center_4845'].'</b>
                <div style="margin-top:10px;">
                   '.$lang['control_center_4846'].'
                </div>
                <div style="margin: 10px 0px;">
                    <span style="margin-right:4px;font-weight:bold;">'.$lang['control_center_4847'].' </span>
                    '.RCView::select(array('name'=>"mycap_project_id", "id" => "sel-mycap-project", $disableAPI=>$disableAPI, 'class'=>'external-modules-input-element d-inline py-0 px-1 ms-1',
        'style'=>'height:24px;max-width:500px;'), $allProjects, '', 100).'
                </div>
                <div style="margin: 10px 0px;">
                    <span style="margin-right:4px;font-weight:bold;">'.$lang['control_center_4848'].' </span>
                    '.RCView::select(array('name'=>"mycap_par_id", "id" => "sel-mycap-participant", $disableAPI=>$disableAPI, 'class'=>'external-modules-input-element d-inline py-0 px-1 ms-1',
        'style'=>'height:24px;max-width:500px;'), $allParticipants).'
                </div>
                <div style="margin:20px 0 5px;">
                    <button class="btn btn-primaryrc btn-sm" style="font-size:13px;" '.$disableAPI.'="'.$disableAPI.'" onclick="executeAPITests('.$noOfTests.');">'.$lang['control_center_4849'].'</button>
                </div>
            </div>
        </div>';

print '<div id="apiTestResult"></div>';

include 'footer.php';