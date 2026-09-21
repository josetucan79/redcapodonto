<?php
use Vanderbilt\REDCap\Classes\MyCap;
global $Proj;
?>
<script type="text/javascript">
    var oneTimeType = '<?=MyCap\Task::TYPE_ONETIME?>';
    var infiniteType = '<?=MyCap\Task::TYPE_INFINITE?>';
    var repeatingType = '<?=MyCap\Task::TYPE_REPEATING?>';
    var fixedType = '<?=MyCap\Task::TYPE_FIXED?>';

    var dailyFreqVal = '<?=MyCap\Task::FREQ_DAILY?>';
    var weeklyFreqVal = '<?=MyCap\Task::FREQ_WEEKLY?>';
    var monthlyFreqVal = '<?=MyCap\Task::FREQ_MONTHLY?>';

    var endsNever = '<?=MyCap\Task::ENDS_NEVER?>';
    var afterCount = '<?=MyCap\Task::ENDS_AFTERCOUNT?>';
    var afterDays = '<?=MyCap\Task::ENDS_AFTERDAYS?>';
    var onDate = '<?=MyCap\Task::ENDS_ONDATE?>';

    // Task setup validation message variables
    var isLongitudinal = <?php echo $Proj->longitudinal ? 1 : 0 ?>;
    var firstEventId = <?php echo $Proj->firstEventId ?>;
</script>
<style type="text/css">
    th.setup-form-header {
        color:#444;
        background-color:#cccccc;
        text-align: Left;
        font-family: "Open Sans",Helvetica,Arial,Helvetica,sans-serif;
        font-size:13px;
        font-weight: bold;
        border: 1px solid #CCCCCC;
        padding: 0px;
    }
    .error-field {border: 1px solid red !important;}
    .dropdown-options {
        display: flex;
        font-weight: normal;
        padding: 5px 0px 0px 7px;
        align-items: baseline;
    }
</style>
<?php
addLangToJS(['design_774', 'design_776', 'mycap_mobile_app_151', 'mycap_mobile_app_166', 'mycap_mobile_app_167', 'mycap_mobile_app_168',
            'mycap_mobile_app_169', 'mycap_mobile_app_170', 'mycap_mobile_app_171', 'mycap_mobile_app_172', 'mycap_mobile_app_173', 'mycap_mobile_app_174', 'mycap_mobile_app_175',
            'mycap_mobile_app_176', 'config_functions_89', 'data_import_tool_85', 'mycap_mobile_app_178', 'mycap_mobile_app_179', 'mycap_mobile_app_180', 'mycap_mobile_app_181',
            'mycap_mobile_app_182', 'mycap_mobile_app_107', 'design_984', 'mycap_mobile_app_137', 'mycap_mobile_app_779', 'mycap_mobile_app_836', 'mycap_mobile_app_142', 'mycap_mobile_app_143']);
loadJS('MyCapProject.js');

$one_time = MyCap\Task::TYPE_ONETIME;
$infinite = MyCap\Task::TYPE_INFINITE;
$repeating = MyCap\Task::TYPE_REPEATING;
$fixed = MyCap\Task::TYPE_FIXED;

$daily = MyCap\Task::FREQ_DAILY;
$weekly = MyCap\Task::FREQ_WEEKLY;
$monthly = MyCap\Task::FREQ_MONTHLY;

$install_date = MyCap\Task::RELATIVETO_JOINDATE;
$baseline_date = MyCap\Task::RELATIVETO_ZERODATE;
$baseline_date_settings = MyCap\ZeroDateTask::getBaselineDateSettings();
$use_baseline_date = $baseline_date_settings['enabled'] ?? false;

$never = MyCap\Task::ENDS_NEVER;
$after_count = MyCap\Task::ENDS_AFTERCOUNT;
$after_days = MyCap\Task::ENDS_AFTERDAYS;
$ends_on_date = MyCap\Task::ENDS_ONDATE;
?>
<style type="text/css">
    label[for] { cursor: pointer; }
</style>

<form id="saveTaskSettings" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
    <input type="hidden" name="is_active_task" id="is_active_task" value="<?php echo $is_active_task;?>">
    <table cellspacing="3" style="width:100%; font-size: 13px;">
        <tr>
            <td colspan="3">
                <div id="task_enabled_div" class="clearfix <?php echo($enabled_for_mycap ? 'darkgreen' : 'red') ?>" style="max-width:1050px;margin: -5px -7px 0px;font-size:13px;">
                    <div style="float:left;width:250px;font-weight:bold;padding:5px 0 0 25px;">
                        <?php echo RCView::tt('mycap_mobile_app_856') ?>
                    </div>
                    <div style="float:left;padding:5px 0 0;">
                        <i id="task_enabled_img" class="me-2 <?=($enabled_for_mycap ? "fas fa-check-circle text-successrc" : "fas fa-minus-circle")?>"></i>
                        <select name="task_enabled" class="x-form-text x-form-field" style="margin-bottom:3px;"
                                onchange="if ($(this).val()=='1'){
							    $('#task_enabled_img').removeClass('fa-minus-circle').addClass('text-successrc').addClass('fa-check-circle');
                                $('#task_enabled_div').removeClass('red').addClass('darkgreen');
							} else {
							    $('#task_enabled_img').removeClass('text-successrc').removeClass('fa-check-circle').addClass('fa-minus-circle');
                                $('#task_enabled_div').removeClass('darkgreen').addClass('red');
							}">
                            <option value="1" <?php echo ( $enabled_for_mycap ? 'selected' : '') ?>><?php echo RCView::tt('survey_432') ?></option>
                            <option value="0" <?php echo (!$enabled_for_mycap ? 'selected' : '') ?>><?php echo RCView::tt('survey_433') ?></option>
                        </select>
                        <span class="newdbsub ms-4"><?php echo RCView::tt('mycap_mobile_app_857') ?></span>
                    </div>
                </div>
            </td>
        </tr>
        <tr class="mycap_setting_row">
            <td colspan="3">
                <div class="header" style="padding:7px 10px 5px;margin:-5px -8px 10px; color: #800000;"><i class="fas fa-info-circle"></i> <?php print RCView::tt('mycap_mobile_app_107') ?></div>
            </td>
        </tr>
        <?php if (!$batteryInstrumentIssueExists) { ?>
            <tr class="mycap_setting_row">
                <td valign="top" style="width:20px;">
                    <img src="<?php echo APP_PATH_IMAGES ?>tag_orange.png">
                </td>
                <td valign="top" style="font-weight:bold; width:220px;">
                    <?php print RCView::tt('mycap_mobile_app_108') ?><div class="requiredlabel p-0">* <?=RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="task_title" type="text" value="<?php echo htmlspecialchars(label_decode($task_title), ENT_QUOTES) ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_109') ?>
                    </div>
                </td>
            </tr>

            <tr class="mycap_setting_row">
                <td valign="top" style="width:20px;">
                    <img src="<?php echo APP_PATH_IMAGES ?>table_gear.png">
                </td>
                <td valign="top" style="font-weight:bold;padding-bottom:15px;">
                    <?php print RCView::tt('mycap_mobile_app_110') ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:15px;">
                    <?php
                    if ($is_active_task == 0 && !$isPromis) {
                        $questionnaire_format = MyCap\Task::QUESTIONNAIRE;
                        $form_format = MyCap\Task::FORM;
                        ?>
                        <div>
                            <input type="radio" name="question_format" id="questionnaire" <?php echo ($question_format == $questionnaire_format ? "checked" : "") ?> value="<?php echo $questionnaire_format; ?>">
                            <label for="questionnaire"><?php echo MyCap\Task::toString($questionnaire_format)." - "; ?>
                                <span class="newdbsub" style="font-weight:normal;"><i><?php print RCView::tt('mycap_mobile_app_111') ?></i></span></label>
                        </div>
                        <div style="margin:4px 0;">
                            <input type="radio" name="question_format" id="form" <?php echo ($question_format == $form_format ? "checked" : "") ?> value="<?php echo $form_format; ?>">
                            <label for="form"><?php echo MyCap\Task::toString($form_format)." - "; ?></label>
                            <span class="newdbsub" style="font-weight:normal;"><i><?php print RCView::tt('mycap_mobile_app_129') ?></i></span>
                        </div>
                    <?php } else {
                        $urlPostFix = MyCap\ActiveTask::getHelpURLForTaskFormat($question_format);
                        ?>
                        <div style="margin:4px 0;">
                            <input type="hidden" name="question_format" value="<?php echo $question_format;?>">
                            <?php echo MyCap\ActiveTask::toString($question_format); ?>
                        </div>
                    <?php } ?>
                </td>
            </tr>

            <tr class="mycap_setting_row">
                <td valign="top" colspan="2" style="font-weight:bold;padding-bottom:5px;padding-left:5px;">
                    <i class="fas fa-chart-line"></i>&nbsp; <?php print RCView::tt('mycap_mobile_app_114') ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:15px;">
                    <div>
                        <?php if ($is_active_task == 0 && !$isPromis) { ?>
                            <input type="radio" name="card_display" id="percentcomplete" <?php echo ($card_display == MyCap\Task::TYPE_PERCENTCOMPLETE ? "checked" : "") ?> value="<?php echo MyCap\Task::TYPE_PERCENTCOMPLETE; ?>"
                                   onclick="if ($(this).is(':checked')){
                                            $('#cardDateLineFields').slideUp('fast');
                                        }">
                        <?php } ?>
                        <label for="percentcomplete"><?php print RCView::tt('mycap_mobile_app_115'); ?></label><a href='javascript:;' class='help' style='font-size:10px;margin-left:3px;' onclick="simpleDialog(null,null,'percentCompleteExplainPopup',650);">?</a>
                        <!-- PERCENT COMPLETE EXPLANATION DIALOG POP-UP -->
                        <div id="percentCompleteExplainPopup" title="<?php print RCView::tt_js2('mycap_mobile_app_115'); ?>" class="simpleDialog">
                            <div>
                                <?php print RCView::tt('mycap_mobile_app_112') ?>
                            </div>
                            <div style="margin-top: 15px;">
                                <b><?php print RCView::tt('mycap_mobile_app_130') ?></b><br><br>
                                <img style="max-width:400px;" src="<?php echo APP_PATH_IMAGES ?>card_display_percent.png">
                            </div>
                        </div>
                    </div>
                    <?php if ($is_active_task == 0 && !$isPromis) { ?>
                        <div style="margin:4px 0;">
                            <input type="radio" name="card_display" id="chart" <?php echo ($card_display == MyCap\Task::TYPE_DATELINE ? "checked" : "") ?> value="<?php echo MyCap\Task::TYPE_DATELINE; ?>"
                                   onclick="if ($(this).is(':checked')){
                                                $('#cardDateLineFields').slideDown('fast');
                                            }">
                            <label for="chart"><?php print RCView::tt('mycap_mobile_app_116'); ?></label><a href='javascript:;' class='help' style='font-size:10px;margin-left:3px;' onclick="simpleDialog(null,null,'chartExplainPopup',800);">?</a>
                            <!-- CHART EXPLANATION DIALOG POP-UP -->
                            <div id="chartExplainPopup" title="<?php print RCView::tt_js2('mycap_mobile_app_116'); ?>" class="simpleDialog">
                                <div>
                                    <?php print RCView::tt('mycap_mobile_app_113') ?><br>
                                    <?php print RCView::tt('mycap_mobile_app_134') ?><br>
                                    <?php print RCView::tt('mycap_mobile_app_135') ?><br>
                                    <?php print RCView::tt('mycap_mobile_app_136') ?><br>
                                </div>
                                <div style="margin-top: 15px;">
                                    <b><?php print RCView::tt('mycap_mobile_app_130') ?></b><br><br>
                                    <img style="max-width:400px; border: 1px solid #ddd;" src="<?php echo APP_PATH_IMAGES ?>card_display_chart_flutter.png">
                                </div>
                            </div>
                        </div>
                        <?php
                        $modifyInstBtn = RCView::button(array('type'=>'button', 'onclick'=>"window.location.href=app_path_webroot+'Design/online_designer.php?pid={$_GET['pid']}&page={$_GET['page']}';", 'class'=>'jqbuttonmed'),
                            RCView::img(array('src'=>'blog_pencil.png', 'style'=>'vertical-align:middle;position:relative;top:-1px;')) .
                            RCView::span(array('style'=>'vertical-align:middle;color:#444;'), RCView::tt('data_entry_202'))
                        );
                        ?>
                        <div id="cardDateLineFields" style="<?php echo ($card_display == MyCap\Task::TYPE_DATELINE) ? '' : 'display: none;'; ?>">
                            <div class="additional-inputs-div" style="padding:5px 8px;background-color:#f5f5f5;border:1px solid #ddd;font-size:12px;margin:8px 0 0;">
                                <div style="margin-bottom:8px;color:#A00000;line-height:14px; width: 100%;">
                                    <div style="font-weight:bold;margin:3px 0 6px;font-size:13px;">
                                        <i class="fas fa-chart-bar"></i>&nbsp; <?php print RCView::tt('mycap_mobile_app_159') ?>
                                    </div>

                                    <?php print RCView::tt('mycap_mobile_app_163') ?>
                                    <a href="javascript:;" style="font-size:11px;text-decoration:underline;" onclick="simpleDialog(null,null,'chartExplainPopup',800);"><?php print RCView::tt('scheduling_78') ?></a>
                                </div>
                                <div>
                                    <table width="100%" cellpadding="3" cellspacing="3" border="0">
                                        <tr>
                                            <td width="20%" style="font-weight: bold; font-size: 13px;">
                                                <?php print RCView::tt('mycap_mobile_app_160') ?>
                                                <div class="requiredlabel p-0">* <?=RCView::tt('data_entry_39')?></div>
                                            </td>
                                            <td valign="top">
                                                <?php echo RCView::select(array('name'=>"x_date_field", 'class'=>'x-form-text x-form-field',
                                                    'style'=>'height:24px;margin:0 3px 0 10px;max-width:200px; font-size:13px;'), $date_fields, $x_date_field);?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-weight: bold; font-size: 13px;">
                                                <?php print RCView::tt('mycap_mobile_app_161') ?>
                                                <div class="requiredlabel p-0">* <?=RCView::tt('data_entry_39')?></div>
                                            </td>
                                            <td>
                                                <?php echo RCView::select(array('name'=>"x_time_field", 'class'=>'x-form-text x-form-field',
                                                    'style'=>'height:24px;margin:0 3px 0 10px;max-width:200px;font-size: 13px;'), $time_fields, $x_time_field);?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-weight: bold;font-size: 13px;">
                                                <?php print RCView::tt('mycap_mobile_app_162') ?>
                                                <div class="requiredlabel p-0">* <?=RCView::tt('data_entry_39')?></div>
                                            </td>
                                            <td>
                                                <?php echo RCView::select(array('name'=>"y_numeric_field", 'class'=>'x-form-text x-form-field',
                                                    'style'=>'height:24px;margin:0 3px 0 10px;max-width:200px;font-size: 13px;'), $numeric_fields, $y_numeric_field);?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div style="float: right;margin:3px 0;"><?php echo $modifyInstBtn; ?></div>
                                <div class="clear"></div>
                            </div>
                        </div>
                    <?php } ?>
                </td>
            </tr>
            <?php
            // If Active Task, include Active Task Configuration Section
            if (($is_active_task == 1 && !$isPromis) || $isBatteryInstrument) {
                if (is_null($extended_config_json) || empty($extended_config_json)) {
                    $taskObj = MyCap\ActiveTask::getActiveTaskObj($question_format);
                    $extended_config_json = isset($taskObj) ? MyCap\ActiveTask::extendedConfigAsString($taskObj) : null;
                }

                // Render the active task configuration html
                $activeTaskFormat = $question_format;
                $configs = json_decode($extended_config_json, true);
                if (!empty($configs)) {
                    ?>
                    <tr>
                        <td colspan="3">
                            <table width="100%">
                                <tr>
                                    <td colspan="3">
                                        <div class="header" style="padding:7px 10px 5px;margin:-5px -8px 10px; color: #800000;"><i class="fas fa-cog"></i>
                                            <span id="activeTaskHeading">
                                            <?php echo ($isBatteryInstrument) ? 'Health Measure Battery' : MyCap\ActiveTask::toString($question_format)." ".RCView::tt('multilang_72').RCView::tt('colon'); ?>
                                        </span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" valign="top" style="padding-bottom:15px;">
                                        <?php
                                        foreach ($configs as $key => $value) {
                                            if (is_null($value)) {
                                                $$key = "";
                                            } else {
                                                $$key = $value;
                                            }
                                        }
                                        include APP_PATH_DOCROOT . "MyCap/activetask_extended_config.php";
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                <?php }
            }
            ?>
        <?php } else { ?>
            <tr class="mycap_setting_row">
                <td valign="top" style="width:20px;">
                    <img src="<?php echo APP_PATH_IMAGES ?>tag_orange.png">
                </td>
                <td valign="top" style="font-weight:bold; width:220px;">
                    <?php print RCView::tt('mycap_mobile_app_108') ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <?php echo htmlspecialchars(label_decode($task_title), ENT_QUOTES) ?>
                </td>
            </tr>
            <tr class="mycap_setting_row">
                <td valign="top" style="width:20px;">

                </td>
                <td valign="top" style="font-weight:bold; width:220px; padding-bottom: 20px;">
                    <?php print RCView::tt('mycap_mobile_app_100').RCView::tt('questionmark'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <?php echo $enabled_for_mycap == 1 ? RCView::tt('design_100') : RCView::tt('design_99').'<span style="padding-left: 30px;" class="note">'.RCView::tt('mycap_mobile_app_500').'</span>'; ?>
                </td>
            </tr>
        <?php }
        if ($batteryInstrumentIssueExists) { ?>
            <!-- Do not allow to schedule if its battery PROMIS instrument and its not 1st in list -->
        <?php } else { ?>
            <?php if ($Proj->longitudinal) {
                ?>
                <tr>
                    <td colspan="3" style="padding-bottom: 5px;"><b><?php print RCView::tt('mycap_mobile_app_724') ?></b></td>
                </tr>
                <tr class="mycap_setting_row">
                    <td colspan="3">
                        <table cellspacing="0" class="form_border" style="width:100%;table-layout:fixed;">
                            <tr>
                                <td class="header" style="width:170px;text-align:center;"><?php print RCView::tt('create_project_104') ?></td>
                                <!--<td class="header" style="font-size:11px;"><?php /*echo RCView::tt('global_242') */?></td>-->
                                <td class="header" style="font-size:11px;"><?php print RCView::tt('mycap_mobile_app_537') ?></td>
                            </tr>
                            <?php
                            if (count($events) > 0) {
                                foreach ($events as $eventId) {
                                    if (in_array($eventId, $scheduledEvents)) { // Enabled
                                        $enabledStyle = "";
                                        $disabledStyle = "display:none;";
                                        $opacityClass = "";
                                        $taskEnabledChecked = 'checked = "checked"';
                                        $eventEnabledClass = 'darkgreen';
                                    } else { // Disabled
                                        $enabledStyle = "display:none;";
                                        $disabledStyle = "";
                                        $opacityClass = "opacity35";
                                        $taskEnabledChecked = "";
                                        $eventEnabledClass = 'grey';
                                    }
                                    ?>
                                    <tr id="tstr-<?php echo $eventId;?>">
                                        <td class="data <?php echo $eventEnabledClass ?>" valign="top" style="text-align:center;padding:6px;padding-top:10px;">
                                            <div style="padding:3px 8px 8px 2px;font-size:13px;"><span style="font-size:13px;"><b><?php echo $Proj->eventInfo[$eventId]['name_ext'] ?></b></span></div>
                                            <div class="disableSchedule" id="div_ts_icon_enabled-<?php echo $eventId;?>" style="<?php echo $enabledStyle?>">
                                                <span><img src="<?php echo APP_PATH_IMAGES ?>checkbox_checked.png"></span>
                                                <?php
                                                    print RCView::div(array('style'=>'color:green;'), RCView::tt("index_30")) .
                                                            RCView::div(array('style'=>'padding:10px 0 0;'),
                                                            RCView::button(array('class'=>'jqbuttonsm', 'style'=>'font-size:10px;font-family:tahoma;',
                                                                'onclick'=>"taskSetupActivate(0, $eventId);return false;"), RCView::tt("control_center_153")));
                                                ?>
                                            </div>
                                            <div class="enableSchedule" id="div_ts_icon_disabled-<?php echo $eventId;?>" style="<?php echo $disabledStyle?>">
                                                <span><img src="<?php echo APP_PATH_IMAGES ?>checkbox_cross.png"></span>
                                                <?php
                                                    // "Not enabled" text/icon
                                                    print RCView::div(array('style'=>'color:#DB2A0F;'), RCView::tt("global_23")) .
                                                        RCView::div(array('style'=>'padding:10px 0 0;'),
                                                        RCView::button(array('class'=>'jqbuttonsm', 'style'=>'font-size:10px;font-family:tahoma;',
                                                            'onclick'=>"taskSetupActivate(1, $eventId);return false;"),
                                                            RCView::tt("survey_152")
                                                        )
                                                    );
                                                ?>
                                            </div>
                                            <input name="tsactive-<?php echo $eventId;?>" id="tsactive-<?php echo $eventId;?>" class="hidden event-enabled" type="checkbox" <?php echo $taskEnabledChecked;?>>
                                            <input name="tsevent[]" id="tsevent-<?php echo $eventId;?>" class="hidden" type="textbox" value="<?php echo $eventId;?>">
                                        </td>
                                        <!--<td class="data " style="padding:6px;" valign="top">
                                            <div style="padding:3px 8px 8px 2px;font-size:13px;"><span style="font-size:13px;"><b><?php /*echo $Proj->eventInfo[$eventId]['name_ext'] */?></b></span></div>
                                        </td>-->
                                        <td valign="top" class="data <?php echo $eventEnabledClass ?> <?php echo $opacityClass?>" style="padding:6px 6px 3px;font-size:12px;">
                                            <table id="schedule-setting-table" style="width:100%;border: 0px;">
                                                <tr>
                                                    <th data-event-name="event_<?php echo $eventId;?>" class="setup-form-header" style="padding:7px 10px 5px;font-weight:bold;color:#000;">
                                                        <?php if ($taskEnabledChecked != "") { ?>
                                                            <i class="fas fa-edit"></i> <?php print ($is_active_task) ? RCView::tt('mycap_mobile_app_832') : RCView::tt('mycap_mobile_app_833'); ?></th>
                                                        <?php } else { ?>
                                                            <i class="fas fa-plus"></i> <?php print ($is_active_task) ? RCView::tt('mycap_mobile_app_834') : RCView::tt('mycap_mobile_app_835'); ?></th>
                                                        <?php } ?>
                                                </tr>
                                                <tr>
                                                    <td>
                                                        <?php
                                                        if (!$Proj->isRepeatingForm($eventId, $form)) {
                                                            print 	RCView::div(array('class'=>'yellow','style'=>'max-width:910px;'),
                                                                RCView::img(array('src'=>'exclamation_orange.png')) .
                                                                RCView::b(RCView::tt('global_03').RCView::tt('colon')) . " ".RCView::tt('mycap_mobile_app_534')." \"".$task_title."\""
                                                            );
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>

                                                <?php
                                                $setUpSection = 'optional_settings';
                                                include APP_PATH_DOCROOT . "MyCap/task_setup.php";

                                                $setUpSection = 'schedules';
                                                include APP_PATH_DOCROOT . "MyCap/task_setup.php";
                                                ?>
                                            </table>
                                        </td>
                                    </tr>
                                <?php }
                            } ?>
                            </table>
                    </td></tr>
            <?php } else {
                $eventId = $Proj->firstEventId;
                ?>
                <tr class="mycap_setting_row">
                    <td colspan="3">
                        <table cellspacing="0" class="form_border" style="width:100%;table-layout:fixed;">
                            <input name="tsactive-<?php echo $Proj->firstEventId;?>" id="tsactive-<?php echo $Proj->firstEventId;?>" class="hidden event-enabled" type="checkbox" checked = "checked">
                            <input name="tsevent[]" id="tsevent-<?php echo $Proj->firstEventId;?>" class="hidden" type="textbox" value="<?php echo $Proj->firstEventId;?>">
                            <?php
                            $setUpSection = 'optional_settings';
                            include APP_PATH_DOCROOT . "MyCap/task_setup.php";

                            $setUpSection = 'schedules';
                            include APP_PATH_DOCROOT . "MyCap/task_setup.php";
                            ?>
                        </table>
                    </td>
                </tr>
                <?php
            }
            if (!$Proj->longitudinal) {
                $schedules = MyCap\Task::getTaskSchedules($task_id, 'all');
                $scheduledEvents = array_keys($schedules);
                foreach ($scheduledEvents as $eventId) {
                ?>
                    <input name="tsactive-<?php echo $eventId;?>" id="tsactive-<?php echo $eventId;?>" class="hidden event-enabled" type="checkbox" checked = "checked">
                    <input name="tsevent[]" id="tsevent-<?php echo $eventId;?>" class="hidden" type="textbox" value="<?php echo $eventId;?>">
                <?php
                }
            }
        }
        if (!empty($triggers)) {
        ?>
        <tr class="mycap_setting_row">
            <td colspan="3">
                <div class="header" style="padding:7px 10px 5px;margin:-5px -8px 10px; color: #800000;"><?php print RCView::tt('mycap_mobile_app_353') ?></div>
            </td>
        </tr>
        <?php
        foreach ($triggers as $trigger) {
            ?>
            <tr>
                <td valign="top" colspan="3" style="padding-left: 20px;">
                    Condition:
                    <?php if ($trigger->condition == MyCap\ActiveTasks\Promis::CONDITION_COMPLETED) { ?>
                        <code>when this task is completed</code>
                    <?php } ?>
                </td>
            </tr>
            <tr>
                <td valign="top" colspan="3" style="padding-left: 20px;">
                    Action:
                    <?php if ($trigger->action == MyCap\ActiveTasks\Promis::ACTION_AWAKEN) { ?>
                        <code>activate target task</code>
                    <?php } elseif ($trigger->action == MyCap\ActiveTasks\Promis::ACTION_AWAKEN_AND_NOTIFY) { ?>
                        <code>activate target task and display notification</code>
                    <?php } elseif ($trigger->action == MyCap\ActiveTasks\Promis::ACTION_AUTO_CONTINUE) { ?>
                        <code>auto-continue to target task</code>
                    <?php } ?>
                </td>
            </tr>
            <tr>
                <td colspan="3" valign="top" style="padding-left: 20px;">
                    Target: <code><?php echo $trigger->target; ?></code>
                </td>
            </tr>
            <?php
            }
        }
        ?>
		<!-- Save Button -->
		<tr>
			<td colspan="2" <?php echo ($Proj->longitudinal ? '' : 'style="border-top:1px solid #ddd;"') ?>></td>
			<td valign="middle" style="<?php echo ($Proj->longitudinal ? '' : 'border-top:1px solid #ddd;')?>padding:20px 0 20px 15px;">
				<button type="button" class="btn btn-primaryrc" <?php echo $disabledSaveBtn;?> id="taskSettingsSubmit" style="font-weight:bold;"><?php print RCView::tt('report_builder_28') ?></button>
			</td>
		</tr>

		<!-- Cancel/Delete buttons -->
		<tr>
            <td colspan="2" style="border-top:1px solid #ddd;"></td>
            <td valign="middle" style="border-top:1px solid #ddd;padding:10px 0 20px 15px;">
                <button class="btn btn-defaultrc" onclick="history.go(-1);return false;">-- <?php print RCView::tt_js2('global_53'); ?>--</button><br>
                <?php if (PAGE == 'MyCap/edit_task.php' && ($is_active_task == 0 || $isPromis)) { ?>
                    <!-- Option to delete the mycap settings -->
                    <div style="margin:30px 0 10px;">
                        <button class="btn btn-defaultrc btn-sm" style="color:#A00000;" onclick="deleteMyCapSettings(<?php echo $_GET['task_id'] ?>, '<?php echo $_GET['page'] ?>');return false;"><?php print RCView::tt_js2('mycap_mobile_app_354'); ?></button>
                    </div>
                <?php } ?>
            </td>
        </tr>

    </table>
</form>