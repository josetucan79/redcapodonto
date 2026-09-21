<?php
use Vanderbilt\REDCap\Classes\MyCap\Task;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTask;
use Vanderbilt\REDCap\Classes\MyCap\ZeroDateTask;

if (in_array($eventId, $scheduledEvents)) { // Enabled
    $schedule_relative_to = $install_date;
    $schedule_type = $infinite;
} else { // Disabled
    // Optional Settings
    $allow_retro_completion = $allow_save_complete_later = $include_instruction_step = $include_completion_step = 0;
    // Task Schedule
    $schedule_relative_to = $install_date;
    $schedule_type = $infinite;
    $schedule_frequency = $daily;
    $schedule_ends = $never;
    $schedule_days_fixed = $schedule_days_of_the_week = $schedule_days_of_the_month = $schedule_end_count = $schedule_end_after_days = $schedule_end_date = '';
    $schedule_interval_week = $schedule_interval_month = 1;
    if ($Proj->longitudinal) {
        // Set default to days offset of event
        $schedule_relative_offset = $Proj->eventInfo[$eventId]['day_offset'];
    } else {
        $schedule_relative_offset = 0;
    }
}
if (isset($task_id) && $task_id != '') { // Get values for edit Task Settings at event-level
    $scheduleArr = Task::getTaskSchedulesByEventId($task_id, $eventId);
    foreach ($scheduleArr as $key => $value) {
        $$key = $value;
    }
} else { // Set default values for create task form
    // Optional Settings
    $allow_retro_completion = $allow_save_complete_later = $include_instruction_step = $include_completion_step = 0;
    // Task Schedule
    $schedule_relative_to = $install_date;
    $schedule_type = $infinite;
    $schedule_frequency = $daily;
    $schedule_ends = $never;
    $schedule_days_fixed = $schedule_days_of_the_week = $schedule_days_of_the_month = $schedule_end_count = $schedule_end_after_days = $schedule_end_date = '';
    $schedule_interval_week = $schedule_interval_month = 1;
    if ($Proj->longitudinal) {
        // Set default to days offset of event
        $schedule_relative_offset = $Proj->eventInfo[$eventId]['day_offset'];
    } else {
        $schedule_relative_offset = 0;
    }
}
if ($Proj->longitudinal && $Proj->multiple_arms) {
    $baselineDates = ZeroDateTask::baselineDatesForArms();
}
switch($setUpSection) {
    case 'optional_settings':
        ?>
        <tr data-event="event_<?php echo $eventId;?>" id="tr-optional-<?php echo $eventId;?>">
            <td>
                <table width="100%">
                    <?php
                        if (!$batteryInstrumentIssueExists) {
                            $allow_retroactive_completion_checked = $allow_retro_completion ? "checked = 'checked'" : "";
                            $disabled = $style = '';
                            if ($schedule_type == $infinite || $schedule_type == $one_time) {
                                $disabled = "disabled";
                                $style = 'style="opacity: 0.6;"';
                            }
                    ?>
                    <tr class="mycap_setting_row">
                        <td colspan="3">
                            <div class="header" style="padding:7px 10px 5px;margin:-5px -8px 10px;color: #800000;">
                                <i class="fas fa-cog"></i> <?php echo RCView::tt('design_984') ?>
                                <?php echo Task::getCopyToDropdownHTML($form, $eventId, 'optional'); ?>
                            </div>

                        </td>
                    </tr>
                    <tr class="mycap_setting_row" id="allow_retro_completion_row-<?php echo $eventId;?>" <?php echo $style;?>>
                        <td valign="top" style="width:20px;">
                            <input type="checkbox" <?php echo $disabled;?> <?php echo $allow_retroactive_completion_checked;?> style="position:relative;top:2px;" id="allow_retroactive_completion-<?php echo $eventId;?>" name="allow_retroactive_completion-<?php echo $eventId;?>">
                        </td>
                        <td valign="top" style="padding-bottom:3px;" colspan=2>
                            <label for="allow_retroactive_completion-<?php echo $eventId;?>"><img src="<?php echo APP_PATH_IMAGES ?>calendar_exclamation.png" alt="">
                                <?php echo RCView::b(RCView::tt('mycap_mobile_app_117')) . " <span class='newdbsub'>" . RCView::tt('mycap_mobile_app_118')."</span>" ?></label>
                        </td>
                    </tr>
                    <?php }
                    if ($is_active_task == 0 && !$isPromis && !$batteryInstrumentIssueExists) {
                        $allow_saving_checked = $allow_save_complete_later ? "checked = 'checked'" : "";
                    ?>
                    <tr class="mycap_setting_row">
                        <td valign="top" style="width:20px;">
                            <input type="checkbox" <?php echo $allow_saving_checked;?> style="position:relative;top:2px;" id="allow_saving-<?php echo $eventId;?>" name="allow_saving-<?php echo $eventId;?>">
                        </td>
                        <td valign="top" style="padding-bottom:3px;" colspan=2>
                            <label for="allow_saving-<?php echo $eventId;?>"><img src="<?php echo APP_PATH_IMAGES ?>arrow_circle_315.png" alt="">
                                <?php echo RCView::b(RCView::tt('mycap_mobile_app_119')) . " <span class='newdbsub'>" . RCView::tt('mycap_mobile_app_120')."</span>" ?></label>
                        </td>
                    </tr>
                    <?php
                    $instruction_step_checked = $include_instruction_step ? "checked='checked'" : "";
                    $completion_step_checked = $include_completion_step ? "checked='checked'" : "";

                    $instruction_step_display = $include_instruction_step ? "" : "display:none;";
                    $completion_step_display = $include_completion_step ? "" : "display:none;";
                    ?>
                    <tr class="mycap_setting_row">
                        <td valign="top" style="width:20px;">
                            <input type="checkbox" <?php echo $instruction_step_checked;?> style="position:relative;top:2px;" id="instruction_step-<?php echo $eventId;?>" name="instruction_step-<?php echo $eventId;?>" onchange="
                                    if ($(this).is(':checked')){
                                        $('#instruction_steps_settings-<?php echo $eventId;?>').slideDown('fast');
                                        $('#instruction_step_title-<?php echo $eventId;?>').focus();
                                    } else {
                                        $('#instruction_steps_settings-<?php echo $eventId;?>').slideUp('fast');
                                    }
                                ">
                        </td>
                        <td valign="top" style="padding-bottom:3px;" colspan=2>
                            <label for="instruction_step-<?php echo $eventId;?>"><i class="fas fa-list-ul"></i> <?php echo RCView::b(RCView::tt('mycap_mobile_app_121')) . " <span class='newdbsub'>" . RCView::tt('mycap_mobile_app_122')."</span>" ?></label>
                            <div class="additional-inputs-div" id="instruction_steps_settings-<?php echo $eventId;?>" style="padding:5px 8px;background-color:#f5f5f5;border:1px solid #ddd;font-size:12px;margin:8px 0 8px 0;<?php echo $instruction_step_display;?>">
                                <div style="margin-bottom:8px;color:#A00000;line-height:14px; width: 100%;">
                                    <div style="font-weight:bold;margin:3px 0 6px;font-size:13px;">
                                        <i class="fas fa-list-ul"></i>&nbsp; <?php echo RCView::tt('mycap_mobile_app_164') ?>
                                    </div>
                                </div>
                                <div>
                                    <table width="100%" cellpadding="3" cellspacing="3" border="0" style="font-size: 12px;">
                                        <tr>
                                            <td width="14%" style="font-weight: bold;">
                                                <?php echo RCView::tt('training_res_05').RCView::tt('colon'); ?>
                                                <div class="requiredlabel p-0">* <?=RCView::tt('data_entry_39')?></div>
                                            </td>
                                            <td valign="top">
                                                <?php echo RCView::input(array('id'=>'instruction_step_title-'.$eventId, 'style'=>'width:80%;height:24px;margin:0 3px 0 10px;', 'class'=>'x-form-text x-form-field',
                                                    'name'=>'instruction_step_title-'.$eventId, 'type'=>'text', 'value' => $instruction_step_title??""));?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td valign="top" style="font-weight: bold;">
                                                <?php echo RCView::tt('mycap_mobile_app_126').RCView::tt('colon'); ?>
                                                <div class="requiredlabel p-0">* <?=RCView::tt('data_entry_39')?></div>
                                            </td>
                                            <td valign="top">
                                                <?php echo RCView::textarea(array('id'=>'instruction_step_content-'.$eventId,
                                                    'name'=>'instruction_step_content-'.$eventId, 'class'=>'x-form-field notesbox','style'=>'width:90%;margin:0 3px 0 10px;vertical-align: top;'), $instruction_step_content??"") ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr class="mycap_setting_row">
                        <td valign="top" style="width:20px;">
                            <input type="checkbox" <?php echo $completion_step_checked; ?> style="position:relative;top:2px;" id="completion_step-<?php echo $eventId;?>" name="completion_step-<?php echo $eventId;?>" onchange="
                                    if ($(this).is(':checked')){
                                        $('#completion_steps_settings-<?php echo $eventId;?>').slideDown('fast');
                                        $('#completion_step_title-<?php echo $eventId;?>').focus();
                                    } else {
                                        $('#completion_steps_settings-<?php echo $eventId;?>').slideUp('fast');
                                    }
                                ">
                        </td>
                        <td valign="top" style="padding-bottom:15px;" colspan=2>
                            <label for="completion_step-<?php echo $eventId;?>"><i class="fas fa-list-ul"></i> <?php echo RCView::b(RCView::tt('mycap_mobile_app_123')) . " <span class='newdbsub'>" . RCView::tt('mycap_mobile_app_124')."</span>" ?></label>
                            <div class="additional-inputs-div" id="completion_steps_settings-<?php echo $eventId;?>" style="padding:5px 8px;background-color:#f5f5f5;border:1px solid #ddd;font-size:12px;margin:8px 0 8px 0;<?php echo $completion_step_display;?>">
                                <div style="margin-bottom:8px;color:#A00000;line-height:14px; width: 100%;">
                                    <div style="font-weight:bold;margin:3px 0 6px;font-size:13px;">
                                        <i class="fas fa-list-ul"></i>&nbsp; <?php echo RCView::tt('mycap_mobile_app_165') ?>
                                    </div>
                                </div>
                                <div>
                                    <table width="100%" cellpadding="3" cellspacing="3" border="0" style="font-size: 12px;">
                                        <tr>
                                            <td width="14%" style="font-weight: bold;">
                                                <?php echo RCView::tt('training_res_05').RCView::tt('colon'); ?>
                                                <div class="requiredlabel p-0">* <?=RCView::tt('data_entry_39')?></div>
                                            </td>
                                            <td valign="top">
                                                <?php echo RCView::input(array('id'=>'completion_step_title-'.$eventId, 'style'=>'width:80%;height:24px;margin:0 3px 0 10px;', 'class'=>'x-form-text x-form-field',
                                                    'name'=>'completion_step_title-'.$eventId, 'type'=>'text', 'value' => $completion_step_title??""));?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td valign="top" style="font-weight: bold;">
                                                <?php echo RCView::tt('mycap_mobile_app_126').RCView::tt('colon'); ?>
                                                <div class="requiredlabel p-0">* <?=RCView::tt('data_entry_39')?></div>
                                            </td>
                                            <td valign="top">
                                                <?php echo RCView::textarea(array('id'=>'completion_step_content-'.$eventId, 'name'=>'completion_step_content-'.$eventId, 'class'=>'x-form-field notesbox',
                                                    'style'=>'width:90%;margin:0 3px 0 10px;vertical-align: top;'), $completion_step_content??"") ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
            </table>
        </td>
    </tr>
    <?php
        break;

    case 'schedules':
        if (is_null($schedule_type)) {
            $schedule_type = $infinite;
        }
        if (is_null($schedule_frequency)) {
            $schedule_frequency = $daily;
        }
        if (is_null($schedule_ends)) {
            $schedule_ends = $never;
        }
        if (is_null($schedule_relative_offset)) {
            $schedule_relative_offset = 0;
        }
        $schedule_days_of_the_week_list = explode(",", $schedule_days_of_the_week ?? "");

        $schedule_list = [];
        if ($schedule_ends != $never) {
            $schedule_list = explode(",", $schedule_ends);
        }

        $baseline_date_enabled = true;
        if ($Proj->longitudinal && $Proj->multiple_arms) {
            $baseline_date_enabled = ($baselineDates[$Proj->eventInfo[$eventId]['arm_num']]!="" ? true : false);
        }
        ?>
        <tr data-event="event_<?php echo $eventId;?>" id="tr-schedules-<?php echo $eventId;?>">
            <td>
                <table width="100%">
                    <tr class="mycap_setting_row">
                        <td colspan="2">
                            <div class="header" style="padding:7px 10px 5px;margin:-5px -8px 10px; color: #800000;">
                                <i class="fas fa-clock"></i> <?php print RCView::tt('mycap_mobile_app_137');?>
                                <?php echo Task::getCopyToDropdownHTML($form, $eventId, 'schedules'); ?>
                            </div>
                            <div class="clear"></div>
                        </td>
                    </tr>
                    <tr class="mycap_setting_row" <?php echo (($use_baseline_date == false || $baseline_date_enabled == false) ? 'style="display:none;"' : '');?> >
                        <td valign="top" style="font-weight:bold;padding-bottom:15px;" width="30%">
                            <?php print RCView::tt('mycap_mobile_app_128');?>
                        </td>
                        <td valign="top" style="padding-left:15px;padding-bottom:15px;">
                            <div>
                                <input type="radio" name="schedule_relative_to-<?php echo $eventId;?>" id="install_date-<?php echo $eventId;?>" <?php echo ($schedule_relative_to == $install_date ? "checked" : "");?> value="<?php echo $install_date; ?>">
                                <label for="install_date-<?php echo $eventId;?>"><?php print RCView::tt('mycap_mobile_app_125'); ?>
                            </div>
                            <div style="margin:4px 0;">
                                <input type="radio" name="schedule_relative_to-<?php echo $eventId;?>" id="baseline_date-<?php echo $eventId;?>" <?php echo ($schedule_relative_to == $baseline_date ? "checked" : "");?> value="<?php echo $baseline_date; ?>">
                                <label for="baseline_date-<?php echo $eventId;?>"><?php print RCView::tt('mycap_mobile_app_127'); ?>
                            </div>
                        </td>
                    </tr>
                    <tr class="mycap_setting_row">
                        <td valign="top" style="font-weight:bold;padding-bottom:15px;" width="30%">
                            <?php echo RCView::tt('mycap_mobile_app_140') ?>
                        </td>
                        <td valign="top" style="padding-left:15px;padding-bottom:15px;">
                            <div>
                                <input class="schedule_type_sel" id="onetime-<?php echo $eventId;?>" type="radio" name="schedule_type-<?php echo $eventId;?>" <?php echo ($schedule_type == $one_time ? "checked" : "") ?> value="<?php echo $one_time; ?>">
                                <label for="onetime-<?php echo $eventId;?>"><?php echo RCView::tt('mycap_mobile_app_141'); ?></label>
                            </div>
                            <div style="margin:4px 0;">
                                <input class="schedule_type_sel" id="infinite-<?php echo $eventId;?>" type="radio" name="schedule_type-<?php echo $eventId;?>" <?php echo ($schedule_type == $infinite ? "checked" : "") ?> value="<?php echo $infinite; ?>">
                                <label for="infinite-<?php echo $eventId;?>"><?php echo RCView::tt('mycap_mobile_app_142'); ?></label>
                            </div>
                            <div style="margin:4px 0;">
                                <input class="schedule_type_sel" id="repeating-<?php echo $eventId;?>" type="radio" name="schedule_type-<?php echo $eventId;?>" <?php echo ($schedule_type == $repeating ? "checked='checked'" : "") ?> value="<?php echo $repeating; ?>">
                                <label for="repeating-<?php echo $eventId;?>"><?php echo RCView::tt('mycap_mobile_app_143'); ?></label>
                            </div>
                            <?php
                                $displayWeekly = $displayMonthly = '';
                                if ($schedule_frequency == $daily || $schedule_frequency == '') {
                                    $displayMonthly = $displayWeekly = 'none';
                                } elseif ($schedule_frequency == $weekly) {
                                    $displayMonthly = 'none';
                                } elseif ($schedule_frequency == $monthly) {
                                    $displayWeekly = 'none';
                                }

                                $displayEndTaskFields = '';
                                if ($schedule_type == $one_time || $schedule_type == $fixed) {
                                    $displayEndTaskFields = 'none';
                                }
                                $repeating_disable_css = ($schedule_type == $repeating) ? "" : "disableInputs";
                            ?>
                            <div id="scheduleRepeatingFields" class="<?php echo $repeating_disable_css; ?>" style="margin-top:0.15rem; margin-left: 15px;" >
                                <div>
                                    <i class="fas fa-redo" style="margin-right:1px;"></i> <?php echo RCView::tt('mycap_mobile_app_823'); ?>
                                    <?php echo RCView::select(array('name'=>"schedule_frequency-".$eventId,'class'=>'x-form-text schedule_frequency_sel', 'style'=>'height:24px;max-width:90px;width:90px;position:relative;top:2px;'),
                                        array($daily=>RCView::tt('mycap_mobile_app_894'), $weekly=>RCView::tt('mycap_mobile_app_895'), $monthly=>RCView::tt('mycap_mobile_app_896')), $schedule_frequency)?>
                                    <span id="schedulePrefix" style="display: none;"> <?php echo RCView::tt('mycap_mobile_app_592'); ?></span>
                                    <span id="scheduleFreqWeekFields" style="display: <?php echo $displayWeekly;?>;">
                                        <select name="schedule_interval_week-<?php echo $eventId;?>" class="x-form-text">
                                            <?php for ($i = 1; $i <= 24; $i++) {
                                                if ($i>1) {
                                                    $week_label = $i. ' '.RCView::tt('mycap_mobile_app_898');
                                                } else {
                                                    $week_label = $i. ' '.RCView::tt('mycap_mobile_app_897');
                                                }
                                            ?>
                                                <option value="<?php echo $i;?>" <?php echo (isset($schedule_interval_week) && $schedule_interval_week == $i) ? 'selected="selected"' : '';  ?>><?php echo $week_label;?></option>
                                            <?php } ?>
                                        </select>
                                    </span>
                                    <span id="scheduleFreqMonthFields" style="display: <?php echo $displayMonthly;?>">
                                        <select name="schedule_interval_month-<?php echo $eventId;?>" class="x-form-text">
                                            <?php for ($i = 1; $i <= 12; $i++) {
                                                if ($i>1) {
                                                    $month_label = $i. ' '.RCView::tt('mycap_mobile_app_900');
                                                } else {
                                                    $month_label = $i. ' '.RCView::tt('mycap_mobile_app_899');
                                                }
                                            ?>
                                                <option value="<?php echo $i;?>" <?php echo (isset($schedule_interval_month) && $schedule_interval_month == $i) ? 'selected="selected"' : '';  ?>><?php echo $month_label;?></option>
                                            <?php } ?>
                                        </select>
                                    </span>
                                </div>
                                <div id="scheduleDaysOfWeekFields" style="padding-top: 10px; display: <?php echo $displayWeekly;?>;">
                                    <i class="far fa-calendar-times-o" style="margin-right:3px;"></i> <?php echo RCView::tt('mycap_mobile_app_827'); ?>
                                    <?php
                                    foreach($daysOfWeek as $val => $day) {
                                        $is_checked = in_array($val, $schedule_days_of_the_week_list) ? 'checked = "checked"' : '';
                                    ?>
                                        <span style="padding-right: 5px;">
                                            <input type="checkbox" value="<?php echo $val;?>" <?php echo $is_checked;?> style="position:relative;top:2px;" id="schedule_days_of_the_week<?php echo $val.'-'.$eventId; ?>" name="schedule_days_of_the_week-<?php echo $eventId; ?>[]">
                                            <label for="schedule_days_of_the_week<?php echo $val.'-'.$eventId; ?>"><?php echo $day; ?></label>
                                        </span>
                                    <?php } ?>
                                </div>
                                <div id="scheduleDaysOfMonthFields" style="padding-top: 10px; display: <?php echo $displayMonthly;?>;">
                                    <i class="far fa-calendar-times-o" style="margin-right:3px;"></i> <?php echo RCView::tt('mycap_mobile_app_824'); ?>
                                    <input type="text" name="schedule_days_of_the_month-<?php echo $eventId;?>" value="<?php echo $schedule_days_of_the_month ?? "";?>" placeholder="1,7" class="x-form-text x-form-field" style="height:24px;position:relative;top:0px;">
                                </div>
                            </div>
                            <?php
                                $fixed_disable_css = ($schedule_type == $fixed) ? "" : "disableInputs";
                            ?>
                            <div style="margin:4px 0;">
                                <input class="schedule_type_sel" id="fixed-<?php echo $eventId;?>" type="radio" name="schedule_type-<?php echo $eventId;?>" <?php echo ($schedule_type == $fixed ? "checked" : "") ?> value="<?php echo $fixed; ?>">
                                <label for="fixed-<?php echo $eventId;?>"><?php echo RCView::tt('mycap_mobile_app_144'); ?></label>
                            </div>
                            <div id="scheduleFixedFields" class="<?php echo $fixed_disable_css; ?>" style="margin-top:0.15rem; margin-left: 15px;">
                                <i class="far fa-calendar-times-o" style="margin-right:3px;"></i> <?php echo RCView::tt('mycap_mobile_app_824'); ?>
                                <input type="text" name="schedule_days_fixed-<?php echo $eventId;?>" placeholder="1,7" value="<?php echo $schedule_days_fixed ?? ""; ?>" class="x-form-text x-form-field" style="height:24px;position:relative;top:0px;">
                            </div>
                        </td>
                    </tr>
                    <tr class="mycap_setting_row">
                        <td valign="top" style="font-weight:bold;padding-bottom:15px;">
                            <?php echo RCView::tt('mycap_mobile_app_145') ?>
                        </td>
                        <td class="external-modules-input-td" style="padding-bottom: 15px; padding-left: 15px;">
                            <?php echo RCView::input(array('id'=>'schedule_relative_offset', 'style'=>'width:30%;', 'class'=>'x-form-text x-form-field', 'name'=>'schedule_relative_offset-'.$eventId, 'type'=>'text', 'value' => $schedule_relative_offset));?>
                        </td>
                    </tr>
                    <?php
                        if ($schedule_type == $infinite) {
                            $schedule_label = RCView::tt('mycap_mobile_app_142');
                        }
                        if ($schedule_type == $repeating) {
                            $schedule_label = RCView::tt('mycap_mobile_app_143');
                        }
                    ?>
                    <tr class="mycap_setting_row" id="endTaskFields" style="display: <?php echo $displayEndTaskFields;?>;" >
                        <td valign="top" style="font-weight:bold;padding-bottom:15px;">
                            <?php echo RCView::tt('mycap_mobile_app_825'); ?> <span id="typeSelection"><?php echo $schedule_label; ?></span> <?php echo RCView::tt('mycap_mobile_app_826'); ?>
                        </td>
                        <td valign="top" style="padding-left:15px;padding-bottom:15px;">
                            <div>
                                <input type="radio" class="schedule-ends" name="schedule_ends-<?php echo $eventId;?>" id="schedule_ends_never-<?php echo $eventId;?>" <?php echo ($schedule_ends == $never ? "checked" : "") ?> value="<?php echo $never; ?>">
                                <label for="schedule_ends_never-<?php echo $eventId;?>" class="m-0 align-middle"><?php echo RCView::tt('mycap_mobile_app_146'); ?></label>
                            </div>
                            <div style="margin:4px 0;">
                                <input type="radio" class="schedule-ends" name="schedule_ends-<?php echo $eventId;?>" id="schedule_ends_conditions-<?php echo $eventId;?>" <?php echo (!empty($schedule_list) ? "checked" : "") ?> value="conditions">
                                <label for="schedule_ends_conditions-<?php echo $eventId;?>" class="m-0 align-middle"><?php echo RCView::tt('mycap_mobile_app_831'); ?>

                                </label>
                            </div>
                            <div style="margin:4px 0; padding-left: 25px;">
                                <input type="checkbox" class="schedule_ends_conditions" name="schedule_ends_list-<?php echo $eventId;?>[]" id="schedule_ends_after_count-<?php echo $eventId;?>" <?php echo (in_array($after_count, $schedule_list) ? "checked" : "") ?> value="<?php echo $after_count; ?>">
                                <label for="schedule_ends_after_count-<?php echo $eventId;?>" class="m-0 align-middle"><?php echo RCView::tt('mycap_mobile_app_147');?> <input type="text" name="schedule_end_count-<?php echo $eventId;?>" value="<?php echo $schedule_end_count??""; ?>" class="x-form-text schedule-end-count-input" maxlength="4" style="height:24px;width:42px;position:relative;top:0px;">
                                    <?php echo RCView::tt('survey_738');?></label>
                            </div>
                            <div style="margin:4px 0;padding-left: 25px;">
                                <input type="checkbox" class="schedule_ends_conditions" name="schedule_ends_list-<?php echo $eventId;?>[]" id="schedule_ends_after_days-<?php echo $eventId;?>" <?php echo (in_array($after_days, $schedule_list) ? "checked" : "") ?> value="<?php echo $after_days; ?>">
                                <label for="schedule_ends_after_days-<?php echo $eventId;?>" class="m-0 align-middle"><?php echo RCView::tt('mycap_mobile_app_148');?> <input type="text" name="schedule_end_after_days-<?php echo $eventId;?>" value="<?php echo $schedule_end_after_days??""; ?>" class="x-form-text schedule-end-after-days" maxlength="4" style="height:24px;width:42px;position:relative;top:0px;">
                                    <?php echo RCView::tt('mycap_mobile_app_149');?></label>
                            </div>
                            <div style="margin:4px 0;padding-left: 25px;">
                                <input type="checkbox" class="schedule_ends_conditions" name="schedule_ends_list-<?php echo $eventId;?>[]" id="schedule_ends_on_date-<?php echo $eventId;?>" <?php echo (in_array($ends_on_date, $schedule_list) ? "checked" : "") ?> value="<?php echo $ends_on_date; ?>">
                                <label for="schedule_ends_on_date-<?php echo $eventId;?>" class="m-0 align-middle"><?php echo RCView::tt('mycap_mobile_app_150');?>
                                    <input id="schedule_end_date-<?php echo $eventId;?>" name="schedule_end_date-<?php echo $eventId;?>" type="text" style="width:123px;" class="x-form-text x-form-field date-input"
                                           placeholder="<?php echo str_replace(array('M','D','Y'),array('MM','DD','YYYY'),DateTimeRC::get_user_format_label());?>"
                                           onblur="redcap_validate(this,'','','hard','date_'+user_date_format_validation,1,1,user_date_format_delimiter);"
                                           value="<?php echo DateTimeRC::format_ts_from_ymd($schedule_end_date??""); ?>"
                                           onkeydown="if(event.keyCode==13){return false;}"
                                           onfocus="this.value=trim(this.value); if(this.value.length == 0 && $('.ui-datepicker:first').css('display')=='none'){$(this).next('img').trigger('click');}">
                                    <span class='df'><?php echo DateTimeRC::get_user_format_label(); ?></span>
                                </label>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    <?php
    break;
}
?>