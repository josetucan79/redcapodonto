<?php
require_once dirname(__FILE__, 2) . '/Config/init_project.php';

function isProjectStatusAnalisysCleanup() {
	global $Proj;
	$statusAnalysisCleanup = 2;
	return ($Proj?->project['status'] ?? 0) == $statusAnalysisCleanup;
}

if(isProjectStatusAnalisysCleanup()) {
	// Output warning message that project is in analysis status
	ob_start();
	?>
	<div class="yellow my-2" style="max-width:100%;">
		<i class="fas fa-exclamation-triangle text-warning"></i>
		<span>
			<?= $lang['global_03']; // "Warning" ?> 
			<?= $lang['ws_347'] ?>
		</span>
		<details class="my-2">
			<summary><?= $lang['ws_22'] ?></summary>
			<p><?= $lang['ws_348'] ?></p>
			<a href="mailto:<?= $project_contact_email; ?>" style="text-decoration:underline;">
				<?= $lang['bottom_39']; ?>
			</a>
		</details>
	</div>
	<?php
	$html = ob_get_clean();
	// Return content as JSON
	print json_encode_rc(['item_count'=>-1, 'html'=>$html]);
	exit;
}

/** @var DynamicDataPull $DDP */
// First check if the user has user access rights to adjudicate source data
if (!$DDP->userHasAdjudicationRights(true))
{
	// Output error message that user has no access
	ob_start();
	?>
	<div class="red my-2" style="max-width:100%;">
		<i class="fas fa-exclamation-circle text-danger"></i>
		<span id="RTWS_sourceDataCheck_userAccessErrorTitle">
			<?= $lang['ws_21'] . " " . $DDP->getSourceSystemName() . 
				($realtime_webservice_type == 'FHIR' ? $lang['ws_203'] . " " . $DDP->getSourceSystemName() . $lang['period'] : ""); ?>
		</span>
		<details class="my-2">
			<summary><?= $lang['ws_22'] ?></summary>
			<?= ($realtime_webservice_type == 'FHIR' ? $lang['ws_204'] : $lang['ws_23']) . " "; ?>
			<a href="mailto:<?= $project_contact_email; ?>" style="text-decoration:underline;">
				<?= $lang['bottom_39']; ?>
			</a>
			<?= $lang['period']; ?>
		</details>
	</div>
	<?php
	$html = ob_get_clean();
	// Return content as JSON
	print json_encode_rc(['item_count'=>-1, 'html'=>$html]);
	exit;
}


## PERFORMANCE: Kill any currently running processes by the current user/session on THIS page
System::killConcurrentRequests(5);


// if (!is_numeric($_GET['event_id'])) {
// 	// If record is passed in query string, obtain it
// 	$record = strip_tags(html_entity_decode($_POST['record'], ENT_QUOTES));
// 	$form_data = array();
// 	$event_id = null;
// 	$output_html = (!isset($_GET['output_html']) || (isset($_GET['output_html']) && $_GET['output_html'] != '0'));
// } else {
// 	// OR if ALL form data is passed as separate POST elements
// 	$record = strip_tags(html_entity_decode($_POST[$table_pk], ENT_QUOTES));
// 	$form_data = $_POST;
// 	$event_id = $_GET['event_id'];
// 	$output_html = true;
// }

$record = strip_tags(html_entity_decode($_POST['record'], ENT_QUOTES));
$form_data = [];
$event_id = $_GET['event_id'] ?? '';
$output_html = (!isset($_GET['output_html']) || (isset($_GET['output_html']) && $_GET['output_html'] != '0'));

$record_exists = ($_GET['record_exists'] == '1') ? '1' : '0';
$show_excluded = (isset($_GET['show_excluded']) && $_GET['show_excluded'] == '1');
$forceDataFetch = (isset($_GET['forceDataFetch']) && $_GET['forceDataFetch'] == '1');

// Get number of items to adjudicate and the html to display inside the dialog
list ($itemsToAdjudicate, $tableHtml)
	= $DDP->fetchAndOutputData($record, $event_id, $form_data, $_GET['day_offset'], $_GET['day_offset_plusminus'],
								$output_html, $record_exists, $show_excluded, $forceDataFetch, $_GET['instance'], 
								(isset($_GET['page']) && $_GET['instance'] > 0 ? $_GET['page'] : null));


$response = ['item_count'=>$itemsToAdjudicate, 'html'=>$tableHtml];
// add errors to response if any
if(isset($DDP->fhirData) && $DDP->fhirData->hasErrors())
{
	$response['errors'] = $DDP->fhirData->getErrors();
}
// Output data returned from web service as JSON
HttpClient::printJSON($response);