<?php
use Vanderbilt\REDCap\Classes\MyCap\Page;
use Vanderbilt\REDCap\Classes\MyCap\Link;
use Vanderbilt\REDCap\Classes\MyCap\Contact;
use Vanderbilt\REDCap\Classes\MyCap\Theme;
use Vanderbilt\REDCap\Classes\MyCap\Participant;
use Vanderbilt\REDCap\Classes\MyCap\Message;
use Vanderbilt\REDCap\Classes\MyCap\SyncIssues;
use Vanderbilt\REDCap\Classes\MyCap\Notification;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
// If not using a type of project with mycap or not enabled MyCap at system-level, then don't allow user to use this page.
if (!$mycap_enabled_global || !$mycap_enabled) redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");

global $myCapProj;
$message="";
$message_text = array(
    'A'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_37'], // Page Added
    'U'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_38'], // Page Updated
    'D'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_39'], // Page Deleted
    'AL'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_67'], // Link Added
    'UL'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_68'], // Link Updated
    'DL'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_69'], // Link Deleted
    'ML'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_99'], // Link Moved
    'AC'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_77'], // Contact Added
    'UC'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_78'], // Contact Updated
    'DC'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_79'], // Contact Deleted
    'MC'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_98'], // Contact Moved
    'UT'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_90'], // Theme Updated
    'AA'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_430'], // Announcement Added
    'UA'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_431'], // Announcement Updated
    'DA'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_445'], // Announcement Deleted
    'NU'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_868'], // Notification Settings updated
    'NMU'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['mycap_mobile_app_868'], // Notification Settings updated
);

if (array_key_exists('message', $_REQUEST)) {
    $message = $message_text[$_REQUEST['message']];
}

if (!$user_rights['design'] && !$user_rights['mycap_participants']) {
    redirect(APP_PATH_WEBROOT."index.php?pid=".PROJECT_ID);
}

// If user is going to initialization tab but has init'd the project in the app, then take them to Dashboard instead
if (!isset($_GET['help']) && !isset($_GET['syncissues']) && !isset($_GET['participants']) && !isset($_GET['about']) && !isset($_GET['contacts'])
    && !isset($_GET['links']) && !isset($_GET['theme']) && !isset($_GET['messages']) && !isset($_GET['outbox']) && !isset($_GET['announcements'])
    && !isset($_GET['notification']) && !isset($_GET['msg_settings']))
{
    redirect(PAGE_FULL."?participants=1&pid=".PROJECT_ID);
}

// display the page
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// Add language used to MyCapProject.js
addLangToJS(array(
    'alerts_42',
    'designate_forms_13',
    'global_19',
    'global_53',
    'mycap_mobile_app_07',
    'mycap_mobile_app_08',
    'mycap_mobile_app_21',
    'mycap_mobile_app_22',
    'mycap_mobile_app_23',
    'mycap_mobile_app_25',
    'mycap_mobile_app_26',
    'mycap_mobile_app_33',
    'mycap_mobile_app_42',
    'mycap_mobile_app_43',
    'mycap_mobile_app_47',
    'mycap_mobile_app_54',
    'mycap_mobile_app_56',
    'mycap_mobile_app_57',
    'mycap_mobile_app_58',
    'mycap_mobile_app_63',
    'mycap_mobile_app_64',
    'mycap_mobile_app_65',
    'mycap_mobile_app_66',
    'mycap_mobile_app_72',
    'mycap_mobile_app_73',
    'mycap_mobile_app_74',
    'mycap_mobile_app_75',
    'mycap_mobile_app_76',
    'mycap_mobile_app_92',
    'mycap_mobile_app_95',
    'mycap_mobile_app_691',
    'mycap_mobile_app_693',
    'survey_605',
    'pub_085',
    'mycap_mobile_app_905','global_01'
));
loadJS('Libraries/biomp.js');
loadJS('MyCapProject.js');
loadCSS("MyCap.css");

// Reorder Page
if (isset($_SESSION['move_page_msg']) && !empty($_SESSION['move_page_msg'])) {
    $pageId = $_SESSION['focus_page_id'];
    $pageObj = new Page();
    $allPages = $pageObj->getAboutPagesSettings(PROJECT_ID);
    ?>
    <script type="text/javascript">
        simpleDialog('<?php echo js_escape($_SESSION['move_page_msg']) ?>', '<?php echo js_escape($lang['design_346']) ?>', null, 600, "scrollToAboutPage('<?=$_SESSION['focus_page_id']?>')");
    </script>
    <?php
    unset($_SESSION['move_page_msg']);
    unset($_SESSION['focus_page_id']);
}

$systemImagesList = Page::$systemImageNameEnum;
?>
<script type="text/javascript">
    var message = <?=json_encode($message)?>;
    var systemImages = <?php echo json_encode($systemImagesList); ?>;
    var pleaseSelectPage = '<?php echo js_escape($lang['mycap_mobile_app_34']) ?>';
    var langNewFormRights2 = '<?php echo js_escape($lang['global_79']) ?>';
</script>
<?php

// Publish new version help - hidden dialog
print RCView::simpleDialog($lang['mycap_mobile_app_96'].$lang['mycap_mobile_app_930'], $lang['mycap_mobile_app_92'], 'publishVersionDialog');

// Version published success message box
print "<div class='versionPublishMsg darkgreen' style='max-width:600px;text-align:center; display:none;'><img src='".APP_PATH_IMAGES."tick.png'> ".$lang['mycap_mobile_app_93']."</div>";

## ABOUT PAGES LISTING
if (isset($_GET['about']) && $user_rights['design'])
{
    // Online Designer tabs
    include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
    // Check if any notices need to be displayed regarding Draft Mode
    include APP_PATH_DOCROOT . "Design/draft_mode_notice.php";
    // Fix About pages images issue
    Page::fixAboutImages(PROJECT_ID);
    print Message::renderAppDesignTabs();
    print Page::renderAddEditForm();
    // MOVE PAGE DIALOG POP-UP
	print '<div id="move_page_popup" title="'.js_escape2($lang['mycap_mobile_app_31']).'" style="display: none;"></div>';

	print '<div id="previewModal" class="modal fade" role="dialog" data-backdrop="static" data-keyboard="true">
                <div align="center">
                    <button type="button" data-dismiss="modal" align="center">
                        <i style="font-size: 30px;" class="fa fa-window-close"></i>
                    </button>
                </div>
                <div class="modal-dialog phone" style="pointer-events: auto;" align="center">
                    <div id="previewContent" class="center-block"></div>
                </div>
            </div>';

    $pageObj = new Page();
    print $pageObj->renderAboutPagesSetupPage();
}
## LINKS LISTING
elseif (isset($_GET['links']) && $user_rights['design'])
{
    loadJS('Libraries/jquery_tablednd.js');
    // Online Designer tabs
    include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
    // Check if any notices need to be displayed regarding Draft Mode
    include APP_PATH_DOCROOT . "Design/draft_mode_notice.php";
    print Message::renderAppDesignTabs();
    // Add/Edit Link form
    print Link::renderAddEditForm();
    // Link Listing
    print Link::renderLinksSetupPage();
}
## CONTACTS LISTING
elseif (isset($_GET['contacts']) && $user_rights['design'])
{
    loadJS('Libraries/jquery_tablednd.js');
    // Online Designer tabs
    include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
    // Check if any notices need to be displayed regarding Draft Mode
    include APP_PATH_DOCROOT . "Design/draft_mode_notice.php";
    print Message::renderAppDesignTabs();
    // Add/Edit Contacts form
    print Contact::renderAddEditForm();

    print '<div id="previewModal" class="modal fade" role="dialog" data-backdrop="static" data-keyboard="true">
                <div align="center">
                    <button type="button" data-dismiss="modal" align="center">
                        <i style="font-size: 30px;" class="fa fa-window-close"></i>
                    </button>
                </div>
                <div class="modal-dialog phone" align="center">
                    <div id="previewContent" class="center-block"></div>
                </div>                
            </div>';
    // Contacts listing
    print Contact::renderContactsSetupPage();
}
## THEME
elseif (isset($_GET['theme']) && $user_rights['design'])
{
    loadJS('Libraries/bootstrap.tinycolor.min.js');
    loadJS('Libraries/bootstrap.colorpickersliders.js');
    loadCSS("bootstrap.colorpickersliders.css");
    // Online Designer tabs
    include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
    // Check if any notices need to be displayed regarding Draft Mode
    include APP_PATH_DOCROOT . "Design/draft_mode_notice.php";
    print Message::renderAppDesignTabs();
    print Theme::renderThemeSetupPage();
}
## PARTICIPANTS
elseif (isset($_GET['participants']) && $user_rights['mycap_participants'])
{
    addLangToJS(array('mycap_mobile_app_363', 'survey_360', 'mycap_mobile_app_369', 'mycap_mobile_app_370', 'mycap_mobile_app_371', 'mycap_mobile_app_372',
        'mycap_mobile_app_398', 'mycap_mobile_app_399', 'global_01', 'mycap_mobile_app_438', 'mycap_mobile_app_439', 'mycap_mobile_app_440',
        'survey_180', 'global_79', 'mycap_mobile_app_901', 'mycap_mobile_app_902', 'mycap_mobile_app_935', 'mycap_mobile_app_936', 'control_center_153', 'survey_152', 'setup_08', 'mycap_mobile_app_937'));
    loadJS('Libraries/clipboard.js');
    renderPageTitle("<img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:35px;position:relative;top:-2px;margin-right:1px;'>&nbsp;" . $lang['mycap_mobile_app_628']);
    print $myCapProj->renderTabs();
	print '<div id="partlist_outerdiv" style="margin-bottom:20px;">';
    print Participant::renderParticipantList();
	print '</div>';
}
## INBOX MESSAGES
elseif (isset($_GET['messages']) && $user_rights['mycap_participants'])
{
    renderPageTitle("<img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:35px;position:relative;top:-2px;margin-right:1px;'>&nbsp;" . $lang['mycap_mobile_app_628']);
    print $myCapProj->renderTabs();
    addLangToJS(array( 'global_01', 'mycap_mobile_app_438', 'mycap_mobile_app_439', 'mycap_mobile_app_440', 'survey_180', 'design_100', 'design_99'));
    print Message::renderMessagesTabs();
    print Message::renderInboxMessagesList();
}
## OUTBOX MESSAGES
elseif (isset($_GET['outbox']) && $user_rights['mycap_participants'])
{
    renderPageTitle("<img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:35px;position:relative;top:-2px;margin-right:1px;'>&nbsp;" . $lang['mycap_mobile_app_628']);
    print $myCapProj->renderTabs();
    addLangToJS(array( 'global_01', 'mycap_mobile_app_438', 'mycap_mobile_app_439', 'mycap_mobile_app_440', 'survey_180'));
    print Message::renderMessagesTabs();
    print Message::renderOutboxMessagesList();
}
## ANNOUNCEMENTS
elseif (isset($_GET['announcements']) && $user_rights['mycap_participants'])
{
    renderPageTitle("<img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:35px;position:relative;top:-2px;margin-right:1px;'>&nbsp;" . $lang['mycap_mobile_app_628']);
    print $myCapProj->renderTabs();
    print Message::renderMessagesTabs();
    addLangToJS(array('mycap_mobile_app_422', 'mycap_mobile_app_423', 'mycap_mobile_app_428', 'mycap_mobile_app_429', 'mycap_mobile_app_443', 'mycap_mobile_app_444'));
    // Add/Edit Announcement form
    print Message::renderAddEditAnnouncementForm();
    print Message::renderAnnouncementList();
}
## ANNOUNCEMENTS
elseif (isset($_GET['notification']) && $user_rights['mycap_participants'])
{
    // Online Designer tabs
    include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
    // Check if any notices need to be displayed regarding Draft Mode
    include APP_PATH_DOCROOT . "Design/draft_mode_notice.php";
    print Message::renderAppDesignTabs();
    addLangToJS(array( 'global_01', 'mycap_mobile_app_871'));
    print Notification::renderNotificationSettingsPage();
}
## SYNC ISSUES LISTING
elseif (isset($_GET['syncissues']) && $user_rights['mycap_participants'])
{
    renderPageTitle("<img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:35px;position:relative;top:-2px;margin-right:1px;'>&nbsp;" . $lang['mycap_mobile_app_628']);
    print $myCapProj->renderTabs();
    addLangToJS(array('pub_085'));
    print SyncIssues::renderSyncIssues();
}
## Help
elseif (isset($_GET['help']) && $user_rights['mycap_participants'])
{
    renderPageTitle("<img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:35px;position:relative;top:-2px;margin-right:1px;'>&nbsp;" . $lang['mycap_mobile_app_628']);
    print $myCapProj->renderTabs();
    print RCView::div(array('class' => 'mt-5 pt-5 fs15', 'style'=>''),
    $lang['mycap_mobile_app_686'] . " " . $lang['mycap_mobile_app_687'] .
        RCView::a(['href' => APP_PATH_WEBROOT."Resources/misc/mycap_help.pdf", 'target' => '_blank', 'class'=>'text-dangerrc fs15 ms-2', 'style' => 'text-decoration:underline'],
            '<i class="fa-solid fa-file-pdf me-1 fs16"></i>'.$lang['mycap_mobile_app_688']
        )
    );
}
## MESSAGE NOTIFICATIONS
elseif (isset($_GET['msg_settings']) && $user_rights['mycap_participants'])
{
    renderPageTitle("<img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:35px;position:relative;top:-2px;margin-right:1px;'>&nbsp;" . $lang['mycap_mobile_app_628']);
    print $myCapProj->renderTabs();
    print Message::renderMessagesTabs();
    // Render Message notification settings page
    print Message::renderNotificationSettingsPage();
    addLangToJS(['mycap_mobile_app_429', 'survey_515', 'mycap_mobile_app_979', 'mycap_mobile_app_980', 'leftparen', 'rightparen', 'mycap_mobile_app_981', 'mycap_mobile_app_982']);
} else {
    redirect(APP_PATH_WEBROOT."index.php?pid=".PROJECT_ID);
}
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';