<?php

include 'header.php';
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);
if (!ACCESS_SYSTEM_CONFIG) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); });</script>";

function getEhrSettings() {

    $queryString = "SELECT * FROM redcap_ehr_settings";
    $result = db_query($queryString);
    $settings = [];
    while($row = db_fetch_assoc($result)) {
        $id = $row['ehr_id'] ?? null;
        $settings[$id] = $row;
    }
    return $settings;
}

$settingsList = getEhrSettings();

?>

<ul class="nav nav-tabs" id="myTab" role="tablist">
    <?php foreach($settingsList as $ehrID => $settings) :
        $isActive = ($ehrID === array_key_first($settingsList)); ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $isActive ? 'active' : '' ?>"
        id="ehr-<?= $ehrID ?>-tab"
        data-bs-toggle="tab"
        data-bs-target="#ehr-<?= $ehrID ?>-pane"
        type="button" role="tab" aria-controls="ehr-<?= $ehrID ?>-tab" aria-selected="<?= $isActive ? 'true' : 'false' ?>"><?= $settings['ehr_name'] ?? $ehrID ?></button>
    </li>
    <?php endforeach ; ?>
</ul>
<form action="" method="POST">
<div class="tab-content" id="myTabContent">
    <?php foreach($settingsList as $ehrID => $settings) :
        $isActive = ($ehrID === array_key_first($settingsList)); ?>
        <div class="tab-pane fade <?= $isActive ? 'show active' : '' ?>" id="ehr-<?= $ehrID ?>-pane" role="tabpanel" aria-labelledby="ehr-<?= $ehrID ?>-pane"><?= $name ?>
            <div class="ehr-settings">
                <div class="row border-top">
                    <div class="col">
                        <span class="settings-title">
                            <?= $lang['ws_234'] ?>
                        </span>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <span class="settings-subtitle">
                            <?php echo $lang['ws_214'] ?>
                        </span>
                        <div class="small text-muted" style="margin-bottom:20px;">
                            <?php echo $lang['ws_235'] ?>
                        </div>
                    </div>
                    <div class="col">
                        <input class="form-control form-control-sm" type="text" data-name="ehr_name"
                            name="ehr_name[<?= $ehrID ?>]" 
                            value="<?= $settings['ehr_name'] ?? '' ?>" />
                        <div class="small text-muted">
                            <?=js_escape($lang['control_center_4881'])?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <span class="settings-subtitle">
                            <?= $lang['ws_219'] ?>
                        </span>
                        <div class="small text-muted">
                            <?= $lang['ws_220'] ?>
                        </div>
                    </div>
                    <div class="col">

                            <div class="row">
                                <div class="col">
                                    <span class="settings-subtitle" style="color:#800000;">
                                        <?= $lang['ws_221'] ?>
                                    </span>
                                    <input class="form-control form-control-sm" autocomplete="new-password" type="text" data-name="client_id"
                                        name="client_id[<?= $ehrID ?>]" 
                                        value="<?= $settings['client_id'] ?? '' ?>" />
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <span class="settings-subtitle" style="color:#800000;"><?= $lang['ws_222'] ?></span>
                                    <div class="input-group">
                                        <input class="form-control form-control-sm" autocomplete="new-password" type="password" data-name="client_secret"
                                            name="client_secret[<?= $ehrID ?>]" 
                                            value="<?= $settings['client_secret'] ?? '' ?>" />
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-password-reveal><?= $lang['ws_223'] ?></button>
                                    </div>
                                </div>
                                <div class="small text-muted">
                                    <?= $lang['ws_232'] ?>
                                </div>
                            </div>

                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <span class="settings-subtitle">
                            <?= $lang['ws_224'] ?>
                        </span>
                        <div class="small text-muted mb-2">
                            <?= $lang['ws_225']."<br>".$lang['ws_260'] ?>
                        </div>
                        
                        <div class="ms-5">
                            <div class="row">
                                <div class="col-3">
                                    <span class="settings-subtitle" style="color:#800000;">
                                        <?= $lang['ws_228'] ?>
                                    </span>
                                </div>
                                <div class="col-9">
                                    <input class="form-control form-control-sm" type="text" id="fhir_endpoint_base_url" data-name="fhir_base_url" onblur="validateUrl(this);"
                                        name="fhir_base_url[<?= $ehrID ?>]" 
                                        value="<?= $settings['fhir_base_url'] ?? '' ?>" />
                                </div>
                            </div>

                            <div class="row">
                                <div class="col">
                                    <div class="small text-muted my-2">
                                        <?= $lang['ws_229'] ?> &nbsp;
                                        <button class="jqbuttonmed text-nowrap" style="color:#0101bb;font-size: 11px;" data-find-fhir-urls><?= $lang['ws_231'] ?></button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-3">
                                    <span class="settings-subtitle" style="color:#800000;">
                                        <?= $lang['ws_226'] ?>
                                    </span>
                                </div>
                                <div class="col-9">
                                    <input class="form-control form-control-sm" type="text" id="fhir_endpoint_token_url" data-name="fhir_token_url" onblur="validateUrl(this);"
                                        name="fhir_token_url[<?= $ehrID ?>]" 
                                        value="<?= $settings['fhir_token_url'] ?? '' ?>" />
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-3">
                                    <span class="settings-subtitle" style="color:#800000;">
                                        <?= $lang['ws_227'] ?>
                                    </span>
                                </div>
                                <div class="col-9">
                                    <input class="form-control form-control-sm" type="text" id="fhir_endpoint_authorize_url" data-name="fhir_authorize_url" onblur="validateUrl(this);"
                                        name="fhir_authorize_url[<?= $ehrID ?>]" 
                                        value="<?= $settings['fhir_authorize_url'] ?? '' ?>" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <span class="settings-subtitle">
                            <?= "Identity provider (optional)" ?>
                        </span>
                        <div class="small text-muted">
                            <?= $lang['fhir_identity_provider_title'] ?>
                        </div>
                    </div>
                    <div class="col">
                        <input class="form-control form-control-sm" type="text" id="fhir_identity_provider" data-name="fhir_identity_provider" onblur="validateUrl(this);"
                            name="fhir_identity_provider[<?= $ehrID ?>]" 
                            value="<?= $settings['fhir_identity_provider'] ?? '' ?>" />
                        <div class="small text-muted">
                            <span><?= $lang['fhir_identity_provider_description'] ?></span>
                            <span class="d-block"><?= $lang['fhir_identity_provider_description2'] ?></span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <span class="settings-subtitle">
                            <?= $lang['ws_217'] ?>
                        </span>
                        <div class="small text-muted">
                            <?= $lang['ws_218'] ?>
                        </div>
                    </div>
                    <div class="col">
                        <input class="form-control form-control-sm" type="text" data-name="patient_identifier_string"
                            name="patient_identifier_string[<?= $ehrID ?>]" 
                            value="<?= $settings['patient_identifier_string'] ?? '' ?>"/>
                        <div class="small text-muted">
                            <?=js_escape($lang['control_center_4882'])?>
                        </div>
                        <div class="small text-muted" style="color:#0101bb;margin-top:15px;">
                        <?= $lang['control_center_4883'] ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ; ?>
</div>
<button type="submit">Save</button>
</form>

<div id="ehr-specific-settings-wrapper"></div>


<template id="ehr-specific-settings-template">
    <div class="row border-top">
        <div class="col">
            <span class="settings-title">
                <?= $lang['ws_234'] ?>
            </span>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <span class="settings-subtitle">
                <?php echo $lang['ws_214'] ?>
            </span>
            <div class="small text-muted" style="margin-bottom:20px;">
                <?php echo $lang['ws_235'] ?>
            </div>
        </div>
        <div class="col">
            <input class="form-control form-control-sm" type="text" data-name="ehr_name" value="" />
            <div class="small text-muted">
                <?=js_escape($lang['control_center_4881'])?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <span class="settings-subtitle">
                <?= $lang['ws_219'] ?>
            </span>
            <div class="small text-muted">
                <?= $lang['ws_220'] ?>
            </div>
        </div>
        <div class="col">

                <div class="row">
                    <div class="col">
                        <span class="settings-subtitle" style="color:#800000;">
                            <?= $lang['ws_221'] ?>
                        </span>
                        <input class="form-control form-control-sm" autocomplete="new-password" type="text" data-name="client_id" value="" />
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <span class="settings-subtitle" style="color:#800000;"><?= $lang['ws_222'] ?></span>
                        <div class="input-group">
                            <input class="form-control form-control-sm" autocomplete="new-password" type="password" data-name="client_secret" value="" />
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-password-reveal><?= $lang['ws_223'] ?></button>
                        </div>
                    </div>
                    <div class="small text-muted">
                        <?= $lang['ws_232'] ?>
                    </div>
                </div>

        </div>
    </div>

    <div class="row">
        <div class="col">
            <span class="settings-subtitle">
                <?= $lang['ws_224'] ?>
            </span>
            <div class="small text-muted mb-2">
                <?= $lang['ws_225']."<br>".$lang['ws_260'] ?>
            </div>
            
            <div class="ms-5">
                <div class="row">
                    <div class="col-3">
                        <span class="settings-subtitle" style="color:#800000;">
                            <?= $lang['ws_228'] ?>
                        </span>
                    </div>
                    <div class="col-9">
                        <input class="form-control form-control-sm" type="text" id="fhir_endpoint_base_url" data-name="fhir_base_url" value="" onblur="validateUrl(this);">
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <div class="small text-muted my-2">
                            <?= $lang['ws_229'] ?> &nbsp;
                            <button class="jqbuttonmed text-nowrap" style="color:#0101bb;font-size: 11px;" data-find-fhir-urls><?= $lang['ws_231'] ?></button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-3">
                        <span class="settings-subtitle" style="color:#800000;">
                            <?= $lang['ws_226'] ?>
                        </span>
                    </div>
                    <div class="col-9">
                        <input class="form-control form-control-sm" type="text" id="fhir_endpoint_token_url" data-name="fhir_token_url" value="" onblur="validateUrl(this);">
                    </div>
                </div>

                <div class="row">
                    <div class="col-3">
                        <span class="settings-subtitle" style="color:#800000;">
                            <?= $lang['ws_227'] ?>
                        </span>
                    </div>
                    <div class="col-9">
                        <input class="form-control form-control-sm" type="text" id="fhir_endpoint_authorize_url" data-name="fhir_authorize_url" value="" onblur="validateUrl(this);">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <span class="settings-subtitle">
                <?= "Identity provider (optional)" ?>
            </span>
            <div class="small text-muted">
                <?= $lang['fhir_identity_provider_title'] ?>
            </div>
        </div>
        <div class="col">
            <input class="form-control form-control-sm" type="text" id="fhir_identity_provider" data-name="fhir_identity_provider" value="" onblur="validateUrl(this);">
            <div class="small text-muted">
                <span><?= $lang['fhir_identity_provider_description'] ?></span>
                <span class="d-block"><?= $lang['fhir_identity_provider_description2'] ?></span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <span class="settings-subtitle">
                <?= $lang['ws_217'] ?>
            </span>
            <div class="small text-muted">
                <?= $lang['ws_218'] ?>
            </div>
        </div>
        <div class="col">
            <input class="form-control form-control-sm" type="text" data-name="patient_identifier_string" value=""/>
            <div class="small text-muted">
                <?=js_escape($lang['control_center_4882'])?>
            </div>
            <div class="small text-muted" style="color:#0101bb;margin-top:15px;">
            <?= $lang['control_center_4883'] ?>
            </div>
        </div>
    </div>
</template>