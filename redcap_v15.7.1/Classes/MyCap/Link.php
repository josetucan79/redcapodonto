<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use RCView;
class Link
{
    public static $linkIcons = [
        'ic_accessibility' => 'Accessibility',
        'ic_assessment' => 'Assessment',
        'ic_assignment' => 'Assignment',
        'ic_bookmark' => 'Bookmark',
        'ic_bubble_chart' => 'Bubble Chart',
        'ic_dashboard' => 'Dashboard',
        'ic_date_range' => 'Date Range',
        'ic_donut_large' => 'Donut Large',
        'ic_equalizer' => 'Equalizer',
        'ic_event' => 'Event',
        'ic_event_note' => 'Event Note',
        'ic_face' => 'Face',
        'ic_help_outline' => 'Help',
        'ic_info_outline' => 'Info',
        'ic_insert_chart' => 'Insert Chart',
        'ic_library_books' => 'Library Books',
        'ic_link' => 'Link',
        'ic_map' => 'Map',
        'ic_multiline_chart' => 'Multiline Chart',
        'ic_pie_chart' => 'Pie Chart',
        'ic_place' => 'Place',
        'ic_report_problem' => 'Report Problem',
        'ic_schedule' => 'Schedule',
        'ic_show_chart' => 'Show Chart',
        'ic_today' => 'Today',
        'ic_web' => 'Web'
    ];

    /**
     * Get layout of link icons listing
     *
     * @return string
     */
    public static function getLinkIconsList() {
        $linkIconsList = '';
        $linkIcons = self::$linkIcons;
        if (count($linkIcons) > 0) {
            $linkIconsList = '<ul class="link-icons-list">';
            foreach ($linkIcons as $linkIcon => $description) {
                $linkIconsList .= '<li data-value="'.$linkIcon.'" class="link-icon" style="margin-bottom: 5px; position: relative; display: inline-block;">
                                    <div><img src="'.APP_PATH_IMAGES.'mycap_link_icons/'.$linkIcon.'.png" /></div>
                                    <span>'.$description.'</span>
                                    <i class="fa fa-check tick" style="display:none;"></i>
                                </li>';
            }
            $linkIconsList .= '</ul>';
        }

        return $linkIconsList;
    }

    /**
     * Render Add/Edit Contact Forms
     *
     * @return string
     */
    public static function renderAddEditForm() {
        global $lang, $user_rights, $Proj;
        // Get array of DAGs
        $dags = $Proj->getGroups();

        $form = '<form class="form-horizontal" action="" method="post" id="saveLink" enctype="multipart/form-data">                
                <div class="modal fade" id="external-modules-configure-modal-link" name="external-modules-configure-modal-link" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true">
                    <div class="modal-dialog" role="document" style="max-width: 950px !important;">
                        <div class="modal-content">
                            <div class="modal-header py-2">
                                <button type="button" class="py-2 close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                <h4 id="add-edit-title-text" class="modal-title mc-form-control-custom"></h4>
                            </div>
                            <div class="modal-body pt-2">
                                <div id="errMsgContainerModal" class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;"></div>
                                <div class="mb-2">
                                    '.$lang['mycap_mobile_app_49'].'
                                </div>
                                <table class="mc_code_modal_table" id="code_modal_table_update">';
        if ($user_rights['group_id'] == '' && !empty($dags) && \Design::isDraftPreview() == false) {
            $form .= ' <tr class="mc-form-control-custom">
                            <td colspan="2">
                                <div class="form-control-custom-title clearfix">
                                    <div class="boldish fs14" style="margin-top:2px;"><i class="fas fa-users"></i> '.RCView::tt('data_entry_564').'</div>
                                </div>
                            </td>
                        </tr>
                        <tr class="mc-form-control-custom" field="">
                            <td class="align-text-top pt-1 pe-1">
                                <label class="text-nowrap boldish">'.RCView::tt('data_entry_323').RCView::tt('colon').'</label>
                            </td>
                            <td class="external-modules-input-td">
                                '.RCView::select(array('name' => 'dag_id', 'id'=>'dag_id', 'class'=>'x-form-field ms-3', 'style'=>'max-width:500px;font-size:14px;'), array(''=>$lang['data_access_groups_ajax_23'])+$dags).'
                                <div class="requiredlabel ms-3" style="color:#C00000;">'.RCView::tt('mycap_mobile_app_959').'</div>
                            </td>
                        </tr>';
        }

        $form .= '<tr class="mc-form-control-custom">
                                        <td colspan="2">
                                            <div class="mc-form-control-custom-title clearfix">
                                                <div class="boldish fs14" style="margin-top:2px;"><i class="fas fa-info-circle"></i> '.$lang['mycap_mobile_app_50'].'</div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="mc-form-control-custom" field="">
                                        <td class="align-text-top pt-1 pe-1">
                                            <label class="text-nowrap boldish">'.$lang['mycap_mobile_app_51'].'</label><div class="requiredlabel p-0">* '.$lang['data_entry_39'].'</div>
                                        </td>
                                        <td class="external-modules-input-td">
                                            <input type="text" id="link_name" name="link_name" placeholder="'.$lang['email_users_12'].'" class="d-inline ms-3" style="font-size:15px;width:500px;" maxlength="100">
                                        </td>
                                    </tr>
                                    <tr class="mc-form-control-custom" field="">
                                        <td class="align-text-top pt-1 pe-1">
                                            <label class="text-nowrap boldish">'.$lang['mycap_mobile_app_52'].'</label><div class="requiredlabel p-0">* '.$lang['data_entry_39'].'</div>
                                        </td>
                                        <td class="external-modules-input-td">
                                            <input type="text" id="link_url" name="link_url" placeholder="'.$lang['api_docs_050'].'" class="external-modules-input-element ms-3" style="max-width:95%;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <div class="data mt-3 mb-4 px-3 pt-1 pb-2" style="background:#f8f8f8;">
                                                <div class="mb-2">'.$lang['design_984'].'</div>
                                                <div>
                                                    <input id="append_project_code" name="append_project_code" style="position:relative;top:2px;margin-right:3px;" type="checkbox">
                                                    <label for="append_project_code" style="color:#A00000;" class="boldish me-2 mb-0">'.$lang['mycap_mobile_app_59'].'</label><div class="fs12 ps-4 cc_info" style="margin-top:2px;line-height: 1.1;">'.$lang['mycap_mobile_app_60'].'</div>
                                                </div>
                                                <div style="margin-top:10px;">
                                                    <input id="append_participant_code" name="append_participant_code" style="position:relative;top:2px;margin-right:3px;" type="checkbox">
                                                    <label for="append_participant_code" style="color:#A00000;" class="boldish me-2 mb-0">'.$lang['mycap_mobile_app_61'].'</label><div class="fs12 ps-4 cc_info" style="margin-top:2px;line-height: 1.1;">'.$lang['mycap_mobile_app_62'].'</div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="mc-form-control-custom">
                                        <td colspan="2">
                                            <div class="mc-form-control-custom-title clearfix">
                                                <div class="boldish fs14" style="margin-top:2px;"><i class="fas fa-image"></i> '.$lang['mycap_mobile_app_53'].'</div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="mc-form-control-custom" field="">
                                        <td class="align-text-top pt-1 pe-1">
                                            <label class="text-nowrap boldish">'.$lang['mycap_mobile_app_55'].'</label><div class="requiredlabel p-0">* '.$lang['data_entry_39'].'</div>
                                        </td>
                                        <td class="external-modules-input-td pb-3">
                                            <input type="hidden" name="selected_icon" id="selected_icon" value="">
                                            <ul class="link-icons-list">
                                                '.self::getLinkIconsList().'
                                            </ul>
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
     * Render Links listing page
     *
     * @return string
     */
    public static function renderLinksSetupPage()
    {
        global $lang;
        renderPageTitle("<div style='float:left;'>{$lang['mycap_mobile_app_04']}</div><br>");
        print RCView::p(array('class'=>'mt-0 mb-2', 'style'=>'max-width:900px;'), $lang['mycap_mobile_app_44']);
        print MyCap::getMessageContainers();

        print "<div id='links_list_parent_div' class='mt-3'>".self::renderLinkList()."</div>";
    }

    /**
     * Get html table listing all links
     *
     * @return string
     */
    public static function renderLinkList()
    {
        global $lang, $Proj;
        // Ensure dashboards are in correct order
        self::checkLinkOrder();
        // Get list of reports to display as table (only apply user access filter if don't have Add/Edit Reports rights)
        $links = self::getLinks(PROJECT_ID);
        // Build table
        $rows = array();
        $item_num = 0; // loop counter
        foreach ($links as $link_id => $attr)
        {
            foreach ($attr as $configKey => $configVal) {
                // Store values in array to convert to JSON to use when loading the dialog
                $info_modal[$item_num][str_replace("_", "-", $configKey)] = $configVal . "";
            }
            $link_name = $attr['link_name'];
            // First column
            $rows[$item_num][] = RCView::span(array('style'=>'display:none;'), $link_id);
            // Link order number
            $rows[$item_num][] = ($item_num+1);
            // Link Icon
            $rows[$item_num][] = RCView::div(array('class'=>'wrap fs14'),
                RCView::div(array('style'=>'margin-right:"120px";', 'title'=>Link::$linkIcons[$attr['link_icon']]), RCView::img(array('src' => APP_PATH_IMAGES . 'mycap_link_icons/'.$attr['link_icon'].'.png', 'style' => 'width:24px;')))
            );
            // Link Name
            $dag_name = '';
            if ($attr['dag_id'] != null) {
                $dag_name = '<span class="nowrap fs11 py-1 ps-1 pe-2" style="color:#008000;">['.$Proj->getGroups($attr['dag_id']).']</span>';
            }
            $rows[$item_num][] = RCView::div(array('class'=>'wrap fs14'),
                RCView::div(array('style'=>'margin-right:"120px";', 'class' => 'dash-title'), RCView::escape($link_name).$dag_name)
            );
            // Link URL
            $rows[$item_num][] = RCView::div(array('class'=>'wrap fs14'),
                RCView::div(array('style'=>'margin-right:"120px";', 'class' => 'wrap-long-url'), self::fullLinkUrl($attr['link_url'], $attr['append_project_code'], $attr['append_participant_code']))
            );
            // edit/delete options
            $rows[$item_num][] =
                RCView::span(array('class'=>'rprt_btns'),
                    //Edit
                    RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11', 'style'=>'color:#000080;margin-right:2px;padding: 1px 6px;', 'onclick'=>"__rcfunc_editLinkRow{$item_num}();"),
                        '<i class="fas fa-pencil-alt"></i> ' .$lang['global_27']
                    ) .
                    // Delete
                    RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11', 'style'=>'color:#A00000;padding: 1px 6px;', 'onclick'=>"deleteLink($link_id, '".$link_name."');return true;"),
                        '<i class="fas fa-times"></i> ' .$lang['global_19']
                    )
                )
                ."<script type=\"text/javascript\">function __rcfunc_editLinkRow{$item_num}(){ editLink(".json_encode($info_modal[$item_num]).",'".$link_id."',".$item_num.") }</script>";
            // Increment row counter
            $item_num++;
        }
        // Add last row as "add new link" button
        $rows[$item_num] = array('', '', '',
            RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs12', 'style'=>'color:#000080;margin:12px 0;', 'onclick'=>"editLink('', '', '');"),
                '<i class="fas fa-plus fs11"></i> ' . $lang['mycap_mobile_app_47']
            ), '', '');
        // Set table headers and attributes
        $col_widths_headers = array();
        $col_widths_headers[] = array(18, "", "center");
        $col_widths_headers[] = array(18, "", "center");
        $col_widths_headers[] = array(30, $lang['mycap_mobile_app_48']);
        $col_widths_headers[] = array(150, $lang['mycap_mobile_app_45']);
        $col_widths_headers[] = array(500, $lang['api_docs_050']);
        $col_widths_headers[] = array(160, $lang['mycap_mobile_app_46'], "center");
        // Render the table
        return renderGrid("links_list", "", 950, 'auto', $col_widths_headers, $rows, true, false, false);
    }

    /**
     * Return all links (unless one is specified explicitly) as an array of their attributes
     *
     * @param int $project_id
     * @param int $link_id
     * @return Array
     */
    public static function getLinks($project_id, $link_id=null)
    {
        $links = array();
        // If link_id is 0 (link doesn't exist), then return field defaults from tables
        if ($link_id === 0) {
            // Add to links array
            $links[$link_id] = getTableColumns('redcap_mycap_links');
            // Return array
            return $links[$link_id];
        }

        // Get main attributes
        $sql = "SELECT * FROM redcap_mycap_links WHERE project_id = ".$project_id;
        if (is_numeric($link_id)) $sql .= " AND link_id = $link_id";
        $sql .= " ORDER BY link_order";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Add to reports array
            $links[$row['link_id']] = $row;
        }
        // If no links, then return empty array
        if (empty($links)) return array();

        // Return array of report(s) attributes
        if ($link_id == null) {
            return $links;
        } else {
            return $links[$link_id];
        }
    }

    /**
     * Checks for errors in the link order of all links (in case their numbering gets off)
     *
     * @return boolean
     */
    public static function checkLinkOrder()
    {
        // Do a quick compare of the field_order by using Arithmetic Series (not 100% reliable, but highly reliable and quick)
        // and make sure it begins with 1 and ends with field order equal to the total field count.
        $sql = "SELECT SUM(link_order) AS actual, ROUND(COUNT(1)*(COUNT(1)+1)/2) AS ideal,
				MIN(link_order) AS min, MAX(link_order) AS max, COUNT(1) AS link_count
				FROM redcap_mycap_links WHERE project_id = " . PROJECT_ID;
        $q = db_query($sql);
        $row = db_fetch_assoc($q);
        db_free_result($q);
        if ( ($row['actual'] != $row['ideal']) || ($row['min'] != '1') || ($row['max'] != $row['link_count']) )
        {
            return self::fixLinkOrder();
        }
    }

    /**
     * Fixes the link order of all links (if somehow their numbering gets off)
     *
     * @return boolean
     */
    public static function fixLinkOrder()
    {
        // Set all link_orders to null
        $sql = "select @n := 0";
        db_query($sql);
        // Reset field_order of all fields, beginning with "1"
        $sql = "UPDATE redcap_mycap_links
				SET link_order = @n := @n + 1 WHERE project_id = ".PROJECT_ID."
				ORDER BY link_order, link_id";
        if (!db_query($sql))
        {
            // If unique key prevented easy fix, then do manually via looping
            $sql = "SELECT link_id FROM redcap_mycap_links WHERE project_id = ".PROJECT_ID." ORDER BY link_order, link_id";
            $q = db_query($sql);
            $link_order = 1;
            $link_orders = array();
            while ($row = db_fetch_assoc($q)) {
                $link_orders[$row['link_id']] = $link_order++;
            }
            // Reset all orders to null
            $sql = "UPDATE redcap_mycap_links SET link_order = NULL WHERE project_id = ".PROJECT_ID;
            db_query($sql);
            foreach ($link_orders as $link_id => $link_order) {
                // Set order of each individually
                $sql = "UPDATE redcap_mycap_links SET link_order = $link_order WHERE link_id = $link_id";
                db_query($sql);
            }
        }
        // Return boolean on success
        return true;
    }

    /**
     * Append query string params to the URL if needed. E.g. From:
     *   http://www.foo.com/
     * To:
     *   http://www.foo.com/?projectCode=[...]&participantCode=[...]
     *
     * @param string $url
     * @param boolean $appendProjectCode
     * @param boolean $appendParticipantCode
     * @return string
     */
    public static function fullLinkUrl($url, $appendProjectCode, $appendParticipantCode)
    {
        global $myCapProj;
        $param_url = '';
        if ($appendProjectCode) {
            $projectCode = $myCapProj->project['code'];
            $param_url .= strpos(
                $url,
                '?'
            ) ? '&' : '?';
            $param_url .= "projectCode=$projectCode";
        }

        if ($appendParticipantCode) {
            $param_url .= (strpos($url, '?') !== false || strpos($param_url, '?') !== false) ? '&' : '?';
            $param_url .= "participantCode=U-EXAMPLECODE";
        }

        $url .= '<span style="color: #888;">'.$param_url.'</span>';
        return $url;
    }
}
