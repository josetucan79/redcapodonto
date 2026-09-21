<?php


// Disable authentication
define("NOAUTH", true);

// Set flag to designate this as the cron job
define("CRON", true);

// Set cron job USERID (to prevent any jobs or modules from setting it later)
if (!defined("USERID")) define("USERID", "SYSTEM");

// Config for non-project pages
require_once dirname(__FILE__) . "/Config/init_global.php";

// Add more time for processing the crons
System::increaseMaxExecTime(7200);

// Instantiate the class
$cron = new Cron();

// Execute the jobs
$cron->execute();
