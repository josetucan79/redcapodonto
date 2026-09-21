<?php

include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
include APP_PATH_DOCROOT . 'Logging/filters.php';

// Increase memory limit in case needed for intensive processing
System::increaseMemory(2048);

// Query logging table
$result = db_query($logging_sql);

// Set headers
$headers = array($lang['reporting_19'], $lang['global_11'], $lang['reporting_21'], str_replace("\n", " ", br2nl($lang['reporting_22'])), $lang['global_49']);
// If project-level flag is set, then add "reason changed" to row data
if ($require_change_reason) $headers[] = "Reason for Data Change(s)";

// Set file name and path
$filename = APP_PATH_TEMP . date("YmdHis") . '_' . PROJECT_ID . '_logging.csv';

// Begin writing file from query result
$fp = fopen($filename, 'w');

if ($fp && $result)
{
	// Write headers to file
	fputcsv($fp, $headers, User::getCsvDelimiter(), '"', '');

	// Set values for this row and write to file
	while ($row = db_fetch_assoc($result))
	{
		if (!SUPER_USER && (strpos($row['description'], "(Admin only) Stop viewing project as user") === 0 || strpos($row['description'], "(Admin only) View project as user") === 0)) {
			continue;
		}
		fputcsv($fp, Logging::renderLogRow($row, false), User::getCsvDelimiter(), '"', '');
	}

	// Close file for writing
	fclose($fp);
	db_free_result($result);

	// Open file for downloading
	$download_filename = camelCase(html_entity_decode($app_title, ENT_QUOTES)) . "_Logging_" . date("Y-m-d_Hi") . ".csv";
	header('Pragma: anytextexeptno-cache', true);
	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=$download_filename");

	// Open file for reading and output to user
	$fp = fopen($filename, 'rb');
	print addBOMtoUTF8(fread($fp, filesize($filename)));

	// Close file and delete it from temp directory
	fclose($fp);
	unlink($filename);

	// Logging
	Logging::logEvent("", Logging::getLogEventTable($project_id),"MANAGE",$project_id,"project_id = $project_id",(isset($_GET['download_all']) ? "Export entire logging record" : "Export logging (custom)"));

}
else
{
	print $lang['global_01'];
}
