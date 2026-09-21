<?php

if (isset($_GET['pid'])) {
    require_once 'Config/init_project.php';
} else {
    require_once 'Config/init_global.php';
}
if (!isDev(true)) System::redirectHome();
if (isset($_GET['pid']) && $_SERVER['REQUEST_METHOD'] != 'POST') include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
########################################################################################################################











########################################################################################################################
if (isset($_GET['pid']) && $_SERVER['REQUEST_METHOD'] != 'POST') include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';