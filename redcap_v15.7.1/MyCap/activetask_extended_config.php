<?php
use Vanderbilt\REDCap\Classes\MyCap\ActiveTask;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;
switch($activeTaskFormat) {
    case ActiveTask::RANGEOFMOTION:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription;?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <?php
                $kneeChecked = ($bodyPart == ActiveTasks\RangeOfMotion::BODY_PART_KNEE) ? 'checked="checked"' : '';
                $shoulderChecked = ($bodyPart == ActiveTasks\RangeOfMotion::BODY_PART_SHOULDER) ? 'checked="checked"' : '';
            ?>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('survey_1174'); ?>
                    <div class="newdbsub" style="font-weight: normal;">
                        <?php print RCView::tt('mycap_mobile_app_196');?>
                    </div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <div>
                        <input type="radio" name="extendedConfig_bodyPart" <?php echo $kneeChecked;?> id="knee" value="<?php echo ActiveTasks\RangeOfMotion::BODY_PART_KNEE; ?>">
                        <label for="knee"><?php print RCView::tt('mycap_mobile_app_198');?></label>
                    </div>
                    <div style="margin:4px 0;">
                        <input type="radio" name="extendedConfig_bodyPart" <?php echo $shoulderChecked;?> id="shoulder" value="<?php echo ActiveTasks\RangeOfMotion::BODY_PART_SHOULDER; ?>">
                        <label for="shoulder"><?php print RCView::tt('mycap_mobile_app_199');?></label>
                    </div>

                </td>
            </tr>
            <?php
                $leftChecked = ($limbOption == ActiveTasks\RangeOfMotion::LIMB_OPTION_LEFT) ? 'checked="checked"' : '';
                $rightChecked = ($limbOption == ActiveTasks\RangeOfMotion::LIMB_OPTION_RIGHT) ? 'checked="checked"' : '';
            ?>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_195'); ?>
                    <div class="newdbsub" style="font-weight: normal;">
                        <?php print RCView::tt('mycap_mobile_app_197');?>
                    </div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <div>
                        <input type="radio" name="extendedConfig_limbOption" <?php echo $leftChecked;?> id="left" value="<?php echo ActiveTasks\RangeOfMotion::LIMB_OPTION_LEFT; ?>">
                        <label for="left"><?php print RCView::tt('mycap_mobile_app_200');?></label>
                    </div>
                    <div style="margin:4px 0;">
                        <input type="radio" name="extendedConfig_limbOption" <?php echo $rightChecked;?> id="right" value="<?php echo ActiveTasks\RangeOfMotion::LIMB_OPTION_RIGHT; ?>">
                        <label for="right"><?php print RCView::tt('mycap_mobile_app_201');?></label>
                    </div>

                </td>
            </tr>
        </table>
        <?php
        break;

    case ActiveTask::SHORTWALK:
    ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription;?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_202');?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_numberOfStepsPerLeg" type="text" value="<?php echo $numberOfStepsPerLeg;?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_202')." ".RCView::tt_js2('mycap_mobile_app_208')." ".ActiveTasks\ShortWalk::ORKSHORTWALKTASKMINIMUMNUMBEROFSTEPSPERLEG." ".RCView::tt_js2('control_center_4469') ?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_202');?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_203');?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_205');?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_restDuration" type="text" value="<?php echo $restDuration;?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_206');?>
                    </div>
                </td>
            </tr>
        </table>
    <?php
    break;
    case ActiveTask::TWOFINGERTAPPINGINTERVAL:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_207');?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_duration" type="text" value="<?php echo $duration; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_207')." ".RCView::tt_js2('mycap_mobile_app_208')." ".ActiveTasks\TwoFingerTappingInterval::ORKTWOFINGERTAPPINGMINIMUMDURATION." ".RCView::tt_js2('control_center_4469'); ?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_207');?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_209'); ?>
                    </div>
                </td>
            </tr>
            <?php
                $bothChecked = ($handOptions == ActiveTasks\TwoFingerTappingInterval::HANDOPTIONS_BOTH) ? "checked" : "";
                $leftChecked = ($handOptions == ActiveTasks\TwoFingerTappingInterval::HANDOPTIONS_LEFT) ? "checked = 'checked'" : "";
                $rightChecked = ($handOptions == ActiveTasks\TwoFingerTappingInterval::HANDOPTIONS_RIGHT) ? "checked = 'checked'" : "";
            ?>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_210'); ?>
                    <div class="newdbsub" style="font-weight: normal;">
                        <?php print RCView::tt('mycap_mobile_app_211'); ?>
                    </div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <div>
                        <input type="radio" name="extendedConfig_handOptions" <?php echo $bothChecked;?> id="both" value="<?php echo ActiveTasks\TwoFingerTappingInterval::HANDOPTIONS_BOTH; ?>">
                        <label for="both"><?php print RCView::tt('global_74');?></label>
                    </div>
                    <div>
                        <input type="radio" name="extendedConfig_handOptions" <?php echo $leftChecked;?> id="left" value="<?php echo ActiveTasks\TwoFingerTappingInterval::HANDOPTIONS_LEFT; ?>">
                        <label for="left"><?php print RCView::tt('mycap_mobile_app_200');?></label>
                    </div>
                    <div style="margin:4px 0;">
                        <input type="radio" name="extendedConfig_handOptions" <?php echo $rightChecked;?> id="right" value="<?php echo ActiveTasks\TwoFingerTappingInterval::HANDOPTIONS_RIGHT; ?>">
                        <label for="right"><?php print RCView::tt('mycap_mobile_app_201');?></label>
                    </div>

                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::FITNESSCHECK:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_212'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_walkDuration" type="text" value="<?php echo $walkDuration; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_213'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_205');?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_restDuration" type="text" value="<?php echo $restDuration; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_205')." ".RCView::tt_js2('mycap_mobile_app_208')." ".ActiveTasks\FitnessCheck::ORKFITNESSSTEPMINIMUMDURATION." ".RCView::tt_js2('control_center_4469') ?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_205');?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_215');?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::TIMEDWALK:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_216');?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_distanceInMeters" type="text" value="<?php echo $distanceInMeters; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_217')." ".ActiveTasks\TimedWalk::ORKTIMEDWALKMINIMUMDISTANCEINMETERS." ".RCView::tt_js2('mycap_mobile_app_218')." ".ActiveTasks\TimedWalk::ORKTIMEDWALKMAXIMUMDISTANCEINMETERS." ".RCView::tt_js2('mycap_mobile_app_219');?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_216');?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_220');?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_822')." ".RCView::tt('mycap_mobile_app_535');?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_timeLimit" type="text" value="<?php echo $timeLimit; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_822')." ".RCView::tt_js2('mycap_mobile_app_208')." ".ActiveTasks\TimedWalk::ORKTIMEDWALKMINIMUMDURATION." ".RCView::tt_js2('control_center_4469');?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_822');?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_222');?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::SPATIALSPANMEMORY:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_223'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_initialSpan" type="text" value="<?php echo $initialSpan; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_223')." ".RCView::tt_js2('mycap_mobile_app_208')." ".ActiveTasks\SpatialSpanMemory::ORKSPATIALSPANMEMORYTASKMINIMUMINITIALSPAN?><br><?php print RCView::tt_js2('mycap_mobile_app_225')?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_223'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_226'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_227'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_minimumSpan" type="text" value="<?php echo $minimumSpan; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_228'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_229'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_maximumSpan" type="text" value="<?php echo $maximumSpan; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_214')." ".ActiveTasks\SpatialSpanMemory::ORKSPATIALSPANMEMORYTASKMAXIMUMSPAN?>.<br><?php print RCView::tt_js2('mycap_mobile_app_204');?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_229'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_221');?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_224');?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_playSpeed" type="text" value="<?php echo $playSpeed; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_224')." ".RCView::tt_js2('mycap_mobile_app_208')." ".ActiveTasks\SpatialSpanMemory::ORKSPATIALSPANMEMORYTASKMINIMUMPLAYSPEED." ".RCView::tt_js2('control_center_4469')?><br><?php print RCView::tt_js2('mycap_mobile_app_230')." ".ActiveTasks\SpatialSpanMemory::ORKSPATIALSPANMEMORYTASKMAXIMUMPLAYSPEED." ".RCView::tt_js2('control_center_4469')?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_224');?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_231');?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_232');?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_maxTests" type="text" value="<?php echo $maxTests; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_232')." ".RCView::tt_js2('mycap_mobile_app_208')." ".ActiveTasks\SpatialSpanMemory::ORKSPATIALSPANMEMORYTASKMINIMUMMAXTESTS?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_232');?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_233');?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_234');?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_maxConsecutiveFailures" type="text" value="<?php echo $maxConsecutiveFailures; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_234')." ".RCView::tt_js2('mycap_mobile_app_208')." ".ActiveTasks\SpatialSpanMemory::ORKSPATIALSPANMEMORYTASKMINIMUMMAXCONSECUTIVEFAILURES?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_234');?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_235');?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::STROOP:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_238'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_numberOfAttempts" type="text" value="<?php echo $numberOfAttempts; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_239')." ".ActiveTasks\Stroop::ORKSTROOPMINIMUMATTEMPTS?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_238'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_240'); ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::TRAILMAKING:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_241'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_trailMakingInstruction" type="text" value="<?php echo $trailMakingInstruction; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_242'); ?>
                    </div>
                </td>
            </tr>
            <?php
                $aChecked = ($trailType == ActiveTasks\TrailMaking::TYPE_A) ? 'checked="checked"' : '';
                $bChecked = ($trailType == ActiveTasks\TrailMaking::TYPE_B) ? 'checked="checked"' : '';
            ?>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_243'); ?>
                    <div class="newdbsub" style="font-weight: normal;">
                        <?php print RCView::tt('mycap_mobile_app_244'); ?>
                    </div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <div>
                        <input type="radio" name="extendedConfig_trailType" <?php echo $aChecked;?> id="a" value="<?php echo ActiveTasks\TrailMaking::TYPE_A;?>">
                        <label for="a"><?php print RCView::tt('mycap_mobile_app_245'); ?></label>
                    </div>
                    <div>
                        <input type="radio" name="extendedConfig_trailType" <?php echo $bChecked;?> id="b" value="<?php echo ActiveTasks\TrailMaking::TYPE_B;?>">
                        <label for="b"><?php print RCView::tt('mycap_mobile_app_246'); ?></label>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::PSAT:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold;  width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <?php
                $avChecked = ($presentationMode == ActiveTasks\PSAT::MODE_AUDITORYANDVISUAL) ? 'checked="checked"' : '';
                $aChecked = ($presentationMode == ActiveTasks\PSAT::MODE_AUDITORY) ? 'checked="checked"' : '';
                $vChecked = ($presentationMode == ActiveTasks\PSAT::MODE_VISUAL) ? 'checked="checked"' : '';
            ?>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_247'); ?>
                    <div class="newdbsub" style="font-weight: normal;">
                        <?php print RCView::tt('mycap_mobile_app_248'); ?>
                    </div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <div>
                        <input type="radio" name="extendedConfig_presentationMode" <?php echo $avChecked;?> id="av" value="<?php echo ActiveTasks\PSAT::MODE_AUDITORYANDVISUAL; ?>">
                        <label for="av"><?php print RCView::tt('mycap_mobile_app_249'); ?></label>
                    </div>
                    <div>
                        <input type="radio" name="extendedConfig_presentationMode" <?php echo $aChecked;?> id="a" value="<?php echo ActiveTasks\PSAT::MODE_AUDITORY; ?>">
                        <label for="a"><?php print RCView::tt('mycap_mobile_app_250'); ?></label>
                    </div>
                    <div>
                        <input type="radio" name="extendedConfig_presentationMode" <?php echo $vChecked;?> id="v" value="<?php echo ActiveTasks\PSAT::MODE_VISUAL; ?>">
                        <label for="v"><?php print RCView::tt('mycap_mobile_app_251'); ?></label>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_252'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_interStimulusInterval" type="text" value="<?php echo $interStimulusInterval; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="
                                <?php print RCView::tt_js2('mycap_mobile_app_252')." ".RCView::tt_js2('mycap_mobile_app_253')." ".ActiveTasks\PSAT::ORKPSATINTERSTIMULUSMINIMUMINTERVAL." ".RCView::tt_js2('control_center_4469')." ".RCView::tt_js2('config_functions_90')." ".ActiveTasks\PSAT::ORKPSATINTERSTIMULUSMAXIMUMINTERVAL." ".RCView::tt_js2('control_center_4469'); ?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_252'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_254'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_255'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_stimulusDuration" type="text" value="<?php echo $stimulusDuration; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_255')." ".RCView::tt_js2('mycap_mobile_app_253')." ".ActiveTasks\PSAT::ORKPSATSTIMULUSMINIMUMDURATION." ".RCView::tt_js2('control_center_4469').RCView::tt_js2('config_functions_90')." ".RCView::tt_js2('mycap_mobile_app_252')?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_255'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_256'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_257'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_seriesLength" type="text" value="<?php echo $seriesLength; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_257')." ".RCView::tt_js2('mycap_mobile_app_253')." ".ActiveTasks\PSAT::ORKPSATSERIEMINIMUMLENGTH?>
                              <?php print RCView::tt_js2('mycap_mobile_app_258')." ".RCView::tt_js2('config_functions_90')." ".ActiveTasks\PSAT::ORKPSATSERIEMAXIMUMLENGTH." ".RCView::tt_js2('mycap_mobile_app_258') ?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_257'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_259');?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::TOWEROFHANOI:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_260'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_numberOfDisks" type="text" value="<?php echo $numberOfDisks; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_261')." ".ActiveTasks\TowerOfHanoi::MAXIMUMNUMBEROFDISKS ?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_260'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_262'); ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::REACTIONTIME:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_263'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_maximumStimulusInterval" type="text" value="<?php echo $maximumStimulusInterval; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_264'); ?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_263'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_265'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_266'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_minimumStimulusInterval" type="text" value="<?php echo $minimumStimulusInterval; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_266')." ".RCView::tt_js2('mycap_mobile_app_267')." ".ActiveTasks\ReactionTime::MINIMUMSTIMULUSINTERVAL?>." data-title="<?php print RCView::tt_js2('mycap_mobile_app_266'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_268'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_269'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_thresholdAcceleration" type="text" value="<?php echo $thresholdAcceleration; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_269')." ".RCView::tt_js2('mycap_mobile_app_267')." ". ActiveTasks\ReactionTime::MINIMUMTHRESHOLDACCELERATION?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_269'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_270'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_238'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_numberOfAttempts" type="text" value="<?php echo $numberOfAttempts; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_238')." ".RCView::tt_js2('mycap_mobile_app_267')." ". ActiveTasks\ReactionTime::MINIMUMNUMBEROFATTEMPTS?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_238'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_271'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_272'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_timeout" type="text" value="<?php echo $timeout; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_272')." ".RCView::tt_js2('mycap_mobile_app_267')." ".ActiveTasks\ReactionTime::MINIMUMTIMEOUT?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_272'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_273'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_274'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_successSound" type="text" value="<?php echo $successSound; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_275'); ?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_274'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_276'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_277'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_timeoutSound" type="text" value="<?php echo $timeoutSound; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_278'); ?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_277'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_279'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_280'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_failureSound" type="text" value="<?php echo $failureSound; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_281'); ?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_280'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_282'); ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::AUDIO:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_283'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_speechInstruction" type="text" value="<?php echo $speechInstruction; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_284'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_285'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_shortSpeechInstruction" type="text" value="<?php echo $shortSpeechInstruction; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_286'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_207');?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_duration" type="text" value="<?php echo $duration; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_207')." ".RCView::tt_js2('mycap_mobile_app_208')." ".ActiveTasks\Audio::ORKAUDIOTASKMINIMUMDURATION." ".RCView::tt_js2('control_center_4469')?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_207');?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_287');?>
                    </div>
                </td>
            </tr>
            <?php
                $yesChecked = ($checkAudioLevel == 1) ? 'checked="checked"' : '';
                $noChecked = ($checkAudioLevel == 0) ? 'checked="checked"' : '';
            ?>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_288');?>
                    <div class="newdbsub" style="font-weight: normal;">
                        <?php print RCView::tt('mycap_mobile_app_289');?>
                    </div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <div>
                        <input type="radio" name="extendedConfig_checkAudioLevel" <?php echo $yesChecked;?> id="yes" value="1">
                        <label for="yes"><?php print RCView::tt('design_100');?></label>
                    </div>
                    <div>
                        <input type="radio" name="extendedConfig_checkAudioLevel" <?php echo $noChecked;?> id="no" value="0">
                        <label for="no"><?php print RCView::tt('design_99');?></label>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::AUDIORECORDING:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; padding-bottom: 10px; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_319'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_infoTitle" type="text" value="<?php echo $infoTitle; ?>" class="x-form-text x-form-field" style="width:80%;">
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_320'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <textarea name="extendedConfig_infoInstructions" class="x-form-field notesbox" style="width:90%;margin-bottom:3px;vertical-align: top;"><?php echo $infoInstructions; ?></textarea>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_321'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_captureTitle" type="text" value="<?php echo $captureTitle; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_322'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_323'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <textarea name="extendedConfig_captureInstructions" class="x-form-field notesbox" style="width:90%;margin-bottom:3px;vertical-align: top;"><?php echo $captureInstructions; ?></textarea>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_324'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_325'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_waitTime" type="text" value="<?php echo $waitTime; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_325')." ".RCView::tt_js2('mycap_mobile_app_267')." 0 ".RCView::tt_js2('control_center_4469')?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_325'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_326'); ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::SELFIECAPTURE:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; padding-bottom: 10px; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_319'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_infoTitle" type="text" value="<?php echo $infoTitle; ?>" class="x-form-text x-form-field" style="width:80%;">
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_320'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <textarea name="extendedConfig_infoInstructions" class="x-form-field notesbox" style="width:90%;margin-bottom:3px;vertical-align: top;"><?php echo $infoInstructions; ?></textarea>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_321'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_captureTitle" type="text" value="<?php echo $captureTitle; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_347'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_323'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <textarea name="extendedConfig_captureInstructions" class="x-form-field notesbox" style="width:90%;margin-bottom:3px;vertical-align: top;"><?php echo $captureInstructions; ?></textarea>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_324'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_349'); ?>
                    <div class="requiredlabel p-0">* <?php print RCView::tt('data_entry_39')?></div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_waitTime" type="text" value="<?php echo $waitTime; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_349')." ".RCView::tt_js2('mycap_mobile_app_267')." 0 ".RCView::tt_js2('control_center_4469')?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_349'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_348'); ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::SPEECHRECOGNITION:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_290'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <select name="extendedConfig_speechRecognizerLocale" class="x-form-text x-form-field fs14" style="max-width:400px;">
                        <?php
                            $localList = \Vanderbilt\REDCap\Classes\MyCap\Locale::getLocaleList();
                            foreach ($localList as $key => $value) { ?>
                                <option <?php echo ($key == $speechRecognizerLocale) ? "selected" : "";?> value="<?php echo $key;?>"><?php echo $value;?></option>
                            <?php } ?>
                    </select>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_291'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_292'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_speechRecognitionText" type="text" value="<?php echo $speechRecognitionText; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_293'); ?>
                    </div>
                </td>
            </tr>
            <?php
                $yesChecked = ($shouldHideTranscript == 1) ? 'checked="checked"' : '';
                $noChecked = ($shouldHideTranscript == 0) ? 'checked="checked"' : '';
            ?>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_294'); ?>
                    <div class="newdbsub" style="font-weight: normal;">
                        <?php print RCView::tt('mycap_mobile_app_295'); ?>
                    </div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <div>
                        <input type="radio" name="extendedConfig_shouldHideTranscript" <?php echo $yesChecked;?> id="yes" value="1">
                        <label for="yes"><?php print RCView::tt('design_100')?></label>
                    </div>
                    <div>
                        <input type="radio" name="extendedConfig_shouldHideTranscript" <?php echo $noChecked;?> id="no" value="0">
                        <label for="no"><?php print RCView::tt('design_99')?></label>
                    </div>
                </td>
            </tr>
            <?php
                $yesChecked = ($allowsEdittingTranscript == 1) ? 'checked="checked"' : '';
                $noChecked = ($allowsEdittingTranscript == 0) ? 'checked="checked"' : '';
            ?>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_296'); ?>
                    <div class="newdbsub" style="font-weight: normal;">
                        <?php print RCView::tt('mycap_mobile_app_297'); ?>
                    </div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <div>
                        <input type="radio" name="extendedConfig_allowsEdittingTranscript" <?php echo $yesChecked;?> id="yes" value="1">
                        <label for="yes"><?php print RCView::tt('design_100')?></label>
                    </div>
                    <div>
                        <input type="radio" name="extendedConfig_allowsEdittingTranscript" <?php echo $noChecked;?> id="no" value="0">
                        <label for="no"><?php print RCView::tt('design_99')?></label>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::TONEAUDIOMETRY:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_298'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_speechInstruction" type="text" value="<?php echo $speechInstruction; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_299'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_300'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_shortSpeechInstruction" type="text" value="<?php echo $shortSpeechInstruction; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_301'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_302'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_toneDuration" type="text" value="<?php echo $toneDuration; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_302')." ".RCView::tt_js2('mycap_mobile_app_208')." ".ActiveTasks\ToneAudiometry::ORKTONEAUDIOMETRYTASKTONEMINIMUMDURATION." ".RCView::tt_js2('control_center_4469')?> " data-title="<?php print RCView::tt_js2('mycap_mobile_app_302'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_303'); ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::HOLEPEG:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
            <?php
                $leftChecked = ($dominantHand == ActiveTasks\HolePeg::HAND_LEFT) ? 'checked="checked"' : '';
                $rightChecked = ($dominantHand == ActiveTasks\HolePeg::HAND_RIGHT) ? 'checked="checked"' : '';
            ?>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_304'); ?>
                    <div class="newdbsub" style="font-weight: normal;">
                        <?php print RCView::tt('mycap_mobile_app_305'); ?>
                    </div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <div>
                        <input type="radio" name="extendedConfig_dominantHand" <?php echo $leftChecked;?> id="left" value="<?php echo ActiveTasks\HolePeg::HAND_LEFT; ?>">
                        <label for="left"><?php print RCView::tt('mycap_mobile_app_200');?></label>
                    </div>
                    <div style="margin:4px 0;">
                        <input type="radio" name="extendedConfig_dominantHand" <?php echo $rightChecked;?> id="right" value="<?php echo ActiveTasks\HolePeg::HAND_RIGHT; ?>">
                        <label for="right"><?php print RCView::tt('mycap_mobile_app_201');?></label>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_306'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_numberOfPegs" type="text" value="<?php echo $numberOfPegs; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_306')." ".RCView::tt_js2('mycap_mobile_app_267')." ".ActiveTasks\HolePeg::ORKHOLEPEGTESTMINIMUMNUMBEROFPEGS?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_306'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_307'); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_308'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_threshold" type="text" value="<?php echo $threshold; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_308')." ".RCView::tt_js2('mycap_mobile_app_267')." ".ActiveTasks\HolePeg::ORKHOLEPEGTESTMINIMUMTHRESHOLD?>.<br><?php print RCView::tt_js2('mycap_mobile_app_309')." ".ActiveTasks\HolePeg::ORKHOLEPEGTESTMAXIMUMTHRESHOLD?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_308'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_310'); ?>
                    </div>
                </td>
            </tr>
            <?php
            $yesChecked = ($rotated == 1) ? 'checked="checked"' : '';
            $noChecked = ($rotated == 0) ? 'checked="checked"' : '';
            ?>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_311'); ?>
                    <div class="newdbsub" style="font-weight: normal;">
                        <?php print RCView::tt('mycap_mobile_app_312'); ?>
                    </div>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <div>
                        <input type="radio" name="extendedConfig_rotated" <?php echo $yesChecked;?> id="yes" value="1">
                        <label for="yes"><?php print RCView::tt('design_100')?></label>
                    </div>
                    <div>
                        <input type="radio" name="extendedConfig_rotated" <?php echo $noChecked;?> id="no" value="0">
                        <label for="no"><?php print RCView::tt('design_99')?></label>
                    </div>
                </td>
            </tr>
            <tr>
                <td valign="top" style="font-weight:bold;">
                    <?php print RCView::tt('mycap_mobile_app_822'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_timeLimit" type="text" value="<?php echo $timeLimit; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?php print RCView::tt_js2('mycap_mobile_app_822')." ".RCView::tt_js2('mycap_mobile_app_267')." ".ActiveTasks\HolePeg::ORKHOLEPEGTESTMINIMUMDURATION?>" data-title="<?php print RCView::tt_js2('mycap_mobile_app_822'); ?>"></i>
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_313'); ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::AMSLERGRID:
        ?>
        <table cellspacing="3" style="width:100%; font-size: 13px;">
            <tr>
                <td valign="top" style="font-weight:bold; width:240px;">
                    <?php print RCView::tt('mycap_mobile_app_193'); ?>
                </td>
                <td valign="top" style="padding-left:15px;padding-bottom:5px;">
                    <input name="extendedConfig_intendedUseDescription" type="text" value="<?php echo $intendedUseDescription; ?>" class="x-form-text x-form-field" style="width:80%;">
                    <div class="newdbsub">
                        <?php print RCView::tt('mycap_mobile_app_194'); ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        break;
        case ActiveTask::PROMIS:
            if ($isBatteryInstrument) {
                ?>
                <table cellspacing="3" style="width:100%; font-size: 13px;">
                    <tr>
                        <td valign="top" style="width:240px; padding-bottom: 10px;">
                            This instrument is part of a Health Measure battery. You may configure the first instrument as a
                            task. Each subsequent instrument will automatically start after the previous instrument is completed.
                        </td>
                    </tr>
                    <?php

                    $currentPos = $Proj->forms[$form]['form_number'];
                    $currentBatteryPos = $batteryInstrumentsList[$form]['batteryPosition'];
                    foreach ($batteryInstrumentsList as $form_name => $item) {
                        $batteryPosition = $item['batteryPosition'];
                        if ($item['instrumentPosition'] >= $currentPos) {
                            if ($item['batteryPosition'] < $currentBatteryPos) {
                                continue;
                            }
                            $currentBatteryPos++;
                            $form_description = RCView::escape($item['title']);
                            ?>
                            <tr>
                                <td valign="top" style="padding-left: 20px;">
                                    <?php echo ($form_name == $form) ? '<b>'.$form_description.'</b>' : $form_description; ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </table>
            <?php
            }
        break;
}
?>