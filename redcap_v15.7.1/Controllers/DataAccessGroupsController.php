<?php

class DataAccessGroupsController extends Controller
{
	// Save User-DAG assignment
	public function saveUserDAG()
	{
		global $user_rights;
		header("Content-Type: application/json");
		if ($user_rights['data_access_groups'] != 1) {
			$result = '0'; // user must have DAG page permission
		} else {
			try {
				$user = $_POST['user'];
				$dag = $_POST['dag'];
				$enabled = $_POST['enabled']=='true';
				$module = new DAGSwitcher();
				$result = $module->saveUserDAG($user, $dag, $enabled);
			} catch (Exception $ex) {
				http_response_code(500);
				$result = 'Exception: '.$ex->getMessage();
			}
		}
		print json_encode(array('result'=>$result));
	}

	// Render the DAG Switcher table
	public function getDagSwitcherTable()
	{
		if (isset($_GET['rowoption']) && $_GET['rowoption'] == 'users') {
			UIState::saveUIStateValue(PROJECT_ID, 'data_access_groups', 'rowoption', 'users');
		} else {
			UIState::removeUIStateValue(PROJECT_ID, 'data_access_groups', 'rowoption');
		}
		$module = new DAGSwitcher();
		print $module->renderDAGPageTableContainer(isset($_GET['tablerowsonly']));
	}

	// Switch DAG
	public function switchDag()
	{
		$module = new DAGSwitcher();
		print $module->switchToDAG($_POST['dag']);
	}

	// Render main DAG page
	public function index()
	{
		DataAccessGroups::renderPage();
	}

	// DAG ajax requests on main DAG page
	public function ajax()
	{
		DataAccessGroups::ajax();
	}

    // Download Sample CSV for DAGs of Project
    public function downloadDag()
    {
        Logging::logEvent("", "redcap_data_access_groups", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export data access groups");

        $dags = Project::getDAGRecords();
        $content = (!empty($dags)) ? arrayToCsv($dags) : 'data_access_group_name,unique_group_name,data_access_group_id';

        $project_title = REDCap::getProjectTitle();
        $filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($project_title, ENT_QUOTES)))), 0, 30)
            ."_DAGs_".date("Y-m-d").".csv";

        header('Pragma: anytextexeptno-cache', true);
        header("Content-type: application/csv");
        header('Content-Disposition: attachment; filename=' . $filename);
        echo addBOMtoUTF8($content);
        exit;
    }

    // Download Sample CSV for User-DAG Assignment of Project
    public function downloadUserDag()
    {
        Logging::logEvent("", "redcap_data_access_groups_users", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export User-DAG assignments");

        $user_dags = Project::getUserDAGRecords();
        $content = (!empty($user_dags)) ? arrayToCsv($user_dags) : 'username,redcap_data_access_group';

        $project_title = REDCap::getProjectTitle();
        $filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($project_title, ENT_QUOTES)))), 0, 30)
            ."_UserDAG_".date("Y-m-d").".csv";

        header('Pragma: anytextexeptno-cache', true);
        header("Content-type: application/csv");
        header('Content-Disposition: attachment; filename=' . $filename);
        echo addBOMtoUTF8($content);
        exit;
    }

    // Upload CSV for DAGs
    public function uploadDag()
    {
        extract($GLOBALS);

        $count = 0;
        $errors = array();
        $csv_content = $preview = "";
        $commit = false;
        if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name'])) {
            $csv_content = file_get_contents($_FILES['file']['tmp_name']);
        } elseif (isset($_POST['csv_content']) && $_POST['csv_content'] != '') {
            $csv_content = $_POST['csv_content'];
            $commit = true;
        }

        if ($csv_content != "")
        {
            $data = csvToArray(removeBOM($csv_content));

            // Begin transaction
            db_query("SET AUTOCOMMIT=0");
            db_query("BEGIN");

            $Proj = new Project(PROJECT_ID);
            $Proj->resetGroups();

            list ($count, $errors) = DataAccessGroups::uploadDAGs(PROJECT_ID, $data);
            // Build preview of changes being made
            if (!$commit && empty($errors))
            {
                $cells = "";
                foreach (array_keys($data[0]) as $this_hdr) {
                    $cells .= RCView::td(array('style'=>'padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'), $this_hdr);
                }
                $rows = RCView::tr(array(), $cells);

                foreach($data as $dag)
                {
                    $group_name = $dag['data_access_group_name'];
                    $unique_group_name = trim($dag['unique_group_name']);

                    // Check for changes
                    $old_group_name = '';
                    // Assume that if $unique_group_name set means that exists in project as its already handled while validation
                    if ($unique_group_name != '') {
                        // Check if DAG record will be updated
                        $groups = $Proj->getUniqueGroupNames();
                        $group_id = array_search($unique_group_name, $groups);

                        $col1class = ($Proj->groups[$group_id] != $group_name) ? 'yellow' : 'gray';
                        $old_group_name = ($col1class == 'gray') ? "" :
                                RCView::div(array('style'=>'color:#777;font-size:11px;'), "({$Proj->groups[$group_id]})");
                    } else {
                        // New DAG record will be added
                        $col1class = 'green';
                    }
                    // Add row
                    $rows .= RCView::tr(array(),
                        RCView::td(array('class'=>$col1class),
                            $group_name . $old_group_name
                        ) .
                        RCView::td(array('class'=>'gray'),
                            $unique_group_name
                        )
                    );
                }
                $preview = RCView::table(array('cellspacing'=>1), $rows);
            }
            if ($commit && empty($errors)) {
                // Commit
                $csv_content = "";
                db_query("COMMIT");
                db_query("SET AUTOCOMMIT=1");
                Logging::logEvent("", "redcap_data_access_groups", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Import data access groups");
            } else {
                // ERROR: Roll back all changes made and return the error message
                db_query("ROLLBACK");
                db_query("SET AUTOCOMMIT=1");
            }

            $_SESSION['imported'] = 'dags';
            $_SESSION['count'] = $count;
            $_SESSION['errors'] = $errors;
            $_SESSION['csv_content'] = $csv_content;
            $_SESSION['preview'] = $preview;
        }

        redirect(APP_PATH_WEBROOT . 'index.php?route=DataAccessGroupsController:index&pid=' . PROJECT_ID);
    }

    // Upload CSV for User-DAG Assignment
    public function uploadUserDag()
    {
        extract($GLOBALS);

        $csv_content = $preview = "";
        $commit = false;
        if (isset($_FILES['file']['tmp_name']) && !empty($_FILES['file']['tmp_name'])) {
            $csv_content = file_get_contents($_FILES['file']['tmp_name']);
        } elseif (isset($_POST['csv_content']) && $_POST['csv_content'] != '') {
            $csv_content = $_POST['csv_content'];
            $commit = true;
        }

        if ($csv_content != "")
        {
            $data = csvToArray(removeBOM($csv_content));

            // Begin transaction
            db_query("SET AUTOCOMMIT=0");
            db_query("BEGIN");

            $Proj = new Project(PROJECT_ID);
            $Proj->resetGroups();
            $projectUsers = UserRights::getPrivileges(PROJECT_ID);

            list ($count, $errors) = DataAccessGroups::uploadUserDAGMappings(PROJECT_ID, $data);

            // Build preview of changes being made
            if (!$commit && empty($errors))
            {
                $cells = "";
                foreach (array_keys($data[0]) as $this_hdr) {
                    $cells .= RCView::td(array('style'=>'padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'), $this_hdr);
                }
                $rows = RCView::tr(array(), $cells);

                foreach($data as $dag)
                {
                    $username = $dag['username'];
                    $unique_group_name = trim($dag['redcap_data_access_group']);

                    // Check for changes
                    $user_rights = $projectUsers[PROJECT_ID][strtolower($username)];

                    $groups = $Proj->getUniqueGroupNames();
                    // if $unique_group_name is non-empty, No need to check if group exists as already handled in validation
                    $group_id = ($unique_group_name != '') ? array_search($unique_group_name, $groups) : NULL;

                    $col2class = ($user_rights['group_id'] != $group_id) ? 'yellow' : 'gray';
                    $old_group_name = (is_null($user_rights['group_id'])) ? $lang['data_access_groups_ajax_24']: $groups[$user_rights['group_id']];
                    $old_group_name = ($col2class == 'gray') ? "" :
                                        RCView::div(array('style'=>'color:#777;font-size:11px;'), "({$old_group_name})");
                    if ($unique_group_name == '' && $col2class == 'yellow') {
                        $unique_group_name = $lang['data_access_groups_ajax_24'];
                    }

                    // Add row
                    $rows .= RCView::tr(array(),
                        RCView::td(array('class'=>'gray'),
                            $username
                        ) .
                        RCView::td(array('class'=>$col2class),
                            $unique_group_name . $old_group_name
                        )
                    );
                }
                $preview = RCView::table(array('cellspacing'=>1), $rows);
            }
            if ($commit && empty($errors)) {
                // Commit
                $csv_content = "";
                db_query("COMMIT");
                db_query("SET AUTOCOMMIT=1");
                Logging::logEvent("", "redcap_user_rights", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Import User-DAG assignments");
            } else {
                // ERROR: Roll back all changes made and return the error message
                db_query("ROLLBACK");
                db_query("SET AUTOCOMMIT=1");
            }

            $_SESSION['imported'] = 'userdags';
            $_SESSION['count'] = $count;
            $_SESSION['errors'] = $errors;
            $_SESSION['csv_content'] = $csv_content;
            $_SESSION['preview'] = $preview;
        }

        redirect(APP_PATH_WEBROOT . 'index.php?route=DataAccessGroupsController:index&pid=' . PROJECT_ID);
    }
}