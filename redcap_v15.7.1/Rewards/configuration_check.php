<?php include __DIR__.'/partials/header.php'; ?>
<?php include __DIR__.'/partials/tabs.php'; ?>
<?php
$criteriaData = require __DIR__.'/partials/configuration_check.php';
$valid = $criteriaData['valid'] ?? false;
$legend = $criteriaData['legend'] ?? '';

?>
<div style="width: auto; max-width: 800px;">
    <?= $legend ?>
</div>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
