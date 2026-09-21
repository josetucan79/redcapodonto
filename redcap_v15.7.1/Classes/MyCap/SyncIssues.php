<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use RCView;
use Vanderbilt\REDCap\Classes\MyCap\Api\DB\ProjectMapper;
use Vanderbilt\REDCap\Classes\MyCap\Api\Exceptions\SaveException;

class SyncIssues
{
    const ISSUES_PER_PAGE = 100;

    const ERROR_COULD_NOT_SAVE_TO_REDCAP = 1;
    const ERROR_COULD_NOT_FIND_PARTICIPANT = 2;
    const ERROR_COULD_NOT_FIND_PROJECT = 3;
    const ERROR_OTHER = 4;

    /**
     * Get table layout for listing of syncissues
     *
     * @return string
     */
    public static function renderSyncIssues()
    {
        global $lang, $user_rights, $Proj;

        // Is user in a DAG? If so, display their DAG name.
        $dagDisplay = ($user_rights['group_id'] == '') ? '' : $Proj->getGroups($user_rights['group_id']);
        if ($dagDisplay != '') {
            $dagDisplay = "<span style='color:#008000; font-weight: normal;'> ($dagDisplay)</span>";
        }

        print MyCap::getMessageContainers();
        $exampleLink = '<a href="javascript:;" onclick="simpleDialog(null,null,\'sync_issues_example\',720);" style="text-decoration:underline;margin-left:3px;">'.$lang['survey_1075'].'</a>';
        print RCView::p(array('class'=>'mt-0 mb-3', 'style'=>'max-width:900px;'), $lang['mycap_mobile_app_502'].' '.$exampleLink);

        $exampleText = '<ol>
                            <li>'.$lang['mycap_mobile_app_505'].'</li>
                            <li>'.$lang['mycap_mobile_app_520'].'</li>
                            <li>'.$lang['mycap_mobile_app_521'].'</li>
                            <li>'.$lang['mycap_mobile_app_522'].'</li>
                            <li>'.$lang['mycap_mobile_app_523'].'</li>
                            <li>'.$lang['mycap_mobile_app_524'].'</li>
                            <li>'.$lang['mycap_mobile_app_525'].'</li>
                            <li>'.$lang['mycap_mobile_app_526'].'</li>
                            <li>'.$lang['mycap_mobile_app_527'].'</li>
                            <li>'.$lang['mycap_mobile_app_528'].'</li>
                            <li>'.$lang['mycap_mobile_app_529'].'</li>
                            <li>'.$lang['mycap_mobile_app_530'].'</li>
                        </ol>';
        print '<div id="sync_issues_example" class="simpleDialog" title="'.js_escape2($lang['mycap_mobile_app_501']).' - '.js_escape2($lang['survey_101']).'">
					<h6>'.$lang['design_472'].'</h6>
					<p>'.$exampleText.'</p>
				</div>';
        print self::renderSyncIssuesList();
    }

    /**
     * Return all sync issues for project
     *
     * @param int $project_id
     * @return array $issues
     */
    public static function getSyncIssues($project_id = null)
    {
        if (is_null($project_id)) {
            global $myCapProj;
        } else {
            $myCapProj = new MyCap($project_id);
        }

        global $user_rights;
        $groupID = ($user_rights['group_id'] != '' ? $user_rights['group_id'] : array());
        $codesList = array();
        $executeSQL = true;
        // Format $codesList as array
        if (!is_array($groupID) && $groupID == '0') {
            // If passing group_id as "0", assume we want to return unassigned records.
        } elseif (!empty($groupID) && is_numeric($groupID)) {
            $codesList = Participant::getParticipantsInDAG(array($groupID));
            if (empty($codesList))  $executeSQL = false;
        } elseif (!is_array($groupID)) {
            $codesList = array();
        }

        $issues = array();

        if ($executeSQL) {
            if (isset($_GET['filterBeginTime']) && $_GET['filterBeginTime'] != '') {
                $filterBeginTimeYmd = \DateTimeRC::format_ts_to_ymd($_GET['filterBeginTime']);
            }
            if (isset($_GET['filterEndTime']) && $_GET['filterEndTime'] != '') {
                $filterEndTimeYmd = \DateTimeRC::format_ts_to_ymd($_GET['filterEndTime']);
            }

            $project_code = $myCapProj->project['code'] ?? "";

            // Get main attributes
            $sql = "SELECT * FROM redcap_mycap_syncissues WHERE project_code = '".db_escape($project_code)."'";
            if (isset($_GET['filterStatus']) && $_GET['filterStatus'] != '') $sql .= " AND resolved = '".db_escape($_GET['filterStatus'])."'";
            if (!empty($_GET['filterParticipant'])) $sql .= " AND participant_code = '".db_escape($_GET['filterParticipant'])."'";
            if (!empty($codesList)) $sql .= " AND participant_code IN (".prep_implode($codesList).")";

            $sql .= " ORDER BY received_date DESC";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $date = $row['received_date'];
                // Filter by begin time - Recieved On
                if (isset($filterBeginTimeYmd) && substr($date, 0, 16) < $filterBeginTimeYmd) {
                    unset($row);
                    continue;
                }
                // Filter by end time - Recieved On
                if (isset($filterEndTimeYmd) && substr($date, 0, 16) > $filterEndTimeYmd) {
                    unset($row);
                    continue;
                }
                // Add to messages array
                $issues[] = $row;
            }
        }

        // If no messages, then return empty array
        if (empty($issues)) return array();

        return $issues;
    }

    /**
     * Return number of sync issues of project those are in "UnResolved" status - to display with tab label at top of page
     *
     * @param int $projectId
     * @return int $num_issues
     */
    public static function getUnresolvedIssuesCount($projectId) {
        global $user_rights;
        $groupID = ($user_rights['group_id'] != '' ? $user_rights['group_id'] : array());
        $codesList = array();
        $executeSQL = true;
        $num_issues = 0;
        // Format $codesList as array
        if (!is_array($groupID) && $groupID == '0') {
            // If passing group_id as "0", assume we want to return unassigned records.
        } elseif (!empty($groupID) && is_numeric($groupID)) {
            $codesList = Participant::getParticipantsInDAG(array($groupID));
            if (empty($codesList))  $executeSQL = false;
        } elseif (!is_array($groupID)) {
            $codesList = array();
        }

        if ($executeSQL) {
            if (is_null($projectId)) {
                global $myCapProj;
            } else {
                $myCapProj = new MyCap($projectId);
            }

            $project_code = $myCapProj->project['code'] ?? "";
            if ($project_code == '') return 0;
            $sql = "SELECT COUNT(*) FROM redcap_mycap_syncissues WHERE project_code = '".db_escape($project_code)."' AND resolved = 0";
            if (!empty($codesList)) $sql .= " AND participant_code IN (".prep_implode($codesList).")";
            $num_issues = db_result(db_query($sql), 0);
        }

        return $num_issues;
    }

    /**
     * Render Sync Issues listing html
     *
     * @return string
     */
    public static function renderSyncIssuesList()
    {
        global $lang, $myCapProj;

        // Get list of sync issues to display as table
        $issues_list = self::getSyncIssues(PROJECT_ID);

        ## BUILD THE DROP-DOWN FOR PAGING THE SYNC ISSUES
        // Get issues count
        $issuesCount = count($issues_list);
        // Section the Sync Issues into multiple pages
        $num_per_page = self::ISSUES_PER_PAGE;
        // Calculate number of pages for dropdown
        $num_pages = ceil($issuesCount/$num_per_page);
        // Limit
        $limit_begin = 0;
        if (!isset($_GET['pagenum'])) $_GET['pagenum'] = 1;
        if (isset($_GET['pagenum']) && $_GET['pagenum'] == 'last') {
            $_GET['pagenum'] = $num_pages;
        }
        if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum']) && $_GET['pagenum'] > 1) {
            $limit_begin = ($_GET['pagenum'] - 1) * $num_per_page;
        }
        ## Build the paging drop-down for issues list
        $pageDropdown = "<select onchange='loadIssuesList(this.value)' style='vertical-align:middle;font-size:11px;'>";
        //Loop to create options for dropdown
        for ($i = 1; $i <= $num_pages; $i++) {
            $end_num   = $i * $num_per_page;
            $begin_num = $end_num - $num_per_page + 1;
            $value_num = $end_num - $num_per_page;
            if ($end_num > $issuesCount) $end_num = $issuesCount;
            $pageDropdown .= "<option value='$i' " . ($_GET['pagenum'] == $i ? "selected" : "") . ">$begin_num - $end_num</option>";
        }
        $pageDropdown .= "</select>";
        $pageDropdown  = "{$lang['survey_45']} $pageDropdown {$lang['survey_133']} $issuesCount";

        // If viewing ALL issues, then set $num_per_page to null to return all issues
        if (isset($_GET['pagenum']) && $_GET['pagenum'] == 'ALL') $num_per_page = null;

        $item_num = 0; // loop counter
        foreach (array_slice($issues_list, $limit_begin, $num_per_page) as $this_issue => &$attr)
        {
            $issue_id = $attr['uuid'];
            foreach ($attr as $configKey => $configVal) {
                // Store values in array to convert to JSON to use when loading the dialog
                $info_modal[$item_num][str_replace("_", "-", $configKey)] = $configVal . "";
            }
            // Trim identifier
            $received_date = \DateTimeRC::format_ts_from_ymd($attr['received_date']);
            $status = ($attr['resolved'] == 1) ? $lang['dataqueries_52'] : $lang['mycap_mobile_app_504'];
            $statusClass =  ($attr['resolved'] == 1) ? 'badge-success' : 'badge-danger';

            $participantCode = $attr['participant_code'];
            $participant_details = Participant::getParticipantDetails($participantCode);
            $identifier = $participant_details[$participantCode]['identifier'];
            $record = $participant_details[$participantCode]['record'];
            $eventId = $attr['event_id'];

            global $Proj;
            $recordLink = "DataEntry/record_home.php?pid=".PROJECT_ID."&id={$record}";
            if ($Proj->multiple_arms) {
                if ($eventId != '') {
                    $recordLink .= "&arm=" . $Proj->eventInfo[$eventId]['arm_num'];
                }
            }
            $display_id = "<a href='".APP_PATH_WEBROOT.$recordLink."' style='font-size:12px;text-decoration:underline;'>{$record}</a>";

            global $Proj;
            $instrument = $Proj->forms[$attr['instrument']]['menu'];

            // Add to array
            $issue_list_full[$i] = array();
            $issue_list_full[$i][] = "<div class='wrapemail'>{$received_date}</div>";
            $issue_list_full[$i][] = "<div class='tag badge ".$statusClass."' style='padding: 4px;'>{$status}</div>";
            $issue_list_full[$i][] = "<div class='wrapemail'>{$identifier}</div>";
            $issue_list_full[$i][] = "<div class='wrapemail'>{$display_id}</div>";
            $issue_list_full[$i][] = "<div class='wrapemail'>{$instrument}</div>";
            $issue_list_full[$i][] = "<div class='wrapemail'>{$issue_id}</div>";

            if ($issue_id != '') {
                $issue_list_full[$i][] = '<a onclick="openSyncIssueDetails(\''.$myCapProj->project['code'].'\', \''.$participantCode.'\', \''.$attr['uuid'].'\');" href="javascript:;" style="outline:none;color:green;font-family:Tahoma;font-size:12px;"><i class="fas fa-edit"></i> </a>';
                // Increment row counter
                $item_num++;
            } else {
                $issue_list_full[$i][] = '';
            }

            $i++;
            // Remove this row to save memory
            unset($issues_list[$this_issue]);
        }

        // If no issues exist yet, render one row to let user know that
        if (empty($issue_list_full))
        {
            // No issues exist yet
            $issue_list_full[0] = array(RCView::div(array('class'=>'wrap','style'=>'color:#800000;'), $lang['mycap_mobile_app_503']),"","","","","");
        }

        // Build issues list table
        $issuesTableWidth = 955;
        $issuesTableHeaders = array();
        $issuesTableHeaders[] = array(120, $lang['global_18']);
        $issuesTableHeaders[] = array(80, $lang['home_33']);
        $issuesTableHeaders[] = array(167, $lang['mycap_mobile_app_508']);
        $issuesTableHeaders[] = array(50, $lang['global_49']);
        $issuesTableHeaders[] = array(168, $lang['global_89']);
        $issuesTableHeaders[] = array(230, $lang['mycap_mobile_app_509']);
        $issuesTableHeaders[] = array(47, $lang['mobile_app_87'], "center", "string", false);

        $all_participants = Participant::getAllParticipantCodesDropDownList(PROJECT_ID);
        $all_statuses = array('' => $lang['dashboard_12'], '1' => $lang['dataqueries_52'], '0' => $lang['mycap_mobile_app_504']);
        $issuesTableTitle = RCView::div(array(),
                        RCView::div(array('style'=>'padding:2px 5px 0 5px;float:left;font-size:14px;'),
                            $lang['mycap_mobile_app_501'] . RCView::br() .
                            RCView::span(array('style'=>'line-height:24px;color:#666;font-size:11px;font-weight:normal;'),
                                $lang['mycap_mobile_app_427']
                            )
                        ) .
                        ## FILTERS
                        RCView::div(array('style'=>'max-width:500px;font-weight:normal;float:left;font-size:11px;padding-left:15px;margin-left:10px;border-left:1px solid #ccc;'),
                            // Date/time range
                            $lang['global_18']." ".$lang['survey_439'] .
                            RCView::text(array('id'=>'filterBeginTime','value'=>$_GET['filterBeginTime']??'','class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-right:8px;margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                            $lang['global_18']." ".$lang['survey_440'] .
                            RCView::text(array('id'=>'filterEndTime','value'=>(isset($_GET['filterEndTime']) ? $_GET['filterEndTime'] : ""),'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                            RCView::span(array('class'=>'df','style'=>'color:#777;'), '('.\DateTimeRC::get_user_format_label().' H:M)') . RCView::br() .
                            // Display all active participants displayed in this view
                            $lang['survey_441'] .
                            RCView::select(array('id'=>'filterParticipant','style'=>'font-size:11px;margin:2px 3px;'), $all_participants, $_GET['filterParticipant']??'',300) .
                            // Display status names displayed in this view
                            $lang['survey_441'] .
                            RCView::select(array('id'=>'filterStatus','style'=>'margin-left:3px;font-size:11px;'), $all_statuses, $_GET['filterStatus']??'',300) .
                            RCView::br() .
                            // "Apply filters" button
                            RCView::button(array('class'=>'jqbuttonsm','style'=>'margin-top:5px;font-size:11px;color:#800000;','onclick'=>"loadIssuesList(1)"), $lang['survey_442']) .
                            RCView::a(array('href'=>PAGE_FULL."?syncissues=1&pid=".PROJECT_ID,'style'=>'vertical-align:middle;margin-left:15px;text-decoration:underline;font-weight:normal;font-size:11px;'), $lang['setup_53'])
                        ) .
                        ## PAGING
                        RCView::div(array('style'=>'font-weight:normal;float:right;font-size:11px;padding-left:12px;border-left:1px solid #ccc;'),
                            $pageDropdown
                        ) .
                        RCView::div(array('class'=>'clear'), '')
                    );
        // Build Issue List
        $list = renderGrid("syncissues_table", $issuesTableTitle, $issuesTableWidth-count($issuesTableHeaders), "auto", $issuesTableHeaders, $issue_list_full);
        return "<div class='mt-3'>".$list."</div>";
    }

    /**
     * Get Sync Issue details
     *
     * @param string $projectCode
     * @param string $participantCode
     * @param string $issueId
     *
     * @return array $details
     */
    public static function getSyncIssueDetails($participantCode, $issueId)
    {
        global $Proj, $lang;
        $participant_details = Participant::getParticipantDetails($participantCode);
        $identifier = $participant_details[$participantCode]['identifier'];
        $record = $participant_details[$participantCode]['record'];

        $sql = "SELECT * FROM redcap_mycap_syncissues WHERE uuid = '".db_escape($issueId)."'";
        $q = db_query($sql);
        $details = array();
        while ($row = db_fetch_assoc($q)) {
            $formLink = "<a href='" . APP_PATH_WEBROOT . "Design/online_designer.php?pid=".$Proj->project_id."&page=".$row['instrument']."' style='font-weight:bold;text-decoration:underline;'>".$Proj->forms[$row['instrument']]['menu']."</a>";

            $details = array('result_id' => $row['uuid'],
                            'status' => ($row['resolved'] == 1) ? '<div class="tag badge badge-success" style="padding: 4px;">'.$lang['dataqueries_52'].'</div>' : '<div class="tag badge badge-danger" style="padding: 4px;">'.$lang['mycap_mobile_app_504'].'</div>',
                            'resolved_status' => $row['resolved'],
                            'resolved_by' => $row['resolved_by'],
                            'received_date' => \DateTimeRC::format_ts_from_ymd($row['received_date']),
                            'participant' => $identifier,
                            'record' => $record,
                            'instrument' => $row['instrument'],
                            'instrumentLink' => $formLink,
                            'eventId' => $row['event_id'],
                            'error_type' => $row['error_type'],
                            'error_type_text' => self::getErrorTypeText($row['error_type']),
                            'comment' => $row['resolved_comment'],
                            'error_message' => $row['error_message'],
                            'payload' => $row['payload']);
        }

        // If no participant, then return empty array
        if (empty($details)) return array();

        return $details;
    }

    /**
     * Display Sync Issue Details popup
     *
     * @param string $projectCode
     * @param string $participantCode
     * @param string $issueId
     *
     * @return string $html
     */
    public static function displaySyncIssueDetails($projectCode, $participantCode, $issueId)
    {
        global $lang;
        $participantWasLoaded = false;
        if (!Participant::isValidParticipant($participantCode)) {
            return $lang['mycap_mobile_app_510'];
        } else {
            $participantWasLoaded = true;
        }

        $issue = self::getSyncIssueDetails($participantCode, $issueId);

        $errorMessage = json_decode($issue['error_message'], true);
        $parsedIssues = [];
        if ($issue['error_type'] == self::ERROR_COULD_NOT_SAVE_TO_REDCAP) {
            $parsedIssues = SaveException::parseIssues($errorMessage);
        }

        $payload = json_decode(
            $issue['payload'],
            true
        );
        $issueData = json_decode(
            $payload['result'],
            true
        );
        if ($participantWasLoaded) {
            $projectMapper = new ProjectMapper(PROJECT_ID);
            $projectMapper->repeatInstanceName = $issue['instrument'];
            $fields = $projectMapper->dictionaryForInstruments([$issue['instrument']]);
            $projectMapper->fields = array_combine(
                array_keys($fields),
                array_keys($fields)
            );
            $annotationMap = [];
            foreach ($fields as $name => $field) {
                foreach (Task::$requiredAnnotations as $annotation) {
                    if (strpos($field['field_annotation'], $annotation) !== false) {
                        $annotationMap[$annotation] = $name;
                        continue;
                    }
                }
            }
            if (isset($issueData)) {
                foreach ($issueData as $name => $value) {
                    if (array_key_exists($name, $annotationMap)) {
                        $issueData[$annotationMap[$name]] = $value;
                        unset($issueData[$name]);
                    }
                }
            }
            $redcapData = $projectMapper->oneByFilter(
                "uuid = '".$issue['result_id']."'"
            )->results();
            if (!is_null($redcapData) && count($redcapData)) {
                $diffData = [];
                foreach ($redcapData as $name => $value) {
                    $d = [
                        'field' => $name,
                        'redcapValue' => $value,
                        'issueValue' => '',
                        'mismatch' => true
                    ];
                    if (isset($issueData) && array_key_exists($name, $issueData)) {
                        $d['issueValue'] = $issueData[$name];
                    }
                    if ($name == 'redcap_repeat_instance') {
                        $vars['redcapRepeatInstance'] = $value;
                        continue;
                    }
                    if ($name == 'event_id') {
                        $vars['eventId'] = $value;
                        continue;
                    }
                    if ($d['redcapValue'] == $d['issueValue']) {
                        $d['mismatch'] = false;
                    }
                    $diffData[] = $d;
                }
                $diffDataList = $diffData;
            }
        }
        if (!isset($diffData)) {
            $diffData = [];
            foreach ($issueData as $name => $value) {
                $d = [
                    'field' => $name,
                    'redcapValue' => 'N/A',
                    'issueValue' => $value,
                    'mismatch' => true
                ];
                $diffData[$name] = $d;
            }
            krsort($diffData);
            $diffDataList = array_values($diffData);
        }
        global $Proj;
        $reasons = array();

        $display_id = '';
        if ($issue['record'] != '') {
            $recordLink = "DataEntry/record_home.php?pid=".PROJECT_ID."&id={$issue['record']}";
            if ($Proj->multiple_arms) {
                if ($issue['eventId'] != '') {
                    $recordLink .= "&arm=" . $Proj->eventInfo[$issue['eventId']]['arm_num'];
                }
            }
            $display_id = "<a href='".APP_PATH_WEBROOT.$recordLink."' style='text-decoration:underline;'>{$issue['record']}</a>";
        }        

        ob_start();
        ?>
        <form id="SyncIssueSetupForm">
            <input type="hidden" name="issueId" value="<?php echo $issue['result_id']; ?>">
            <input type="hidden" name="participantCode" value="<?php echo $participantCode; ?>">
            <input type="hidden" name="projectCode" value="<?php echo $projectCode; ?>">
            <div style="padding-bottom:5px;line-height:14px;">
                <?php echo $lang['mycap_mobile_app_511']; ?><br><br>
            </div>
            <div>
                <div class="ui-dialog-content ui-widget-content">
                    <table width="100%">
                        <tr>
                            <td width="50%" valign="top">
                                <fieldset class="darkgreen" style="padding:0 0 0 8px;border-width:1px;margin-bottom:10px;">
                                    <legend style="font-weight:bold;color:#333;">
                                        <i class="fas fa-info-circle"></i> <?php echo $lang['mycap_mobile_app_506']; ?>
                                    </legend>
                                    <div style="padding:3px 8px 8px 2px;">
                                        <div style="color:#800000;"><b><?php echo $lang['mycap_mobile_app_512'].$lang['colon']; ?></b><span style="font-size:13px;margin-left:8px;color:#000000;"><?php echo $issue['received_date'];?></span></div>
                                        <div style="color:#800000;padding-top:5px;"><b><?php echo $lang['survey_1126']; ?></b><span style="font-size:13px;margin-left:8px;color:#000000;"><?php echo $issue['participant'];?></span>
                                            <?php if ($display_id != '') { ?>
                                                <b><?php echo RCView::tt('leftparen') . RCView::tt('dataqueries_93'). " ".$display_id . RCView::tt('rightparen'); ?> </b>
                                            <?php } ?>
                                        </div>
                                        <div style="color:#800000;padding-top:5px;"><b><?php echo $lang['dataqueries_214']; ?></b><span style="font-size:13px;margin-left:8px;color:#000000;"><?php echo $issue['status'];?></span></div>
                                        <div style="color:#800000;padding-top:5px;"><b><?php echo $lang['design_493'];?></b><span style="font-size:13px;margin-left:8px;color:#000000;"><?php echo $issue['instrumentLink'];?></span></div>
                                        <?php if ($Proj->longitudinal && $issue['eventId'] != '') { ?>
                                            <div style="color:#800000;padding-top:5px;"><b><?php echo $lang['global_242'].$lang['colon'];?></b><span style="font-size:13px;margin-left:8px;color:#000000;"><?php echo $Proj->eventInfo[$issue['eventId']]['name_ext'];?></span></div>
                                        <?php } ?>
                                        <div style="color:#800000;padding-top:5px;"><b><?php echo $lang['mycap_mobile_app_509'].$lang['colon']; ?></b><span style="font-size:13px;margin-left:8px;color:#000000;"><?php echo $issue['result_id'];?></span></div>
                                        <div style="color:#800000;padding-top:5px;"><b><?php echo $lang['mycap_mobile_app_513'].$lang['colon']; ?></b><span style="font-size:13px;margin-left:8px;color:#000000;"><?php echo $issue['error_type_text'];?></span></div>
                                    </div>
                                </fieldset>
                            </td>
                            <td width="50%" valign="top">
                                <fieldset style="padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom: 10px;">
                                    <legend style="font-weight:bold;color:#333;">
                                        <i class="fas fa-check-circle"></i> <?php echo $lang['mycap_mobile_app_514']; ?>
                                    </legend>
                                    <div style="padding:10px 0 5px 2px;">
                                        <div style="text-indent:-1.9em;margin-left:1.9em;margin-top:4px;">
                                            <input name="is_resolved" <?php echo ($issue['resolved_status'] == 1) ? 'checked="checked"' : '';?> type="checkbox">
                                            <span style="color:#800000;"><b><?php echo $lang['dataqueries_52'].$lang['questionmark']; ?></b></span>
                                        </div>
                                        <div style="text-indent:-1.9em;margin-left:1.9em; padding-top: 5px;">
                                            <span style="color:#800000;"><b><?php echo $lang['control_center_4559']; ?></b></span><br>
                                            <textarea name="resolution_comment" class="x-form-field" style="line-height:14px; height: 75px;font-size:12px;width:95%;max-width:95%;margin-top:3px;"><?php echo $issue['comment'];?></textarea>
                                        </div>
                                    </div>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="projhdr"><?php echo $lang['mycap_mobile_app_515'];?></div>
                <div>
                    <table cellspacing="1" width="100%">
                        <?php if (count($parsedIssues) > 0) {
                            ?>
                            <tr>
                                <td style="padding:6px;font-size:13px;border:1px solid #ccc;background-color:#D7D7D7;color:#000;font-weight:bold;"><?php echo $lang['mycap_mobile_app_516']; ?></td>
                                <td style="padding:6px;font-size:13px;border:1px solid #ccc;background-color:#D7D7D7;color:#000;font-weight:bold;"><?php echo $lang['data_import_tool_99']; ?></td>
                                <td style="padding:6px;font-size:13px;border:1px solid #ccc;background-color:#D7D7D7;color:#000;font-weight:bold;"><?php echo $lang['global_20'];?></td>
                                <td style="padding:6px;font-size:13px;border:1px solid #ccc;background-color:#D7D7D7;color:#000;font-weight:bold;"><?php echo $lang['data_export_tool_274'];?></td>
                            </tr>
                            <?php
                            foreach ($parsedIssues as $parsedIssue) {
                                $reasons[$parsedIssue['key']] = $parsedIssue['description'];
                                ?>
                                <tr class="gray">
                                    <?php if ($parsedIssue['parseSuccessful']) { ?>
                                    <td style="padding: 6px; border: 1px solid #ccc;"><?php echo $parsedIssue['key']; ?></td>
                                    <td style="padding: 6px; border: 1px solid #ccc;"><?php echo $parsedIssue['val']; ?></td>
                                    <td style="padding: 6px; border: 1px solid #ccc;"><?php echo $parsedIssue['description']; ?></td>
                                    <?php } else { ?>
                                    <td style="padding: 6px; border: 1px solid #ccc;"></td>
                                    <td style="padding: 6px; border: 1px solid #ccc;"></td>
                                    <td style="padding: 6px; border: 1px solid #ccc;">MyCap was unable to parse the error</td>
                                    <?php } ?>
                                    <td style="padding: 6px; border: 1px solid #ccc;"><?php echo $parsedIssue['raw']; ?></td>
                                </tr>
                        <?php }
                        } else if (count($errorMessage)) {
                            foreach ($errorMessage as $message) { ?>
                            <tr class="gray">
                                <td style="padding: 6px; border: 1px solid #ccc;"><?php echo $message; ?></td>
                            </tr>
                        <?php }
                        }?>
                    </table>
                </div>
                <div class="projhdr"><?php echo $issue['instrumentLink'];?></div>
                <div>
                    <table cellspacing="1" width="100%">
                        <tr>
                            <td width="4%" style="padding:6px;font-size:13px;border:1px solid #ccc;background-color:#D7D7D7;color:#000;font-weight:bold;"></td>
                            <td width="40%" style="padding:6px;font-size:13px;border:1px solid #ccc;background-color:#D7D7D7;color:#000;font-weight:bold;"><?php echo $lang['graphical_view_23']; ?></td>
                            <td width="15%" style="padding:6px;font-size:13px;border:1px solid #ccc;background-color:#D7D7D7;color:#000;font-weight:bold;"><?php echo $lang['api_101'];?></td>
                            <td width="15%" style="padding:6px;font-size:13px;border:1px solid #ccc;background-color:#D7D7D7;color:#000;font-weight:bold;"><?php echo $lang['mycap_mobile_app_101'];?></td>
                            <td width="25%" style="padding:6px;font-size:13px;border:1px solid #ccc;background-color:#D7D7D7;color:#000;font-weight:bold;">Reason</td>
                        </tr>
                        <?php foreach ($diffDataList as $diffData) { ?>
                                <tr class="gray">
                                    <td style="padding: 6px; border: 1px solid #ccc;"><?php echo $diffData['mismatch'] ? '<i class="fas fa-info-circle" style="color: red;"></i>' : '<i class="fas fa-check-circle" style="color: green;"></i>'; ?></td>
                                    <td style="padding: 6px; border: 1px solid #ccc;"><?php echo $diffData['field'].' <code>"'.$Proj->metadata[$diffData['field']]['element_label'].'"</code>'; ?></td>
                                    <td style="padding: 6px; border: 1px solid #ccc;"><?php echo $diffData['redcapValue']; ?></td>
                                    <td style="padding: 6px; border: 1px solid #ccc;"><?php echo $diffData['issueValue']; ?></td>
                                    <td style="padding: 6px; border: 1px solid #ccc;"><?php echo $reasons[$diffData['field']]; ?></td>
                                </tr>
                            <?php } ?>
                    </table>
                </div>
            </div>
        </form>
        <?php
        $html = ob_get_clean();

        return $html;
    }

    /**
     * Returns error description text based on error_type
     * 1: REDCap Save, 2: Could not find participant, 3: Could not find project, 4: Other
     *
     * @return string
     */
    public static function getErrorTypeText($errorType)
    {
        global $lang;
        switch ($errorType) {
            case self::ERROR_COULD_NOT_SAVE_TO_REDCAP:
                return $lang['mycap_mobile_app_517'];
            case self::ERROR_COULD_NOT_FIND_PARTICIPANT:
                return $lang['mycap_mobile_app_518'];
            case self::ERROR_COULD_NOT_FIND_PROJECT:
                return $lang['mycap_mobile_app_519'];
            case self::ERROR_OTHER:
                return $lang['create_project_19'];
        }
    }

    /**
     * Save Sync Issue
     *
     * @param array $data
     * @return integer|\Exception
     */
    public static function save($data)
    {
        $details = self::getSyncIssueDetails($data['participantCode'], $data['uuid']);
        if (!empty($details)) {
            $sql = "UPDATE redcap_mycap_syncissues SET
                        received_date = '" . db_escape($data['receivedDate']) . "',
                        participant_code =  '" . db_escape($data['participantCode']) . "',
                        project_code = '" . db_escape($data['projectCode']) . "',
                        payload = '" . db_escape($data['payload']) . "',
                        instrument = '" . db_escape($data['instrument']) . "',
                        event_id = " . checkNull($data['eventId']) . ",
                        error_type = '" . db_escape($data['errorType']) . "',
                        error_message =  '" . db_escape($data['errorMessage']) . "',
                        resolved = '0'
                    WHERE
                        uuid = '" . db_escape($data['uuid']) . "'";
            if (db_query($sql)) {
                $affected = db_affected_rows();
            } else {
                throw new \Exception("Could not update synchronization issue: ".$data['uuid']);
            }
        } else {
            $sql = "INSERT INTO redcap_mycap_syncissues (
                        uuid, 
                        received_date, 
                        participant_code, 
                        project_code, 
                        payload, 
                        instrument,
                        event_id,
                        error_type,
                        error_message
                    ) VALUES (
                        '" . db_escape($data['uuid']) . "',
                        '" . db_escape($data['receivedDate']) . "',
                        '" . db_escape($data['participantCode']) . "',
                        '" . db_escape($data['projectCode']) . "',
                        '" . db_escape($data['payload']) . "',
                        '" . db_escape($data['instrument']) . "',
                        " . checkNull($data['eventId']) . ",
                        '" . db_escape($data['errorType']) . "',
                        '" . db_escape($data['errorMessage']) . "'
                    )";
            if (db_query($sql)) {
                $affected = db_affected_rows();
            } else {
                throw new \Exception("Could not insert synchronization issue: ".$data['uuid']);
            }
        }

        return $affected;
    }

    /**
     * Save Sync Issue File
     *
     * @param array $data
     * @return integer|\Exception
     */
    public static function saveFile($data)
    {
        $sql = "INSERT INTO redcap_mycap_syncissuefiles (
                        uuid, 
                        doc_id
                    ) VALUES (
                        '" . db_escape($data['uuid']) . "',
                        '" . db_escape($data['docId']) . "'			        
                    )";
        if (db_query($sql)) {
            $affected = db_affected_rows();
        } else {
            throw new \Exception("Could not insert synchronization issue file: ".$data['uuid'].", ".$data['docId']);
        }

        return $affected;
    }
}