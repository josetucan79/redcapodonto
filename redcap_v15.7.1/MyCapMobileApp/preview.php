<?php

use Vanderbilt\REDCap\Classes\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\Page;
use Vanderbilt\REDCap\Classes\MyCap\Contact;

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
global $lang, $myCapProj;
$status = $msg = '';
# About Preview on iPhone
if (isset($_GET['section']) && $_GET['section'] == 'about')
{
    $pageObj = new Page();
    $pages = $pageObj->getAboutPagesSettings($_GET['pid']);
    $preview = '<div class="row">
                    <div class="col-xs-12 wrap-modal-slider">
                        <div id="pages">';

    $counter = 0;
    foreach ($pages as $page) {
        $counter++;

        if ($page['image_type'] == Page::IMAGETYPE_SYSTEM) {
            $imageSrc = APP_PATH_IMAGES.Page::$systemImageNameEnum[$page['system_image_name']].".png";
            $img = '<img src="'.$imageSrc.'" class="thumbnail"/>';
        } else {

            if ($page['custom_logo'] == '' || $page['custom_logo'] == 0) {
                $imageSrc = APP_PATH_IMAGES."intro_logo.png";
            } else {
                $imageSrc = APP_PATH_WEBROOT.'DataEntry/image_view.php?pid='.PROJECT_ID.'&doc_id_hash='.\Files::docIdHash($page['custom_logo']).'&id='.$page['custom_logo'];
            }
            $img = '<img src="'.$imageSrc.'" class="thumbnail"/>';
        }
        $preview .= '<div style="width: 100%;" class="about-page '.(($counter > 1) ? 'toggable' : '').'">
                        '.$img.'
                        <h3 style="width: 100%;" class="text-center">'.RCView::escape($page['page_title']).'</h3>';
        $preview .= '<p style="width:100%;" '.(($page['sub_type'] == Page::SUBTYPE_HOME) ? 'class=""' : '').'>';
        if (strlen($page['page_content']) < 400) {
            $preview .= nl2br(htmlentities($page['page_content']));
        } else {
            $short_text = substr($page['page_content'],0, 400).$lang['mycap_mobile_app_157'];
            $preview .= nl2br(htmlentities($short_text));
        }
        $preview .= '</p></div>';
    }

    $preview .= '</div></div></div>';
    $title = $lang['mycap_mobile_app_02'];

    print MyCap\MyCap::getPreviewTemplate($title, $preview);
} else if (isset($_GET['section']) && $_GET['section'] == 'contacts') {
    $contacts = Contact::getContacts($_GET['pid']);

    $preview = '<div class="tvc">';

    $counter = 0;
    foreach ($contacts as $contact) {
        $counter++;

        $preview .= '<div class="row">
                        <div class="col-xs-12 tvc-section-label">'.$contact['contact_header'].'</div>
                    </div>';
        if (!empty($contact['contact_title'])) {
            $preview .= '<div class="row">
                            <div class="col-xs-3 tvc-row-label">'.$lang['email_users_12'].'</div>
                            <div class="col-xs-9 tvc-row-data text-end">'.$contact['contact_title'].'</div>
                        </div>';
        }
        if (!empty($contact['phone_number'])) {
            $preview .= '<div class="row">
                            <div class="col-xs-3 tvc-row-label">'.$lang['design_89'].'</div>
                            <div class="col-xs-9 tvc-row-data text-end">'.$contact['phone_number'].'</div>
                        </div>';
        }
        if (!empty($contact['email'])) {
            $preview .= '<div class="row">
                            <div class="col-xs-3 tvc-row-label">'.$lang['global_33'].'</div>
                            <div class="col-xs-9 tvc-row-data text-end">'.$contact['email'].'</div>
                        </div>';
        }
        if (!empty($contact['website'])) {
            $preview .= '<div class="row">
                            <div class="col-xs-3 tvc-row-label">'.$lang['mycap_mobile_app_158'].'</div>
                            <div class="col-xs-9 tvc-row-data text-end">'.$contact['website'].'</div>
                        </div>';
        }
        if (!empty($contact['additional_info'])) {
            $preview .= '<div class="row">
                            <div class="col-xs-12 tvc-row-data">'.nl2br($contact['additional_info']).'</div>
                        </div>';
        }
    }
    $preview .= '</div>';
    $title = $lang['mycap_mobile_app_192'];

    print MyCap\MyCap::getPreviewTemplate($title, $preview);
}