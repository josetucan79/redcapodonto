<?php
require_once dirname(__FILE__, 3) . '/Config/init_project.php';

// If user does not have Project Setup/Design rights, do not show this page
// if (!$user_rights['design']) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");

include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

if(!$rewards_enabled_global) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");

?>
<?= sprintf('<div class="projhdr"><i class="fas fa-gift"></i> %s</div>', $lang['rewards_feature_name_extended']) ?>