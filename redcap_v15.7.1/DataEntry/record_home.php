<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
$draft_preview_enabled = Design::isDraftPreview(PROJECT_ID);
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
DataEntry::renderRecordHomePage();
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';