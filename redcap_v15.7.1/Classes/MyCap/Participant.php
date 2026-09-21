<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use RCView;

class Participant
{
    const PARTICIPANTS_PER_PAGE = 100;

    /**
     * Get table layout of all participant stored in db
     *
     * @return string
     */
    public static function renderParticipantList()
    {
        global $lang, $myCapProj, $Proj, $user_rights;

        $baselineDateEnabled = ZeroDateTask::baselineDateEnabled();

        $participantIdentifierEnabled = ((isset($myCapProj->project['participant_custom_field']) && $myCapProj->project['participant_custom_field'] != '')
                                      || (isset($myCapProj->project['participant_custom_label']) && $myCapProj->project['participant_custom_label'] != ''));
        $participant_identifier_img = $participantIdentifierEnabled ? 'tick_small_circle.png' : 'bullet_delete.png';
        $flutter_notice_img = ($myCapProj->project['converted_to_flutter'] == 1) ? 'tick_small_circle.png' : 'bullet_delete.png';

        print RCView::p(array('class'=>'mt-0 mb-2', 'style'=>'max-width:900px;'), $lang['mycap_mobile_app_554']);
        print RCView::p(array('class'=>'mt-3 mb-1 fs12', 'style'=>'line-height:1.2;color:#666;max-width:900px;'), $lang['mycap_mobile_app_585']);
        print RCView::p(array('class'=>'mt-0 mb-4 fs12', 'style'=>'line-height:1.2;color:#666;max-width:900px;'), $lang['mycap_mobile_app_627']);
        // Get list of participants to display as table
        list ($part_list, $participantCount) = self::getParticipantList(PROJECT_ID);

        ## BUILD THE DROP-DOWN FOR PAGING THE PARTICIPANTS
        // Section the Participant List into multiple pages
        $num_per_page = self::PARTICIPANTS_PER_PAGE;
        // Calculate number of pages for dropdown
        $num_pages = ceil($participantCount/$num_per_page);
        ## Build the paging drop-down for participant list
        $pageDropdown = "<select onchange='loadParticipantList(this.value)' style='vertical-align:middle;font-size:11px;'>";
        //Loop to create options for dropdown
        for ($i = 1; $i <= $num_pages; $i++) {
            $end_num   = $i * $num_per_page;
            $begin_num = $end_num - $num_per_page + 1;
            if ($end_num > $participantCount) $end_num = $participantCount;
            $pageDropdown .= "<option value='$i' " . ($_GET['pagenum'] == $i ? "selected" : "") . ">$begin_num - $end_num</option>";
        }
        $pageDropdown .= "</select>";
        $pageDropdown  = "{$lang['survey_45']} $pageDropdown {$lang['survey_133']} $participantCount";

        // If viewing ALL participants, then set $num_per_page to null to return all participants
        if ($_GET['pagenum'] == 'ALL') $num_per_page = null;

        list($all_participants, $all_records) = self::getDropDownList(PROJECT_ID);

        $myCapConfigVersion = $myCapProj->getConfigVersion();

		// foreach (array_slice($part_list, $limit_begin, $num_per_page) as $this_part=>&$attr) {
		foreach ($part_list as $this_part=>&$attr) {
            // Trim identifier
            $identifier = trim($attr['identifier']);

            if ($attr['is_deleted'] == 0) {
                // Give warning if we're on Config Version 0
                $opacity_qr = "";
                if ($myCapConfigVersion == 0) {
                    $clickFn = "simpleDialog('".RCView::tt_js2('mycap_mobile_app_891')."', '".RCView::tt_js2('global_48')."');";
                    $opacity_qr = "opacity: 0.6;";
                } else {
                    $clickFn = "getQRCode('".$attr['record']."', '".$attr['event_id']."');";
                }
                $access_code = 	RCView::a(array('href'=>'javascript:;', 'onclick'=>$clickFn),
                    (!gd2_enabled()
                        ? RCView::img(array('src'=>'ticket_arrow.png', 'style'=>'vertical-align:middle;'))
                        : RCView::img(array('src'=>'access_qr_code.gif', 'style'=>'vertical-align:middle;'.$opacity_qr))
                    )
                );
            } else {
                $access_code = '<span style="color:#ccc;">'.$lang['control_center_149'].'</span>';
            }

            $recordLink = "DataEntry/record_home.php?pid=".PROJECT_ID."&id={$attr['record']}";
            if ($Proj->multiple_arms) {
                if ($attr['event_id'] != '') {
                    $recordLink .= "&arm=" . $Proj->eventInfo[$attr['event_id']]['arm_num'];
                }
            }
            $display_id = "<a href='".APP_PATH_WEBROOT.$recordLink."' style='font-size:12px;text-decoration:underline;'>{$attr['record']}</a>";

            // Add to array
            $part_list_full[$i] = array();
            $part_list_full[$i][] = "<div class='wrapemail'>{$identifier}</div>";
            $part_list_full[$i][] = "<div class='wrap' style='word-wrap:break-word;'>$display_id</div>";

            $additional_info = '';
            if ($attr['join_date'] != '-') {
                $additional_info = self::displayJoinDateAdditionalInfoPopup($attr['join_date_utc'], $attr['timezone']);
            }

            $part_list_full[$i][] = "<div class='wrapemail'>{$attr['join_date']} {$additional_info}</div>";
            if ($baselineDateEnabled) {
                $part_list_full[$i][] = "<div class='wrapemail'>{$attr['baseline_date']}</div>";
            }
            // Quick code and QR code
            $part_list_full[$i][] = $access_code;
            $buttons = '';
            if ($attr['record'] != '') {
                if ($attr['join_date'] == '-') {
                    $joined = 0;
                    $buttons .= '<a onclick="simpleDialog(\''.RCView::tt_js2('mycap_mobile_app_890').'\', \''.RCView::tt_js2('global_48').'\');" href="javascript:;" style="margin-right:5px;outline:none; color:#666;font-family:Tahoma;font-size:12px; opacity: 0.6;"><i class="fas fa-comment-alt"></i> '.$lang['mycap_mobile_app_415'].'</a>';
                } else {
                    $joined = 1;
                    $buttons .= '<a onclick="openMessagesHistory(\''.$this_part.'\');" href="javascript:;" style="margin-right:3px;outline:none;color:#3E72A8;font-family:Tahoma;font-size:12px;"><i class="fas fa-comment-alt"></i> '.$lang['mycap_mobile_app_415'].'</a>';
                }
                $buttons .= '|';
                if ($attr['is_deleted'] == 1) {
                    $buttons .= '<a onclick="openMyCapParticipantStatus(\''.$attr['record'].'\',\''.$this_part.'\',\'enable\', \''.$joined.'\');" href="javascript:;" style="margin-left:3px;outline:none;color:#B00000;font-family:Tahoma;font-size:12px;"><i class="fas fa-toggle-on"></i> '.$lang['survey_152'].'</a>';
                } else {
                    $buttons .= '<a onclick="openMyCapParticipantStatus(\''.$attr['record'].'\',\''.$this_part.'\',\'disable\', \''.$joined.'\');" href="javascript:;" style="margin-left:3px;outline:none;color:#777;font-family:Tahoma;font-size:12px;"><i class="fas fa-toggle-off"></i> '.$lang['control_center_153'].'</a>';
                }
                $part_list_full[$i][] = $buttons;
            } else {
                $part_list_full[$i][] = '';
            }

            $i++;
            // Remove this row to save memory
            unset($part_list[$this_part]);
        }

        // If no participants exist yet, render one row to let user know that
        //$part_list_full = array();
        if (empty($part_list_full))
        {
            // No participants exist yet
            $part_list_full[0] = array(RCView::div(array('class'=>'wrap','style'=>'color:#800000;'), $lang['survey_34']),"","","","","");
        }

        // Build participant list table
        $partTableWidth = 955;
        $partTableHeaders = array();
        $partTableHeaders[] = array(269, $lang['mycap_mobile_app_357']. RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11 ms-4',
                                'id'=>"set-identifier"), '<i class="fas fa-tag"></i> '. $lang['survey_152']. RCView::img(array('src' => APP_PATH_IMAGES . $participant_identifier_img, 'style'=>'margin-left:5px;position:relative;top:-1px;'))));
        $partTableHeaders[] = array(100, $lang['global_49'], "center");
        $partTableHeaders[] = array(($baselineDateEnabled ? 150 : 312), $lang['mycap_mobile_app_125']);
        if ($baselineDateEnabled) {
            $partTableHeaders[] = array(150, $lang['mycap_mobile_app_127']);
        }

        // Quick code and QR code
        $partTableHeaders[] = array(65, "<div class='wrap' style='line-height:1.1;'>".(gd2_enabled() ? $lang['mycap_mobile_app_356'] : $lang['survey_628'])."</div>", "center", "string", false);
        $partTableHeaders[] = array(135, $lang['mobile_app_87'], "center", "string", false);

        if ($myCapConfigVersion == 0) {
            $clickFnTemplate = "simpleDialog('".RCView::tt_js2('mycap_mobile_app_891')."', '".RCView::tt_js2('global_48')."');";
            $opacity_template = "opacity: 0.6;";
        } else {
            $clickFnTemplate = "openEmailTemplatePopup('', '', 'qr');return false;";
	        $opacity_template = "";
        }

        $partTableTitle = RCView::div(array(),
            RCView::div(array('style'=>'padding:2px 5px 0 5px;float:left;font-size:14px;'),
                $lang['mycap_mobile_app_626'] . RCView::br() .
                RCView::span(array('style'=>'line-height:24px;color:#666;font-size:11px;font-weight:normal;'),
                    $lang['mycap_mobile_app_374'] .
                    RCView::br() .
                    RCView::br() .
                    ## PAGING
                    RCView::span(array('style'=>'color:#555;font-size:11px;font-weight:normal;'),
                        $pageDropdown
                    )
                )
            ) .
            ## QUICK BUTTONS
            RCView::div(array('style'=>'font-weight:normal;float:left;font-size:11px;padding-left:12px;border-left:1px solid #ccc;'),
                RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11', 'style'=>'margin-top:5px;color:#000066;display:block;'.$opacity_template,
                    'onclick'=>$clickFnTemplate), '<i class="fas fa-user-plus"></i> '.$lang['mycap_mobile_app_375'].
                    RCView::img(array('id' => 'flutterNoticeImg', 'src' => APP_PATH_IMAGES . $flutter_notice_img, 'style'=>'margin-left:5px;position:relative;top:-1px;'))) .
                RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11', 'style'=>'margin-top:20px;color:#A00000;display:block;',
                    'onclick'=>"displayParticipantsLogicPopup();return false;"), '<i class="fas fa-eye-slash"></i> '.$lang['mycap_mobile_app_376'])
            ) .
            ## FILTERS
            RCView::div(array('style'=>'max-width:500px;font-weight:normal;float:left;font-size:11px;padding-left:15px;margin-left:10px;border-left:1px solid #ccc;'),
                // Date/time range
                $lang['mycap_mobile_app_125']." ".$lang['survey_439'] .
                RCView::text(array('id'=>'filterIBeginTime','value'=>$_GET['filterIBeginTime']??'','class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-right:8px;margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                $lang['mycap_mobile_app_125']." ".$lang['survey_440'] .
                RCView::text(array('id'=>'filterIEndTime','value'=>(isset($_GET['filterIEndTime']) ? $_GET['filterIEndTime'] : ""),'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                RCView::span(array('class'=>'df','style'=>'color:#777;'), '('.\DateTimeRC::get_user_format_label().' H:M)') . RCView::br() .
                // Date/time range
                (!$baselineDateEnabled ? '' :
                    $lang['mycap_mobile_app_127']." ".$lang['survey_439'] .
                    RCView::text(array('id'=>'filterBBeginTime','value'=>($_GET['filterBBeginTime']??""),'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-right:8px;margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                    $lang['mycap_mobile_app_127']." ".$lang['survey_440'] .
                    RCView::text(array('id'=>'filterBEndTime','value'=>(isset($_GET['filterBEndTime']) ? $_GET['filterBEndTime'] : ""),'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                    RCView::span(array('class'=>'df','style'=>'color:#777;'), '('.\DateTimeRC::get_user_format_label().' H:M)') . RCView::br()
                ) .
                // Display all active participants displayed in this view
                $lang['survey_441'] .
                RCView::select(array('id'=>'filterParticipant','style'=>'font-size:11px;margin:2px 3px;'), $all_participants, $_GET['filterParticipant']??'',300) .
                // Display record names displayed in this view
                $lang['survey_441'] .
                RCView::select(array('id'=>'filterRecord','style'=>'margin-left:3px;font-size:11px;'), $all_records, $_GET['filterRecord']??'',300) .
                RCView::br() .
                // "Apply filters" button
                RCView::button(array('class'=>'jqbuttonsm','style'=>'margin-top:5px;font-size:11px;color:#800000;','onclick'=>"loadParticipantList(1)"), $lang['survey_442']) .
                RCView::a(array('href'=>PAGE_FULL."?participants=1&pid=".PROJECT_ID,'style'=>'vertical-align:middle;margin-left:15px;text-decoration:underline;font-weight:normal;font-size:11px;'), $lang['setup_53'])
            ) .
            RCView::div(array('class'=>'clear'), '')
        );

        $table_width = $partTableWidth - count($partTableHeaders);

        // Give Recommendation if we're transitioned to Flutter app
        $style = '';
        if (!$myCapProj->project['converted_to_flutter']) { // converted_to_flutter = "0" in db
            $style = 'display: none;';
            print "<div id='flutterNotice' class='yellow repo-updates' style='width: ".$table_width."px;'>
                        <div style='color:#A00000;'>
                            <i class='fas fa-bell'></i> <span style='margin-left:3px;'>
                             ".$lang['mycap_mobile_app_754']."
                             ".$lang['mycap_mobile_app_782'] . " " .
                RCView::a(['href' => APP_PATH_WEBROOT."Resources/misc/mycap_transition.pdf", 'target' => '_blank', 'class'=>'fs13', 'style' => 'text-decoration:underline'],
                    $lang['mycap_mobile_app_783']
                ) ."
                                                             ".$lang['mycap_mobile_app_780'] . " " .
                RCView::a(['href' => APP_PATH_WEBROOT."Resources/misc/mycap_features.pdf", 'target' => '_blank', 'class'=>'fs13', 'style' => 'text-decoration:underline'],
                    $lang['mycap_mobile_app_781']
                ) ." ".$lang['mycap_mobile_app_784'].$lang['period'] . "
                            <div class='mt-2'><button onclick=\"transitionToFlutter('".RCView::tt_js('mycap_mobile_app_764')."','".RCView::tt_js('global_53')."','".RCView::tt_js('mycap_mobile_app_765')."','".RCView::tt_js('mycap_mobile_app_763')."');\" class='btn btn-danger btn-xs'>".$lang['mycap_mobile_app_755']."</button></div>
                        </div>
                    </div>";
        } else { // converted_to_flutter = "1" in db
            if (is_null($myCapProj->project['flutter_conversion_time'])) {
                // Do not display success message if project is not converted to flutter / converted to flutter without clicking "transition" button
                $style = 'display: none;';
            } else {
                // Display success message for 1 month only after project is converted to flutter
                if(strtotime($myCapProj->project['flutter_conversion_time']) < strtotime('-30 days')) {
                    $style = 'display: none;';
                }
            }
        }

        // transition to Flutter success message box
        print "<div class='flutterConversionMsg darkgreen' style='text-align:left; margin-bottom: 10px; color: green;".$style." width: ".$table_width."px;'><img src='".APP_PATH_IMAGES."tick.png'> <b>".$lang['mycap_mobile_app_767']."</b></div>";

        // transition from Dynamic link to new App link message box
        if (!$myCapProj->project['acknowledged_app_link']) { // acknowledged_app_link = "0" in db
            print "<div id='appLinkNotice' class='yellow repo-updates' style='width: ".$table_width."px;'>
                        <div style='color:#A00000;'>
                            <i class='fas fa-bell'></i> <span style='margin-left:3px;'>
                             <b>".RCView::tt('mycap_mobile_app_943')."</b>
                             <br>".RCView::tt('mycap_mobile_app_944')." 
                                 ".RCView::tt('mycap_mobile_app_946') . " " .
                                    RCView::a(['href' => "https://projectmycap.org/wp-content/uploads/2025/04/MyCapApp_NewAppAppLinks.pdf", 'target' => '_blank', 'class'=>'fs13', 'style' => 'text-decoration:underline'],
                                        $lang['mycap_mobile_app_942']
                                    ) ."
                            <div class='mt-2'><button onclick=\"acknowledgeNewAppLink();\" class='btn btn-danger btn-xs'>".RCView::tt('mycap_mobile_app_945')."</button></div>
                        </div>
                    </div>";
        }
        // Build Participant List
        renderGrid("participant_table", $partTableTitle, $table_width, "auto", $partTableHeaders, $part_list_full);
    }

    /**
     * Get list of all fields as type text to set as participant identifier
     *
     * @param string $id
     * @param string $name
     * @param string $selected
     * @param string $disabled
     * @return string
     */
    public static function renderParticipantDisplayLabelDropDown($id="", $name="", $selected="", $disabled="")
    {
        global $table_pk, $lang;
        // Set id and name
        $id   = (trim($id)   == "") ? "" : "id='$id'";
        $name = (trim($name) == "") ? "" : "name='$name'";
        // Staring building drop-down
        $html = "<select $id $name class='x-form-text x-form-field' $disabled>
                    <option value=''>{$lang['edit_project_60']}</option>";
        // Get list of fields ONLY from follow up forms to add to Select list
        $followUpFldOptions = "";
        $sql = "SELECT field_name, element_label FROM redcap_metadata 
                WHERE project_id = " . PROJECT_ID . "
                    AND field_name != CONCAT(form_name,'_complete') AND field_name != '$table_pk' 
                    AND (misc IS NULL OR (misc NOT LIKE '%@CALCTEXT%' AND misc NOT LIKE '%@CALCDATE%'))
                    AND element_type = 'text' ORDER BY field_order";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q))
        {
            $this_field = $row['field_name'];
            // Set field label
            $this_label = "$this_field - " . strip_tags(br2nl(label_decode($row['element_label'], false)));
            // Ensure label is not too long
            if (strlen($this_label) > 57) $this_label = substr($this_label, 0, 40) . "..." . substr($this_label, -15);
            // Add option
            $html .= "<option value='$this_field' " . ($this_field == $selected ? "selected" : "") . ">$this_label</option>";
        }
        // Finish drop-down
        $html .= "</select>";
        return $html;
    }

    /**
     * Get all participants stored in db
     *
     * @param interget $project_id
     * @return array
     */
    public static function getParticipantList($project_id)
    {
        global $user_rights, $myCapProj, $Proj;
        $groupID = ($user_rights['group_id'] != '' ? $user_rights['group_id'] : array());
        $codesList = array();
        // Format $codesList as array
        $executeSQL = true;
        if (!is_array($groupID) && $groupID == '0') {
            // If passing group_id as "0", assume we want to return unassigned records.
        } elseif (!empty($groupID) && is_numeric($groupID)) {
            $codesList = self::getParticipantsInDAG(array($groupID));
            if (empty($codesList))  $executeSQL = false;
        } elseif (!is_array($groupID)) {
            $codesList = array();
        }

        // Build participant list
        $part_list = array();
		$total_found_rows = 0;
        if ($executeSQL)
        {
	        $condition = $myCapProj->project['participant_allow_condition'] ?? "";

	        ## PERFORM MORE FILTERING
	        // Now filter participants by filters defined
	        if (isset($_GET['filterIBeginTime']) && $_GET['filterIBeginTime'] != '') {
		        $filterIBeginTimeYmd = \DateTimeRC::format_ts_to_ymd($_GET['filterIBeginTime']);
	        }
	        if (isset($_GET['filterIEndTime']) && $_GET['filterIEndTime'] != '') {
		        $filterIEndTimeYmd = \DateTimeRC::format_ts_to_ymd($_GET['filterIEndTime']);
	        }
	        if (isset($_GET['filterIBeginTime']) && $_GET['filterBBeginTime'] != '') {
		        $filterBBeginTimeYmd = \DateTimeRC::format_ts_to_ymd($_GET['filterBBeginTime']);
	        }
	        if (isset($_GET['filterBEndTime']) && $_GET['filterBEndTime'] != '') {
		        $filterBEndTimeYmd = \DateTimeRC::format_ts_to_ymd($_GET['filterBEndTime']);
	        }

	        // Set paging defaults
	        if (isset($_GET['pagenum']) && (isinteger($_GET['pagenum']) || $_GET['pagenum'] == 'last')) {
		        // do nothing
	        } elseif (!isset($_GET['pagenum'])) {
		        $_GET['pagenum'] = 1;
	        } else {
		        $_GET['pagenum'] = 'ALL';
	        }
	        // Set query limit, but only if we're not if we're using participant display logic AND not if we're filtering by install date or baseline date ranges
	        $limit_begin = "";
	        if (isset($_GET['pagenum']) && isinteger($_GET['pagenum']) && $_GET['pagenum'] > 0
		        && $condition == "" && !isset($filterIBeginTimeYmd) && !isset($filterIEndTimeYmd) && !isset($filterBBeginTimeYmd) && !isset($filterBEndTimeYmd)
            ) {
		        $limit_begin = "\nLIMIT ".(($_GET['pagenum'] - 1) * self::PARTICIPANTS_PER_PAGE).", ".self::PARTICIPANTS_PER_PAGE;
	        }

            // If participant condition is set, get list of records meeting that condition
			$conditionRecords = [];
            if ($condition != "") {
				$getDataParams = ['project_id'=>$project_id, 'groups'=>$user_rights['group_id'], 'fields'=>$Proj->table_pk, 'filterLogic'=>$condition];
				$conditionRecords = array_keys(\Records::getData($getDataParams));
            }
			if (empty($conditionRecords)) {
				$checkRecordNameEachLoop = false;
			} else {
				// If we're querying more than 25% of the project's records, then don't put field names in query but check via PHP each loop.
				$recordCount = \Records::getRecordCount($project_id);
				$checkRecordNameEachLoop = $recordCount > 0 ? ((count($conditionRecords) / $recordCount) > 0.25) : true;
			}
			$recordsKeys = array_fill_keys($conditionRecords, true);

            // Build query for the list of participants
            $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM redcap_mycap_participants WHERE project_id = ".$project_id;
            $sql .= $Proj->longitudinal ? " AND event_id in (".implode(", ", array_keys($Proj->eventInfo)).")" : " AND event_id = ".$Proj->firstEventId;
            if (!empty($_GET['filterRecord'])) {
                $sql .= " AND record = '".db_escape($_GET['filterRecord'])."'";
			} elseif (!$checkRecordNameEachLoop && !empty($conditionRecords)) {
				$sql_records = " AND record in (" . prep_implode($conditionRecords) . ")";
				if (strlen($sql.$sql_records) > 1000000) {
					$checkRecordNameEachLoop = true;
				} else {
					$sql .= $sql_records;
				}
			}
            if (!empty($_GET['filterParticipant'])) $sql .= " AND code = '".db_escape($_GET['filterParticipant'])."'";
            if (!empty($codesList)) $sql .= " AND code IN (".prep_implode($codesList).")";
            $sql .= "\nORDER BY record regexp '^[A-Z]', abs(record), left(record,1), CONVERT(SUBSTRING_INDEX(record,'-',-1),UNSIGNED INTEGER), CONVERT(SUBSTRING_INDEX(record,'_',-1),UNSIGNED INTEGER), record";
            $sql .= $limit_begin;
	        $q = db_query($sql);
            $sqlRows = [];
	        while ($row = db_fetch_assoc($q)) {
				// If we need to validate the record name in each loop, then check.
				if ($checkRecordNameEachLoop && !isset($recordsKeys[$row['record']])) continue;
                // Add to array
		        $sqlRows[] = $row;
	        }
            unset($recordsKeys);
	        $total_found_rows = db_result(db_query('SELECT FOUND_ROWS()')) ?? 0;

	        // Pre-fetch participant data to use below for condition, baseline data field, and participant_custom_field
			$dataFields = array_keys(getBracketedFields($condition, true, true, true));
	        $baseline_field = $myCapProj->project['baseline_date_field'] ?? "";
	        if (strpos($baseline_field, "|") !== false) {
                $baseline_fields = explode("|", $baseline_field);
	        } else {
		        $baseline_fields = [$baseline_field];
            }
            foreach ($baseline_fields as &$baseline_field) {
	            if (strpos($baseline_field, "-") !== false) $baseline_field = explode("-", $baseline_field, 2)[1];
	            if ($baseline_field != "") $dataFields[] = $baseline_field;
            }
	        $dataFields = array_unique($dataFields);
            if (($myCapProj->project['participant_custom_field']??'') == '') {
				$dataFields = array_merge($dataFields, array_keys(getBracketedFields($myCapProj->project['participant_custom_label']??'', true, true, true)));
            } else {
				$dataFields[] = $myCapProj->project['participant_custom_field'];
            }
	        $getDataParams = ['project_id'=>$project_id, 'groups'=>$user_rights['group_id'], 'fields'=>array_merge([$Proj->table_pk],$dataFields),
				              'records'=>$conditionRecords, 'returnBlankForGrayFormStatus'=>true, 'returnEmptyEvents'=>true];
            unset($conditionRecords);
	        $data = empty($dataFields) ? [] : \Records::getData($getDataParams);

            foreach ($sqlRows as $key=>$row)
            {
                // Filter by begin time - Install Date
                if (isset($filterIBeginTimeYmd) && substr($row['join_date'], 0, 16) < $filterIBeginTimeYmd) {
                    unset($row); continue;
                }
                // Filter by end time - Install Date
                if (isset($filterIEndTimeYmd) && substr($row['join_date'], 0, 16) > $filterIEndTimeYmd) {
                    unset($row); continue;
                }
                // Get baseline date value
                $baselineDateIdentifierYmd = $baseline_date_identifier = self::getBaselineDateIdentifier($row['record'], $project_id, $row['event_id'], true, $data);
                // Filter by begin time - Baseline Date
                if (isset($filterBBeginTimeYmd) && substr($baselineDateIdentifierYmd, 0, 16) < $filterBBeginTimeYmd) {
                    unset($row); continue;
                }
                // Filter by end time - Baseline Date
                if (isset($filterBEndTimeYmd) && substr($baselineDateIdentifierYmd, 0, 16) > $filterBEndTimeYmd) {
                    unset($row); continue;
                }
                // Set with identifier, and basic defaults for counts
                $part_list[$row['code']] = array(
                    'record' => $row['record'],
                    'event_id' => $row['event_id'],
                    'repeat_instance' => 1,
                    'identifier' => self::getParticipantIdentifier($row['record'], $project_id, null, $row['event_id'], $data),
                    'join_date' => (!empty($row['join_date'])) ? \DateTimeRC::format_user_datetime($row['join_date'], 'Y-M-D_24') : '-',
                    'join_date_utc' => (!empty($row['join_date_utc'])) ? \DateTimeRC::format_user_datetime($row['join_date_utc'], 'Y-M-D_24') : '',
                    'timezone' => $row['timezone'],
                    'baseline_date' => (!empty($baseline_date_identifier) ? \DateTimeRC::format_user_datetime($baseline_date_identifier, 'Y-M-D_24') : '-'),
                    'is_deleted' => $row['is_deleted']
                );
                unset($sqlRows[$key]);
            }
        }

	    // If we're filtering by install/baseline date, then $participantCount should reflect the filtered amount, not the count of all participants
	    if ($condition != "" || isset($filterIBeginTimeYmd) || isset($filterIEndTimeYmd) || isset($filterBBeginTimeYmd) || isset($filterBEndTimeYmd)) {
		    $total_found_rows = count($part_list);
	    }

        // Truncate part_list array to limit to a specific page
        if ($_GET['pagenum'] != 'ALL' && $limit_begin == "") {
			$num_pages = ceil($total_found_rows/self::PARTICIPANTS_PER_PAGE);
			$page_num_array_slice = 1;
			if (isset($_GET['pagenum']) && $_GET['pagenum'] == 'last') {
				$page_num_array_slice = $num_pages;
			} elseif (isset($_GET['pagenum']) && isinteger($_GET['pagenum']) && $_GET['pagenum'] > 0) {
				$page_num_array_slice = $_GET['pagenum'];
			}
			$limit_array_slice = ($page_num_array_slice - 1) * self::PARTICIPANTS_PER_PAGE;
			$part_list = array_slice($part_list, $limit_array_slice, self::PARTICIPANTS_PER_PAGE);
		}

        // Return array of the participant attributes & a count of the total participants that exist
        return [$part_list, $total_found_rows];
    }

    /**
     * Get participant identifier details by record
     *
     * @param string $record
     * @return string
     */
    public static function getParticipantIdentifier($record, $projectId = null, $participantCode=null, $eventId = null, $data = [])
    {

        if (!isinteger($projectId) && $participantCode != null) {
            $sql = "select project_id from redcap_mycap_participants where code = '".db_escape($participantCode)."'";
            $q = db_query($sql);
            if (db_num_rows($q)) {
                $projectId = db_result($q, 0);
            }
            $Proj = new \Project($projectId);
        } elseif (!isinteger($projectId) && defined("PROJECT_ID")) {
            $projectId = PROJECT_ID;
            global $Proj;
        }
        $myCapProj = new MyCap($projectId);
        $participant_identifier = (($myCapProj->project['participant_custom_field']??'') == '') ? ($myCapProj->project['participant_custom_label']??'') : "[".$myCapProj->project['participant_custom_field']."]";
        $identifier =  \Piping::replaceVariablesInLabel($participant_identifier, $record, $eventId, 1, $data, false, $projectId, false);
        return $identifier;
    }

    /**
     * Get baseline identifier details by record
     *
     * @param string $record
     * @param integer $projectId
     * @param integer $eventId
     * @param boolean $convertDateFormat
     *
     * @return string
     */
    public static function getBaselineDateIdentifier($record, $projectId=null, $eventId = null, $convertDateFormat=true, $data=[])
    {
        global $myCapProj;
        if (is_null($myCapProj) && !is_null($projectId)) {
            $myCapProj = new MyCap($projectId);
        }
        if (!is_null($projectId)) {
            $Proj = new \Project($projectId);
        } else {
            global $Proj;
            $projectId = defined("PROJECT_ID") ? PROJECT_ID : $Proj->project_id;
        }

        $field = (!isset($myCapProj->project['baseline_date_field']) || $myCapProj->project['baseline_date_field'] == '') ? '' : $myCapProj->project['baseline_date_field'];

        $baseline_field = $myCapProj->project['baseline_date_field'];

        if ($Proj->longitudinal) {
            if ($Proj->multiple_arms) {
                $arm = $Proj->eventInfo[$eventId]['arm_num'];
                $eventsInArm = $Proj->getEventsByArmNum($arm);
                $fields = explode("|", $baseline_field??"");
                foreach ($fields as $field1) {
	                $parts = explode("-", $field1);
	                if (count($parts) >= 2) {
		                list($event_id, $field_name) = $parts;
	                } else {
		                $event_id = $field1;
		                $field_name = null;
	                }
	                if (in_array($event_id, $eventsInArm)) {
                        $eventId = $event_id;
                        $baseline_field = $field_name;
                        $field = $baseline_field;
                        break;
                    }
                }
            } else {
                $date_arr = explode("-", ($myCapProj->project['baseline_date_field']??""));
                if (count($date_arr) > 1) {
                    list ($eventId, $baseline_field) = $date_arr;
                    $field = $baseline_field;
                }
            }
        }
        $this_field = (!isset($myCapProj->project['baseline_date_field']) || $myCapProj->project['baseline_date_field'] == '') ? '' : "[".$baseline_field."]";
        $identifier = \Piping::replaceVariablesInLabel($this_field, $record, $eventId, 1, $data, false, $projectId, false);

        // Convert date value to y-m-d format
        if ($convertDateFormat
                && isset($Proj->metadata[$field])
                && $Proj->metadata[$field]['element_type'] == 'text'
                && substr($Proj->metadata[$field]['element_validation_type'], 0, 4) == "date"
                && (substr($Proj->metadata[$field]['element_validation_type'], -4) == "_dmy" || substr($Proj->metadata[$field]['element_validation_type'], -4) == "_mdy"))
        {
            $thisValType = $Proj->metadata[$field]['element_validation_type'];
            if (in_array($thisValType, array('date_mdy', 'datetime_mdy', 'datetime_seconds_mdy', 'date_dmy', 'datetime_dmy', 'datetime_seconds_dmy'))) {
                $identifier = \DateTimeRC::datetimeConvert($identifier, substr($thisValType, -3), 'ymd');
            }
        }

        return $identifier;
    }
    /**
     * Make QR code for a participant. Optionally overlay an image onto the QR code.
     *
     * @param string $endpoint
     * @param string $project_code
     * @param string $par_code
     * @param string $overlayPngPath
     * @return string
     */
    public static function makeParticipantImage($endpoint, $project_code, $par_code, $overlayPngPath = '', $outfile=null)
    {
        $participant_link = Participant::makeParticipantmakeJoinUrl(
            $endpoint,
            $project_code,
            $par_code
        );
        // After scanning qr code it will show payload as "app joining link" instead of json "{"endpoint":"...", "studyCode": "...", "participantCode":"..."}"
        return ParticipantQRCode::makeBase64($participant_link, $overlayPngPath, $outfile);
    }

    /**
     * Generate unique code for MyCap project
     *
     * @param integer $project_id
     * @return string $code
     */
    public static function generateUniqueCode($project_id)
    {
        do {
            // Excluding letters I & O and number 0 to avoid confusion
            $code = 'U-' . MyCap::generateRandomString(20);
            $sql = "SELECT * FROM redcap_mycap_participants WHERE project_id = ".$project_id." AND code = '".db_escape($code)."'";
            $q = db_query($sql);
            $count = db_num_rows($q);
        } while ($count > 0);

        return $code;
    }

    /**
     * Save into mycap participant db table.
     *
     * @param integer $project_id
     * @param string $record
     * @param integer $event_id
     * @return void
     */
    public static function saveParticipant($project_id, $record, $event_id = '')
    {
        $Proj = new \Project($project_id);
        if ($event_id == '') {
            $event_id = $Proj->firstEventId;
        }

        $code = self::generateUniqueCode($project_id);
        $sql = "INSERT INTO redcap_mycap_participants (code, project_id, record, event_id) VALUES
                ('".db_escape($code)."', ".$project_id.", '".db_escape($record)."', '".$event_id."')";
        db_query($sql);

        self::updateMyCapParticipantCodeFields($project_id, $record, $code);
    }

    /**
     * Get Participant code for a record.
     *
     * @param integer $project_id
     * @param string $record
     * @return string
     */
    public static function getRecordParticipantCode($project_id=null, $record=null, $event_id = null)
    {
        // Verify project_id as numeric
        if (!is_numeric($project_id)) return false;
        $Proj = new \Project($project_id);
        // Make sure record is not null
        if ($record == null) return false;
        $condition = "";
        if ($Proj->longitudinal) {
            if ($event_id != null) {
                // Fix: Smart var [mycap-participant-code] - Check in list of events belongs to an arm as this should return a value if $_GET['event_id'] is set which is different from event stored in redcap_mycap_participant table
                $arm = $Proj->eventInfo[$event_id]['arm_num'];
                $eventsInArm = $Proj->getEventsByArmNum($arm);
                $condition = " AND event_id IN (".prep_implode($eventsInArm).")";
            }
        }

        // Query data table
        $sql = "SELECT code FROM redcap_mycap_participants WHERE project_id = ".$project_id." AND record = '".db_escape($record)."'".$condition;
        $q = db_query($sql);
        if (!$q || ($q && !db_num_rows($q))) return false;
        // Get participant_code
        $participant_code = db_result($q, 0);
        // Return participant_code
        return $participant_code;
    }

    /**
     * Get participants and records dropdown list for filtering
     *
     * @param integer $project_id
     * @return array
     */
    public static function getDropDownList($project_id)
    {
        global $lang, $user_rights, $myCapProj, $Proj;

		$condition = $myCapProj->project['participant_allow_condition'] ?? "";
		if (($myCapProj->project['participant_custom_field']??'') == '') {
			$dataFields = array_keys(getBracketedFields($myCapProj->project['participant_custom_label']??'', true, true, true));
		} else {
			$dataFields = [$myCapProj->project['participant_custom_field']];
		}

		$getDataParams = ['project_id'=>PROJECT_ID, 'groups'=>$user_rights['group_id'], 'fields'=>array_merge([$Proj->table_pk],$dataFields), 'filterLogic'=>$condition];
		$all_records_values = \Records::getData($getDataParams);
		$all_record_names = array_keys($all_records_values);
		$all_records = array('' => $lang['reporting_37']) + array_combine($all_record_names, $all_record_names);

        $sql = "SELECT record, event_id, code FROM redcap_mycap_participants 
                WHERE project_id = $project_id
                ORDER BY record regexp '^[A-Z]', abs(record), left(record,1), CONVERT(SUBSTRING_INDEX(record,'-',-1),UNSIGNED INTEGER), CONVERT(SUBSTRING_INDEX(record,'_',-1),UNSIGNED INTEGER), record";
        $q = db_query($sql);
		$all_participants = array('' => $lang['mycap_mobile_app_365']);
        while ($row = db_fetch_assoc($q)) {
            if (!isset($all_record_names[$row['record']])) continue;
			$all_participants[$row['code']] = self::getParticipantIdentifier($row['record'], $project_id, null, $row['event_id'], $all_records_values);
		}

        // Return array
        return array($all_participants, $all_records);
    }

    /**
     * Display Allow participant logic section
     *
     * @return string
     */
    public static function displayLogicTable()
    {
        global $lang, $longitudinal, $myCapProj;

        // Instructions
        $html = RCView::div(array('style' => 'margin:0 0 5px;'),
            $lang['mycap_mobile_app_366']
        );
        $html .= RCView::div(array('style' => 'color:green;font-size:11px;'),
            $lang['mycap_mobile_app_368']
        );
        $participant_allow_logic = $myCapProj->project['participant_allow_condition'];
        ob_start();
        ?>
        <style type="text/css">
            .form-control-custom textarea{
                display: block;
                width: 100%;
                height: 32px;
                padding: 4px 8px;
                font-size: 13px;
                line-height: 1.42857143;
                color: #555;
                background-color: #fff;
                background-image: none;
                border: 1px solid #ccc;
                border-radius: 4px;
                -webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
                box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
                -webkit-transition: border-color ease-in-out .15s,-webkit-box-shadow ease-in-out .15s;
                -o-transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
                transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
            }
            .form-control-custom textarea{
                height: 100%;
            }
        </style>
        <form id="LogicForm">
            <div>
                <div class="form-control-custom" style="overflow:hidden;color:#A00000;background-color:#f7f7f7;border:1px solid #ddd;margin:10px 0 30px;" >
                    <table width="100%">
                        <tr>
                            <td class="external-modules-input-td pb-0 ps-3">
                                <div class="mb-1 boldish condition-andor-text2"><?=$lang['mycap_mobile_app_367']?></div>
                                <textarea tabindex="-1" id="allow-participant-condition" name="allow-participant-condition" onfocus="openLogicEditor($(this))" class="external-modules-input-element ms-4" style="max-width:95%;" onkeydown="logicSuggestSearchTip(this, event);" onblur="validate_logic($(this).val());"><?php echo htmlspecialchars(label_decode($participant_allow_logic), ENT_QUOTES) ?></textarea>
                                <div class="clearfix">
                                    <div class='my-1 ms-4 fs11 float-start text-secondary'><?php echo ($longitudinal ? "(e.g., [enrollment_arm_1][age] > 30 and [enrollment_arm_1][sex] = \"1\")" : "(e.g., [age] > 30 and [sex] = \"1\")") ?></div>
                                    <div class="float-end"><a href="javascript:;" class="opacity75" style="text-decoration:underline;font-size:11px;font-weight:normal;" onclick="helpPopup('5','category_33_question_1_tab_5')" ;"=""><?php echo $lang['survey_527']; ?></a></div>
                                </div>
                                <div id='allow-participant-condition_Ok' class='logicValidatorOkay ms-4'></div>
                                <script type='text/javascript'>logicValidate($('#allow-participant-condition'), false, 1);</script>
                                <?php
                                print logicAdd("allow-participant-condition");
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </form>
        <?php
        $html .= ob_get_clean();

        // Return all html to display
        return $html;
    }

    /**
     * Generate Participant URL
     *
     * @param string $endpoint
     * @param string $project_code
     * @param string $par_code
     * @return string
     */
    public static function makeParticipantmakeJoinUrl($endpoint, $project_code, $par_code) {
        $projectId = MyCap::getProjectIdByCode($project_code);
        if (!isinteger($projectId)) return "";
        $myCapProj = new MyCap($projectId);
        $payload = [
            "endpoint" => $endpoint,
            "project" => $project_code,
        ];
        // This is not a security mechanism. This exists solely to
        // make the JSON URL-friendly and hopefully shorten the
        // URL.
        $base64Payload = (base64_encode(json_encode($payload)));
        $paramaters = [
            'payload' => $base64Payload,
            'participant' => $par_code,
            'isFlutter' => $myCapProj->project['converted_to_flutter']
        ];
        return DynamicLink::makeJoinUrl($paramaters);
    }

    /**
     * Generate HTML template for sending joining info to particpant depending on type
     *
     * @param string $type
     * @return string
     */
    public static function getTemplateMessage($type = 'qr', $preview=false)
    {
        global $lang;
        $myCapProj = new MyCap(PROJECT_ID);
        $project_id = "[project-id]";
        $par_code = "[mycap-participant-code]";
        if ($preview && defined("PROJECT_ID")) {
            $project_id .= '&amp;preview_pid='.PROJECT_ID;
        }

        $isFlutter = $myCapProj->project['converted_to_flutter'];
        switch ($type) {
            case 'qr':
                $message = '<b>'.$lang['mycap_mobile_app_742'].'</b>
<ol>
    <li>'.($isFlutter ? $lang['mycap_mobile_app_768'] : $lang['mycap_mobile_app_803']).' <a href="'.($isFlutter ? MyCap::URL_FLUTTER_IOS_APP_STORE : MyCap::URL_IOS_APP_STORE).'"><u>'.$lang['mycap_mobile_app_744'].'</u></a>'.$lang['mycap_mobile_app_745'].' <a href="'.($isFlutter ? MyCap::URL_FLUTTER_GOOGLE_PLAY_STORE : MyCap::URL_GOOGLE_PLAY_STORE).'"><u>'.$lang['mycap_mobile_app_746'].'</u></a>).</li>
    <li> '.($isFlutter ? $lang['mycap_mobile_app_947'] : $lang['mycap_mobile_app_804']).'<br /><br /><img src="'.APP_PATH_WEBROOT_FULL.'redcap_v'.REDCAP_VERSION.'/MyCap/participant_info.php?action=displayParticipantQrCode&amp;pid='.$project_id.'&amp;par_code='.$par_code.'" width="285" height="285" style="margin-top:5px;width:285px;height:285px;border:1px solid #ccc;" /></li>
</ol>';
                break;
            case 'url':
                $message = '<b>'.$lang['mycap_mobile_app_742'].'</b>
<ol>
    <li>[mycap-participant-link:'.$lang['piping_105'].'] '.$lang['mycap_mobile_app_950'].'</li>
    <li>'.($isFlutter ? $lang['mycap_mobile_app_949'] : $lang['mycap_mobile_app_805']).'</li>
</ol>';
                break;
            case 'both':
                $message = '<b>'.$lang['mycap_mobile_app_750'].'</b>
<ol>
    <li>[mycap-participant-link:'.$lang['piping_105'].'] '.($isFlutter ? $lang['mycap_mobile_app_950'].' '.$lang['mycap_mobile_app_949'] : $lang['mycap_mobile_app_806']).'</li>
    <li>'.($isFlutter ? $lang['mycap_mobile_app_776'] : $lang['mycap_mobile_app_807']).' <a href="'.($isFlutter ? MyCap::URL_FLUTTER_IOS_APP_STORE : MyCap::URL_IOS_APP_STORE).'"><u>'.$lang['mycap_mobile_app_744'].'</u></a>'.$lang['mycap_mobile_app_745'].' <a href="'.($isFlutter ? MyCap::URL_FLUTTER_GOOGLE_PLAY_STORE : MyCap::URL_GOOGLE_PLAY_STORE).'"><u>'.$lang['mycap_mobile_app_746'].'</u></a>)'.($isFlutter ? $lang['mycap_mobile_app_948'] : $lang['mycap_mobile_app_808']).'<br /><br /><img src="'.APP_PATH_WEBROOT_FULL.'redcap_v'.REDCAP_VERSION.'/MyCap/participant_info.php?action=displayParticipantQrCode&amp;pid='.$project_id.'&amp;par_code='.$par_code.'" width="285" height="285" style="margin-top:5px;width:285px;height:285px;border:1px solid #ccc;" /></li>
</ol>';
                break;
            default:
                $message = $lang['dataqueries_57'];
        }
        $text = ($_GET['pid'] != '' && $_GET['record'] != '') ? \Piping::pipeSpecialTags($message, $_GET['pid'], $_GET['record'], $_GET['event_id']) : $message;
        $text = str_replace(["\r", "\n", "\t"], '', $text);
        return $text;
    }

    /**
     * Validate if passed participant code is valid
     *
     * @param string $participantCode
     * @return string
     */
    public static function isValidParticipant($participantCode, $projectId = null, $checkDeleted = true) {
        if (is_null($projectId)) {
            $projectId = PROJECT_ID;
        }
        // some basic checks before going to the DB
        if (empty($participantCode) || strlen($participantCode) !== 22 || !preg_match('/[A-Z0-9]/', $participantCode)) {
            return false;
        }
        $sql = "SELECT COUNT(*) AS matchCount FROM redcap_mycap_participants WHERE project_id = ".$projectId." AND code = '".db_escape($participantCode)."'";
        if ($checkDeleted) $sql .= " AND is_deleted = 0";

        $q = db_query($sql);
        $total = db_result($q, 0, 'matchCount');

        return ($total == 1) ? true : false;
    }

    /**
     * Return participant details
     *
     * @param int $participantCode
     * @return array $details
     */
    public static function getParticipantDetails($participantCode)
    {
        $details = array();

        // Get main attributes
        $sql = "SELECT * FROM redcap_mycap_participants 
                WHERE code='".db_escape($participantCode)."' ".(defined("PROJECT_ID") ? "AND project_id = ".PROJECT_ID : "");
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Add to participants array
            $identifier = self::getParticipantIdentifier($row['record'], (defined("PROJECT_ID") ? PROJECT_ID : null), $participantCode, $row['event_id']);

            $details[$row['code']] = array('record' => $row['record'],
                                            'repeat_instance' => 1,
                                            'identifier' => $identifier,
                                            'event_id' => $row['event_id'],
                                            'join_date' => (!empty($row['join_date'])) ? \DateTimeRC::format_user_datetime($row['join_date'], 'Y-M-D_24') : '-',
                                            'baseline_date' => (!empty($row['baseline_date'])) ? \DateTimeRC::format_user_datetime($row['baseline_date'], 'Y-M-D_24') : '-',
                                            'push_notification_ids' => $row['push_notification_ids'],
                                            'is_deleted' => $row['is_deleted']);
        }

        // If no participant, then return empty array
        if (empty($details)) return array();

        return $details;
    }

    /**
     * Get participants dropdown list for filtering in sync issues - values will be code
     * returns all participants (do not test allow logic)
     *
     * @param integer $project_id
     * @return array
     */
    public static function getAllParticipantCodesDropDownList($project_id)
    {
        global $lang, $user_rights;

        $filterByGroupID = ($user_rights['group_id'] != '' ? $user_rights['group_id'] : array());
        // Format $filterByGroupID as array
        if (!is_array($filterByGroupID) && $filterByGroupID == '0') {
            // If passing group_id as "0", assume we want to return unassigned records.
        } elseif (!empty($filterByGroupID) && is_numeric($filterByGroupID)) {
            $filterByGroupID = array($filterByGroupID);
        } elseif (!is_array($filterByGroupID)) {
            $filterByGroupID = array();
        }

        $sql = "SELECT p.* FROM redcap_mycap_participants p, redcap_record_list AS rl 
                WHERE p.project_id = rl.project_id and p.project_id = ".$project_id." AND p.record = rl.record";
        if (!is_array($filterByGroupID) && $filterByGroupID == '0') {
            $sql .= " AND rl.dag_id is null";
        } elseif (!empty($filterByGroupID)) {
            $sql .= " AND rl.dag_id in (".prep_implode($filterByGroupID).")";
        }

        $sql .= " ORDER BY p.record";
        $q = db_query($sql);
        $all_participants = array('' => $lang['mycap_mobile_app_365']);

        while ($row = db_fetch_assoc($q))
        {
            $all_participants[$row['code']] = self::getParticipantIdentifier($row['record'], $project_id, null, $row['event_id']);
        }

        // Return array
        return $all_participants;
    }

    /**
     * Return all participants (unless one is specified explicitly) as an array of their attributes
     *
     * @param int $project_id
     * @param string $par_code
     * @return array
     */
    public static function getParticipants($project_id, $par_code='')
    {
        $pars = array();

        // Get main attributes
        $sql = "SELECT * FROM redcap_mycap_participants WHERE project_id = ".$project_id;

        if (!empty($par_code)) $sql .= " AND code = '".db_escape($par_code)."'";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Add to $pars array
            $pars[$row['code']] = $row;
        }
        // If no participants, then return empty array
        if (empty($pars)) return array();

        // Return array of report(s) attributes
        if ($par_code == '') {
            return $pars;
        } else {
            return $pars[$par_code];
        }
    }

    /**
     * Return all participant codes belongs to list of all dags
     *
     * @param array $groupID
     * @param integer $projectId
     * @return array
     */
    public static function getParticipantsInDAG($groupID, $projectId = null, $excludeDisabledParCodes = false) {
        if (is_null($projectId)) {
            $projectId = PROJECT_ID;
        }
        $par_codes = array();
        $sql = "SELECT p.code FROM redcap_mycap_participants AS p, redcap_record_list AS rl WHERE p.project_id = ".$projectId." AND p.record = rl.record";

        if (!is_array($groupID) && $groupID == '0') {
            $sql .= " AND rl.dag_id is null";
        } elseif (!empty($groupID)) {
            $sql .= " AND rl.dag_id in (".prep_implode($groupID).")";
        }

        if ($excludeDisabledParCodes) {
            $sql .= " AND p.is_deleted = 0";
        }
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q))
        {
            $par_codes[] = $row['code'];
        }
        return $par_codes;
    }

    /**
     * Translate all images from MyCap EM format to REDCap Core format in joining info template/message
     *
     * @param string $message
     * @param string $par_code_field
     * @return string
     */
    public static function translateJoiningInfoImages($message, $par_code_field) {
        $skipParams = array('type=module', 'prefix=mycap', 'page=web/api/index', 'NOAUTH');
        preg_match_all('/<img[^>]+>/i', $message, $images);

        foreach ($images[0] as $image) {
            $doc = new \DOMDocument();
            $doc->loadHTML($image);
            $xpath = new \DOMXPath($doc);
            $src = $xpath->evaluate("string(//img/@src)");

            $parts = explode("?", $src);

            if ($parts[0] == APP_PATH_WEBROOT_FULL.'api/') {
                $part1 = str_replace(APP_PATH_WEBROOT_FULL.'api/', APP_PATH_WEBROOT_FULL.'redcap_v'.REDCAP_VERSION.'/MyCap/participant_info.php', $parts[0]);

                $params = explode("&", $parts[1]);

                $result = array_intersect($params, $skipParams);
                if ($result == $skipParams) {
                    foreach ($params as $param) {
                        if (!in_array($param, $skipParams)) {
                            list($attr, $value) = explode("=", $param);
                            if ($attr == 'stu_code') {
                                $pid = MyCap::getProjectIdByCode($value);
                                $newAttr['pid'] = ($pid == false) ? PROJECT_ID : $pid;
                            }
                            if ($attr == 'par_code' && $value == "[".$par_code_field."]") {
                                $newAttr['par_code'] = "[mycap-participant-code]";
                            } else {
                                $newAttr['par_code'] = $value;
                            }
                            if ($attr == 'action') {
                                $newAttr['action'] = $value;
                            }
                        }
                    }
                    foreach ($newAttr as $attr => $val) {
                        $attrArr[] = $attr."=".$val;
                    }
                    $part2 = join("&amp;", $attrArr);
                }
                $newImage = $part1."?".$part2;

                $message = str_replace('&amp;', '&', $message);
                $message = str_replace($src, $newImage, $message);
            }
        }
        $message = str_replace("[".$par_code_field."]", "[mycap-participant-code]", $message);
        return $message;
    }

    /**
     * Returns true if the participant exists for record, false if not.
     *
     * @param int $project_id
     * @param string $record
     * @return boolean
     */
    public static function existParticipant($project_id, $record) {
        $sql = "SELECT 1 FROM redcap_mycap_participants WHERE project_id = '".db_escape($project_id)."' AND record = '".db_escape($record)."'";
        return db_num_rows(db_query($sql)) > 0;
    }

    /**
     * Sync Participant db table with records - Data Import/Auto Record Generation - v1.7 EM
     *
     * @param int $project_id
     * @return void
     */
    public static function fixParticipantList($project_id) {
        // Fetch all records and insert into MyCap participants db table
        if (\Records::getRecordListCacheStatus($project_id) == 'COMPLETE') {
            // Use the record list cache for speed
            $sql = "select l.record from redcap_record_list l
                    left join redcap_mycap_participants m on m.record = l.record and l.project_id = m.project_id
                    where l.project_id = $project_id and m.record is null";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                Participant::saveParticipant($project_id, $row['record']);
            }
        } else {
            // If record list cache is not complete, use slower method
            $recordNames = array_values(\Records::getRecordList($project_id));
            if (!empty($recordNames)) {
                foreach ($recordNames as $record) {
                    if (!Participant::existParticipant($project_id, $record)) {
                        Participant::saveParticipant($project_id, $record);
                    }
                }
            }
        }
    }

    /**
     * MyCap - convert all alert messages and survey completion texts having participant joining info to flutter app info
     *
     * @param int $project_id
     * @return void
     */
    public static function convertParticipantAccessHTMLToFlutter($project_id)
    {
        if (is_null($project_id)) {
            $project_id = PROJECT_ID;
            global $Proj;
        } else {
            $Proj = new \Project($project_id);
        }
        $alertsCount = 0;
        $surveysUpdated = array();
        // Update access html in all alerts
        $alertOb = new \Alerts();
        foreach ($alertOb->getAlertSettings($project_id) as $alert_id => $alert) {
            $alert_message = self::translateInstallLinkUrls($alert['alert_message']);
            if ($alert_message != $alert['alert_message']) {
                $alertsCount++;
                $sql = "UPDATE redcap_alerts SET alert_message = ".checkNull($alert_message)." WHERE project_id = ".$project_id." AND alert_id = $alert_id";
                db_query($sql);
            }
        }

        // Update access html in all surveys - completion texts
        foreach ($Proj->surveys as $this_survey_id => $survey_attr) {
            // Update Survey Completion text
            $acknowledgement = self::translateInstallLinkUrls($survey_attr['acknowledgement']);
            if ($acknowledgement != $survey_attr['acknowledgement']) {
                $surveysUpdated[] = $this_survey_id;
                $sql = "UPDATE redcap_surveys SET acknowledgement = ".checkNull($acknowledgement)." WHERE project_id = ".$project_id." AND survey_id = $this_survey_id";
                db_query($sql);
            }

            // Update Survey Confirmation Email content text
            $confirmation_email_content = self::translateInstallLinkUrls($survey_attr['confirmation_email_content']);
            if ($confirmation_email_content != $survey_attr['confirmation_email_content']) {
                $surveysUpdated[] = $this_survey_id;
                $sql = "UPDATE redcap_surveys SET confirmation_email_content = ".checkNull($confirmation_email_content)." WHERE project_id = ".$project_id." AND survey_id = $this_survey_id";
                db_query($sql);
            }

            // Update Survey Scheduler Email content text
            $email_contents  = array();
            $sql = "SELECT ss_id, email_content FROM redcap_surveys_scheduler WHERE survey_id = '".$this_survey_id."'";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q))
            {
                $email_contents[$row['ss_id']] = $row['email_content'];
            }

            if (count($email_contents) > 0) {
                foreach ($email_contents as $ssId => $emailContent) {
                    $newEmailContent = self::translateInstallLinkUrls($emailContent);
                    if ($newEmailContent != $emailContent) {
                        $surveysUpdated[] = $this_survey_id;
                        $sql = "UPDATE redcap_surveys_scheduler SET email_content = ".checkNull($newEmailContent)." WHERE ss_id = '".$ssId."'";
                        db_query($sql);
                    }
                }
            }
        }
        $surveysUpdated = array_unique($surveysUpdated);
        $surveysCount = count($surveysUpdated);

        return array($alertsCount, $surveysCount);
    }
    /**
     * MyCap - convert all alert messages and survey completion texts having install link URLs to flutter app install URLs
     *
     * @param string $message
     * @return void
     */
    public static function translateInstallLinkUrls($message)
    {
        $message = str_replace(MyCap::URL_IOS_APP_STORE, MyCap::URL_FLUTTER_IOS_APP_STORE, html_entity_decode($message));
        $message = str_replace(MyCap::URL_GOOGLE_PLAY_STORE, MyCap::URL_FLUTTER_GOOGLE_PLAY_STORE, $message);
        return $message;
    }


    /**
     * Save Participant Code to fields
     *
     * @param int $projectId
     * @param string $record
     * @param string $code
     *
     * @return void
     */
    public static function updateMyCapParticipantCodeFields($projectId, $record, $code) {
        $Proj = new \Project($projectId);

        // Update Code (if applicable) - Update field value where action tag is @MC-PARTICIPANT-CODE
        $fields = \Form::getMyCapParticipantCodeFields($projectId ?? null);
        $fields[] = $Proj->table_pk; // Add record id to return event, instance, etc.
        $records = json_decode(\REDCap::getData($projectId, 'json', [$record], $fields), true);

        $instance = null;
        $event_id = null;
        foreach ($records as $attr) {
            if ($attr['redcap_repeat_instance'] ?? null == $instance || $attr['redcap_repeat_instance'] ?? null == "") {
                foreach ($fields as $field) {
                    if ($field != $Proj->table_pk) { // Skip when its primary key
                        // Save code value
                        $record_data = [[$Proj->table_pk => $record, $field => $code]];
                        if ($Proj->longitudinal) $record_data[0]['redcap_event_name'] = $attr['redcap_event_name'];
                        $hasRepeatingInstances = ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingForm($event_id, $attr['redcap_repeat_instrument'] ?? false));
                        if ($hasRepeatingInstances) {
                            $record_data[0]['redcap_repeat_instrument'] = $attr['redcap_repeat_instrument'] ?? false;
                            $record_data[0]['redcap_repeat_instance'] = $attr['redcap_repeat_instance'] ?? false;
                        }
                        $params = ['project_id'=>$projectId, 'dataFormat'=>'json', 'data'=>json_encode($record_data)];
                        $response = \REDCap::saveData($params);
                    }
                }
            }
        }
    }

    /**
     * Display icon and popup on hover along with install date (if utc date/timezone is recorded for participant while joining app
     *
     * @param string $joinDateUTC
     * @param string $timezone
     *
     * @return string
     */
    public static function displayJoinDateAdditionalInfoPopup($joinDateUTC, $timezone) {
        $popup_content = $additional_info = '';
        if (!empty($joinDateUTC) || !empty($timezone)) {
            if (!empty($joinDateUTC)) {
                $popup_content .= "<b>".RCView::tt_js2('mycap_mobile_app_839')."</b> ".$joinDateUTC;
            }
            if (!empty($timezone)) {
                if (!empty($popup_content)) {
                    $popup_content .= '<br><br>';
                }
                $popup_content .= "<b>".RCView::tt_js2('mycap_mobile_app_840')."</b> ".$timezone;
            }
            $additional_info = '<i class="fas fa-info-circle text-secondary" data-toggle="popover" data-content="'.$popup_content.'" data-title="'.RCView::tt_js2('mycap_mobile_app_70').'" data-bs-toggle="popover" data-bs-content="'.$popup_content.'" ></i>';
        }
        return $additional_info;
    }

    /**
     * MyCap - convert all alert messages and survey completion texts having new app link URLs instead of flutter dynamic link URLs
     *
     * @param string $message
     * @return void
     */
    public static function translateToNewAppLinkUrls($message)
    {
        $message = str_replace(DynamicLink::FLUTTER_URLPREFIX_JOIN, DynamicLink::APP_LINK_URLPREFIX_JOIN, html_entity_decode($message));
        return $message;
    }
}
