<?php

class ProjectDashController extends Controller
{
	// Render the setup page
	public function index()
	{
		$this->render('HeaderProject.php', $GLOBALS);
		$dash = new ProjectDashboards();
		$dash->renderSetupPage();
		$this->render('FooterProject.php');
	}

	// View a dashboard
	public function view()
	{
		if (!isset($_GET['dash_id']) || !isinteger($_GET['dash_id'])) {
			redirect(APP_PATH_WEBROOT."index.php?route=ProjectDashController:index&pid={$_GET['pid']}");
		}
		$this->render('HeaderProject.php', $GLOBALS);
		$dash = new ProjectDashboards();
		$dash->viewDash($_GET['dash_id']);
		$this->render('FooterProject.php');
	}

	// View access list
	public function access()
	{
		global $lang;
		$dash = new ProjectDashboards();
		// Display list of usernames who would have access
		$content = $dash->displayDashboardAccessUsernames($_POST);
		// Output JSON
		print json_encode_rc(array('content'=>$content, 'title'=>$lang['report_builder_108']));
	}

	// Save a dashboard
	public function save()
	{
		if (!isset($_GET['dash_id']) || !isinteger($_GET['dash_id'])) {
			redirect(APP_PATH_WEBROOT."index.php?route=ProjectDashController:index&pid={$_GET['pid']}");
		}
		$dash = new ProjectDashboards();
		$dash->saveDash();
	}

	// Copy dashboard
	public function copy()
	{
		$dash = new ProjectDashboards();
		// Validate id
		if (!isset($_POST['dash_id'])) exit('0');
		$dash_id = $_POST['dash_id'];
		$dash1 = $dash->getDashboards(PROJECT_ID, $dash_id);
		if (empty($dash1)) exit('0');
		// Copy the report and return the new report_id
		$new_dash_id = $dash->copyDash($dash_id);
		if ($new_dash_id === false) exit('0');
		// Return HTML of updated report list and report_id
		print json_encode_rc(array('new_dash_id'=>$new_dash_id, 'html'=>$dash->renderDashboardList()));
	}

	// Delete dashboard
	public function delete()
	{
		$dash = new ProjectDashboards();
		// Validate id
		if (!isset($_POST['dash_id'])) exit('0');
		$dash_id = $_POST['dash_id'];
		$dash1 = $dash->getDashboards(PROJECT_ID, $dash_id);
		if (empty($dash1)) exit('0');
		// Delete the dashboard
		$success = $dash->deleteDash($dash_id);
		print ($success === false) ? '0' : '1';
	}

	// Reorder dashboards
	public function reorder()
	{
		$dash = new ProjectDashboards();
		$dash->reorderDashboards();
	}

	// Reload left-hand menu panel of dashboards
	public function viewpanel()
	{
        // If user is collapsing a Dashboard Folder, then set in database
        if (isset($_POST['collapse']) && isset($_POST['folder_id']) && is_numeric($_POST['folder_id'])) {
            DataExport::collapseReportFolder($_POST['folder_id'], $_POST['collapse'], 'project_dashboard');
        }
		$dash = new ProjectDashboards();
		list ($dashboardsListTitle, $dashboardsListCollapsed) = $dash->outputDashboardPanelTitle();
		$dashboardsList = $dash->outputDashboardPanel();
		if ($dashboardsList != "") {
			print renderPanel($dashboardsListTitle, $dashboardsList, 'dashboard_panel', $dashboardsListCollapsed);
		}
	}

	// Create a custom short URL for a dashboard
	public function shorturl()
	{
		if (!isset($_POST['hash']) || !isset($_POST['custom_url']) || !isset($_POST['dash_id'])) exit("0");
		$dash = new ProjectDashboards();
		$dash->saveShortUrl($_POST['hash'], $_POST['custom_url'], $_POST['dash_id']);
	}

	// Delete the custom short URL for a dashboard
	public function remove_shorturl()
	{
		if (!isset($_POST['dash_id']) || !isinteger($_POST['dash_id'])) exit("0");
		$dash = new ProjectDashboards();
		$dash->removeShortUrl($_POST['dash_id']);
	}

	// Reset dashboard cache
	public function reset_cache()
	{
		if (!isset($_POST['dash_id']) || !isinteger($_POST['dash_id'])) exit("0");
		$dash = new ProjectDashboards();
		print $dash->resetCache($_POST['dash_id']) ? '1' : '0';
	}

	// Enable/disable colorblind feature of Pie/Donut Charts
	public function colorblind()
	{
		$dash = new ProjectDashboards();
		$dash->colorblind();
		print "1";
	}

	// Send request to admin to enable a public dashboard
	public function request_public_enable()
	{
		if (!isset($_POST['dash_id']) || !isinteger($_POST['dash_id'])) exit("0");
		$dash = new ProjectDashboards();
		$dash->requestPublicEnable($_POST['dash_id']);
	}

	// Admin approves request to enable a public dashboard
	public function public_enable()
	{
		if (!(SUPER_USER && ((isset($_GET['dash_id']) && isinteger($_GET['dash_id'])) || (isset($_POST['dash_id']) && isinteger($_POST['dash_id']))))) {
			exit("ERROR");
		}
		if (isset($_POST['dash_id'])) {
			$dash = new ProjectDashboards();
			$dash->publicEnable((int)$_POST['dash_id'], false);
		} else {
			$this->render('HeaderProject.php', $GLOBALS);
			$dash = new ProjectDashboards();
			$dash->publicEnable((int)$_GET['dash_id'], true);
			$this->render('FooterProject.php');
		}
	}

    public function dashFoldersDialog()
    {
        print DataExport::outputReportFoldersDialog('project_dashboard');
    }

    public function dashFolderCreate()
    {
        print DataExport::reportFolderCreate('project_dashboard');
    }
    public function dashFolderDisplayTable()
    {
        print DataExport::outputReportFoldersTable('project_dashboard');
    }
    public function dashFolderDisplayDropdown()
    {
        print DataExport::outputReportFoldersDropdown('project_dashboard');
    }
    public function dashFolderDisplayTableAssign()
    {
        print DataExport::outputReportFoldersTableAssign($_POST['folder_id'], $_POST['hide_assigned'], 'project_dashboard');
    }
    public function dashFolderAssign()
    {
        $dashObj = new ProjectDashboards();
        print $dashObj->dashboardFolderAssign();
    }

    public function dashFolderEdit()
    {
        print DataExport::reportFolderEdit('project_dashboard');
    }

    public function dashFolderDelete()
    {
        print DataExport::reportFolderDelete('project_dashboard');
    }
    public function dashSearch()
    {
        print DataExport::reportSearch($_GET['term'], 'project_dashboard');
    }
    public function dashFolderResort()
    {
        print DataExport::reportFolderResort($_POST['data'], 'project_dashboard');
    }

	/**
	 * Generate a QR code for a public dashboard.
	 * This function outputs a PNG image directly to the buffer.
	 * @return void 
	 */
	public function get_qr_code_png() {
		if (!isset($_GET['dash_id']) || !isinteger($_GET['dash_id'])) {
			print "Access Denied";
		}
		$short = isset($_GET['short']) ? $_GET['short'] == "1" : false;
		$dashboards = new ProjectDashboards();
		$dash = $dashboards->getDashboards(PROJECT_ID, $_GET['dash_id']);
		$link = $short ? $dash['short_url'] : APP_PATH_SURVEY_FULL . "?__dashboard=" . $dash['hash'];
		QRCodeUtils::output_qr_code_png($link);
	}

	/**
	 * Generate a QR code for a public dashboard.
	 * This function outputs an SVG string directly to the buffer.
	 * @return void 
	 */
	public function get_qr_code_svg() {
		if (!isset($_GET['dash_id']) || !isinteger($_GET['dash_id'])) {
			print "Access Denied";
		}
		$short = isset($_GET['short']) ? $_GET['short'] == "1" : false;
		$dashboards = new ProjectDashboards();
		$dash = $dashboards->getDashboards(PROJECT_ID, $_GET['dash_id']);
		$link = $short ? $dash['short_url'] : APP_PATH_SURVEY_FULL . "?__dashboard=" . $dash['hash'];
		$filename = "Dashboard {$dash["hash"]}" . ($short ? " (Short)" : "") . ".svg";
		header('Content-Type: image/svg+xml');
		header("Content-Disposition: attachment; filename=\"$filename\"");
		print QRCodeUtils::generate_qr_code_svg($link);
	}
}