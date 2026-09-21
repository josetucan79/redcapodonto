<?php include __DIR__.'/partials/header.php'; ?>
<?php
use Vanderbilt\REDCap\Classes\Utility\SessionDataUtils;

// If user does not have Project Setup/Design rights, do not show this page
if (!$user_rights['design']) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && ACCESS_SYSTEM_CONFIG) {
    flash('alert-success', 'data saved');
    redirect(previousURL());
}
?>
<?php include __DIR__.'/partials/tabs.php'; ?>

<?php

?>
<div style="max-width: 800px; clear: both">
<form action="" method="POST">
    

</form>
<?= SessionDataUtils::getAlerts() ?>
</div>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
