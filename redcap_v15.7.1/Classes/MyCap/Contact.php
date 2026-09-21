<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use RCView;
class Contact
{
    const TYPE_SUPPORT = '.Support';

    /**
     * Render Add/Edit Contact Forms
     *
     * @return string
     */
    public static function renderAddEditForm() {
        global $lang, $user_rights, $Proj;
        // Get array of DAGs
        $dags = $Proj->getGroups();

        $form = '<form class="form-horizontal" action="" method="post" id="saveContact" enctype="multipart/form-data">                
                <div class="modal fade" id="external-modules-configure-modal-contact" name="external-modules-configure-modal-contact" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true">
                    <div class="modal-dialog" role="document" style="max-width: 950px !important;">
                        <div class="modal-content">
                            <div class="modal-header py-2">
                                <button type="button" class="py-2 close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                <h4 id="add-edit-title-text" class="modal-title mc-form-control-custom"></h4>
                            </div>
                            <div class="modal-body pt-2">
                                <div id="errMsgContainerModal" class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;"></div>
                                <div class="mb-2">
                                    '.$lang['mycap_mobile_app_153'].'
                                </div>
                                <table class="mc_code_modal_table" id="code_modal_table_update">';
        if ($user_rights['group_id'] == '' && !empty($dags) && \Design::isDraftPreview() == false) {
            $form .= '<tr class="mc-form-control-custom">
                        <td class="align-text-top pt-2 pe-1">
                            <label class="text-nowrap boldish">'.RCView::tt('data_entry_323').'</label>
                        </td>
                        <td class="external-modules-input-td">
                            '.RCView::select(array('name' => 'dag_id', 'id'=>'dag_id', 'class'=>'x-form-field ms-3', 'style'=>'max-width:500px;font-size:14px;'), array(''=>$lang['data_access_groups_ajax_23'])+$dags).'
                            <div class="requiredlabel ms-3" style="color:#C00000;">'.RCView::tt('mycap_mobile_app_958').'</div>
                        </td>
                    </tr>';
        }

        $form .= '<tr class="mc-form-control-custom" field="">
                        <td class="align-text-top pt-2 pe-1">
                            <label class="text-nowrap boldish">'.$lang['mycap_mobile_app_633'].$lang['colon'].'</label><div class="requiredlabel p-0">* '.$lang['data_entry_39'].'</div>
                        </td>
                        <td class="external-modules-input-td">
                            <input type="text" id="header" name="header" placeholder="'.$lang['training_res_05'].'" class="d-inline ms-3" style="font-size:15px;width:500px;" maxlength="100">
                        </td>
                    </tr>
                    <tr class="mc-form-control-custom" field="">
                        <td class="align-text-top pt-1 pe-1">
                            <label class="text-nowrap boldish">'.$lang['email_users_12'].$lang['colon'].'</label>
                        </td>
                        <td class="external-modules-input-td">
                            <input type="text" id="title" name="title" placeholder="'.$lang['email_users_12'].'" class="external-modules-input-element ms-3" style="max-width:95%;">
                        </td>
                    </tr>
                    <tr class="mc-form-control-custom" field="">
                        <td class="align-text-top pt-1 pe-1">
                            <label class="text-nowrap boldish">'.$lang['design_89'].$lang['colon'].'</label>
                        </td>
                        <td class="external-modules-input-td">
                            <input type="text" id="phone" name="phone" placeholder="'.$lang['design_89'].'" class="external-modules-input-element ms-3" style="max-width:95%;">
                        </td>
                    </tr>
                    <tr class="mc-form-control-custom" field="">
                        <td class="align-text-top pt-1 pe-1">
                            <label class="text-nowrap boldish">'.$lang['global_33'].$lang['colon'].'</label>
                        </td>
                        <td class="external-modules-input-td">
                            <input type="text" id="email" name="email" placeholder="'.$lang['global_33'].'" class="external-modules-input-element ms-3" style="max-width:95%;">
                        </td>
                    </tr>
                    <tr class="mc-form-control-custom" field="">
                        <td class="align-text-top pt-1 pe-1">
                            <label class="text-nowrap boldish">'.$lang['edit_project_124'].'</label>
                        </td>
                        <td class="external-modules-input-td">
                            <input type="text" id="weburl" name="weburl" placeholder="'.$lang['mycap_mobile_app_158'].'" class="external-modules-input-element ms-3" style="max-width:95%;">
                        </td>
                    </tr>
                    <tr class="mc-form-control-custom" field="">
                        <td class="align-text-top pt-1 pe-1">
                            <label class="text-nowrap boldish">'.$lang['mycap_mobile_app_70'].$lang['colon'].'</label>
                        </td>
                        <td class="external-modules-input-td">
                            <textarea id="info" name="info" placeholder="'.$lang['mycap_mobile_app_70'].'" class="external-modules-input-element ms-3" style="max-width:95%;height:100px;"></textarea>
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
     * Render Contacts listing page
     *
     * @return string
     */
    public static function renderContactsSetupPage()
    {
        global $lang;
        renderPageTitle("<div style='float:left;'>{$lang['mycap_mobile_app_03']}</div><br>");
        print RCView::p(array('class'=>'mt-0 mb-2', 'style'=>'max-width:900px;'), $lang['mycap_mobile_app_71']);
        print MyCap::getMessageContainers();

        print '<div class="mt-3 mb-4">
                    <button id="contactsPreview" type="button" class="btn btn-sm btn-defaultrc ms-2" data-toggle="modal" data-target="#previewModal"><i class="fa-solid fa-mobile-screen-button"></i> '.$lang['design_699'].'</button>
                </div>';
        print "<div id='contacts_list_parent_div' class='mt-3'>".self::renderContactList()."</div>";
    }

    /**
     * Get table layout of all contacts stored in db
     *
     * @return string
     */
    public static function renderContactList()
    {
        global $lang, $Proj;
        // Ensure contacts are in correct order
        self::checkContactOrder();
        // Get list of contacts to display as table
        $contacts = self::getContacts(PROJECT_ID);
        // Build table
        $rows = array();
        $item_num = 0; // loop counter
        foreach ($contacts as $contact_id => $attr)
        {
            foreach ($attr as $configKey => $configVal) {
                // Store values in array to convert to JSON to use when loading the dialog
                $info_modal[$item_num][str_replace("_", "-", $configKey)] = $configVal . "";
            }
            // First column
            $rows[$item_num][] = RCView::span(array('style'=>'display:none;'), $contact_id);
            // Contact order number
            $rows[$item_num][] = ($item_num+1);

            $contact_header = RCView::escape($attr['contact_header']);
            // Contact Header
            $dag_name = '';
            if ($attr['dag_id'] != null) {
                $dag_name = '<span class="nowrap fs11 py-1 ps-1 pe-2" style="color:#008000;">['.$Proj->getGroups($attr['dag_id']).']</span>';
            }
            $rows[$item_num][] = RCView::div(array('class'=>'wrap fs14'),
                RCView::div(array('style'=>'margin-right:"120px";', 'class' => 'dash-title'), $contact_header.$dag_name)
            );
            // Contact Title
            $rows[$item_num][] = RCView::div(array('class'=>'wrap fs14'),
                RCView::div(array('style'=>'margin-right:"120px";', 'class' => 'dash-title'), RCView::escape($attr['contact_title']))
            );
            // edit/delete options
            $rows[$item_num][] =
                RCView::span(array('class'=>'rprt_btns'),
                    //Edit
                    RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11', 'style'=>'color:#000080;margin-right:2px;padding: 1px 6px;', 'onclick'=>"__rcfunc_editContactRow{$item_num}();"),
                        '<i class="fas fa-pencil-alt"></i> ' .$lang['global_27']
                    ) .
                    // Delete
                    RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11', 'style'=>'color:#A00000;padding: 1px 6px;', 'onclick'=>"deleteContact($contact_id, '".$contact_header."');return true;"),
                        '<i class="fas fa-times"></i> ' .$lang['global_19']
                    )
                )
                ."<script type=\"text/javascript\">function __rcfunc_editContactRow{$item_num}(){ editContact(".json_encode($info_modal[$item_num]).",'".$contact_id."',".$item_num.") }</script>";
            // Increment row counter
            $item_num++;
        }
        // Add last row as "add new contact" button
        $rows[$item_num] = array('', '',
            RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs12', 'style'=>'color:#000080;margin:12px 0;', 'onclick'=>"editContact('', '', '');"),
                '<i class="fas fa-plus fs11"></i> ' . $lang['mycap_mobile_app_72']
            ), '', '');
        // Set table headers and attributes
        $col_widths_headers = array();
        $col_widths_headers[] = array(18, "", "center");
        $col_widths_headers[] = array(18, "", "center");
        $col_widths_headers[] = array(400, $lang['training_res_05']);
        $col_widths_headers[] = array(300, $lang['email_users_12']);
        $col_widths_headers[] = array(160, $lang['global_1549'], "center");
        // Render the table
        return renderGrid("contacts_list", "", 950, 'auto', $col_widths_headers, $rows, true, false, false);
    }

    /**
     * Return all contacts (unless one is specified explicitly) as an array of their attributes
     *
     * @param int $project_id
     * @param int $contact_id
     * @return array $contacts
     */
    public static function getContacts($project_id, $contact_id = null)
    {
        $contacts = array();
        // If $contact_id is 0 (contact doesn't exist), then return field defaults from tables
        if ($contact_id === 0) {
            // Add to links array
            $contacts[$contact_id] = getTableColumns('redcap_mycap_contacts');
            // Return array
            return $contacts[$contact_id];
        }

        // Get main attributes
        $sql = "SELECT * FROM redcap_mycap_contacts WHERE project_id = ".$project_id;
        if (is_numeric($contact_id)) $sql .= " AND contact_id = $contact_id";
        $sql .= " ORDER BY contact_order";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Add to contacts array
            $contacts[$row['contact_id']] = $row;
        }
        // If no contacts, then return empty array
        if (empty($contacts)) return array();

        // Return array of contact(s) attributes
        if ($contact_id == null) {
            return $contacts;
        } else {
            return $contacts[$contact_id];
        }
    }

    /**
     * Checks for errors in the contact order of all links (in case their numbering gets off)
     *
     * @return boolean
     */
    public static function checkContactOrder()
    {
        // Do a quick compare of the field_order by using Arithmetic Series (not 100% reliable, but highly reliable and quick)
        // and make sure it begins with 1 and ends with field order equal to the total field count.
        $sql = "SELECT SUM(contact_order) AS actual, ROUND(COUNT(1)*(COUNT(1)+1)/2) AS ideal,
				MIN(contact_order) AS min, MAX(contact_order) AS max, COUNT(1) AS contact_count
				FROM redcap_mycap_contacts WHERE project_id = " . PROJECT_ID;
        $q = db_query($sql);
        $row = db_fetch_assoc($q);
        db_free_result($q);
        if ( ($row['actual'] != $row['ideal']) || ($row['min'] != '1') || ($row['max'] != $row['contact_count']) )
        {
            return self::fixContactOrder();
        }
    }

    /**
     * Fixes the contact order of all contacts (if somehow their numbering gets off)
     *
     * @return boolean
     */
    public static function fixContactOrder()
    {
        // Set all contact_orders to null
        $sql = "select @n := 0";
        db_query($sql);
        // Reset field_order of all fields, beginning with "1"
        $sql = "UPDATE redcap_mycap_contacts
				SET contact_order = @n := @n + 1 WHERE project_id = ".PROJECT_ID."
				ORDER BY contact_order, contact_id";
        if (!db_query($sql))
        {
            // If unique key prevented easy fix, then do manually via looping
            $sql = "SELECT contact_id FROM redcap_mycap_contacts WHERE project_id = ".PROJECT_ID." ORDER BY contact_order, contact_id";
            $q = db_query($sql);
            $contact_order = 1;
            $contact_orders = array();
            while ($row = db_fetch_assoc($q)) {
                $link_orders[$row['contact_id']] = $contact_order++;
            }
            // Reset all orders to null
            $sql = "UPDATE redcap_mycap_contacts SET contact_order = NULL WHERE project_id = ".PROJECT_ID;
            db_query($sql);
            foreach ($contact_orders as $contact_id => $contact_order) {
                // Set order of each individually
                $sql = "UPDATE redcap_mycap_contacts SET contact_order = $contact_order WHERE contact_id = $contact_id";
                db_query($sql);
            }
        }
        // Return boolean on success
        return true;
    }
}
