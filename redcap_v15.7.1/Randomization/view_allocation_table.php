<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$randomization) System::redirectHome();
if (!UserRights::isSuperUserNotImpersonator()) System::redirectHome();

$rid = Randomization::getRid($_GET['rid']);
if (!$rid) System::redirectHome();

if (!isset($_GET['stratum'])) System::redirectHome();
$stratum = htmlspecialchars($_GET['stratum']??"", ENT_QUOTES);

// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
renderPageTitle('<i class="fas fa-random"></i> ' . $lang['random_166']);

// Instructions
//print Randomization::renderInstructions();

// Page tabs
Randomization::renderTabs($rid);

Randomization::renderAllocationTable($rid, $stratum);

print RCView::script("
	$('[data-bs-toggle=tooltip]').each(function() {
		new bootstrap.Tooltip(this, {
			html: true,
			trigger: 'hover',
			placement: 'top'
		});
    });", true);

// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';