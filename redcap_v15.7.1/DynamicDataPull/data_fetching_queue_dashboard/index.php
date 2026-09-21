<?php
include __DIR__.'/partials/header.php';
include __DIR__.'/partials/tabs.php';
?>
<div style="max-width: 800px;">
    <div>
        <?= $lang['cdp_dashboard_index_bg_processes_description'] ?>
    </div>
    <div>
        <?= $lang['cdp_dashboard_index_cached_values_description'] ?>
    </div>
</div>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';