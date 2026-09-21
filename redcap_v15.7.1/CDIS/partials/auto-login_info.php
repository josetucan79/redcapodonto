<?php
    define("NOAUTH", true);
    if(!defined('REDCAP_VERSION')) {
        require_once dirname(__DIR__, 2) . "/Config/init_global.php";
    }
?>
<div data-auto-login-legend>
    <div class="d-flex flex-column gap-2">
        <div>
            <i class="fas fa-circle-check fa-fw text-success"></i>
            <span><?= Language::tt('cdis_info_auto_login_active') ?></span>
        </div>
        <div>
            <i class="fas fa-times-circle fa-fw text-danger"></i>
            <span><?= Language::tt('cdis_info_auto_login_inactive') ?></span>
        </div>
    </div>

    <details class="mt-2">
        <summary><span class="fw-bold"><?= Language::tt('cdis_info_auto_login_what_is_summary') ?></span></summary>
        <p><?= Language::tt('cdis_info_auto_login_what_is_description') ?></p>
    </details>
    <details class="mt-2">
        <summary><span class="fw-bold"><?= Language::tt('cdis_info_auto_login_how_to_enable_summary') ?></span></summary>
        <p><?= Language::tt('cdis_info_auto_login_how_to_enable_description') ?></p>
    </details>

</div>
<style>
[data-auto-login-legend] details {
    border-radius: 5px;
    border: solid 1px #cacaca;
    padding: 10px;
}
</style>





