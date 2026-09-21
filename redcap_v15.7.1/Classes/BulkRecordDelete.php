<?php

class BulkRecordDelete
{
    public $canDelete = false;

    public $errors = array();
    public $notes = array();

    public $group_id = null;

    public $arm = null;
    public $arm_id = null;

    public const FETCH_RECORDS_LIMIT = 10000;

    public function init()
    {
        $this->validateUserRights();
        $this->setGroupId();
        $this->renderPage();
    }

    public function setGroupId()
    {
        global $user_rights;
        if ( !empty( \REDCap::getGroupNames() ) && !empty($user_rights['group_id']) ) {
            $this->group_id = $user_rights['group_id'];
        }
    }

    public function renderPage()
    {
        if ($this->canDelete) {
            $this->handleDelete();
            $do_background_delete = (isset($_POST['delete_background']) && $_POST['delete_background'] == '1');
            if ($do_background_delete) {
                $success = true;
                $url = APP_PATH_WEBROOT . "index.php?pid=".PROJECT_ID."&route=BulkRecordDeleteController:index&deletion_id=&async_success=" . ($success ? "1" : "0");
                redirect($url);
            }
        }

        if (!empty($this->errors) || !empty($_SESSION['rmd_errors'])) {
            $this->renderErrors();
        } else {
            $this->renderNotes();
        }
        $this->renderPageHeader();
    }

    public function renderPageHeader()
    {
        global $lang;
        print	RCView::div(array('style'=>'max-width:750px;', 'class'=>'mt-1 mb-4'),
                    RCView::div(array('style'=>'color:#800000;', 'class'=>'fs16 float-left font-weight-bold'),
                        RCView::fa('fas fa-times-circle fs15 mr-1') . $lang['data_entry_619']
                    ) .
                    RCView::div(array('class'=>'clear'), '')
                ) .
                RCView::p(['class'=>'mb-4'],
                    $lang['data_entry_621'] . RCView::br() .
                    RCView::span(['class'=>'text-dangerrc boldish'], RCView::fa('fa-solid fa-circle-exclamation mr-1') . $lang['data_entry_649'])
                );
    }


    public function renderIndexPage()
    {
        extract($GLOBALS);
        session_start();

        if ($GLOBALS['bulk_record_delete_enable_global'] != '1') {
            redirect(APP_PATH_WEBROOT."index.php?pid=".PROJECT_ID);
        }

        $_SESSION['selected_arm'] = $_POST['arm-option1'] ?? ($_SESSION['selected_arm']??"");
        if (isset($_GET['toggle-delete-forms-record'])) {
            $_SESSION['selected_arm'] = $_POST['arm-option2'] ?? ($_SESSION['selected_arm']??"");
        }
        if (isset($_POST['form_event_ajax'])) {
            $_SESSION['form_event_data'] = $_POST['form_event'] ?? null;
            echo json_encode(['status' => 'success']);
            exit;
        }

        include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

        // CSS and JS
        loadCSS('BulkRecordDelete.css');
        loadJS('BulkRecordDelete.js');
        addLangToJS(['data_entry_652', 'data_entry_653', 'data_entry_654', 'data_entry_70']);
        // Tabs
        include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";

        // If does not have rights to delete whole record or part of record, then stop and display error
	    if (!$this->canDelete()) {
		    print RCView::div(['class'=>'red'],
			    RCView::fa("fas fa-exclamation-circle me-1") . RCView::tt('messaging_122')
		    );
		    include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
            exit;
	    }

        // VIEW BACKGROUND IMPORTS
        if (isset($_GET['deletion_id']))
        {
            $this->renderBackgroundDeletionResults();
            include APP_PATH_VIEWS . 'FooterProject.php';
            exit;
        }
        $this->init();
        $this->redirect();
        $hasArms = $Proj->longitudinal && $Proj->multiple_arms;
        $recordsExist = Records::getRecordCount($Proj->getId()) > 0;

        $delLoggingMsg = "";
        if ($Proj->project['allow_delete_record_from_log']) {
            $delLoggingMsg = RCView::div(array('id' => 'allow_delete_record_from_log_parent', 'style' => 'padding:5px;padding-left: 25px;border:1px solid #eee;background-color:#fafafa;text-indent: -1.4em;margin-top:20px;color:#555;'),
                RCView::checkbox(array('id' => 'allow_delete_record_from_log')) .
                RCView::label(['for' => 'allow_delete_record_from_log', 'class' => 'd-inline'], RCView::tt("data_entry_436", "b") . RCView::br() . RCView::tt("data_entry_437"))
            );
        }

        $changeReasonMsg = "";
        if ($Proj->project['require_change_reason']) {
            $changeReasonMsg =
                RCView::div(array('id'=>'change_reason_div'),
                    RCView::div(array('class'=>'font-weight-bold mt-3 mb-1'),
                        RCView::tt("data_entry_69")
                    ) .
                    RCView::textarea(array('id'=>'change-reason', 'class'=>'x-form-textarea x-form-field', 'style'=>'width:400px;height:120px;'))
                );
        }

        ?>
        <script type="text/javascript">
          var fetchLimit = <?php echo \BulkRecordDelete::FETCH_RECORDS_LIMIT; ?>;
          var langRMD = {
            confirm_deletion: '<?php echo js_escape($lang['data_entry_640']) ?>',
            delete_message_instructions: '<?php echo js_escape(join(' ', [$lang['data_entry_646'], '"' . $lang['edit_project_48'] . '"', $lang['edit_project_140'], $delLoggingMsg, $changeReasonMsg])); ?>',
            delete_forms_warning: '<?php echo js_escape($lang['data_entry_641']) ?>',
            delete_records_warning: '<?php echo js_escape($lang['data_entry_642']) ?>',
            confirm_delete_txt: '<?php echo js_escape(join(' ', [$lang['edit_project_47'] , '"' . $lang['edit_project_48'] . '"' , $lang['edit_project_49']])); ?>',
            message_invalid_records_detected: '<?php echo js_escape($lang['data_entry_643']) ?>',
            message_navigation_warning: '<?php echo js_escape($lang['data_entry_645']) ?>',
            previous: '<?php echo js_escape($lang['datatables_11']) ?>',
            next: '<?php echo js_escape($lang['datatables_10']) ?>',
            continue: '<?php echo js_escape($lang['multilang_690']) ?>',
            bg_checkbox: '<?php echo js_escape($lang['data_entry_707']) ?>',
            bg_checkbox2: '<?php echo js_escape($lang['data_entry_708']) ?>'
          };
        </script>
        <?php
        $tabs = array();
        $url = APP_PATH_WEBROOT . "index.php?route=BulkRecordDeleteController:index&pid=" . PROJECT_ID;
        // Tab to enter custom list of records to delete
        $tabs[ $url . '&view=custom-list'] =
            RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_entry_631']);
        // Tab to select records to delete manually
        $tabs[ $url . '&view=record-list'] =
            RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_entry_630']);
        // Default to custom-list
        if (empty($_GET['view'])) $_GET['view'] = 'custom-list';
        $view = isset($_GET['view']) ? htmlspecialchars($_GET['view'], ENT_QUOTES) : "na";
        // delete entire record or just some forms?
        $checked = 'checked';
        $unChecked = '';
        $statusRadio1 = !isset($_GET['toggle-delete-forms-record']) ? $checked : $unChecked;
        ?>
        <div class="container" style="margin-left: 0;">
            <form method="POST" class="delete_records">
                <div style="margin:5px 0 0;">
                    <div class="row">
                        <div class="col">
                            <?php echo RCView::p(['style' => 'font-weight:bold;'], $lang['data_entry_634'])?>
                            <ul style="list-style: none; padding-left: 12px">
                                <li>
                                    <?php
                                    echo RCView::radio(array('id' => 'toggle-delete-entire-record', 'name' => 'toggle-delete-entire-record', 'class' => 'align-middle toggle-delete', 'onclick' => "document.getElementById('toggle-delete-forms-record').checked = false; modifyURL(removeParameterFromURL(window.location.href,'toggle-delete-forms-record'));", $statusRadio1 => $statusRadio1)) .
                                        RCView::label(array('for' => 'toggle-delete-entire-record', 'style' => 'font-size:13px;color:#393733;'), $lang['data_entry_638']);
                                    ?>
                                    <?php echo $this->displayArmsDropdownSelect(false) ?>
                                </li>
                                <li>
                                    <?php
                                    $statusRadio2 = $statusRadio1 === $checked ? $unChecked : $checked;
                                    echo RCView::radio(array('id' => 'toggle-delete-forms-record', 'name' => 'toggle-delete-forms-record', 'class' => 'align-middle toggle-delete', 'onclick' => "document.getElementById('toggle-delete-entire-record').checked = false; modifyURL(window.location.href+'&toggle-delete-forms-record=1');", $statusRadio2 => $statusRadio2)) .
                                        RCView::label(array('for' => 'toggle-delete-forms-record', 'style' => 'font-size:13px;color:#393733;'), $lang['data_entry_639']);
                                    ?>
                                    <?php echo $this->displayArmsDropdownSelect(true) ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <?php echo RCView::p(['style' => 'font-weight:bold;'], $lang['data_entry_635'])?>
                            <ul style="list-style: none; padding-left: 12px">
                                <?php
                                foreach ($tabs as $this_url => $this_label) {
                                    $qs = parse_url($this_url, PHP_URL_QUERY);
                                    parse_str($qs, $these_param_pairs);
                                    $this_view = $these_param_pairs['view'];
                                    $radioId = "radio-" . $this_view;
                                    $checked = $this_view == $_GET['view'] ? 'checked' : '';
                                    ?>
                                    <li >
                                        <?php
                                        echo RCView::radio(array('id' => $radioId, 'class' => 'align-middle', 'value' => $this_url, $checked => $checked)) .
                                            RCView::label(array('for' => $radioId, 'style' => 'font-size:13px;color:#393733;'), $this_label);
                                        ?>
                                    </li>
                                    <?php
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="clear"></div>
                <?php if ($recordsExist) : ?>
                    <div id="<?= $view ?>" class="row" >
                        <div class="col">
                            <input type="hidden" name="result" value="delete" >
                            <input type="hidden" name="group_id" value="<?= $this->group_id ?>">
                            <input type="hidden" name="mode" value="<?= $view ?>">
                            <?php echo RCView::p(['style' => 'font-weight:bold;display:inline-block;'], $view == 'custom-list' ? str_replace('{0}', $lang['data_entry_657'], $lang['data_entry_637']) : str_replace('{0}', $lang['data_entry_658'], $lang['data_entry_637']))?>
                            <span id="count-scheduled-for-deletion" style="border:1px solid red;color:red;padding: 5px;margin-left: 10px;">0</span>
                            <?php if( $view == 'custom-list' ): ?>
                                <div class="form-group" style="padding-top:0">
                                    <small id="validateHelpBlock" class="form-text text-muted">
                                        <?php echo $lang['data_entry_625'] ?>
                                    </small>
                                    <div class="input-group mt-2">
                                        <textarea class="form-control list-input-step"  rows="10" aria-label="With textarea"></textarea>
                                    </div>
                                    <small id="invalidInputBlock" class="invalid-feedback"></small>
                                    <small id="validInputBlock" class="valid-feedback">
                                        <?php  echo $lang['data_entry_626'] ?>
                                    </small>
                                </div>
                                <div class="form-group" style="padding-top:0;">
                                    <ul id="custom-output"></ul>
                                </div>
                            <?php elseif( $view == 'record-list' ):
                                echo RCView::div(array('style'=>'padding:0 0 10px;'),
                                    RCView::div(array('class'=>'form-text text-muted mb-1'),
                                        $lang['data_entry_647']
                                    ).
                                    RCView::span([],
                                        RCView::a(array('href'=>'javascript:;', 'onclick'=>'selectAllRecords(true)', 'style'=>'font-size:11px;text-decoration:underline;margin: 0 10px 0 5px;'),$lang['ws_35']).
                                        RCView::a(array('href'=>'javascript:;', 'onclick'=>'selectAllRecords(false)', 'style'=>'font-size:11px;text-decoration:underline;'),$lang['ws_55'])
                                    )
                                );
                                ?>
                                <div class="form-group" style="padding-top:0;">
                                    <div class="card">
                                        <div class="card-body" style="padding:0 1.25rem;">
                                            <div id="spinner-container" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color:rgb(92, 99, 106); z-index:1;">
                                                <div class="spinner-border text-medium" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                            </div>
                                            <label for="searchBox"></label>
                                            <input type="text" id="searchBox" placeholder="<?=RCView::tt_js2('data_entry_648')?>" style="margin-bottom: 5px;">
                                            <div id="infoText" style="visibility:hidden;" class="text-secondary mb-2 fs11"><i class="fas fa-circle-info"></i> <?php echo $lang['data_entry_644'];?></div>
                                            <div id="record-list-wrapper" class="wrapper">
                                                <ul id="record-output">
                                                    <?php
                                                    ob_start();
                                                    if ($hasArms) $selectedArm = $_SESSION['selected_arm'];
                                                    $this->fetchRecords($selectedArm ?? null, $this->group_id, $this::FETCH_RECORDS_LIMIT + 1); // fetching 1 more than the max allowed per page to determine whether we should display `Next` button.
                                                    $records = json_decode(ob_get_clean(), true);
                                                    $total = 0;
                                                    $showNext = false;
                                                    foreach ($records['records'] as $chunkIndex => $chunk) {
                                                        $total += count($chunk);
                                                        if ($total > $this::FETCH_RECORDS_LIMIT) {
                                                            array_pop($chunk);
                                                            $showNext = true;
                                                        }
                                                        foreach ($chunk as $record => $label) {
                                                            $displayName = ' ';
                                                            $displayName .= $record . ($label == '' ? "" : " ".$label);
                                                            echo '<li>';
                                                            echo '<label>';
                                                            echo '<input type="checkbox" name="records[]" value="' . RCView::escape($record,false) . '">';
                                                            echo RCView::escape($displayName,false);
                                                            echo '</label>';
                                                            echo '</li>';
                                                        }
                                                    }
                                                    ?>
                                                </ul>
                                            </div>
                                            <?php if ($showNext):?>
                                                <div style="position: absolute; bottom: 10px; right: 10px;">
                                                    <button id="prevPage" class="btn btn-secondary" style="display:none;">← Previous</button>
                                                    <button id="nextPage" class="btn btn-secondary">Next →</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="form-group" style="padding-top: 0">
                                <button id="btn-delete-selection" class="btn btn-danger">
                                    <i class="fas fa-trash-alt"></i> <?php echo $lang['global_19'] ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-dangerrc fs15"><i class="fa-solid fa-circle-info"></i> <?php echo $lang['data_entry_632'] ?></p>
                <?php endif; ?>
            </form>
        </div>
        <?php
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    }

	private function displayArmsDropdownSelect(bool $partialDelete)
	{
		global $lang, $Proj;

		$id = $partialDelete ? 'option2' : 'option1';
		$selectedArm = $_SESSION['selected_arm'] ?? $Proj->firstArmNum;
		$recordsByArms = Records::getArmsForAllRecords($Proj->getId());

		// If selected arm is invalid, default to first arm
		if (!in_array($selectedArm, array_keys($recordsByArms))) {
			$selectedArm = $Proj->firstArmNum;
			$_SESSION['form_event_data'] = null; // Reset session selection
		}

		$style = (!$partialDelete && !$Proj->multiple_arms) ? 'display: none;' : '';
		$style2 = !$Proj->multiple_arms ? 'display: none;' : '';

		$html = "<div class=\"form-group form-event-list-wrapper-{$id}\" style=\"display:none; padding-top:0;margin-left:20px;\">\n";

		if ($partialDelete) {
			$html .= "<span class=\"d-block small text-muted\">{$lang['data_entry_659']}</span>\n";
		}

		$html .= "<div class=\"toggle-ribbon\" style=\"{$style}\">\n";
		$html .= "<span class=\"ribbon-text\">\n";
		$html .= $partialDelete ? $lang['data_entry_636'] : $lang['data_entry_704'];

		if ($partialDelete) {
			$html .= " <span id=\"triangle\">&#9660;</span>";
		}

		$html .= "</span>\n";
		$html .= "<div id=\"longitudinal-arms-list-{$id}\" style=\"padding-left:12px;\">\n";
		$html .= "<div style=\"padding-top:10px;{$style2}\">\n";
		$html .= "<label for=\"arm-select-{$id}\">{$lang['data_entry_705']}</label>\n";
		$html .= "<select id=\"arm-select-{$id}\" class=\"arm-select custom-select ms-1\" name=\"arm-{$id}\">\n";

		foreach ($Proj->events as $arm_num => $arm_detail) {
			$selected = ($arm_num == $selectedArm) ? 'selected' : '';
			$label = "{$lang['global_08']} {$arm_num}{$lang['colon']} {$arm_detail['name']}";
			$html .= "<option value=\"{$arm_num}\" {$selected}>{$label}</option>\n";
		}

		$html .= "</select>\n";
		$html .= "</div>\n";

		if ($partialDelete) {
			$formEventListHtml = $this->getFormEventList($selectedArm);
			$html .= "<div id=\"form-event-list-wrapper\">\n{$formEventListHtml}\n</div>\n";
		}

		$html .= "</div>\n"; // longitudinal-arms-list
		$html .= "</div>\n"; // toggle-ribbon
		$html .= "</div>\n"; // form-group

		return $html;
	}

	public function renderNotes()
    {
        if (!empty($this->notes) || !empty($_SESSION['rmd_notes'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $_SESSION['rmd_notes'] = $this->notes;
            } else {
                print $this->buildFeedbackMsg(['notes' => ($_SESSION['rmd_notes']??[])], "success");
            }
        }
    }

    public function buildFeedbackMsg($contents, $type = 'danger')
    {
        $alerts = "";
        $feedback = $contents['notes'] ?? $contents['errors'];
        foreach($feedback as $content) {
            $alerts .= "<div class='alert alert-$type'>$content</div>";
        }
        if (isset($contents['notes'])) {
            unset($_SESSION['rmd_notes']);
        }
        if (isset($contents['errors'])) {
            unset($_SESSION['rmd_errors']);
        }
        return $alerts;
    }

    public function redirect(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            redirect($_SERVER['REQUEST_URI']);
        }
    }

    public function fetchRecords($arm_id, $dag = null, $limit=null, $limitOffset=0)
    {
        global $Proj;
        // Get all records
        $records = \Records::getRecordList($Proj->project_id, $dag, false, false, $arm_id, $limit, $limitOffset);
        $records = array_values($records);
        if (!empty($records)) {
            // add custom record labels
            $recordsAndLabels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($records, true);
        } else if (empty($records) && $limitOffset > 0){
            // back to the first record if we have reached the end
            $records = array_values(\Records::getRecordList($Proj->project_id, $dag, false, false, $arm_id, $limit));
            $recordsAndLabels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($records, true);
        }
        $recordsAndLabels = $recordsAndLabels ?? [];
        foreach ($records as $value) {
            if (!isset($recordsAndLabels[$value])) {
                $recordsAndLabels[$value] = '';
            }
        }
        ksort($recordsAndLabels);
        $records = array_chunk($recordsAndLabels, 1000, true);
        echo json_encode(array("records" => $records));
    }

    public function validateUserRights()
    {
        $this->canDelete = $this->canDelete();
        if (!empty($this->errors)) {
            $this->renderErrors();
        }
        if ($GLOBALS['isAjax'] && !$this->canDelete) {
            exit(RCView::tt('messaging_122',''));
        }
    }

    // Determine if current user can access the Mass Delete page. Return boolean.
    public function canDelete()
    {
        global $user_rights;
        // If Bulk Delete is disabled globally, return false
        if ($GLOBALS['bulk_record_delete_enable_global'] != '1') return false;
        // Ignore this for the bg processing cron job
        if (defined("CRON")) return true;
        // stop client-side manipulation of group_id for unauthorized access
        if (!empty($_POST['group_id']) && $_POST['group_id'] != $user_rights['group_id']) {
	        $errorMsg = RCView::div(['class'=>'red'],
                RCView::fa("fas fa-exclamation-circle me-1") . RCView::tt('messaging_122')
	        );
            $this->errors[] = $errorMsg;
            return false;
        }
        // If user has delete record capabilities, then return true
        return UserRights::canDeleteWholeOrPartRecord();
    }


	// If a specific background bulk record delete batch has been completed, then mark as completed
	public static function checkCompleteAsyncRecordDelete($delete_id, $records_provided)
	{
		global $lang;
		// If job already completed, return false
		$sql = "select status from redcap_record_background_delete where delete_id = ?";
		$q = db_query($sql, $delete_id);
		if (db_result($q, 0) == 'COMPLETED') return false;
		// Get counts
		$sql = "select count(*) as thiscount, round(sum(total_time)/1000) as total_time, sum(error_count) as error_count
				from redcap_record_background_delete_items where delete_id = ? and row_status in ('COMPLETED', 'FAILED')";
		$q = db_query($sql, $delete_id);
		if (!db_num_rows($q)) return false;
		$totalRecordsProcessed = db_result($q, 0, 'thiscount');
		$totalProcessingTime = db_result($q, 0, 'total_time');
		$errorCount = db_result($q, 0, 'error_count');
		if ($errorCount == null) $errorCount = 0;
		if ($totalRecordsProcessed == $records_provided)
		{
			// Mark job as completed (but make sure it wasn't already completed running another cron job)
			$sql = "update redcap_record_background_delete set status = 'COMPLETED' where delete_id = ?";
			$q = db_query($sql, $delete_id);
			if ($q && db_affected_rows() > 0)
			{
				// Get total records deleted
				$sql = "select count(*) from redcap_record_background_delete_items where delete_id = ? and row_status = 'COMPLETED'";
				$q = db_query($sql, $delete_id);
				$recordsDeleted = db_result($q, 0);
				// Also set timestamp of completion
				$sql = "update redcap_record_background_delete 
						set completed_time = ?, total_processing_time = ?, records_deleted = ?, total_errors = ? 
						where delete_id = ?";
				db_query($sql, [date("Y-m-d H:i:s"), $totalProcessingTime, $recordsDeleted, $errorCount, $delete_id]);
				// Notify the user that their data import has completed
				$sql = "select i.user_email, d.project_id, d.request_time, d.completed_time, d.records_provided, i.datetime_format
						from redcap_record_background_delete d, redcap_user_information i
               			where d.delete_id = ? and d.user_id = i.ui_id";
				$q = db_query($sql, $delete_id);
				$row = db_fetch_assoc($q);
				// Convert times into user's preferred datetime format
				$row['request_time'] = DateTimeRC::format_user_datetime($row['request_time'], 'Y-M-D_24', $row['datetime_format']);
				$row['completed_time'] = DateTimeRC::format_user_datetime($row['completed_time'], 'Y-M-D_24', $row['datetime_format']);
				// Send email
				$subject = "[REDCap] ".$lang['data_entry_709']." (PID {$row['project_id']})";
				if ($errorCount > 0) $subject .= " ".strip_tags(RCView::tt_i("data_import_tool_341", [$errorCount]));
				// Add link to view results/errors
				$message = RCView::tt_i("data_entry_710", [$row['request_time'], $row['completed_time'], $row['records_provided'], $recordsDeleted, $errorCount]);
				if ($errorCount > 0) $message .= RCView::br().RCView::br().$lang['data_import_tool_342'];
				$message .= RCView::br().RCView::br().RCView::a(['href'=>APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/index.php?pid={$row['project_id']}&route=BulkRecordDeleteController:index&delete_id=$delete_id"], $lang['data_entry_711']).RCView::br();
				REDCap::email($row['user_email'], \Message::useDoNotReply($GLOBALS['project_contact_email']), $subject, $message, '', '', $GLOBALS['project_contact_name'], [], $row['project_id']);
				return true;
			}
		}
		return false;
	}

	// Dynamically determine the batch size of a single batch of background bulk record delete
	const BATCH_LENGTH_MINUTES = 3;
	const MIN_RECORDS_PER_BATCH = 25;
	const MAX_RECORDS_PER_BATCH = 500;
	public static function getBatchSizeAsyncRecordDelete()
	{
		// Get timestamp of 1 day ago
		$xDaysAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-1,date("Y")));
		// Get average processing time over the past day
		$sql = "select round(avg(total_time)) from (
					select total_time from redcap_record_background_delete_items
					where row_status = 'COMPLETED' and end_time > '".db_escape($xDaysAgo)."'
					order by dr_id desc
				) x";
		$q = db_query($sql);
		$avg_time_per_record_ms = db_result($q, 0);
		if ($avg_time_per_record_ms != null && $avg_time_per_record_ms > 0) {
			$est_records_per_batch = round((self::BATCH_LENGTH_MINUTES*60)/($avg_time_per_record_ms/1000));
			// If calculated value is less than minimum, then use minimum instead
			if ($est_records_per_batch < self::MIN_RECORDS_PER_BATCH) {
				return self::MIN_RECORDS_PER_BATCH;
			} elseif ($est_records_per_batch > self::MAX_RECORDS_PER_BATCH) {
				return self::MAX_RECORDS_PER_BATCH;
			} elseif (isinteger($est_records_per_batch) && $est_records_per_batch > 0) {
				return $est_records_per_batch;
			} else {
				return self::MIN_RECORDS_PER_BATCH;
			}
		} else {
			// If could not determine from table, then use hard-coded default
			return self::MIN_RECORDS_PER_BATCH;
		}
	}

    public static function cancelBackgroundDeleteAction($delete_id, $cancelTime=null)
	{
		if (!isinteger($delete_id)) return false;
		$sql = "update redcap_record_background_delete 
                set status = 'CANCELED', completed_time = ?
                where delete_id = ? and status in ('INITIALIZING','QUEUED','PROCESSING')";
		if ($cancelTime == null) $cancelTime = date("Y-m-d H:i:s");
		$q = db_query($sql, [$cancelTime, $delete_id]);
		if ($q) {
			$sql = "update redcap_record_background_delete_items
                    set row_status = 'CANCELED'
                    where delete_id = ? and row_status not in ('COMPLETED', 'FAILED')";
			return db_query($sql, [$delete_id]);
		}
		return false;
	}

    public static function processBackgroundDeletions()
	{
        // Store initial POST vars since we'll have to overwrite/add some
        $prePost = $_POST;

        // Initialize settings
		$oneHourAgo = date("Y-m-d H:i:s", mktime(date("H")-1,date("i"),date("s"),date("m"),date("d"),date("Y")));
		$oneDayAgo = date("Y-m-d H:i:s", mktime(date("H")-24,date("i"),date("s"),date("m"),date("d"),date("Y")));

		// If any deletes have been stuck initializing for more than an hour, set them as cancelled (since they are obviously never going to start)
		$sql = "select delete_id from redcap_record_background_delete where status = 'INITIALIZING' and request_time < '$oneHourAgo'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			self::cancelBackgroundDeleteAction($row['delete_id']);
		}

		// If any uploads have finished deleting all records but are somehow still stuck as PROCESSING, set to COMPLETED
		$sql = "select delete_id, records_provided from redcap_record_background_delete 
                where status = 'PROCESSING' and request_time > '$oneDayAgo' and records_deleted = records_provided";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			self::cancelBackgroundDeleteAction($row['delete_id'], $row['records_provided']);
		}

		// If any individual batches have been stuck processing for more than 48 hours, set them as cancelled (since they are obviously never going to start)
		$sql = "select delete_id from redcap_record_background_delete where status = 'PROCESSING' and request_time < '$oneDayAgo'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Get the completion/cancel time to apply to this batch
			$sql = "select greatest(max(end_time), max(start_time)) from redcap_record_background_delete_items where delete_id = ?";
			$completionTime = db_result(db_query($sql, $row['delete_id']), 0);
			if ($completionTime == '') $completionTime = date("Y-m-d H:i:s");
			// If all rows are completed, then set batch as completed using the max timestamp
			$sql = "select 1 from redcap_record_background_delete_items where delete_id = ? and row_status = 'PROCESSING' limit 1";
			$someStuckProcessing = (db_num_rows(db_query($sql, $row['delete_id'])) > 0);
			if ($someStuckProcessing) {
				// STUCK: This batch is stuck and will never finish, so cancel it
				self::cancelBackgroundDeleteAction($row['delete_id'], $completionTime);
			} else {
				// COMPLETED: This batch actually completed importing all rows, so mark as completed
				$sql = "update redcap_record_background_delete 
                        set status = 'COMPLETED', completed_time = ?
                        where delete_id = ? and status = 'PROCESSING'";
				db_query($sql, [$completionTime, $row['delete_id']]);
			}
		}

		// Check if we're done with the whole batch
		$prev_delete_id = null;
		$prev_records_provided = null;
		$prev_project_id = null;
		$recordsProcessed = 0;

		// Find dynamic ways to limit these based on server processing speed
		$limit = self::getBatchSizeAsyncRecordDelete();

		// Gather all the processes that might get done in this batch
		$sql = "select r.dr_id
				from redcap_record_background_delete i, redcap_record_background_delete_items r
				where i.delete_id = r.delete_id and i.status in ('PROCESSING', 'QUEUED') and i.completed_time is null and r.row_status = 'QUEUED'
				order by r.delete_id, r.dr_id
				limit $limit";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			// Add dr_id's to array
			$dr_ids = [];
			while ($row = db_fetch_assoc($q)) {
				$dr_ids[] = $row['dr_id'];
			}
			// Set all rows in this batch to PROCESSING status so that other simultaneous crons won't pick them up in the query above
			$sql = "update redcap_record_background_delete_items 
                    set row_status = 'PROCESSING' 
                    where row_status = 'QUEUED' and dr_id in (".implode(',', $dr_ids).")";
			db_query($sql);
			// Now loop through
			foreach ($dr_ids as $key=>$dr_id)
			{
				$sql = "select i.delete_id, r.dr_id, i.project_id, u.username, i.status, i.change_reason,
                        r.record, r.arm_id, i.records_provided, r.row_status, i.form_event, i.remove_log_details
                        from redcap_record_background_delete i, redcap_record_background_delete_items r, redcap_user_information u
                        where i.delete_id = r.delete_id and u.ui_id = i.user_id and r.dr_id = ?";
				$q = db_query($sql, $dr_id);
				if (db_num_rows($q) == 0) continue;
				$row = db_fetch_assoc($q);
                // If we're starting a new project, erase the Proj cache for the previous project (to save memory for the cron)
                if ($prev_project_id !== null && $row['project_id'] != $prev_project_id) {
                    unset(Project::$project_cache[$prev_project_id]);
				}
				// Change QUEUED to PROCESSING for the whole batch, if applicable
				if ($row['status'] == 'QUEUED') {
					$sql = "update redcap_record_background_delete set status = 'PROCESSING' where status = 'QUEUED' and delete_id = ?";
					db_query($sql, $row['delete_id']);
				}
				// If a specific import batch has been completed, then mark as completed
				if ($prev_delete_id !== null && $row['delete_id'] != $prev_delete_id) {
					self::checkCompleteAsyncRecordDelete($row['delete_id'], $row['records_provided']);
				}
				// If this row had already been deleted by another cron or if the batch has been cancelled by the user, then skip this row
				if ($row['row_status'] == 'COMPLETED' || $row['row_status'] == 'FAILED' || $row['row_status'] == 'CANCELED'
					|| $row['status'] == 'CANCELED' || $row['status'] == 'PAUSED') continue;
				// If using "Reason for Change" setting, add it to the record/event's logging
				$changeReasons = [];
				if ($row['change_reason'] != '') { // This won't be done if record auto-numbering is enabled for the import or if no reason is provided
					// Make sure reason for change setting is still enabled in the project, and make sure the record exists first (should not add reason for new records being created)
					$requireChangeReason = db_result(db_query("select require_change_reason from redcap_projects where project_id = ?", $row['project_id']));
					if ($requireChangeReason == '1' && Records::recordExists($row['project_id'], $row['record'])) {
						// Add the reason for the record/event
						$changeReasons[$row['record']][$row['arm_id']] = $row['change_reason'];
					}
				}
				// Set this row as PROCESSING with a start time
				$sql = "update redcap_record_background_delete_items set row_status = 'PROCESSING', start_time = ? where dr_id = ?";
				db_query($sql, [date("Y-m-d H:i:s"), $row['dr_id']]);
				// Clock how long it takes to import the record
				$start_time = microtime(true);
				$errorList = [];
                // Check if record still exists
                $armNum = isinteger($row['arm_id']) ? db_result(db_query("select arm_num from redcap_events_arms where arm_id = ?", $row['arm_id'])) : null;
                if (!Records::recordExists($row['project_id'], $row['record'], $armNum)) {
					$errorList[] = "Record no longer exists.";
				} else {
                    ## DELETE RECORD
					// Prep parameters in the POST array
					$_POST = [];
					$_POST['form_event'] = ($row['form_event'] !== null) ? unserialize($row['form_event']) : $row['form_event'];
                    if ($_POST['form_event'] === false) $_POST['form_event'] = null;
					$_POST['arm'] = $armNum;
					$_POST['records'] = [$row['record']];
					$_POST['delete_logging'] = $row['remove_log_details'];
					$_POST['change-reason'] = $row['change_reason'];
                    // Perform the deletion
					$bgrd = new \BulkRecordDelete();
                    $bgrd->arm = $armNum;
					$bgrd->handleDelete($row['project_id'], $row['username']);
					if (!empty($bgrd->errors)) {
                        foreach ($bgrd->errors as $erval) {
							$errorList[] = strip_tags($erval);
						}
					}
				}
				// Calculate total import time (rounded to milliseconds)
				$total_time = round((microtime(true)-$start_time)*1000);
				// Get error count
				$numErrors = is_array($errorList) ? count($errorList) : 1; // 'errors' should always be an array, so if not, there must be an error (set it to "1")
				// Set final status of this individual deleted record
				if ($numErrors === 0) {
					// Successfully deleted this row
					$sql = "update redcap_record_background_delete_items 
                            set row_status = 'COMPLETED', end_time = ?, error_count = 0, total_time = ? 
                            where dr_id = ?";
					db_query($sql, [date("Y-m-d H:i:s"), $total_time, $row['dr_id']]);
					// Update records_deleted in redcap_record_background_delete (only do this every 10 records or if record took >15s to import)
					if ($total_time > 15000 || $key % 10 === 0) {
						self::updateBgRecordsDeleted($row['delete_id']);
					}
				} else {
					// Error
					$sql = "update redcap_record_background_delete_items 
                            set row_status = 'FAILED', end_time = ?, record = ?, error_count = ?, errors = ?, total_time = ? 
                            where dr_id = ?";
                    $params = [date("Y-m-d H:i:s"), $row['record'], $numErrors, implode("\n", $errorList), $total_time, $row['dr_id']];
					db_query($sql, $params);
					// Increment error count in redcap_record_background_delete
					$sql = "update redcap_record_background_delete set total_errors = total_errors + ? where delete_id = ?";
					db_query($sql, [$numErrors, $row['delete_id']]);
				}
				// Set for next loop
				$prev_delete_id = $row['delete_id'];
				$prev_project_id = $row['project_id'];
				$prev_records_provided = $row['records_provided'];
				$recordsProcessed++;
			}
			// LOOPING DONE: If a specific import batch has been completed, then mark as completed
			self::updateBgRecordsDeleted($prev_delete_id);
			self::checkCompleteAsyncRecordDelete($prev_delete_id, $prev_records_provided);
		}

		// Restore initial POST vars
		$_POST = $prePost;

		// Return the total number of records (not rows) that were deleted
		return $recordsProcessed;
	}

	// Update records_deleted in bg bulk record delete
	public static function updateBgRecordsDeleted($delete_id)
	{
		$sql = "update redcap_record_background_delete 
                set records_deleted = (select count(*) from redcap_record_background_delete_items where delete_id = ? and row_status = 'COMPLETED')
                where delete_id = ?";
		return db_query($sql, [$delete_id, $delete_id]);
	}

    public function handleDelete($pid=null, $userid_logging="")
    {
        global $lang;
        if (isinteger($pid)) {
            $Proj = new \Project($pid);
		} else {
            global $Proj;
		}
        if (defined("CRON") || (isset($_POST['delete']) && $_POST['delete'] == 'true')) {
            if (!UserRights::canDeleteWholeOrPartRecord() || $GLOBALS['bulk_record_delete_enable_global'] != '1') {
                $this->errors[] = "Unauthorized! Missing record delete permission!";
                return;
            }
            try {
                $form_event = $_POST['form_event'] ?? "";
	            $post_records = $_POST['records'] ?? [];
	            if (!is_array($post_records) || empty($post_records)) {
		            $this->errors[] = "No records were requested for deletion. Please try again!";
		            return;
	            }
                // check if multi-arm project
                if ($Proj->longitudinal && $Proj->multiple_arms) {
	                if (!defined("CRON") && isset($_SESSION['selected_arm']) && $this->arm == null) {
		                $this->arm = $_SESSION['selected_arm'];
	                }
	                if ($this->arm != null && $this->arm_id == null) {
		                $this->arm_id = $Proj->getArmIdFromArmNum($this->arm);
	                }
	                $valid_records = \Records::getRecordList($Proj->project_id, $this->group_id, false, false, $this->arm, null, 0, $post_records);
                } else {
	                $valid_records = \Records::getRecordList($Proj->project_id, $this->group_id, false, false, null, null, 0, $post_records);
                }
                // Determine which records we need to delete
                if (count($valid_records) != count($post_records)) {
                    $this->errors[] = "Invalid records were requested for deletion. Please try again!";
                    return;
                } else {
                    // Free up memory
                    unset($post_records);

                    // If doing partial delete with Reason For Change enabled, check to make sure reason is provided
                    if (!empty($form_event) && $Proj->project['require_change_reason'] && (!isset($_POST['change-reason']) || trim($_POST['change-reason']) == '')) {
                        $this->errors[] = "Reason for change was not provided. Please try again!" ;
                        return;
                    }

					// Delete record data from logging? (default to yes if project-level setting is enabled, otherwise if not enabled, default to no)
					$allow_delete_record_from_log = ($Proj->project['allow_delete_record_from_log'] && !(isset($_POST['delete_logging']) && $_POST['delete_logging'] == '0')) ? 1 : 0;

					// Perform background record delete, if applicable
					$do_background_delete = (isset($_POST['delete_background']) && $_POST['delete_background'] == '1');
                    if ($do_background_delete) {
                        // Add batch info to table
						if (!empty($form_event)) $form_event = serialize($form_event);
                        $sql = "insert into redcap_record_background_delete (project_id, user_id, request_time, form_event, 
                                records_provided, remove_log_details, change_reason) values (?,?,?,?,?,?,?)";
						$params = [$Proj->project_id, UI_ID, NOW, $form_event, count($valid_records), $allow_delete_record_from_log, ($_POST['change-reason'] ?? null)];
                        $q = db_query($sql, $params);
                        if (!$q) {
							$this->errors[] = "For unknown reasons, the records could not be scheduled for deletion. Please try again!" ;
                            return;
						}
                        $delete_id = db_insert_id();
                        // Add each record to the items table
						foreach ($valid_records as $record) {
							$sql = "insert into redcap_record_background_delete_items (delete_id, record, arm_id) values (?, ?, ?)";
							$params = [$delete_id, $record, ($this->arm_id ?? null)];
							$q = db_query($sql, $params);
						}
                        // Set it to be queued now
						$sql = "update redcap_record_background_delete set status = 'QUEUED' where delete_id = ?";
						$q = db_query($sql, $delete_id);
                        return;
					}

                    // Set logging user manually if the cron job is performing the delete action
                    if (defined("CRON")) {
	                    $userid_logging = "SYSTEM\n".$userid_logging;
                    }

                    if (empty($form_event)) {
                        // DELETE THE RECORD
                        foreach ($valid_records as $record) {
                            \Records::deleteRecord(
                                $record,
                                $Proj->table_pk,
                                $Proj->multiple_arms,
                                $Proj->project['randomization'],
                                $Proj->project['status'],
                                $Proj->project['require_change_reason'],
                                $this->arm_id,
                                "",
                                $allow_delete_record_from_log,
								$Proj->project_id,
	                            $userid_logging
                            );
                        }
                        $this->notes[] = "<b>" . str_replace('{0}', count($valid_records), $lang['data_entry_628']) . "</b>";
                    } else {
                        // DELETE THE FORM
                        $deleted_forms = '';
                        foreach ($form_event as $one_form_event) {
                            // Split out the form and event
                            list($selected_event_name, $selected_event_id, $selected_form) = $this->splitFormEvent($one_form_event, $Proj->project_id);
                            if (empty($selected_event_name)) {
                                $deleted_forms .= '   <li> ' . $selected_form;
                            } else {
                                $deleted_forms .= '   <li>[' . $selected_event_name . '] ' . $selected_form;
                            }
                            // See if this is a repeating
                            $repeating = $Proj->getRepeatingFormsEvents();
                            $repeat_form = false;
                            if (!empty($repeating) && !is_null($repeating[$selected_event_id])) {
                                // If this event is repeating, make sure this form is repeating or the whole event is repeating
                                $forms_in_event = array_keys($repeating[$selected_event_id]);
                                if (in_array($selected_form, $forms_in_event) or
                                    ($repeating[$selected_event_id] == 'WHOLE')) {
                                    $repeat_form = true;
                                }
                            }
                            // Delete this form for all provided records
                            $this->deleteForm($selected_event_id, $selected_form, $repeat_form, $valid_records, ($_POST['change-reason'] ?? ""), $Proj->project_id, $userid_logging);
                        }
                        $this->notes[] = "<b>" . str_replace(['{0}', '{1}'], [$deleted_forms, count($valid_records)], $lang['data_entry_629']) . "</b>";
                    }
                }
                // Clear events forms selections upon successful deletion
                unset($_SESSION['form_event_data']);
            } catch (\Exception $e) {
                // This catch block catches all exceptions, including database exceptions. Ideally, we want to handle database errors internally and not display them to the user; we might want to explore using transactions.
                $this->errors[] = $e->getMessage();
                return;
            }
        }
    }

    public function renderErrors()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_SESSION['rmd_errors'] = $this->errors;
        } else {
            print $this->buildFeedbackMsg(['errors' => ($_SESSION['rmd_errors']??[])]);
        }
    }

    public function getFormEventList($arm_num = null)
    {
	    $_SESSION['selected_arm'] = $arm_num;
        return self::renderSelectedFormsEvents($_SESSION['form_event_data'] ?? [], $arm_num);
    }


    // Creates a div with selectable forms/events - requires javascript functions
    public static function renderSelectedFormsEvents($selected_forms_events=array(), $selected_arm_num = null)
    {
        global $Proj, $lang, $user_rights;

        // Get an array of all event names (in the current arm)
        $all_events = REDCap::getEventNames(true);

        $hasDeleteRights = UserRights::canDeleteWholeOrPartRecord();

        $checkboxHeaders = "<tr>";
        $checkboxColumns = "<tr>";
        $allArmsCheckboxes = array();
        $maxCheckboxesInGroups = 0;
        foreach ($Proj->events as $arm_num => $arm_detail)
        {
            if ($selected_arm_num && $arm_num != $selected_arm_num) {
                continue;
            }
            if ($Proj->multiple_arms) {
                $checkboxHeaders .= "<th style='font-size:12px;color:#800000;font-weight:bold;'>".$lang['global_08']." $arm_num".$lang['colon']." ".$arm_detail['name']."</th>";
            }
            $checkboxes = array();
            foreach ($arm_detail['events'] as $event_id => $event_attr) {
                $event_name = is_array($all_events) ? $all_events[$event_id] : '';
                if ($Proj->longitudinal) {
                    $checkboxes[] = RCView::div(array('style' => 'margin-top:3px;padding-top:3px;border-top:1px solid #ccc;font-weight:bold;'),
                        RCView::input(array('type' => 'checkbox', 'onclick' => "selectAllInEvent('$event_name', this);", 'id' => 'event-' . $event_name)) .
                        RCView::escape($event_attr['descrip'])
                    );
                } else {
                    $checkboxes[] = RCView::div(array('style' => 'font-weight:bold;'),
                        $lang['global_110'] . $lang['colon']
                    );
                }
                if (isset($Proj->eventsForms[$event_id])) {
                    $all_checked = true;
                    foreach ($Proj->eventsForms[$event_id] as $form) {
                        if (!isset($user_rights['forms'][$form])) continue;
                        // Bulk delete still requires the overall "Delete" right
                        if ($hasDeleteRights && !UserRights::hasDataViewingRights($user_rights['forms'][$form], "view-edit")) continue;
                        $checkbox_id = 'ef-' . $event_name . '-' . $form;
                        $attr = array('type' => 'checkbox', 'id' => $checkbox_id, 'class' => 'efchk', 'name' => 'form_event[]', 'value' => $checkbox_id);
                        if (in_array($checkbox_id, $selected_forms_events)) {
                            $attr['checked'] = 'checked';
                        } else {
                            $all_checked = false;
                        }
                        $checkboxes[] = RCView::div(array('class' => 'wrap ' . ($Proj->longitudinal ? 'hangevl' : 'hangevc')),
                            RCView::input($attr) .
                            RCView::escape($Proj->forms[$form]['menu'])
                        );
                    }
                    if ($all_checked && $Proj->longitudinal) {
                        $checkboxes[] = RCView::script("document.getElementById('event-$event_name').checked = true;");
                    }
                }
            }

            $maxCheckboxesInGroups = max(count($checkboxes), $maxCheckboxesInGroups);
            $allArmsCheckboxes[] = $checkboxes;
        }
        // The following maneuver allows for checkboxes representing different forms in different arms to be aligned when the number of checkboxes/forms in each arm is different.
        foreach ($allArmsCheckboxes as &$checkboxGroup) {
            if (count($checkboxGroup) < $maxCheckboxesInGroups) {
                $count = $maxCheckboxesInGroups - count($checkboxGroup);
                while($count > 0) {
                    $checkboxGroup[] = RCView::div(array('class' => 'wrap ' . ($Proj->longitudinal ? 'hangevl' : 'hangevc')),
                        RCView::input(array('type' => 'checkbox', 'style' => 'visibility: hidden'))
                    );
                    $count--;
                }
            }
            $checkboxColumns .= "<td>".implode($checkboxGroup)."</td>";
        }

        $checkboxTable = "<table id='choose_select_forms_events_table'>" .
            ($Proj->multiple_arms ? "<tr>$checkboxHeaders<tr>" : '') .
            "<tr>$checkboxColumns<tr>
		</table>";

        // Build the hidden div
        $html = RCView::div(array('id'=>'choose_select_forms_events_div'),
            RCView::div(array('id'=>'choose_select_forms_events_div_sub'),
                RCView::div(array('style'=>($Proj->longitudinal ? 'width:400px;min-width:400px;' : 'width:300px;min-width:300px;').'color:#800000;font-weight:bold;font-size:13px;padding:6px 3px 5px;margin-bottom:3px;border-bottom:1px solid #ccc;'),
                    ($Proj->longitudinal ? $lang['data_entry_706'] : $lang['data_entry_706'])
                ) .
                RCView::div(array('style'=>'padding:0 0 10px;'),
                    RCView::span(array('id'=>'select_links_forms'),
                        RCView::button(array('class'=>'btn btn-primaryrc btn-xs', 'onclick'=>'excludeEventsUpdate(1);return false;'),$lang['global_125']).
                        RCView::button(array('class'=>'btn btn-defaultrc btn-xs', 'onclick'=>'excludeEventsUpdate(0);return false;'),$lang['global_53']).
                        RCView::a(array('href'=>'javascript:;', 'onclick'=>'selectAllFormsEvents(true)', 'style'=>'font-size:11px;text-decoration:underline;margin:0 10px 0 30px;'),$lang['ws_35']).
                        RCView::a(array('href'=>'javascript:;', 'onclick'=>'selectAllFormsEvents(false)', 'style'=>'font-size:11px;text-decoration:underline;'),$lang['ws_55'])
                    )
                ).
                $checkboxTable
            )
        );

        return $html;
    }

    public function splitFormEvent($selected_form_event, $pid=null)
    {
		if (isinteger($pid)) {
			$Proj = new \Project($pid);
		} else {
			global $Proj;
		}
        // The format is 'ef-' . event name . '-' . form name for longitudinal projects
        // The format is 'ef--' . form name for class projects
        $pieces = explode("-", $selected_form_event);
        // Find the event_id. If no event_name is specified, there is only one event
        if (empty($pieces[1])) {
            $event_id = $Proj->firstEventId;
        } else {
            $event_id = null;
            $events = \REDCap::getEventNames(true);
            foreach ($events as $event_num => $event_name) {
                if ($event_name == $pieces[1]) {
                    $event_id = $event_num;
                    break;
                }
            }
        }
        return array($pieces[1], $event_id, $pieces[2]);
    }

    public function deleteForm($selected_event_id, $selected_form, $repeating_form, $record_list, $change_reason="", $pid=null, $userid_logging="")
    {
        global $enable_edit_survey_response, $user_rights, $randomization;
		if (isinteger($pid)) {
			$Proj = new \Project($pid);
		} else {
			global $Proj;
		}
        $project_id = $Proj->getId();
        $rf = new \RepeatingForms($project_id, $selected_form);

        $Locking = new Locking();
        $Locking->findLocked($Proj, $record_list);

        $survey_id = $Proj->forms[$selected_form]['survey_id'] ?? null;

        $formEditRights = UserRights::convertToLegacyDataViewingRights($user_rights['forms'][$selected_form]);
        $userCanEditResponses = defined("CRON") || ($enable_edit_survey_response && $formEditRights == '3');

        $econsentEnabledForSurvey = false;
        $eConsentResponseIsEditable = false;
        if ($survey_id != null && Econsent::econsentEnabledForSurvey($survey_id)) {
            $econsentSettings = Econsent::getEconsentSurveySettings($survey_id);
            $eConsentResponseIsEditable = ($econsentSettings['allow_edit'] == '1');
            $econsentEnabledForSurvey = true;
        }

        $performSurveyLevelChecks = ($survey_id != null && (!$enable_edit_survey_response || !$userCanEditResponses || $econsentEnabledForSurvey));

        // Have the records been randomized? If so, make sure the user can't deleted the randomization field
        $formContainsRandFields = false; // default
        if ($randomization && Randomization::setupStatus()) {
            // Get randomization attributes
            $randAttrAll = Randomization::getAllRandomizationAttributes($project_id);
            $rids = array_keys($randAttrAll);
            foreach ($randAttrAll as $randAttr) {
                // Form contains randomization field
                $formContainsRandFields = ($randAttr['targetEvent'] == $selected_event_id && $Proj->metadata[$randAttr['targetField']]['form_name'] == $selected_form);
                if ($formContainsRandFields) break;
                // Loop through strata fields
                foreach ($randAttr['strata'] as $strata_field=>$strata_event) {
                    if ($strata_event == $selected_event_id && $Proj->metadata[$strata_field]['form_name'] == $selected_form) {
                        $formContainsRandFields = true;
                        break 2;
                    }
                }
            }
        }

        if ($repeating_form) {
            foreach($record_list as $record_id) {
                // Check 0) If record has been randomized and this form contains the randomization field or strata fields, skip record
                if ($formContainsRandFields) {
                    // Loop through all Randomization RIDs
                    foreach ($rids as $rid) {
                        if (Randomization::wasRecordRandomized($record_id, $rid)) {
                            continue;
                        }
                    }
                }
                // Loop through instance
                $rf->loadData($record_id, $selected_event_id);
                $all_instances = $rf->getAllInstanceIds($record_id, $selected_event_id);
                foreach ($all_instances as $instance_id)
                {
                    // Check 1) If the form is locked, skip it (cannot delete while locked)
                    if (isset($Locking->locked[$record_id][$selected_event_id][$instance_id][$selected_form."_complete"])) continue;
                    // Survey-related checks
                    if ($performSurveyLevelChecks) {
                        // Is this a survey response? And if so, is it a completed survey response?
                        $responseStatus = Survey::isResponseCompleted($survey_id, $record_id, $selected_event_id, $instance_id);
                        $isSurveyResponse = ($responseStatus !== false);
                        $isCompletedSurveyResponse = ($responseStatus == '1');
                        // Check 2) If no users are allowed to modify survey responses AND this form data is a survey response (not just form data), skip it
                        if ($isSurveyResponse && !$enable_edit_survey_response) continue;
                        // Check 3) If the user does not have form-level rights to modify survey responses for this instrument AND this is a completed survey response, skip it
                        if ($isSurveyResponse && !$userCanEditResponses) continue;
                        // Check 4) If this is a completed e-Consent response, in which e-Consent responses are not allowed to be edited for this survey, skip it
                        if ($isCompletedSurveyResponse && $econsentEnabledForSurvey && !$eConsentResponseIsEditable) continue;
                    }

                    // Delete form instance data
                    $rf->deleteInstance($record_id, $instance_id, $selected_event_id, $change_reason, $userid_logging);
                }
            }
        } else {
            $instance_id = 1; // default for non-repeating data
            foreach ($record_list as $record_id)
            {
                // Check 0) If record has been randomized and this form contains the randomization field or strata fields, skip record
                if ($formContainsRandFields && Randomization::wasRecordRandomized($record_id)) continue;
                // Check 1) If the form is locked, skip it (cannot delete while locked)
                if (isset($Locking->locked[$record_id][$selected_event_id][$instance_id][$selected_form."_complete"])) continue;
                // Survey-related checks
                if ($performSurveyLevelChecks) {
                    // Is this a survey response? And if so, is it a completed survey response?
                    $responseStatus = Survey::isResponseCompleted($survey_id, $record_id, $selected_event_id, $instance_id);
                    $isSurveyResponse = ($responseStatus !== false);
                    $isCompletedSurveyResponse = ($responseStatus == '1');
                    // Check 2) If no users are allowed to modify survey responses AND this form data is a survey response (not just form data), skip it
                    if ($isSurveyResponse && !$enable_edit_survey_response) continue;
                    // Check 3) If the user does not have form-level rights to modify survey responses for this instrument AND this is a completed survey response, skip it
                    if ($isSurveyResponse && !$userCanEditResponses) continue;
                    // Check 4) If this is a completed e-Consent response, in which e-Consent responses are not allowed to be edited for this survey, skip it
                    if ($isCompletedSurveyResponse && $econsentEnabledForSurvey && !$eConsentResponseIsEditable) continue;
                }

                // Delete form data
                \Records::deleteForm($project_id, $record_id, $selected_form, $selected_event_id, $instance_id, null, $change_reason, $userid_logging);
            }
        }
    }

    /**
     * Return HTML of background deletions listing
     *
     * @return string
     */
    public function renderBackgroundDeletionResults()
    {
        addLangToJS(['data_import_tool_346','data_entry_712','data_import_tool_348','data_import_tool_349','data_import_tool_350','data_import_tool_351','data_entry_718',
            'data_entry_719','data_import_tool_354','data_entry_720','data_entry_724','data_entry_714','data_entry_716','data_import_tool_393',
            'data_entry_717','global_79','data_entry_713','data_entry_715','email_users_112','data_import_tool_374']);
        // Display success if BACKGROUND UPLOAD WAS A SUCCESS
        $html = '';
        if (isset($_GET['async_success']))
        {
            if ($_GET['async_success'] == '1') {
                $userEmail = User::getUserInfo(USERID)['user_email'];
                $html .=   "<div id='async_success_dialog' class='simpleDialog' title='".RCView::tt_js('data_entry_721')."'>
                            <div class='darkgreen'><i class=\"fa-solid fa-check\"></i> <span style='font-size:14px;'>".RCView::tt("data_entry_722")." ".
                    RCView::a(['href'=>'mailto:'.$userEmail, 'style'=>'text-decoration:underline;'], $userEmail)." ".RCView::tt("data_entry_723")."</span></div></div>";
            } else {
                $html .= "<div id='async_success_dialog' class='simpleDialog' title='".RCView::tt_js('global_01')."'>
						  <div class='red'><i class=\"fa-solid fa-check\"></i> <span style='font-size:14px;'>".RCView::tt("data_import_tool_312")."</span></div></div>";
            }
        }
        // Display the progress table
        $html .= "<div id='background-deletion-table-parent' style='max-width: 850px;'>
					<table id='background-deletion-table'></table>
				 </div>";
        print $html;
    }

    /**
     * AJAX request to view a project's background deletions
     *
     * @return string
     */
    public function loadBackgroundDeletionsTable()
    {
        global $lang;
        $formatDecimal = User::get_user_number_format_decimal();
        $formatThousands = User::get_user_number_format_thousands_separator();
        // Get list of processes
        $sql = "select i.delete_id, u.username, i.request_time, i.completed_time, i.total_processing_time, 
       			i.status, i.records_provided, i.records_deleted, i.total_errors
				from redcap_record_background_delete i 
				left join redcap_user_information u on i.user_id = u.ui_id
				where i.project_id = ?
				order by i.delete_id desc";
        $q = db_query($sql, PROJECT_ID);
        $rows = [];
        while ($row = db_fetch_assoc($q))
        {
            // Format some things
            $delete_id = $row['delete_id'];
            $row['total_processing_time'] = strtotime(($row['completed_time'] == null) ? NOW : $row['completed_time']) - strtotime($row['request_time']);
            $row['total_processing_time'] = round($row['total_processing_time']/60);
            if ($row['total_processing_time'] > 0) {
                $total_processing_time_display = number_format($row['total_processing_time'], 0, $formatDecimal, $formatThousands);
            } else {
                $total_processing_time_display = '< 1';
            }
            if ($row['status'] == 'FAILED'|| $row['status'] == 'CANCELED') {
                $total_processing_time_display = $row['total_processing_time'] = "";
            }
            $row['total_processing_time'] = ['sort'=>$row['total_processing_time'], 'display'=>$total_processing_time_display];
            $row['records_provided'] = ['sort'=>$row['records_provided'], 'display'=>number_format($row['records_provided'], 0, $formatDecimal, $formatThousands)];
            $row['records_deleted'] = ['sort'=>$row['records_deleted'], 'display'=>number_format($row['records_deleted']??0, 0, $formatDecimal, $formatThousands)];
            $row['request_time'] = ['sort'=>$row['request_time'], 'display'=>"<div class='nowrap fs12'>" . DateTimeRC::format_user_datetime($row['request_time'], 'Y-M-D_24') . "</div>"];
            $row['completed_time'] = ['sort'=>$row['completed_time'], 'display'=>"<div class='nowrap fs12'>" . DateTimeRC::format_user_datetime($row['completed_time'], 'Y-M-D_24') . "</div>"];
            $row['username'] = "<div class='fs12'>" . $row['username'] . "</div>";
            $status = $row['status'];
            if ($row['status'] == 'COMPLETED') {
                $row['status'] = "<div class='nowrap boldish text-successrc'><i class=\"fa-solid fa-check\"></i> {$lang['edit_project_207']}</div>";
            } elseif ($row['status'] == 'FAILED') {
                $row['status'] = "<div class='nowrap boldish text-dangerrc'><i class=\"fa-solid fa-circle-exclamation\"></i> {$lang['data_import_tool_337']}</div>";
            } elseif ($row['status'] == 'CANCELED') {
                $row['status'] = "<div class='nowrap boldish text-dangerrc'><i class=\"fa-solid fa-xmark\"></i> {$lang['scheduling_74']}</div>";
            } elseif ($row['status'] == 'PROCESSING') {
                $row['status'] = "<div class='nowrap boldish'><i class=\"fa-solid fa-spinner\"></i> {$lang['data_import_tool_338']}</div>";
            } elseif ($row['status'] == 'PAUSED') {
                $row['status'] = "<div class='nowrap boldish'><i class=\"fa-solid fa-pause\"></i> {$lang['data_import_tool_375']}</div>";
            } elseif ($row['status'] == 'INITIALIZING') {
                $row['status'] = "<div class='nowrap boldish'><i class=\"fa-solid fa-hourglass\"></i> {$lang['data_import_tool_383']}</div>";
            } else { // QUEUED
                $row['status'] = "<div class='nowrap boldish'><i class=\"fa-solid fa-pause\"></i> {$lang['data_import_tool_339']}</div>";
            }
            $row['status'] .= "<input id='deletion_id_$delete_id' type='hidden' value='$delete_id'>";
            $errorDisplay = RCView::div(['class'=>($row['total_errors'] > 0 ? 'boldish' : '')], number_format($row['total_errors']??0, 0, $formatDecimal, $formatThousands));
            if ($status == 'PROCESSING' || $status == 'QUEUED') {
                // Still processing, so add a Cancel button
                $errorDisplay .= RCView::div(['class'=>'mt-1'], RCView::button(['class'=>'btn btn-xs fs11 btn-rcred nowrap px-1 py-0', 'onclick'=>"cancelBgDelete($delete_id);"], $lang['data_entry_720']));
            } elseif ($row['total_errors'] > 0) {
                // Done processing
                $errorDisplay .= RCView::div(['class'=>'mt-1'], RCView::button(['class'=>'btn btn-xs fs11 btn-rcgreen nowrap px-1 py-0', 'onclick'=>"viewBgDeleteDetails($delete_id);"], $lang['data_import_tool_355']));
            }
            $row['total_errors'] = ['sort'=>$row['total_errors'], 'display'=>$errorDisplay];
            // Remove some non-displayable values
            unset($row['delete_id']);
            // Add to row
            $rows[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode(['data' => $rows], JSON_PRETTY_PRINT);
    }

    /**
     * Cancel a background delete (only possible if user is the requester for this deletion process)
     * @param integer $delete_id
     * @param string $action
     *
     * @return void
     */
    public static function cancelBackgroundDelete($delete_id, $action='view')
    {
        if (!isinteger($delete_id)) exit('10');
        if (!self::currentUserIsAsyncUploader($delete_id) && !UserRights::isSuperUserNotImpersonator()) {
            exit('2');
        } elseif ($action == 'view') {
            exit('1');
        } elseif ($action == 'save') {
            exit(self::cancelBackgroundDeleteAction($delete_id) ? '1' : '0');
        } else {
            exit('20');
        }
    }

    /**
     * Return true/false based on if the current user is the same as the requester who began the deletion
     * @param integer $delete_id
     *
     * @return boolean
     */
    public static function currentUserIsAsyncUploader($delete_id)
    {
        return self::getAsyncDeletionAttr($delete_id)['user_id'] == UI_ID;
    }

    /**
     * Get deletion process attributes by delete_id
     * @param integer $delete_id
     *
     * @return array
     */
    public static function getAsyncDeletionAttr($delete_id)
    {
        $sql = "select * from redcap_record_background_delete where delete_id = ?";
        $q = db_query($sql, $delete_id);
        return (db_num_rows($q) ? db_fetch_assoc($q) : []);
    }

    // View the details of a background delete
    public static function viewBackgroundDeleteDetails($delete_id)
    {
        if (!isinteger($delete_id)) exit('ERROR');
        $isUploaderOrAdmin = (self::currentUserIsAsyncUploader($delete_id) || UserRights::isSuperUserNotImpersonator());
        $btnDisabled = $isUploaderOrAdmin ? "" : "disabled";
        $deleteAttr = self::getAsyncDeletionAttr($delete_id);
        $html = "<div>
					".RCView::tt_i('data_entry_725', [$deleteAttr['total_errors'],  number_format($deleteAttr['records_provided']-$deleteAttr['records_deleted'], 0, User::get_user_number_format_decimal(), User::get_user_number_format_thousands_separator())])."
				 </div>";
        $html .= "<div class='text-center mt-4'>
                    <button class='btn btn-sm btn-primaryrc' onclick=\"window.location.href=app_path_webroot+'index.php?route=BulkRecordDeleteController:downloadBackgroundErrors&delete_id=$delete_id&pid='+pid;\" $btnDisabled><i class=\"fs14 fa-solid fa-file-arrow-down\"></i> ".RCView::tt('data_import_tool_370')."</button>
                  </div>";
        /*$html .= "<div class='text-center mt-3'>
                    <button class='btn btn-sm btn-rcgreen' onclick=\"window.location.href=app_path_webroot+'index.php?route=BulkRecordDeleteController:downloadBackgroundErrorData&delete_id=$delete_id&pid='+pid;\" $btnDisabled><i class=\"fs14 fa-solid fa-file-csv\"></i> ".RCView::tt('data_import_tool_371')."</button>
                  </div>";*/
        if (!$isUploaderOrAdmin) {
            $html .= "<div class='red mt-4 mb-2'>
                        <i class=\"fa-solid fa-circle-exclamation\"></i> ".RCView::tt('data_entry_726')."</button>
                      </div>";
        }
        print $html;
    }

    // Download errors from a background deletion
    public static function downloadBackgroundErrors($delete_id)
    {
        if (!isinteger($delete_id) || (!self::currentUserIsAsyncUploader($delete_id) && !UserRights::isSuperUserNotImpersonator())) exit('ERROR');
        global $lang, $Proj;
        // Get attributes of the import
        $deleteAttr = self::getAsyncDeletionAttr($delete_id);
        // Get delimiter supplied by the user
        $origDelimiter = ","; // Default separator for CSV
        if ($origDelimiter == 'TAB') $origDelimiter = "\t";
        // CSV file
        $timeDeleted = str_replace([' ','-',':'], ['_','',''], $deleteAttr['request_time']);
        $filename = "RecordDeleteErrors".$delete_id."_PID".PROJECT_ID."_".$timeDeleted.".csv";
        // Open connection to create file in memory and write to it
        $fp = fopen('php://memory', "x+");
        if ($Proj->longitudinal && $Proj->multiple_arms) {
	        $headers = array($lang['global_49'], $lang['global_08'], $lang['data_import_tool_100']);
        } else {
	        $headers = array($lang['global_49'], $lang['data_import_tool_100']);
        }
        // Headers
        fputcsv($fp, $headers, $origDelimiter, '"', '');
        // Get the errors from the database
        $sql = "select i.errors, i.error_count, i.record, i.arm_id
                from redcap_record_background_delete_items i, redcap_record_background_delete d
                where d.delete_id = ? and d.delete_id = i.delete_id and i.row_status = 'FAILED'
                order by i.dr_id";
        $q = db_query($sql, $delete_id);
        $lines = [];
        while ($row = db_fetch_assoc($q)) {
            $l = "\"".str_replace('"','""',$row['record'])."\",";
	        if ($Proj->longitudinal && $Proj->multiple_arms) {
		        $l .= $Proj->getArmNumFromArmId($row['arm_id']).",";
	        }
            // Split up into separate rows
            if ($row['error_count'] > 1) {
                foreach (explode("\n", $row['errors']) as $thisRow) {
                    $l .= trim($thisRow);
                }
            } else {
                $l .= "\"".str_replace('"','""',$row['errors'])."\"";
            }
            $lines[] = $l;
        }
        foreach ($lines as $line) {
            // Convert from always-comma CSV to user-defined CSV
            $line = explode(",", $line, 4);
            // Remove quotes around each part, if needed
            foreach ($line as &$thisline) {
                $firstChar = substr($thisline, 0, 1);
                if ($firstChar == '"' || $firstChar == "'") {
                    $thisline = substr($thisline, 1, -1);
                }
            }
            // Add row
            fputcsv($fp, $line, $origDelimiter, '"', '');
        }
        // Open file for reading and output to user
        fseek($fp, 0);
        // Logging
        Logging::logEvent("", "redcap_record_background_delete", "MANAGE", $delete_id, "delete_id = " . $delete_id, "Download error file for background record delete");
        // Output to file
        header('Pragma: anytextexeptno-cache', true);
        header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=$filename");
        print addBOMtoUTF8(stream_get_contents($fp));
    }
}
