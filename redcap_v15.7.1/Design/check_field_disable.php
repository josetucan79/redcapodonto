<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Render 1 if exists in metadata table, and 0 if not
print isset($Proj->metadata[$_GET['field_name']]) ? "1" : "0";
