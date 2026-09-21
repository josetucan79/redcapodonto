<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use RCView;
class Page
{

    const TYPE_INTRO = '.Intro';
    const TYPE_TASKINSTRUCTIONSTEP = '.TaskInstructionStep';
    const TYPE_TASKCOMPLETIONSTEP = '.TaskCompletionStep';
    const SUBTYPE_HOME = '.Home';
    const SUBTYPE_CUSTOM = '.Custom';
    const IMAGETYPE_SYSTEM = '.System';
    const IMAGETYPE_CUSTOM = '.Custom';
    const DEFAULT_DESCRIPTION = 'Short description';

    /**
     * @var array Enumerates system images
     */
    public static $systemImageNameEnum = [
        '.Info' => 'system_info',
        '.Lock' => 'system_lock'
    ];
    public $aboutpages;

    /**
     * Returns human readable string for the given format
     *
     * @param string $format
     * @return string
     */
    public static function toString($format)
    {
        global $lang;
        switch ($format) {
            case 'system_info':
                $retVal = $lang['mycap_mobile_app_156'];
                break;
            case 'system_lock':
                $retVal = $lang['form_renderer_18'];
                break;
            default:
                $retVal = 'Invalid Format';
                break;
        }
        return $retVal;
    }

    /**
     * Render Add/Edit Pages Forms
     *
     * @return string
     */
    public static function renderAddEditForm() {
        global $lang, $user_rights, $Proj;
        // Get array of DAGs
        $dags = $Proj->getGroups();

        $systemImagesList = self::$systemImageNameEnum;
        $systemImages = array_map(function($val) {return self::toString($val);}, $systemImagesList);

        $form = '<form class="form-horizontal" action="" method="post" id="savePage" enctype="multipart/form-data">
                <div class="modal fade" id="external-modules-configure-modal" name="external-modules-configure-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true">
                    <div class="modal-dialog" role="document" style="max-width: 950px !important;">
                        <div class="modal-content">
                            <div class="modal-header py-2">
                                <button type="button" class="py-2 close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                <h4 id="add-edit-title-text" class="modal-title mc-form-control-custom"></h4>
                            </div>
                            <div class="modal-body pt-2">
                                <div id="errMsgContainerModal" class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;"></div>
                                <div class="mb-2">
                                    '.$lang['mycap_mobile_app_09'].'<span id="info-page-msg" style="display: none;"> '.$lang['mycap_mobile_app_152'].'</span>
                                    <div id="home-page-msg" style="padding:20px 0 5px;color:green;display: none;"><i class="fas fa-lock"></i> '.$lang['survey_105'] . ' ' . $lang['mycap_mobile_app_41'] . '</div>
                                </div>
                                <table class="mc_code_modal_table" id="code_modal_table_update">';
        if ($user_rights['group_id'] == '' && !empty($dags) && \Design::isDraftPreview() == false) {
            $form .= ' <tr class="mc-form-control-custom">
                            <td colspan="2">
                                <div class="mc-form-control-custom-title clearfix">
                                    <div class="boldish fs14" style="margin-top:2px;"><i class="fas fa-users"></i> '.RCView::tt('data_entry_564').'</div>
                                </div>
                            </td>
                        </tr>
                        <tr class="mc-form-control-custom" field="">
                            <td class="align-text-top pt-1 pe-1">
                                <label class="text-nowrap boldish">'.RCView::tt('data_entry_323').RCView::tt('colon').'</label>
                            </td>
                            <td class="external-modules-input-td">
                                '.RCView::select(array('name' => 'dag_id', 'id'=>'dag_id', 'class'=>'x-form-field', 'style'=>'max-width:500px;font-size:14px;'), array(''=>$lang['data_access_groups_ajax_23'])+$dags).'
                                <div class="requiredlabel" style="color:#C00000;">'.RCView::tt('mycap_mobile_app_957').'</div>
                            </td>
                        </tr>';
        }

        $form .= '<tr class="mc-form-control-custom">
                                        <td colspan="2">
                                            <div class="mc-form-control-custom-title clearfix">
                                                <div class="boldish fs14" style="margin-top:2px;"><i class="fas fa-info-circle"></i> '.$lang['mycap_mobile_app_10'].'</div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="mc-form-control-custom" field="">
                                        <td class="align-text-top pt-1 pe-1">
                                            <label class="text-nowrap boldish">'.$lang['mycap_mobile_app_11'].'</label><div class="requiredlabel p-0">* '.$lang['data_entry_39'].'</div>
                                        </td>
                                        <td class="external-modules-input-td">
                                            <input type="text" id="page_title" name="page_title" placeholder="'.$lang['training_res_05'].'" class="d-inline" style="font-size:15px;width:500px;" maxlength="100">
                                        </td>
                                    </tr>
                                    <tr class="mc-form-control-custom" field="">
                                        <td class="align-text-top pt-1 pe-1">
                                            <label class="text-nowrap boldish">'.$lang['mycap_mobile_app_12'].'</label><div class="requiredlabel p-0">* '.$lang['data_entry_39'].'</div>
                                        </td>
                                        <td class="external-modules-input-td">
                                            <textarea id="page_content" name="page_content" placeholder="'.$lang['mycap_mobile_app_126'].'" class="external-modules-input-element" style="max-width:95%;height:100px;"></textarea>
                                        </td>
                                    </tr>
                                    <tr class="mc-form-control-custom">
                                        <td colspan="2">
                                            <div class="mc-form-control-custom-title clearfix">
                                                <div class="boldish fs14" style="margin-top:2px;"><i class="fas fa-image"></i> '.$lang['mycap_mobile_app_13'].'</div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="mc-form-control-custom" field="">
                                        <td class="align-text-top pt-1 pe-1">
                                            <label class="text-nowrap boldish">'.$lang['mycap_mobile_app_14'].'</label>
                                        </td>
                                        <td class="external-modules-input-td pb-3">
                                            <div class="clearfix">
                                                <div class="me-4" id="type-system">
                                                    <input type="radio" id="image-type-system" name="image_type" value=".System" style="height:20px;" class="external-modules-input-element align-middle" onclick="setImageLayout($(this).val(), \'\', \'\', \'\');">
                                                    <label class="m-0 align-middle"><i class="fas fa-folder-plus"></i> '.$lang['mycap_mobile_app_15'].'</label>
                                                </div>
                                                <div class="me-4 d-inline">
                                                    <input type="radio" id="image-type-custom" name="image_type" value=".Custom" style="height:20px;" class="external-modules-input-element align-middle" onclick="setImageLayout($(this).val(), \'\', \'\', \'\');">
                                                    <label class="m-0 align-middle"><i class="fas fa-image"></i> '.$lang['mycap_mobile_app_16'].'</label>
                                                </div>
                                                <div id="home-page-note" class="me-4" style="color:#555; font-size:12px; display:none;">
                                                     '.$lang['survey_105'] . ' ' . $lang['mycap_mobile_app_41'] . '
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="mc-form-control-custom" id="systemImageRow" style="display: none;">
                                        <td class="align-text-top pt-1 pe-1">
                                            <label class="text-nowrap boldish">'.$lang['mycap_mobile_app_17'].'</label>
                                        </td>
                                        <td class="external-modules-input-td pb-3">
                                            '.RCView::select(array('name'=>"system_image", 'id'=>"system_image", 'class'=>'external-modules-input-element d-inline py-0 px-1 me-1 fs12', 'style' => 'height:24px;width: 110px;max-width: 110px;'), $systemImages, "").'
                                            <div id="image_div" style=";color:#555;font-size:12px;display: none;">
                                                <img src="'.APP_PATH_WEBROOT.'" style="max-width:400px;">
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="mc-form-control-custom" id="systemImageRow" style="display: none;">
                                        <td class="align-text-top pt-1 pe-1">
                                        </td>
                                        <td class="external-modules-input-td pb-3">
                                            
                                        </td>
                                    </tr>
                                    <tr class="mc-form-control-custom" id="customImageRow" style="display: none;">
                                        <td class="align-text-top pt-1 pe-1">
                                            <label class="text-nowrap boldish">'.$lang['mycap_mobile_app_18'].'</label>
                                            <div class="requiredlabel p-0">* '.$lang['data_entry_39'].'</div>
                                        </td>
                                        <td class="external-modules-input-td pb-3">
                                        <input type="hidden" name="old_image" id="old_image" value="">
                                            <div id="old_image_div" style="color:#555;font-size:12px;display:none;">
                                                '.$lang['mycap_mobile_app_20'].' &nbsp;
                                                <a href="javascript:;" class="remove-image" style="font-size:12px;color:#A00000;text-decoration:none;">[X] '.$lang['mycap_mobile_app_40'].'</a>
                                                <br><br>
                                                <img src="" alt="'.js_escape($lang['survey_1140']).'" title="'.js_escape($lang['survey_1140']).'" style="max-width:250px;">
                                            </div>
                                            <div id="new_image_div" style="color:#555;font-size:12px;display:">
                                                <div class="mb-1 font-weight-bold fs13">'.$lang['mycap_mobile_app_19'].'</div>
                                                <input type="file" name="logo" id="logo_id" size="30" style="font-size:13px;" onchange="checkLogo(this.value);">
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            <input type="hidden" value="" id="index_modal_update" name="index_modal_update">
                            </div>
        
                            <div class="modal-footer">
                                <button class="btn btn-rcgreen" id="btnModalsavePage">'.$lang['designate_forms_13'].'</button>
                                <button class="btn btn-defaultrc" id="btnClosePageModal" data-dismiss="modal" onclick="return false;">'.$lang['global_53'].'</button>
                            </div>
                        </div>
                    </div>
                </div>
           </form>';

        return $form;
    }

    /**
     * Render Page listing page
     *
     * @return string
     */
    public function renderAboutPagesSetupPage() {
        global $lang, $Proj;
        // Loop through all about pages
        $number = 0;
        $pages_html = $form_html = "";

        $allPages = $this->getAboutPagesSettings(PROJECT_ID);
        $noOfPages = count($allPages);

        renderPageTitle("<div style='float:left;'>{$lang['mycap_mobile_app_02']}</div><br>");

        print RCView::p(array('class'=>'mt-0 mb-2', 'style'=>'max-width:900px;'), $lang['mycap_mobile_app_06']);

        print MyCap::getMessageContainers();

        // Add "Modify Project Title in app" button to place at about section
        print '<div class="mt-4 mb-5">
                    <button id="addNewAbout" type="button" class="btn btn-sm btn-rcgreen ms-2" onclick="editAboutPage(\'\', \'\', \'\');"><i class="fas fa-plus"></i> '.$lang['mycap_mobile_app_97'].'</button>
                    <button id="pagesPreview" type="button" class="btn btn-sm btn-defaultrc ms-3" data-toggle="modal" data-target="#previewModal"><i class="fa-solid fa-mobile-screen-button"></i> '.$lang['design_699'].'</button>
                    <button class="btn btn-defaultrc btn-xs fs13 ms-5" onclick="dialogModifyAppProjectTitle();"><i class="fa-solid fa-edit"></i> '.$lang['mycap_mobile_app_691'].'</button>
               </div>';

        if (!empty($allPages)) {
            $pages_html .= '<table class="table table-bordered table-hover dataTable no-footer" id="customizedPagesPreview" style="width: 70%; table-layout: fixed;">';
            foreach ($allPages as $pageId => $attr)
            {
                $homePageNote = '';
                $icon_fa_class = 'fa-info-circle';
                if ($number == 0) {
                    $icon_fa_class = 'fa-home';
                    $homePageNote = '<div style="float: left; color: green;margin-bottom: 0px !important;" class="fs11 mb-3 mx-2"><i class="fas fa-lock"></i> '.$lang['survey_105'].' '.$lang['mycap_mobile_app_41'].'</div>';
                }
                foreach ($attr as $configKey => $configVal) {
                    if ($configKey == 'custom_logo') {
                        if (is_null($configVal))  $configVal = 0;
                        if ($attr['custom_logo'] == '' || $configVal == 0) {
                            $imageSrc = APP_PATH_IMAGES."intro_logo.png";
                        } else {
                            $imageSrc = APP_PATH_WEBROOT.'DataEntry/image_view.php?pid='.PROJECT_ID.'&doc_id_hash='.\Files::docIdHash($configVal).'&id='.$configVal;
                        }
                        $info_modal[$number]['imgSrc'] = $imageSrc;
                    }
                    // Store values in array to convert to JSON to use when loading the dialog
                    $info_modal[$number][str_replace("_", "-", $configKey)] = $configVal . "";
                }

                $dag_name = '';
                if ($attr['dag_id'] != null)     $dag_name = $Proj->getGroups($attr['dag_id']);

                if (strlen($attr['page_content']) < 400) {
                    $content = nl2br(htmlentities($attr['page_content']));
                } else {
                    $short_text = substr($attr['page_content'],0, 400);
                    $content = nl2br(htmlentities($short_text)).'<span style="color: #888;">'.$lang['mycap_mobile_app_157'].'</span>';
                }
                $move_page_link = $remove_page_link = '';
                if ($noOfPages > 2 && $number > 0) {
                    // show move about page link only when there are atleast 3 pages and move is not available for first home page
                    $move_page_link = '<button type="button" class="btn btn-link fs13 py-1 ps-1 pe-2" onclick="moveAboutPage('.$pageId.'); return true;">
                                            <i class="fas fa-arrows-alt"></i> '.$lang['design_172'].'
                                        </button>';
                }
                if ($number > 0) {
                    // Skip first page as home page can not be deleted
                    $remove_page_link = '<button type="button" class="btn btn-link fs13 py-1 ps-1 pe-2" onclick="deleteAboutPage('.$pageId.',\''.$attr['page_title'].'\');return true;">
                                            <i class="fas fa-times"></i> '.$lang['design_170'].'
                                        </button>';
                }

                $pageTitle = (trim($attr['page_title']) == '') ? '' : $lang['colon'].'<span class="font-weight-normal ms-1">'.RCView::escape($attr['page_title']).'</span>';
                $form_html = '<div class="clearfix" style="margin-left: -11px;">
                                <div style="max-width:340px;" class="card-header page-num-box float-start text-truncate"><i class="fas '.$icon_fa_class.' fs13" style="margin-right:5px;"></i>'.$lang['mycap_mobile_app_02'].' #'.($number+1).$pageTitle.'</div>
                                <div class="btn-group nowrap float-start mb-1 ms-2" role="group">
                                    <button type="button" class="btn btn-link fs13 py-1 ps-1 pe-2" onclick="__rcfunc_editAbout_pageRow'.$number.'();">
                                        <i class="fas fa-pencil-alt"></i> '.$lang['global_27'].'
                                    </button>                                                            
                                    '.$remove_page_link.'
                                    '.$move_page_link.'
                                </div>
                                <div class="nowrap py-1 ps-1 pe-2" style="color:#008000; float: right;">'.$dag_name.'</div>
                            </div>';
                $form_html .= "<script type=\"text/javascript\">function __rcfunc_editAbout_pageRow{$number}(){ editAboutPage(".json_encode($info_modal[$number]).",'".$pageId."',".$number.") }</script>";

                // Output row
                $pages_html .= "<tr id='page_".$pageId."' class='".((($number+1)%2) == 0 ? 'even' : 'odd')."'>";
                $pages_html .= "<td class='pt-0 pb-4' style='border-right:0;' data-order='".$number."'>
                                ".$form_html."
                                ".$homePageNote."
                                <div class='clear'></div>
                                <div class='card mt-3'>
                                    <div class='card-body p-2'>".$content."</div>
                                </div>
                            </td>";
                if ($attr['image_type'] == self::IMAGETYPE_SYSTEM) {
                    $imageSrc = APP_PATH_IMAGES.self::$systemImageNameEnum[$attr['system_image_name']].".png";
                }
                $pages_html .= '</td>
                                <td class="pt-3 pb-4" style="width:200px;border-left:0;">
                                    <div class="card" style="height: 200px;">
                                        <div class="card-header bg-light py-1 px-3 clearfix" style="color:#004085;background-color:#d5e3f3 !important;">
                                            <i class="fas fa-image"></i> '.$lang['mycap_mobile_app_24'].'
                                        </div>
                                        <div class="card-body p-0" style="text-align: center; margin: 5px;">
                                            <img style="max-width: 150px;"  src="'.$imageSrc.'" class="thumbnail">                                                                  
                                        </div>
                                    </div>
                                </td>';
                $pages_html .= "</tr>";
                $number++;
            }
            $pages_html .= "</table>";
        }

        return $pages_html;
    }

    /**
     * Return all links for project
     *
     * @param int $projectId
     * @return array
     */
    public function getAboutPagesSettings($projectId)
    {
        if(!isset($projectId) && defined('PROJECT_ID')){
            $projectId = PROJECT_ID;
        }
        // If we already have the structure, return it
        if (!isset($this->aboutpages[$projectId])) {
            // Return values if row exists
            $sql = "SELECT * FROM redcap_mycap_aboutpages WHERE project_id = " . $projectId ." ORDER BY page_order, page_id";
            $q = db_query($sql);
            $this->aboutpages[$projectId] = array();
            while ($row = db_fetch_assoc($q)) {
                unset($row['project_id']);
                $this->aboutpages[$projectId][$row['page_id']] = $row;
            }
        }
        // Check the order of the alerts, if required reorder alerts
        $this->checkOrder($this->aboutpages[$projectId]);
        return $this->aboutpages[$projectId];
    }

    /**
     * Checks for errors in the page order of all pages (in case their numbering gets off)
     *
     * @param array $pages
     * @return boolean
     */
    public function checkOrder($pages)
    {
        // Store the sum of the page_order's and count of how many there are
        $sum   = 0;
        $count = 0;
        // Loop through existing resources
        foreach ($pages as $page_id=>$attr)
        {
            // Ignore pre-defined rules
            if (!is_numeric($page_id)) continue;
            // Add to sum
            $sum += $attr['page_order'] * 1;
            // Increment count
            $count++;
        }
        // Now perform check (use simple math method)
        if ($count * ($count + 1) / 2 != $sum)
        {
            // Out of order, so reorder
            $this->reorder($pages);
        }
    }

    /**
     * Reset the order of the pages for page_order in the table
     *
     * @param array $pages
     * @return void
     */
    public function reorder($pages)
    {
        // Initial value
        $order = 1;
        // Loop through existing resources
        foreach (array_keys($pages) as $page_id)
        {
            // Ignore pre-defined rules
            if (!is_numeric($page_id)) continue;
            $projectId = $this->getPageProjectId($page_id);
            // Save to table
            $sql = "UPDATE redcap_mycap_aboutpages SET page_order = $order WHERE project_id = " . $projectId . " AND page_id = $page_id";
            $q = db_query($sql);
            // Increment the order
            $order++;
        }
    }

    /**
     * Get the project id for the page
     *
     * @param int $pageId
     * @return array
     */
    public function getPageProjectId($pageId)
    {
        if (!isinteger($pageId)) return null;
        $sql = "SELECT project_id FROM redcap_mycap_aboutpages WHERE page_id = $pageId";
        $q = db_query($sql);
        return db_result($q, 0);
    }

    /**
     * Create Zip file for all about custom images
     *
     * @param int $projectId
     * @return array
     */
    public static function createAboutImagesZip($projectId, $autoAddProjectFiles=false) {
        global $edoc_storage_option, $lang;

        // Make sure server has ZipArchive ability (i.e. is on PHP 5.2.0+)
        if (!\Files::hasZipArchive()) {
            exit('ERROR: ZipArchive is not installed. It must be installed to use this feature.');
        }

        // Set paths, etc.
        ## Google Cloud Storage doesn't allow zipping of files, must be done in system temp
        if($edoc_storage_option == '3') {
            $target_zip = sys_get_temp_dir() . "/ImagePack{$projectId}".".zip";
        }
        else {
            $target_zip = APP_PATH_TEMP . "ImagePack{$projectId}".".zip";
        }

        $edocs = array();
        $sql = "SELECT custom_logo FROM redcap_mycap_aboutpages WHERE project_id = '".$projectId."' AND image_type = '".self::IMAGETYPE_CUSTOM."'";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q))
        {
            // Add edoc_id to array
            if (is_numeric($row['custom_logo'])) {
                $edocs[] = $row['custom_logo'];
            }
        }

        // Auto-add project file if we have none but need them (use intro_logo.png)
        if (empty($edocs) && $autoAddProjectFiles) {
            $tempPng = APP_PATH_TEMP . date('YmdHis') . "_mycap_projectfile_" . substr(sha1(rand()), 0, 6) . ".png";
            file_put_contents($tempPng, file_get_contents(APP_PATH_DOCROOT."Resources".DS."images".DS."intro_logo.png"));
            $edocs[] = $edoc_id = \REDCap::storeFile($tempPng, $projectId);
        }

        if (!empty($edocs)) {
            ## CREATE OUTPUT ZIP FILE AND INDEX
            if (is_file($target_zip)) unlink($target_zip);
            // Create ZipArchive object
            $zip = new \ZipArchive;
            // Start writing to zip file
            if ($zip->open($target_zip, \ZipArchive::CREATE) === TRUE)
            {
                foreach ($edocs as $docId)
                {
                    $fileAttr = \Files::getEdocContentsAttributes($docId);
                    if (empty($fileAttr)) continue;
                    list ($mimeType, $docName, $fileContent) = $fileAttr;
                    // Get files stored name
                    $sql = "SELECT stored_name FROM redcap_edocs_metadata WHERE doc_id = ".db_escape($docId);
                    $q = db_query($sql);
                    if (db_num_rows($q)) {
                        // Use existing hash
                        $storedName = db_result($q, 0);
                        $zip->addFromString($storedName, $fileContent);
                    }
                }
                // Done adding to zip file
                $zip->close();
            }
            ## ERROR
            else
            {
                exit("ERROR: Unable to create ZIP archive at $target_zip");
            }

            $name = "ImagePack{$projectId}.zip";
            $edoc_id = \REDCap::storeFile($target_zip, $projectId);

            $myCapProj = new MyCap($projectId);
            // Load Project values to access $myCapProj->project['code'] in below SQL queries - Copy project, Create via XML upload
            $myCapProj->loadMyCapProjectValues();

            $sql = "SELECT doc_id FROM redcap_mycap_projectfiles WHERE project_code = '".$myCapProj->project['code']."' AND category = '3'";
            $edoc = db_result(db_query($sql), 0);

            // Delete the file
            \Files::deleteFileByDocId($edoc, $projectId);

            // Get Project code by Id
            $sql = "DELETE FROM redcap_mycap_projectfiles WHERE project_code = '".$myCapProj->project['code']."' AND category = '3'";
            db_query($sql);

            $sql = "INSERT INTO redcap_mycap_projectfiles (project_code, doc_id, `name`, category) VALUES
                    ('".$myCapProj->project['code']."', '".$edoc_id."', '".$name."', '3')";

            db_query($sql);
        }
    }

    /**
     * Upload file from images folder to edoc
     * @param string $file
     * @return integer $edoc_id
     */
    public static function uploadDefaultImageFile($projectId) {
        $default_image_path = APP_PATH_DOCROOT.'Resources'.DS.'images'.DS.'intro_logo.png';

        $filename_tmp = date('YmdHis') . "_pid" . $projectId . "_" . generateRandomHash(6) . getFileExt($default_image_path, true);

        $copied = copy($default_image_path, APP_PATH_TEMP.$filename_tmp);
        // Set file attributes as if just uploaded
        $fileAttr = array('name'=> basename($default_image_path),
            'type'=> \Files::mime_content_type($default_image_path),
            'size'=>filesize(APP_PATH_TEMP.$filename_tmp),
            'tmp_name'=>APP_PATH_TEMP.$filename_tmp);
        $edoc_id = \Files::uploadFile($fileAttr, $projectId);

        return $edoc_id;
    }

    /**
     * Upload system image file from images folder to edoc
     * @param interger $projectId
     * @param string $type
     * @return integer $edoc_id
     */
    public static function uploadSystemImageFile($projectId, $type) {
        $image_name = self::$systemImageNameEnum[$type].".png";
        $default_image_path = APP_PATH_DOCROOT.'Resources'.DS.'images'.DS.$image_name;

        $filename_tmp = date('YmdHis') . "_pid" . $projectId . "_" . generateRandomHash(6) . getFileExt($default_image_path, true);

        $copied = copy($default_image_path, APP_PATH_TEMP.$filename_tmp);
        // Set file attributes as if just uploaded
        $fileAttr = array('name'=> basename($default_image_path),
            'type'=> \Files::mime_content_type($default_image_path),
            'size'=>filesize(APP_PATH_TEMP.$filename_tmp),
            'tmp_name'=>APP_PATH_TEMP.$filename_tmp);
        $edoc_id = \Files::uploadFile($fileAttr, $projectId);

        return $edoc_id;
    }

    /**
     * Fix about images if anything incorrect after migration or project creation
     *
     * @param int $projectId
     * @return void
     */
    public static function fixAboutImages($projectId)
    {
        $sql = "SELECT page_id, image_type, system_image_name FROM redcap_mycap_aboutpages WHERE project_id = '" . $projectId . "' AND sub_type = '" . self::SUBTYPE_HOME . "'";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Check if home page image is not custom type, set to custom with default intro image
            if ($row['image_type'] != self::IMAGETYPE_CUSTOM) {
                $new_edoc_id = self::uploadSystemImageFile($projectId, $row['system_image_name']);
                $imageType = self::IMAGETYPE_CUSTOM;
                $sql = "UPDATE redcap_mycap_aboutpages SET custom_logo =" . checkNull($new_edoc_id) . ", image_type ='".db_escape($imageType)."', system_image_name = '' WHERE project_id = " . $projectId . " AND page_id = '" . db_escape($row['page_id']) . "'";
                db_query($sql);
            }
        }

        $error_pages = array();
        // Check if images of type custom have valid doc_id set otherwise set to default intro image
        $sql = "SELECT page_id, custom_logo FROM redcap_mycap_aboutpages WHERE project_id = '" . $projectId . "' AND image_type = '" . self::IMAGETYPE_CUSTOM . "'";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            if (!is_numeric($row['custom_logo']) || $row['custom_logo'] == 0 || $row['custom_logo'] == '') {
                $error_pages[] = $row['page_id'];
            } else {
                $this_file = \REDCap::getFile($row['custom_logo']);
                if ($this_file === false || $this_file[2] == '') {
                    $error_pages[] = $row['page_id'];
                }
            }
        }
        if (!empty($error_pages)) {
            foreach ($error_pages as $pageId) {
                $custom_logo = Page::uploadDefaultImageFile($projectId);
                $sql = "UPDATE redcap_mycap_aboutpages SET custom_logo =" . checkNull($custom_logo) . " WHERE project_id = " . $projectId . " AND page_id = '" . db_escape($pageId) . "'";
                db_query($sql);
            }
        }
    }
}
