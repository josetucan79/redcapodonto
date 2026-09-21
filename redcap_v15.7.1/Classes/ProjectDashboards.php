<?php

class ProjectDashboards
{
    private static $maxCacheTime = 10; // minutes
    public static $currentDashHasPrivacyProtection = false;

	// Render the setup page
	public function renderSetupPage()
	{
		global $lang, $Proj;
		renderPageTitle("<div style='float:left;'>
					{$lang['global_182']}
				 </div>
				 <div style='float:right;'>
					<i class=\"fas fa-film\"></i>
					<a onclick=\"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=project_dashboards01.mp4&referer=".SERVER_NAME."&title={$lang['global_182']}','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');\" href=\"javascript:;\" style=\"font-size:12px;text-decoration:underline;font-weight:normal;\">{$lang['training_res_106']} (23 {$lang['calendar_12']})</a>
				 </div><br>");
		$this->renderTabs();
		print RCView::p(array('class'=>'mt-0 mb-2', 'style'=>'max-width:970px;'),
			$lang['dash_05'] . " " .
            RCView::a(array('href'=>'javascript:;', 'onclick'=>"$(this).hide();$('#dashInstr2, #dashInstr3').removeClass('hide');", 'style'=>'text-decoration:underline;'), $lang['alerts_32'])
		);
		print RCView::p(array('id'=>'dashInstr2', 'class'=>'mt-0 mb-2 hide', 'style'=>'max-width:970px;'),
			$lang['dash_110'] . " " .
            RCView::a(array('href'=>'javascript:;', 'onclick'=>"smartVariableExplainPopup();", 'style'=>'text-decoration:underline;'), $lang['dash_111']) . $lang['period']
		);
		if ($GLOBALS['project_dashboard_allow_public'] > 0) {
			print RCView::p(array('id' => 'dashInstr3', 'class' => 'mt-0 mb-2 hide', 'style' => 'max-width:970px;'),
				$lang['dash_14'] . " " . self::getMinDataPointsToDisplay($Proj) . " " . $lang['dash_109']
			);
		}
		if (isset($_GET['addedit'])) {
			print $this->outputCreateDashboardTable(isset($_GET['dash_id']) && isinteger($_GET['dash_id']) ? $_GET['dash_id'] : '');
		} else {
			// JavaScript
			$this->loadDashJS();
			print "<div id='dashboard_list_parent_div' class='mt-3'>".$this->renderDashboardList()."</div>";
		}
	}

	// View a dashboard
	public function viewDash($dash_id)
	{
	    global $lang, $user_rights;
		$this->checkDashHash($dash_id);
		$dash = $this->getDashboards($_GET['pid'], $dash_id);
		// Make sure user has access to this dashboard if viewing inside a project
		$noAccess = false;
		$errorMsg = $lang['shared_library_01'];
        if (!$this->isPublicDash() && defined("USERID") && $dash['user_access'] != 'ALL') {
            // Get list of users with access to dashboard
			$user_list = $this->getReportAccessUsernames($dash);
			// If a user has Setup/Design rights or if they have been given explicit access to this dashboard or if they are an admin, allow them to view it
			$noAccess = !(SUPER_USER || $user_rights['design'] || isset($user_list[strtolower(USERID)]));
        } elseif ($this->isPublicDash() && $dash['is_public'] != '1') {
            // If viewing a public dashboard link that is no longer set as "public", return error message
            $noAccess = true;
            $errorMsg = $lang['dash_50'];
        }
		// Render the dashboard
        if ($noAccess) {
            print RCView::div(array('class'=>'red my-5'), RCView::b($lang['global_01'].$lang['colon'])." ".$errorMsg);
        } else {
			$this->renderDash($dash);
        }
	}

	// Returns boolean regarding whether we are viewing a dashboard via a public link
	public function isPublicDash()
    {
        return (defined("PAGE") && PAGE == 'surveys/index.php' && isset($_GET['__dashboard']) && !isset($_GET['s']));
    }

	// Returns boolean regarding whether we are viewing a survey page
	public function isSurveyPage()
	{
		return isSurveyPage();
	}

	// Returns boolean regarding whether we are viewing a survey queue page
	public function isSurveyQueuePage()
	{
		return (defined("PAGE") && PAGE == 'surveys/index.php' && isset($_GET['sq']));
	}

	// Return the minimum amount of data points that can be displayed on a public dashboard (based on system- or project-level settings)
	public static function getMinDataPointsToDisplay($Proj)
	{
		return ((isset($Proj->project['project_dashboard_min_data_points']) && is_numeric($Proj->project['project_dashboard_min_data_points']))
			? $Proj->project['project_dashboard_min_data_points']
			: $GLOBALS['project_dashboard_min_data_points']);
	}

	// Return boolean if we have met the minimum amount of data points that can be displayed on a public dashboard (based on system- or project-level settings)
	public function canDisplayDataOnPublicDash($num_data_pts, $Proj)
	{
		return (isinteger($num_data_pts) && $num_data_pts >= self::getMinDataPointsToDisplay($Proj));
	}

	// Returns PID from URL hash when viewing a public dashboard
	public function getDashInfoFromPublicHash($d)
	{
		$sql = "select project_id, dash_id, title from redcap_project_dashboards where hash = '".db_escape($d)."'";
		$q = db_query($sql);
		if (db_num_rows($q)) {
		    return array(db_result($q, 0, 'project_id'), db_result($q, 0, 'dash_id'), db_result($q, 0, 'title'));
        } else {
		    return array('', '', '');
        }
	}

	// Is the cached dashboard content outdated?
    private function isCacheOutdated($cache_time)
    {
		return ($cache_time == '' || (strtotime(NOW)-strtotime($cache_time))/60 > self::$maxCacheTime);
    }

	// If the cache is outdated, then refresh it and cache the new content in the db table
	private function refreshCache($dash_id, $body)
	{
	    $sql = "update redcap_project_dashboards set cache_time = '".NOW."', cache_content = '".db_escape($body)."'
	            where dash_id = '".db_escape($dash_id)."'";
	    return db_query($sql);
	}

	// Use the cache? If a user is in a DAG and any Smart Things are attached to a report or is using user-dag-name, then do NOT use cache but build in real time.
    // Note: Public dashboards will never have DAG limiting applied since they have to user rights.
	private function useCache($body)
	{
		global $user_rights, $Proj;

		// Public dashboards must always have static content (will not consider dynamic DAG limiting)
        if ($this->isPublicDash()) return true;

        // If current user is NOT in a DAG, then return true to use the cache
		$userInDag = (isset($user_rights['group_id']) && $user_rights['group_id'] != '');
		if (!$userInDag) return true;

		// If dashboard does not contain Smart Charts, Tables, or Functions, then return true to use the cache
		$dashContainsSmartThings = Piping::containsSmartChartsTablesOrFunctions($body);
		if (!$dashContainsSmartThings) return true;

		// Get list of all smart charts, tables, functions
		$smartCFTs = Piping::getSmartChartsTablesFunctionsTags();
		foreach ($smartCFTs as $key=>$item) {
			$smartCFTs[$key] = preg_quote("[".$item.":");
        }

        // For non-public dashboards, see if any dynamic DAG limiting would occur via Unique Report Names
		$regexReportNames = "/(".implode("|", $smartCFTs).")[^[\]]*(R-[A-Z0-9]{10})[^[\]]*\]/";
		$hasSmartThingsAttachedToReport = (strpos($body, 'R-') !== false && preg_match_all($regexReportNames, $body, $matches, PREG_PATTERN_ORDER));

		// For non-public dashboards, see if any dynamic DAG limiting would occur via :user-dag-name parameter for Smart Things
		$regexUserDagName = "/(".implode("|", $smartCFTs).")[^[\]]*(user-dag-name)[^[\]]*\]/";
		$hasSmartThingsLimitedByUserDag = (strpos($body, 'user-dag-name') !== false && preg_match_all($regexUserDagName, $body, $matches, PREG_PATTERN_ORDER));

		// If we have DAG limiting, then don't cache the dashboard
        $dashHasDataLimitedByDag = ($hasSmartThingsAttachedToReport || $hasSmartThingsLimitedByUserDag);
		$useCache = !$dashHasDataLimitedByDag;
		return $useCache;
	}

	// Render a dashboard (provide $dash as array of dashboard attributes)
	public function renderDash($dash)
	{
		global $lang, $user_rights;

		// Get dashboard title
		$title = decode_filter_tags($dash['title']);

		// Get the dashboard BODY (via the cache or by performing piping in real time)
		// Use the cache? If a user is in a DAG and any Smart Things in a private dashboard are attached to a report (via ":R-XXXXX"), then do NOT use cache but build dashboard in real time.
        $resetCacheTime = true;
        if (!$this->useCache($dash['body'])) {
            // Note: This will NEVER be done for Public dashboards because it is based on being in a DAG.
			// Perform piping of the dashboard body in real time.
			$body = Piping::replaceVariablesInLabel(decode_filter_tags($dash['body']));
		}
		// Check the cache for the dashboard content
        elseif ($this->isCacheOutdated($dash['cache_time'])) {
			// Perform piping of the dashboard body in real time.
            $body = Piping::replaceVariablesInLabel(decode_filter_tags($dash['body']));
            // If we're viewing a public dashboard and it contains errors in the body related to having too few data points (privacy protection),
            // then perform piping in real time and do NOT cache this content (because we don't want to display the privacy errors on the private version of the dashboard).
            $publicDashHasPrivacyProtection = ($this->isPublicDash() && strpos($body, Piping::$smartPublicDashMinDataPtClass) !== false);
			// Even though this is not a public dashboard, if its content is deemed not suitable to display as a public dashboard, then do not cache it.
            if (!$publicDashHasPrivacyProtection && !ProjectDashboards::$currentDashHasPrivacyProtection) {
                // Refresh/save the cache to the database for later retrieval. Also add extra JS for Smart Charts.
                $this->refreshCache($dash['dash_id'], $body . Piping::outputSmartChartsJS());
            }
        }
        // Get dashboard content from cache
        else {
            $resetCacheTime = false;
            $body = replaceUrlImgViewEndpoint($dash['cache_content']); // Run this through replaceUrlImgViewEndpoint() so that embedded image URLs get replaced properly
        }

	    // Build export file name and buttons
		$exportPdfFileName = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", $title))), 0, 30)."_dashboard_".date("Y-m-d_Hi").".pdf";;
	    $pdfBtn = RCView::button(array('class'=>'btn btn-xs btn-defaultrc', 'style'=>'color:#A00000;', 'onclick'=>"exportPageAsPDF('#dashboard_container','".js_escape($exportPdfFileName)."','#dashboard_button_container, a.smart-table-export-link, .dash-snapshot, .redcap-chart-colorblind-toggle');"),
                        RCView::fa('fas fa-file-pdf me-1').$lang['dash_31']
                  );
		$editBtn = '';
		if (!$this->isPublicDash() && isset($user_rights['design']) && $user_rights['design']) {
            $editBtn = RCView::button(array('class' => 'btn btn-xs btn-defaultrc me-2', 'onclick' => "window.location.href=app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:index&addedit=1&dash_id='+getParameterByName('dash_id');"),
                            RCView::fa('fas fa-pencil-alt me-1') . $lang['global_27']
                       );
		}
		$publicLink = '';
		if (!$this->isPublicDash() && $dash['is_public'] && $GLOBALS['project_dashboard_allow_public'] > 0) {
		    $publicUrl = ($dash['short_url'] != '') ? $dash['short_url'] : APP_PATH_SURVEY_FULL . "?__dashboard=" . $dash['hash'];
			$publicLink = RCView::a(array('class' => 'text-primary fs12 me-3', 'href' => $publicUrl, 'target'=>'_blank'),
				RCView::fa('fas fa-link me-1') . $lang['dash_52']
			);
        }

		// Cache time to display on page (will always be NOW except if we're pulling it from the db table cache)
		if ($resetCacheTime) $dash['cache_time'] = NOW;
		// Display cache time and refresh link
		$lastUpdatedTime = round((strtotime(NOW)-strtotime($dash['cache_time']))/60);
		$showRefreshLink = true;
		if ($dash['cache_time'] == '' || $dash['cache_time'] == NOW || $lastUpdatedTime == 0) {
			$lastUpdatedTimeText = $lang['dash_57'];
			$showRefreshLink = false;
        } elseif ($lastUpdatedTime == 1) {
		    // Displaying snapshot created 1 minute ago
			$lastUpdatedTimeText = $lang['dash_53']." $lastUpdatedTime ".$lang['dash_56'];
        } else {
			// Displaying snapshot created X minutes ago
			$lastUpdatedTimeText = $lang['dash_53']." $lastUpdatedTime ".$lang['dash_54'];
        }

		// Don't show the refresh link on public dashboards
        if ($showRefreshLink && $this->isPublicDash()) $showRefreshLink = false;

		$buttonBoxWidth = $this->isPublicDash() ? "270px" : "314px";

		// Output HTML
		$html =
			RCView::div(array('id'=>'dashboard_container'),
				RCView::div(array('id'=>'dashboard_title_container', 'class'=>'clearfix'),
					RCView::div(array('id'=>'dashboard_button_container', 'class'=>'d-print-none', 'style'=>'width:'.$buttonBoxWidth.';'),
						RCView::div(array('class'=>'nowrap dash-snapshot d-print-none mb-2 fs11', 'style'=>'color:#888;'),
							$lastUpdatedTimeText .
                            // "Refresh" link
                            (!$showRefreshLink ? "" :
								RCView::a(array('class'=>'fs11 ms-2', 'style'=>'text-decoration:underline;', 'href'=>'javascript:;', 'onclick'=>"resetDashbardCache('{$dash['dash_id']}');"),
									$lang['control_center_4471']
								)
                            )
					    ) .
						RCView::div(array('style'=>'margin-bottom:2px;'), $publicLink . $editBtn . $pdfBtn)
					) .
					RCView::div(array('style'=>'margin-right:'.$buttonBoxWidth.';'), RCView::div(array('id'=>'dashboard_title'), $title))
                ) .
				RCView::div(array('id'=>'dashboard_body'), $body)
			);
		// Set popovers for public dashboards with privacy protection
        if ($this->isPublicDash()) {
			$html .= "<script type='text/javascript'>$(function(){ $('[data-toggle=\"popover\"]').popover({trigger:'hover'}); });</script>";
		}
        // Output to page
		print $html;
	}

	// Enable/disable colorblind feature of Pie/Donut Charts
	public function colorblind()
	{
		if (isset($_POST['enable_colorblind']) && $_POST['enable_colorblind'] == '1') {
			savecookie('redcap_colorblind', "1", 31536000); // Set to expire after 1 year
		} else {
			deletecookie('redcap_colorblind');
		}
	}

	// Display tabs on page
	public function renderTabs()
	{
		global $lang;
		$tabs = array(	"index.php?route=ProjectDashController:index&addedit=1"=>RCView::fa('fas fa-plus me-1').$lang['dash_01'],
						"index.php?route=ProjectDashController:index"=>RCView::img(array('src'=>'dashboard1.png', 'style'=>'margin-right:1px;')) . $lang['dash_06']);
		if (isset($_GET['dash_id']) && isinteger($_GET['dash_id'])) {
		    $tabs["index.php?route=ProjectDashController:index&addedit=1&dash_id=".$_GET['dash_id']] = RCView::fa('fas fa-pencil-alt me-1').$lang['dash_30'];
        }
		RCView::renderTabs($tabs);
	}

	// Return all dashboards (unless one is specified explicitly) as an array of their attributes
	public function getDashboards($project_id, $dash_id=null)
	{
		// Array to place report attributes
		$dashboards = array();
		// If dash_id is 0 (report doesn't exist), then return field defaults from tables
		if ($dash_id === 0) {
			// Add to reports array
			$dashboards[$dash_id] = getTableColumns('redcap_project_dashboards');
			// Pre-fill empty slots for limiters and fields
			$dashboards[$dash_id]['user_access_users'] = array();
			$dashboards[$dash_id]['user_access_roles'] = array();
			$dashboards[$dash_id]['user_access_dags'] = array();
			// Return array
			return $dashboards[$dash_id];
		}

		// Get main attributes
		$sql = "select * from redcap_project_dashboards where project_id = ".$project_id;
		if (is_numeric($dash_id)) $sql .= " and dash_id = $dash_id";
		$sql .= " order by dash_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Add to reports array
			$dashboards[$row['dash_id']] = $row;
			$dashboards[$row['dash_id']]['user_access_users'] = array();
			$dashboards[$row['dash_id']]['user_access_roles'] = array();
			$dashboards[$row['dash_id']]['user_access_dags'] = array();
		}
		// If no reports, then return empty array
		if (empty($dashboards)) return array();

		// Get user access - users
		$sql = "select * from redcap_project_dashboards_access_users where dash_id in (" . prep_implode(array_keys($dashboards)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$dashboards[$row['dash_id']]['user_access_users'][] = $row['username'];
		}
		// Get user access - roles
		$sql = "select * from redcap_project_dashboards_access_roles where dash_id in (" . prep_implode(array_keys($dashboards)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$dashboards[$row['dash_id']]['user_access_roles'][] = $row['role_id'];
		}
		// Get user access - DAGs
		$sql = "select * from redcap_project_dashboards_access_dags where dash_id in (" . prep_implode(array_keys($dashboards)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$dashboards[$row['dash_id']]['user_access_dags'][] = $row['group_id'];
		}
		// Return array of report(s) attributes
		if ($dash_id == null) {
			return $dashboards;
		} else {
			return $dashboards[$dash_id] ?? [];
		}
	}

    /**
     * Get dashboard names. Returns array with dash_id as key and title as value
     *
     * @param integer $dash_id
     * @param boolean $applyUserAccess
     * @param boolean $fixOrdering
     * @param boolean $useFolderOrdering
     * @return array
     */
	public function getDashboardNames($dash_id=null, $applyUserAccess=false, $fixOrdering=true, $useFolderOrdering=false)
	{
		global $user_rights;

		// Builder SQL to pull dash_id and title in proper order
		if (!$applyUserAccess) {
			$sql = "select distinct r.dash_id, r.title, r.dash_order, r.is_public, r.hash, af.folder_id, af.name as folder, af.position
					from redcap_project_dashboards r
					left join redcap_project_dashboards_folders_items ai on ai.dash_id = r.dash_id
					left join redcap_project_dashboards_folders af on af.folder_id = ai.folder_id
					where r.project_id = ".PROJECT_ID;
		} else {
			// Apply user access rights
			$sql = "select distinct r.dash_id, r.title, r.dash_order, r.is_public, r.hash, af.folder_id, af.name as folder, af.position
					from redcap_project_dashboards r
					left join redcap_project_dashboards_access_users au on au.dash_id = r.dash_id
					left join redcap_project_dashboards_access_roles ar on ar.dash_id = r.dash_id
					left join redcap_project_dashboards_access_dags ad on ad.dash_id = r.dash_id
					left join redcap_project_dashboards_folders_items ai on ai.dash_id = r.dash_id
					left join redcap_project_dashboards_folders af on af.folder_id = ai.folder_id
					where r.project_id = ".PROJECT_ID;
			// Array for WHERE components
			$sql_where_array = array();
			// Include reports with ALL access
			$sql_where_array[] = "r.user_access = 'ALL'";
			// Username check
			if (UserRights::isImpersonatingUser()) {
				$sql_where_array[] = "au.username = '".db_escape(UserRights::getUsernameImpersonating())."'";
			} elseif (defined("USERID")) {
				$sql_where_array[] = "au.username = '".db_escape(USERID)."'";
			}
			// DAG check
			if (is_numeric($user_rights['group_id'])) $sql_where_array[] = "ad.group_id = ".$user_rights['group_id'];
			// Role check
			if (is_numeric($user_rights['role_id'])) $sql_where_array[] = "ar.role_id = ".$user_rights['role_id'];
			// Append WHERE to query
			$sql .= " and (" . implode(" or ", $sql_where_array) . ")";
		}
		$sql .= is_numeric($dash_id) ? " and r.dash_id = $dash_id" : ($useFolderOrdering ? " order by af.position, r.dash_order" : " order by r.dash_order");
		$q = db_query($sql);

		// Add reports to array
		$dashboards = array();
		$dashboardsOutOfOrder = false;
		$counter = 1;
		while ($row = db_fetch_assoc($q)) {
            $public_url = $row['is_public'] ? APP_PATH_SURVEY_FULL . "index.php?__dashboard=".$row['hash'] : "";
			// Add to array
			$dashboards[] = array('dash_id'=>$row['dash_id'], 'title'=>$row['title'], 'is_public'=>$row['is_public'], 'public_url'=>$public_url, 'folder_id'=>$row['folder_id'], 'folder'=>$row['folder'], 'collapsed'=>0);
			// Check report order
			if (isset($row['position']) && is_numeric($row['position'])) {
				$fixOrdering = $dashboardsOutOfOrder = false;
			} elseif ($fixOrdering && $counter++ != $row['dash_order'] && !$dashboardsOutOfOrder) {
				$dashboardsOutOfOrder = true;
			}
		}
		// Return reports array
		if (is_numeric($dash_id)) return $dashboards[0]['title'];
		else return $dashboards;
	}

	// Get html table listing all reports
	public function renderDashboardList()
	{
		global $lang;
		// Ensure dashboards are in correct order
		$this->checkDashOrder();
		// Ensure dashboards have a hash
		$this->checkDashHash();
		// Get list of reports to display as table (only apply user access filter if don't have Add/Edit Reports rights)
		$dashboards = $this->getDashboards(PROJECT_ID);
		// Build table
		$rows = array();
		$item_num = 0; // loop counter
		foreach ($dashboards as $dash_id=>$attr)
		{
			// First column
			$rows[$item_num][] = RCView::span(array('style'=>'display:none;'), $dash_id);
			// Report order number
			$rows[$item_num][] = ($item_num+1);
			// Report title
			$rows[$item_num][] = RCView::div(array('class'=>'wrap fs14'),
				// View Report button
				RCView::div(array('class'=>'float-end text-end me-1', 'style'=>'width:60px;'),
					RCView::button(array('class'=>'btn btn-primaryrc btn-xs fs12 nowrap', 'onclick'=>"openDashboard($dash_id);"),
						'<i class="fas fa-search"></i> ' .$lang['dash_03']
					)
				) .
				// Public link
                (!($attr['is_public'] && $GLOBALS['project_dashboard_allow_public'] > 0) ? "" :
					RCView::div(array('class'=>'float-end text-end', 'style'=>'width:60px;'),
						RCView::a(array('href'=>($attr['short_url'] == "" ? APP_PATH_WEBROOT_FULL.'surveys/index.php?__dashboard='.$attr['hash'] : $attr['short_url']), 'target'=>'_blank', 'class'=>'text-primary fs12 nowrap me-2 ms-1'),
							'<i class="fas fa-link"></i> ' .$lang['dash_35']
						)
					)
				) .
                // Dashboard name
				RCView::div(array('style'=>'margin-right:'.($attr['is_public'] ? "120px" : "60px").';cursor:pointer;', 'class' => 'dash-title', 'onclick'=>"openDashboard($dash_id);"), RCView::escape($attr['title'], false))
            );
			// View/export options
			$rows[$item_num][] =
				RCView::span(array('class'=>'rprt_btns'),
					//Edit
					RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11', 'style'=>'color:#000080;margin-right:2px;padding: 1px 6px;', 'onclick'=>"editDashboard($dash_id);"),
						'<i class="fas fa-pencil-alt"></i> ' .$lang['global_27']
					) .
					// Copy
					RCView::button(array('id'=>'repcopyid_'.$dash_id, 'class'=>'btn btn-defaultrc btn-xs fs11', 'style'=>'margin-right:2px;padding: 1px 6px;', 'onclick'=>"copyDashboard($dash_id,true);"),
						'<i class="far fa-copy"></i> ' .$lang['report_builder_46']
					) .
					// Delete
					RCView::button(array('id'=>'repdelid_'.$dash_id, 'class'=>'btn btn-defaultrc btn-xs fs11', 'style'=>'color:#A00000;padding: 1px 6px;', 'onclick'=>"deleteDashboard($dash_id,true);"),
						'<i class="fas fa-times"></i> ' .$lang['global_19']
					)
				);
			// Increment row counter
			$item_num++;
		}
		// Add last row as "add new report" button
		$rows[$item_num] = array('', '',
			RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs12', 'style'=>'color:#000080;margin:12px 0;', 'onclick'=>"window.location.href = app_path_webroot+'index.php?route=ProjectDashController:index&addedit=1&pid='+pid;"),
				'<i class="fas fa-plus fs11"></i> ' . $lang['dash_01']
			), '');
		// Set table headers and attributes
		$col_widths_headers = array();
		$col_widths_headers[] = array(18, "", "center");
		$col_widths_headers[] = array(18, "", "center");
		$col_widths_headers[] = array(700, $lang['dash_02']);
		$col_widths_headers[] = array(200, $lang['dash_04'], "center");
		// Render the table
		return renderGrid("project_dashboard_list", "", 990, 'auto', $col_widths_headers, $rows, true, false, false);
	}


	// Output html table for users to create or modify dashboards
	public function outputCreateDashboardTable($dash_id=null)
	{
		global $lang, $Proj;
		// Get dash_id
		$dash_id = ($dash_id == null ? 0 : $dash_id);
		// Ensure dashboards have a hash
		$this->checkDashHash($dash_id);
		// Get report attributes
		$dash = $this->getDashboards(PROJECT_ID, $dash_id);
		// Get list of User Roles
		$role_dropdown_options = array();
		foreach (UserRights::getRoles() as $role_id=>$attr) {
			$role_dropdown_options[$role_id] = $attr['role_name'];
		}
		// Get list of all DAGs, events, users, and records
		$dag_dropdown_options = $Proj->getGroups();
		$user_dropdown_options = User::getProjectUsernames(array(), true);
		$user_access_radio_custom_checked = ($dash['user_access'] != 'ALL') ? 'checked' : '';
		$user_access_radio_all_checked = ($dash['user_access'] == 'ALL') ? 'checked' : '';
		if ($dash['user_access'] == 'ALL') {
			// If ALL is selected, then remove custom options
			$dash['user_access_users'] = $dash['user_access_roles'] = $dash['user_access_dags'] = array();
		}
		// Add blank values onto the end of some attributes to create empty row for user to enter a new field, filter, etc.
		$dash['fields'][] = "";
		$dash['limiter_fields'][] = array('field_name'=>'', 'limiter_group_operator'=>'AND', 'limiter_event_id'=>'',
			'limiter_operator' =>'', 'limiter_value'=>'');
		// If creating new report from SELECTED forms/events (Rule B), then display note that all fields/events are pre-selected
		if ($dash_id == '0' && isset($_GET['instruments'])) {
			print   RCView::div(array('class'=>'yellow', 'style'=>'max-width:780px;margin:5px 0 20px;'),
				RCView::img(array('src'=>'exclamation_orange.png')) .
				$lang['report_builder_141']
			);
		}
		// JavaScript & CSS
        $this->loadDashJS();
		?>
        <style type="text/css">
            .labelrc { background: #F5F5F5; }
        </style>
        <?php
		// Initialize table rows
		print  "<div style='margin-top:15px;max-width:1000px;'>
				 <form id='create_report_form'>
					<table id='create_report_table' class='form_border' style='width:100%;'>";

		// Dashboard title
		print   RCView::tr(array(),
			RCView::td(array('class'=>'labelrc create_rprt_hdr nowrap fs14 align-top px-2', 'style'=>'background-color:#e6e6e6;height:50px;color:#0450a5;width:160px;padding-top:12px'),
				$lang['dash_08']
			) .
			RCView::td(array('class'=>'labelrc create_rprt_hdr', 'style'=>'background-color:#e6e6e6;height:50px;padding:5px 10px;'),
				RCView::text(array('name'=>'title', 'value'=>htmlspecialchars($dash['title']??"", ENT_QUOTES), 'class'=>'x-form-text x-form-field', 'onkeydown'=>'if(event.keyCode == 13) return false;', 'style'=>'height:32px;padding: 4px 6px 3px;font-size:16px;width:99%;'))
			)
		);

		## USER ACCESS
		print   RCView::tr(array(),
			RCView::td(array('class'=>'labelrc fs14 align-top p-2', 'style'=>'width:160px;color:#0450a5;'),
                $lang['dash_37']
            ) .
			RCView::td(array('class'=>'labelrc clearfix px-3 pt-2 pb-1'),
				RCView::div(array('class'=>'fs14 mb-2', 'style'=>'color:#0450a5;'),
					RCView::span(array('class'=>'font-weight-normal'), $lang['dash_12']) .
					RCView::div(array('class'=>'fs11 font-weight-normal mt-1', 'style'=>'color:#999;'), $lang['dash_11'])
				) .
				// All users
				RCView::div(array('style'=>'float:left;'),
					RCView::radio(array('name'=>'user_access_radio', 'style'=>'top:3px;position:relative;', 'onchange'=>"displayUserAccessOptions()", 'value'=>'ALL', $user_access_radio_all_checked=>$user_access_radio_all_checked))
				) .
				RCView::div(array('style'=>'float:left;margin:2px 0 0 2px;'),
					$lang['control_center_182']
				) .
				RCView::div(array('style'=>'float:left;color:#888;font-weight:normal;margin:2px 20px 0 25px;'),
					"&ndash; " . $lang['global_46'] . " &ndash;"
				) .
				// Custom user access
				RCView::div(array('style'=>'float:left;'),
					RCView::radio(array('name'=>'user_access_radio', 'style'=>'top:3px;position:relative;', 'onchange'=>"displayUserAccessOptions()", 'value'=>'SELECTED', $user_access_radio_custom_checked=>$user_access_radio_custom_checked))
				) .
				RCView::div(array('style'=>'float:left;margin:2px 0 0 2px;'),
					RCView::div(array('style'=>'margin-bottom:10px;'),
						$lang['report_builder_62'] .
						RCView::span(array('id'=>'selected_users_note1', 'style'=>($dash['user_access'] == 'ALL' ? 'display:none;' : '').'margin-left:10px;color:#800000;font-size:11px;font-weight:normal;'),
							$lang['report_builder_105']
						) .
						RCView::span(array('id'=>'selected_users_note2', 'style'=>($dash['user_access'] != 'ALL' ? 'display:none;' : '').'margin-left:10px;color:#888;font-size:11px;font-weight:normal;'),
							$lang['report_builder_66']
						)
					) .
					RCView::div(array('id'=>'selected_users_div', 'style'=>($dash['user_access'] == 'ALL' ? 'display:none;' : '')),
						// Select Users
						RCView::div(array('style'=>'margin-right:30px;float:left;font-weight:normal;vertical-align:top;'),
							$lang['extres_28'] .
							RCView::div(array('style'=>'margin-left:3px;'),
								RCView::select(array('id'=>'user_access_users', 'name'=>'user_access_users', 'onchange'=>"clearMultiSelect(this);", 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'),
									$user_dropdown_options, $dash['user_access_users'], 200)
							)
						) .
						// Select User Roles
						(empty($role_dropdown_options) ? '' :
							RCView::div(array('style'=>'margin-right:30px;float:left;font-weight:normal;vertical-align:top;'),
								$lang['report_builder_61'] .
								RCView::div(array('style'=>'margin-left:3px;'),
									RCView::select(array('id'=>'user_access_roles', 'name'=>'user_access_roles', 'onchange'=>"clearMultiSelect(this);", 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'),
										$role_dropdown_options, $dash['user_access_roles'], 200)
								)
							)
						) .
						// Select DAGs
						(empty($dag_dropdown_options) ? '' :
							RCView::div(array('style'=>'float:left;font-weight:normal;vertical-align:top;'),
								$lang['extres_52'] .
								RCView::div(array('style'=>'margin-left:3px;'),
									RCView::select(array('id'=>'user_access_dags', 'name'=>'user_access_dags', 'onchange'=>"clearMultiSelect(this);", 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'),
										$dag_dropdown_options, $dash['user_access_dags'], 200)
								)
							)
						) .
						// Get list of users who would have access given the selections made
						RCView::div(array('style'=>'clear:both;padding:5px 0 0 3px;font-size:11px;font-weight:normal;color:#222;'),
							$lang['dash_13'] .
							RCView::button(array('class'=>'jqbuttonsm', 'style'=>'margin-left:7px;font-size:11px;', 'onclick'=>"getUserAccessList();return false;"),
								$lang['report_builder_107']
							)
						)
					)
				)
			)
		);

		## PUBLIC LINK
        $is_public_checked = $dash['is_public'] ? "checked" : "";
		$flashObjectName = 'dashurl';
		// Add text if public dash is not yet enabled but requires admin approval
        $publicDashApprovalText = "";
        $publicDashRequiresApproval = ($GLOBALS['project_dashboard_allow_public'] == '2' && !$dash['is_public'] && !UserRights::isSuperUserNotImpersonator());
		if ($publicDashRequiresApproval) {
			$publicDashApprovalText = RCView::div(array('id'=>'public-dash-enable-warning', 'class'=>'font-weight-normal text-danger mt-2 ms-5 fs14'), '<i class="fas fa-info-circle"></i> '.$lang['dash_74']);
		} elseif ($GLOBALS['project_dashboard_allow_public'] == '2' && !$dash['is_public'] && UserRights::isSuperUserNotImpersonator()) {
			$publicDashApprovalText = RCView::div(array('id'=>'public-dash-enable-warning', 'class'=>'font-weight-normal mt-2', 'style'=>'color:#0450a5;'), '<i class="fas fa-info-circle"></i> '.$lang['dash_75']);
		}
		// Only display if public dashboards are allowed
		if ($GLOBALS['project_dashboard_allow_public'] > 0)
		{
			$public_dash_link = APP_PATH_SURVEY_FULL . "?__dashboard=" . $dash['hash'];
			print   RCView::tr(array(),
                        RCView::td(array('class' => 'labelrc fs14 align-top p-2', 'style' => 'width:160px;color:#0450a5;'),
                            $lang['dash_32']
                        ) .
                        RCView::td(array('class' => 'labelrc clearfix px-3 py-2'),
                            RCView::div(array('class' => ''),
                                RCView::span(array('class' => 'font-weight-normal fs14', 'style' => 'color:#0450a5;'), $lang['dash_33']) .
                                RCView::div(array('class' => 'custom-control custom-switch mt-2'),
                                    // Switch to toggle "public" setting
                                    RCView::checkbox(array('class' => 'custom-control-input', 'name' => 'is_public', 'id' => 'is_public', $is_public_checked => $is_public_checked)) .
                                    RCView::label(array('class' => 'custom-control-label', 'for' => 'is_public'), $lang['dash_34'])
                                ) .
								$publicDashApprovalText .
                                // Public link
                                RCView::div(array('id' => 'public_link_div', 'style' => 'display:flex;align-items:center;', 'class' => 'mt-1 mb-1' . ($dash['is_public'] ? "" : " hide")),
                                    ($dash['hash'] == ""
                                        ?   // Creating new dash
                                        RCView::span(array('class' => 'fs13 nowrap float-start font-weight-normal text-secondary', 'style' => ''),
                                            $lang['dash_39']
                                        )
                                        :   // Editing existing dash
                                        RCView::span(array('class' => 'fs13 nowrap float-start font-weight-normal mt-1 me-1'),
                                            '<i class="fas fa-link me-1"></i>' . RCView::tt("dash_38")
                                        ) .
                                        // Public link
                                        '<input id="' . $flashObjectName . '" value="' . $public_dash_link . '" onclick="this.select();" readonly="readonly" class="staticInput" style="float:left;width:70%;min-width:200px;max-width:360px;">
                                                <button type="button" class="btn btn-defaultrc btn-xs btn-clipboard ms-1" title="' . js_escape2($lang['global_137']) . '" data-clipboard-target="#' . $flashObjectName . '"><i class="fa-solid fa-paste"></i></button>' .
                                        RCView::button(array('id' => 'create-qr-code-btn', 'type' => 'button', 'class' => 'btn btn-xs btn-defaultrc ms-2', 'onclick' => "showQRCode(this, '" . $dash_id . "');"),
                                            '<i class="fa-solid fa-qrcode me-1"></i>' . RCView::tt("dash_139")
                                        ) .
                                        RCView::button(array('id' => 'create-custom-link-btn', 'type' => 'button', 'class' => 'btn btn-xs btn-defaultrc ms-2', 'style' => (($GLOBALS['enable_url_shortener'] == '1' && $dash['short_url'] == '') ? "" : "display:none;"), 'onclick' => "customizeShortUrl('" . js_escape($dash['hash']) . "','$dash_id');"),
                                            '<i class="fa-solid fa-link me-1"></i>' . RCView::tt("dash_42")
                                        )
									)
								) .
								// Custom public link
								RCView::div(array('id' => 'short-link-display', 'class' => 'mt-2 mb-1' . ($dash['is_public'] ? "" : " hide"), 'style' => ($dash['short_url'] == '' ? "display:none;" : "display:flex;") . 'align-items:center;'),
									RCView::span(array('class' => 'fs13 nowrap float-start font-weight-normal mt-1'),
										'<i class="fa-solid fa-link me-1"></i>' . RCView::tt("dash_43")
									) .
									'<input id="' . $flashObjectName . '-custom" value="' . $dash['short_url'] . '" onclick="this.select();" readonly="readonly" class="staticInput" style="float:left;width:70%;min-width:200px;max-width:360px;">
										<button type="button" class="btn btn-defaultrc btn-xs btn-clipboard ms-1" onclick="return false;" title="' . js_escape2($lang['global_137']) . '" data-clipboard-target="#' . $flashObjectName . '-custom" ><i class="fa-solid fa-paste"></i></button>
										' .
										RCView::button(array('id' => 'create-qr-code-btn', 'type' => 'button', 'class' => 'btn btn-xs btn-defaultrc ms-2', 'onclick' => "showQRCode(this, '" . $dash_id . "', true);"),
                                            '<i class="fa-solid fa-qrcode me-1"></i>' . RCView::tt("dash_139")
                                        ) .
										'
										<button type="button" class="btn btn-xs text-danger ms-2" onclick="simpleDialog(\'' . js_escape($lang['dash_44']) . '\',\'' . js_escape($lang['design_654']) . '\',null,500,null,\'' . js_escape($lang['global_53']) . '\',function(){ removeCustomUrl(\'' . $dash_id . '\'); },\'' . js_escape($lang['global_19']) . '\');" onmouseover="$(this).removeClass(\'opacity50\');" onmouseout="$(this).addClass(\'opacity50\');" class="opacity50 delete-btn"><i class="fa-solid fa-trash-can me-1"></i></button>'
								)
                            )
                        )
                    );
		}

		// Body
		print   RCView::tr(array(),
                    RCView::td(array('class'=>'labelrc align-top nowrap fs14 p-2', 'style'=>'color:#0450a5;width:160px;'),
                        RCView::div(array('class'=>''), $lang['dash_09']).
                        RCView::div(array('class'=>'wrap fs11 mt-2', 'style'=>'font-weight:normal;color:#888;line-height:13px;'), $lang['dash_10']) .
                        // Wizard button
						RCView::div(array('class'=>'font-weight-normal', 'style'=>'margin-top:50px;'),
							RCView::div(array('class'=>'fs12', 'style'=>'color:#888;margin-bottom:5px;'), $lang['dash_49']).
							RCView::button(array('class'=>'btn btn-xs btn-primaryrc fs12', 'onclick'=>"openDashWizard();return false;"),
								'<i class="fas fa-magic me-1"></i>'.$lang['dash_48']
							)
                        )
                    ) .
                    RCView::td(array('class'=>'labelrc p-2 font-weight-normal'),
                        RCView::textarea(array('name'=>'body', 'id'=>'body', 'class'=>'x-form-field notesbox mceEditor',
                            'style'=>'width:99%;height:500px;'), decode_filter_tags($dash['body'])) .
                        // Examples of Smart Things
						RCView::div(array('class'=>'fs12 mt-3 mb-2 clearfix', 'style'=>'color:#666;'),
							RCView::div(array('class'=>'clearfix mb-2'),
                                RCView::div(array('class'=>'float-start fs13'), $lang['dash_91']) .
                                RCView::div(array('class'=>'float-end me-3'),
                                    "<span style='vertical-align:middle;margin-right:4px;'>
                                        {$lang['edit_project_186']}
                                    </span>
                                    <button class='btn btn-xs btn-rcgreen btn-rcgreen-light' style='margin-right:6px;font-size:11px;padding:0px 3px 1px;line-height:14px;'  onclick=\"smartVariableExplainPopup();return false;\">[<i class='fas fa-bolt fa-xs' style='margin:0 1px;'></i>] {$lang['global_146']}</button>"
                                )
                            ) .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[aggregate-mean:age]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[aggregate-count:record_id]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[aggregate-max:weight:R-319PCCFN87]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[stats-table:height,weight,bmi,age]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[stats-table:weight,height:min,max,median]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[stats-table:weight,height:user-dag-name]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[scatter-plot:weight,height]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[scatter-plot:weight,height,sex]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[line-chart:weight,height:R-5898NNMYL4]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[line-chart:weight,height,sex]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[pie-chart:education_level]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[pie-chart:race:vanderbilt_dag,duke_dag]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[donut-chart:race:enroll_arm_1,visit1_arm_1]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[bar-chart:ethnicity:R-131EDWCJHN]") .
							RCView::code(array('class'=>'float-start mt-1', 'style'=>'width:272px;'), "[bar-chart:race,sex:bar-vertical,bar-stacked]")
                        )
                    )
                );

		// Set table html
		print     "</table>
					</form>" .
					RCView::div(array('style'=>'text-align:center;margin:30px 0 50px;'),
						RCView::button(array('class'=>'btn btn-primaryrc', 'style'=>'font-size:15px !important;', 'onclick'=>"saveDash($dash_id);"),
							$lang['dash_29']
						) .
						RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;margin-left:20px;font-size:13px;', 'onclick'=>"window.location.href=app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:index'"),
							$lang['global_53']
						)
					) .
			"</div>";
		addLangToJS(['global_53','global_137','survey_200','survey_1560','bottom_90','dash_106','dash_140']);
		// Get drop-down options for field selection and smart things
		$field_options = Form::getFieldDropdownOptions(false, false, false, false, null, false);
		$smart_thing_options = Piping::getSmartChartsTablesFunctionsTags();
		$smart_thing_options = array_combine($smart_thing_options, $smart_thing_options);
		$table_col_options = [''=>$lang['dash_100'], 'count'=>'count', 'missing'=>'missing', 'unique'=>'unique', 'min'=>'min', 'max'=>'max', 'mean'=>'mean', 'median'=>'median', 'stdev'=>'stdev', 'sum'=>'sum'];
        $report_names = array();
		foreach (DataExport::getReportNames(null, false, true, false) as $attr) {
			$report_names[$attr['report_id']] = $attr['title'];
		}
		$report_names_unique = DataExport::getUniqueReportNames(array_keys($report_names));
		$report_options = [""=>$lang['dash_101']];
		foreach ($report_names as $rid=>$rtitle) {
			$report_options[$report_names_unique[$rid]] = strip_tags($rtitle);
		}
        $dags = $Proj->getGroups();
        $dags_unique = $Proj->getUniqueGroupNames();
		$dag_options = array_merge([""=>$lang['dash_102'], "user-dag-name"=>$lang['dash_108']], array_combine($dags_unique, $dags));
		$eventsUnique = $Proj->getUniqueEventNames();
		$eventNames = [];
		foreach ($Proj->eventInfo as $this_event_id=>$attr) {
			$eventNames[] = $attr['name_ext'];
		}
		$event_options = array_merge([""=>$lang['dash_104']], array_combine($eventsUnique, $eventNames));
		?>
        <div id="custom_url_dialog" title="<?php print js_escape2($lang['dash_40']) ?>" class="simpleDialog">
            <div><?php print $lang['dash_41'] ?></div>
            <div class="input-group clearfix" style="margin-top:15px;">
                <span class="input-group-addon float-start" style="margin-top:5px;font-size:16px;font-weight:bold;letter-spacing: 1px;">
                    https://redcap.link/
                </span>
                <input class="form-control customurl-input float-start" style="max-width:200px;margin-left:8px;font-size:15px;letter-spacing: 1px;" type="text">
            </div>
            <div class="mt-3 text-secondary"><?php print $lang['global_03'].$lang['colon']." ".$lang['survey_1272'] ?></div>
        </div>
        <div id="dash_wizard_dialog" title="<?php print js_escape2($lang['dash_92']) ?>" class="simpleDialog fs14">
            <div class="mb-3 fs13" style="line-height: 1.2;"><?php print $lang['dash_107'] ?></div>
            <!-- Step 1: Choose Smart Variable -->
            <div class="font-weight-bold mt-3" style="background-color:#f5f5f5;padding:8px;border:1px solid #ccc;">
                <?=$lang['dash_94'] .
                RCView::select(array('id'=>'smart-thing-var', 'class'=>'x-form-text x-form-field fs14 mt-1 d-block'), $smart_thing_options, "aggregate-min")?>
            </div>
            <!-- Step 2: Choose field(s) -->
            <div class="font-weight-bold mt-3" style="background-color:#f5f5f5;padding:8px;border:1px solid #ccc;">
				<?=$lang['dash_95'] .
				RCView::div(array(),
				    RCView::select(array('class'=>'smart-thing-fields x-form-text x-form-field fs14 mt-1'), $field_options, $Proj->table_pk)
                ) .
				RCView::button(array('id'=>'smart-thing-fields-add', 'class'=>'btn btn-xs btn-defaultrc fs12 mt-2 d-block', 'onclick'=>'addFieldWizard();'), '<i class="fas fa-plus"></i> '.$lang['dash_96'])?>
            </div>
            <!-- Step 3: Optional stuff -->
            <div class="mt-3" style="background-color:#f5f5f5;padding:8px;border:1px solid #ccc;">
                <div class="font-weight-bold"><?=$lang['dash_97']?></div>
                <div class="fs12 mt-1" style="line-height: 1.2;"><?=$lang['dash_112']?></div>
                <!-- Stats table columns -->
                <div class="mt-1 ms-3">
					<?=RCView::div(array('class'=>'d-inline-block', 'style'=>'width:200px;'), $lang['dash_99']) . RCView::select(array('id'=>'smart-thing-table-cols', 'multiple'=>'multiple', 'style'=>'height:70px;', 'class'=>'x-form-text x-form-field fs12 ms-2 mt-3'), $table_col_options, "")?>
                </div>
                <!-- Report filtering -->
                <div class="mt-3">
                    <?=RCView::div(array('class'=>'d-inline-block', 'style'=>'width:200px;'), $lang['dash_98']) . RCView::select(array('id'=>'smart-thing-reports', 'class'=>'x-form-text x-form-field fs12 ms-2'), $report_options, "")?>
                </div>
                <!-- DAG filtering -->
                <?php if (!empty($dags)) { ?>
                <div class="mt-2">
					<?=RCView::div(array('class'=>'d-inline-block', 'style'=>'width:200px;'), $lang['dash_103']) . RCView::select(array('id'=>'smart-thing-dags', 'multiple'=>'multiple', 'style'=>'height:70px;', 'class'=>'x-form-text x-form-field fs12 ms-2 mt-3'), $dag_options, "")?>
                </div>
                <?php } ?>
                <!-- Event filtering -->
				<?php if ($Proj->longitudinal) { ?>
                    <div class="mt-2">
						<?=RCView::div(array('class'=>'d-inline-block', 'style'=>'width:200px;'), $lang['dash_105']) . RCView::select(array('id'=>'smart-thing-events', 'multiple'=>'multiple', 'style'=>'height:70px;', 'class'=>'x-form-text x-form-field fs12 ms-2 mt-3'), $event_options, "")?>
                    </div>
				<?php } ?>
                <?php if ($Proj->longitudinal || !empty($dags)) { ?>
                <div class="mt-2">
                    <?=RCView::div(array('class'=>'fs11 mt-3 mb-1 text-dangerrc', 'style'=>'line-height:1.1;'), $lang['dash_125'])?>
                </div>
                <?php } ?>
            </div>
            <!-- Display generated smart variable -->
            <div class="mt-4 pt-3 mb-4">
                <div class="font-weight-bold ms-1 fs16" style="color:#A00000;"><?=$lang['dash_93']?></div>
                <input id="smart-thing-generated" class="staticInput mt-2 fs15" readonly style="color:#e83e8c;width:645px;font-family:SFMono-Regular,Menlo,Monaco,Consolas,'Liberation Mono','Courier New',monospace" type="text" onclick="this.select();">
                <button class="btn btn-primaryrc btn-xs btn-clipboard ms-1" onclick="return false;" title="<?=js_escape2($lang['global_137'])?>" data-clipboard-target="#smart-thing-generated" style="margin-top: 8px;padding:3px 8px 3px 6px;"><i class="fas fa-paste"></i> <?=$lang['global_137']?></button>
            </div>
        </div>
        <?php
	}

	public function removeShortUrl($dash_id)
    {
        global $lang;
		$dash_id = (int)$dash_id;
		$sql = "update redcap_project_dashboards set short_url = null
                where dash_id = $dash_id and project_id = ".PROJECT_ID;
		if (db_query($sql)) {
			// Logging
			Logging::logEvent($sql, "redcap_project_dashboards", "MANAGE", $dash_id, "dash_id = $dash_id", "Remove custom link for project dashboard" . " - \"".$this->getDashboardName($dash_id)."\"");
			// Return success
			print RCView::div(array('class'=>'boldish fs14'), $lang['dash_45']) . RCView::div(array('class'=>'text-secondary mt-3'), $lang['dash_47']);
		} else {
			exit('0');
		}
	}

	public function resetCache($dash_id, $doLogging=true)
	{
		global $lang;
		$dash_id = (int)$dash_id;
		$sql = "update redcap_project_dashboards set cache_time = null, cache_content = null
                where dash_id = $dash_id and project_id = ".PROJECT_ID;
		if (db_query($sql)) {
			// Logging
			if ($doLogging) {
			    Logging::logEvent($sql, "redcap_project_dashboards", "MANAGE", $dash_id, "dash_id = $dash_id", "Reset cached snapshot for project dashboard" . " - \"".$this->getDashboardName($dash_id)."\"");
			}
			// Return success
			return true;
		}
		return false;
	}

	public function publicEnable($dash_id, $promptToEnable=true)
	{
		global $lang, $project_id, $userid, $redcap_version;

		// Output dialog prompt for admin
        if ($promptToEnable) {
            // Is it already enabled?
			$dashes = $this->getDashboards($project_id, $dash_id);
			if ($dashes['is_public'] == '1') {
			    print RCView::div(array('class'=>'yellow mt-5'), $lang['dash_86']);
			} else {
                // Display dialog
                loadJS('ProjectDashboards.js');
                addLangToJS(['dash_76','dash_77','dash_78','dash_79']);
                ?><script type="text/javascript">$(function(){ promptEnablePublicDash(<?=$dash_id?>,'<?=js_escape($this->getDashboardNames($dash_id))?>'); });</script><?php
			}
		}

		// Approve the dashboard as public
        else
        {
            // Set in table
            $sql = "update redcap_project_dashboards set is_public = 1 where dash_id = $dash_id and project_id = $project_id";
            $q = db_query($sql);
			$this->checkDashHash($dash_id);
			$dashes = $this->getDashboards($project_id, $dash_id);
			$dash_url = APP_PATH_SURVEY_FULL . "index.php?__dashboard=" . $dashes['hash'];
			// Set completed in To-Do List
			$db = new RedCapDB();
			$userInfo = $db->getUserInfoByUsername($_POST['user']);
			if (empty($userInfo)) exit("0");
			$todo_type = "enable project dashboard as public";
			ToDoList::updateTodoStatus($project_id, $todo_type,'completed', $userInfo->ui_id);
			// Send email
            $email = new Message();
            $email->setFrom($GLOBALS['project_contact_email']);
            $email->setFromName($GLOBALS['project_contact_name']);
            $email->setTo($userInfo->user_email);
            $email->setSubject('[REDCap] '.$lang['dash_87']);
			$msg = $lang['dash_88'] . ' "' . RCView::b(strip_tags(REDCap::getProjectTitle($project_id))).'"'.$lang['period']." ".$lang['dash_89']."<br><br>";
			$msg .= $lang['dash_08']." \"".RCView::a(array('href'=>$dash_url), strip_tags($this->getDashboardNames($dash_id)))."\"";
			$email->setBody($msg, true);
            $email->send();
			// Logging
			Logging::logEvent("", "redcap_project_dashboards", "MANAGE", $dash_id, "dashboard=$dash_id", "Project dashboard set as \"public\" via request to admin\n(Dashboard: \"".$this->getDashboardNames($dash_id)."\")");
			// Set return message
			print RCView::div(array('style' => 'color:green;font-size:14px;'),
				RCView::img(array('src' => 'tick.png')) .
				$lang['dash_80']
			);
		}
	}

	public function requestPublicEnable($dash_id)
	{
		global $lang, $project_id, $userid, $send_emails_admin_tasks, $redcap_version;
		$db = new RedCapDB();
		$userInfo = $db->getUserInfoByUsername($userid);
		$ui_id = $userInfo->ui_id;
		$todo_type = "enable project dashboard as public";
		$action_url = APP_PATH_WEBROOT_FULL . "redcap_v$redcap_version/index.php?pid=".$project_id.'&route=ProjectDashController:public_enable&dash_id='.$dash_id.'&user='.$userid;
		$project_url = APP_PATH_WEBROOT_FULL."redcap_v$redcap_version/index.php?pid=$project_id";
		ToDoList::insertAction($ui_id, $GLOBALS['project_contact_email'], $todo_type, $action_url, $project_id);

        // Send email to admin (if applicable)
		if ($send_emails_admin_tasks) {
            $this->getDashboardNames($dash_id);
            $email = new Message();
            $email->setFrom($userInfo->user_email);
            $email->setFromName($userInfo->user_firstname." ".$userInfo->user_lastname);
            $email->setTo($GLOBALS['project_contact_email']);
            $email->setSubject('[REDCap] ' . $lang['dash_82'] . ' (PID '.$project_id.')');
            $msg  = $lang['dash_83']."<br><br>";
            $msg .= $lang['dash_08']." \"".RCView::b($this->getDashboardNames($dash_id))."\"<br>";
            $msg .= $lang['rev_history_15'].$lang['colon']." ".RCView::b($userid)." (".RCView::escape($userInfo->user_firstname." ".$userInfo->user_lastname).")<br>";
            $msg .= $lang['extres_24']." \"".RCView::a(array('href'=>$project_url), strip_tags(REDCap::getProjectTitle($project_id)))."\" (PID $project_id)<br><br>";
            $msg .= RCView::a(array('href'=>$action_url), $lang['dash_84']);
            $email->setBody($msg, true);
			$email->send();
		}
		// Logging
		Logging::logEvent("", "redcap_project_dashboards", "MANAGE", $userid, "dash_id = '$dash_id'", "Request project dashboard be set as \"public\"");
        // Set return message
		print RCView::div(array('style' => 'color:green;font-size:14px;'),
                RCView::img(array('src' => 'tick.png')) .
                $lang['dash_85']
            );
	}

	public function saveShortUrl($hash, $custom_url, $dash_id)
	{
	    global $lang;
		$dash_id = (int)$dash_id;
		if ($GLOBALS['enable_url_shortener']) {
			// Sanitize the custom URL
			$custom_url_orig = $custom_url;
			$custom_url = str_replace(" ", "", trim($custom_url));
			$custom_url = preg_replace("/[^a-zA-Z0-9-_.]/", "", $custom_url);
			if ($custom_url != $custom_url_orig) {
				exit($lang['global_01'].$lang['colon']." ".$lang['survey_1272']." ".$lang['locking_25']);
			}
			// Get custom URL
			$shorturl_status = getREDCapShortUrl(APP_PATH_SURVEY_FULL . '?__dashboard=' . $hash, $custom_url);
			if (isset($shorturl_status['error'])) exit($lang['global_01'].$lang['colon']." ".$shorturl_status['error']);
			if (!isset($shorturl_status['url_short'])) exit("0");
			$shorturl = $shorturl_status['url_short'];
		} else {
			exit('0');
		}
		if (!isURL($shorturl)) exit("".RCView::escape($shorturl));
		// If we got this far, then it was successful. So save short url to the dashboard and return the short url value.
        $sql = "update redcap_project_dashboards set short_url = '".db_escape($shorturl)."'
                where dash_id = $dash_id and project_id = ".PROJECT_ID;
        if (db_query($sql)) {
            // Logging
			Logging::logEvent($sql, "redcap_project_dashboards", "MANAGE", $dash_id, "dash_id = $dash_id", "Create custom link for project dashboard" . " - \"".$this->getDashboardName($dash_id)."\"");
            // Return short URL
			print $shorturl;
		} else {
			exit('0');
		}
	}


	// Return array of all usernames who have access to a given dashboard
	public function getReportAccessUsernames($dash)
	{
		// Get list of ALL users in project
		$all_users = User::getProjectUsernames(array(), true);
		// Get username list
		if ($dash['user_access'] == 'ALL') {
			// ALL USERS
			return $all_users;
		} else {
			// SELECTED USERS
			$selected_users = array();
			// User access rights
			$user_access_users = $user_access_roles = $user_access_dags = array();
			if (isset($dash['user_access_users'])) {
				$user_access_users = $dash['user_access_users'];
				if (!is_array($user_access_users)) $user_access_users = array($user_access_users);
			}
			if (isset($dash['user_access_roles'])) {
				$user_access_roles = $dash['user_access_roles'];
				if (!is_array($user_access_roles)) $user_access_roles = array($user_access_roles);
			}
			if (isset($dash['user_access_dags'])) {
				$user_access_dags = $dash['user_access_dags'];
				if (!is_array($user_access_dags)) $user_access_dags = array($user_access_dags);
			}
			$user_sql = prep_implode($user_access_users);
			if ($user_sql == '') $user_sql = "''";
			$role_sql = prep_implode($user_access_roles);
			if ($role_sql == '') $role_sql = "''";
			$dag_sql = prep_implode($user_access_dags);
			if ($dag_sql == '') $dag_sql = "''";
			// Query tables
			$sql = "select u.username, r.role_id, g.group_id from redcap_user_rights u
					left join redcap_user_roles r on r.role_id = u.role_id
					left join redcap_data_access_groups g on g.group_id = u.group_id
					where u.project_id = ".PROJECT_ID." and
					(u.username in ($user_sql) or r.role_id in ($role_sql) or g.group_id in ($dag_sql))
					order by u.username";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// Add to array
				$selected_users[$row['username']] = array('name'=>$all_users[$row['username']], 'role_id'=>$row['role_id'], 'group_id'=>$row['group_id']);
			}
			return $selected_users;
		}
	}


	// Display list of all usernames who have access to a given dashboard
	public function displayDashboardAccessUsernames($dash)
	{
		global $Proj, $lang;
		// Get list of users
		$user_list = $this->getReportAccessUsernames($dash);
		// Get all roles in the project
		$roles = UserRights::getRoles();
		$hasRoles = !empty($roles);
		// Get all roles in the project
		$dags = $Proj->getGroups();
		$hasDags = !empty($dags);

		// Loop through users and create table rows
		$rows = RCView::tr(array(),
			RCView::td(array('class'=>'header', 'style'=>'width:250px;'),
				$lang['global_17']
			) .
			(!$hasRoles ? '' :
				RCView::td(array('class'=>'header'),
					$lang['global_115']
				)
			) .
			(!$hasDags ? '' :
				RCView::td(array('class'=>'header'),
					$lang['global_78']
				)
			)
		);
		foreach ($user_list as $user=>$attr) {
			// Add user
			$rows .= RCView::tr(array(),
				RCView::td(array('class'=>'labelrc', 'style'=>'width:250px;padding:5px 10px;color:#800000;font-size:13px;font-weight:normal;'),
					$attr['name']
				) .
				(!$hasRoles ? '' :
					RCView::td(array('class'=>'data', 'style'=>'padding:5px 10px;'),
						(is_numeric($attr['role_id']) ? $roles[$attr['role_id']]['role_name'] : '')
					)
				) .
				(!$hasDags ? '' :
					RCView::td(array('class'=>'data', 'style'=>'padding:5px 10px;'),
						(is_numeric($attr['group_id']) ? $dags[$attr['group_id']] : '')
					)
				)
			);
		}
		// No users with access
		if (empty($user_list)) {
			$rows .= RCView::tr(array(),
				RCView::td(array('colspan'=>(1+($hasRoles ? 1 : 0)+($hasDags ? 1 : 0)), 'class'=>'data', 'style'=>'width:250px;padding:5px 10px;color:#800000;font-size:13px;'),
					$lang['report_builder_110']
				)
			);
		}
		// Output table
		$html =	RCView::div(array('style'=>'margin:0 0 15px;'),
				$lang['dash_07']
			) .
			RCView::table(array('class'=>'form_border', 'style'=>"width:100%;table-layout:fixed;"),
				$rows
			);
		// Return html
		return $html;
	}
	
	// Save dashboard
    public function saveDash()
    {
        extract($GLOBALS);

        // Count errors
		$errors = 0;

        // Validate dash_id and see if already exists
		$dash_id = (int)$_GET['dash_id'];
		if ($dash_id != 0) {
			$dash = $this->getDashboards($_GET['pid'], $dash_id);
			if (empty($dash)) exit('0');
		}

        // Report title
		$title = decode_filter_tags($_POST['title']);
		$body = decode_filter_tags($_POST['body']);
		$is_public = (isset($_POST['is_public']) && $_POST['is_public'] == 'on') ? "1" : "0";
        // User access rights
		$user_access_users = $user_access_roles = $user_access_dags = array();
		if (isset($_POST['user_access_users'])) {
			$user_access_users = $_POST['user_access_users'];
			if (!is_array($user_access_users)) $user_access_users = array($user_access_users);
		}
		if (isset($_POST['user_access_roles'])) {
			$user_access_roles = $_POST['user_access_roles'];
			if (!is_array($user_access_roles)) $user_access_roles = array($user_access_roles);
		}
		if (isset($_POST['user_access_dags'])) {
			$user_access_dags = $_POST['user_access_dags'];
			if (!is_array($user_access_dags)) $user_access_dags = array($user_access_dags);
		}
		$user_access = ($_POST['user_access_radio'] == 'SELECTED'
			&& (count($user_access_users) + count($user_access_roles) + count($user_access_dags)) > 0) ? 'SELECTED' : 'ALL';

        // Set up all actions as a transaction to ensure everything is done here
		db_query("SET AUTOCOMMIT=0");
		db_query("BEGIN");

        // Save report in reports table
		if ($dash_id != 0) {
			// Update
			$sqlr = $sql = "update redcap_project_dashboards 
                            set title = '".db_escape($title)."', body = '".db_escape($body)."', user_access = '".db_escape($user_access)."', is_public = $is_public
			                where project_id = ".PROJECT_ID." and dash_id = $dash_id";
			if (!db_query($sql)) $errors++;
		} else {
			// Get next dash_order number
			$q = db_query("select max(dash_order) from redcap_project_dashboards where project_id = ".PROJECT_ID);
			$new_dash_order = db_result($q, 0);
			$new_dash_order = ($new_dash_order == '') ? 1 : $new_dash_order+1;
			// Insert
			$sqlr = $sql = "insert into redcap_project_dashboards (project_id, title, body, user_access, dash_order, is_public)
                            values (".PROJECT_ID.", '".db_escape($title)."', ".checkNull($body).", '".db_escape($user_access)."', $new_dash_order, $is_public)";
			if (!db_query($sql)) $errors++;
			// Set new dash_id
			$dash_id = db_insert_id();
		}

        // USER ACCESS
		$sql = "delete from redcap_project_dashboards_access_users where dash_id = $dash_id";
		if (!db_query($sql)) $errors++;
		foreach ($user_access_users as $this_user) {
			$sql = "insert into redcap_project_dashboards_access_users values ($dash_id, '".db_escape($this_user)."')";
			if (!db_query($sql)) $errors++;
		}
		$sql = "delete from redcap_project_dashboards_access_roles where dash_id = $dash_id";
		if (!db_query($sql)) $errors++;
		foreach ($user_access_roles as $this_role_id) {
			$this_role_id = (int)$this_role_id;
			$sql = "insert into redcap_project_dashboards_access_roles values ($dash_id, '".db_escape($this_role_id)."')";
			if (!db_query($sql)) $errors++;
		}
		$sql = "delete from redcap_project_dashboards_access_dags where dash_id = $dash_id";
		if (!db_query($sql)) $errors++;
		foreach ($user_access_dags as $this_group_id) {
			$this_group_id = (int)$this_group_id;
			$sql = "insert into redcap_project_dashboards_access_dags values ($dash_id, '".db_escape($this_group_id)."')";
			if (!db_query($sql)) $errors++;
		}

        // If there are errors, then roll back all changes
		if ($errors > 0) {
			// Errors occurred, so undo any changes made
			db_query("ROLLBACK");
			// Return '0' for error
			exit('0');
		} else {
			// Logging
            if ($dash_id != 0) {
				$log_descrip = "Edit project dashboard";
			} else {
				$log_descrip = "Create project dashboard";
			}
			Logging::logEvent($sqlr, "redcap_project_dashboards", "MANAGE", $dash_id, "dash_id = $dash_id", $log_descrip . " - \"".$this->getDashboardName($dash_id)."\"");
			// Since we've modified the dashboard, also clear the dashboard cache
			if ($dash_id != 0) {
				$this->resetCache($dash_id, false);
			}
			// Commit changes
			db_query("COMMIT");
			// Response
			$dialog_title = 	RCView::img(array('src'=>'tick.png', 'style'=>'vertical-align:middle')) .
				RCView::span(array('style'=>'color:green;vertical-align:middle'), $lang['dash_19']);
			$dialog_content = 	RCView::div(array('style'=>'font-size:14px;'),
				$lang['dash_18'] . " \"" .
				RCView::span(array('style'=>'font-weight:bold;'), RCView::escape($title)) .
				"\" " . $lang['report_builder_74']
			);
			// Output JSON response
            header("Content-Type: application/json");
			print json_encode_rc(array('dash_id'=>$dash_id, 'newdash'=>($_GET['dash_id'] == 0 ? 1 : 0),
				'title'=>$dialog_title, 'content'=>$dialog_content));
		}
	}

	// Ensure all project dashboards have a hash
	public function checkDashHash($dash_id=null)
	{
	    $sql = "select dash_id from redcap_project_dashboards
                where project_id = ".PROJECT_ID." and hash is null";
		if (isinteger($dash_id) && $dash_id > 0) {
		    $sql .= " and dash_id = $dash_id";
        }
		$q = db_query($sql);
		$dash_ids = [];
		while ($row = db_fetch_assoc($q)) {
			$dash_ids[] = $row['dash_id'];
		}
		// Loop through each dashboard
		foreach ($dash_ids as $dash_id) {
			// Attempt to save it to dashboards table
			$success = false;
			while (!$success) {
				// Generate new unique name (start with 3 digit number followed by 7 alphanumeric chars) - do not allow zeros
				$unique_name = generateRandomHash(11, false, true);
				// Update the table
				$sql = "update redcap_project_dashboards set hash = '".db_escape($unique_name)."' where dash_id = $dash_id";
				$success = db_query($sql);
			}
		}
	}

	// Checks for errors in the dashboard order of all dashboards (in case their numbering gets off)
	public function checkDashOrder()
	{
		// Do a quick compare of the field_order by using Arithmetic Series (not 100% reliable, but highly reliable and quick)
		// and make sure it begins with 1 and ends with field order equal to the total field count.
		$sql = "select sum(dash_order) as actual, round(count(1)*(count(1)+1)/2) as ideal,
				min(dash_order) as min, max(dash_order) as max, count(1) as dash_count
				from redcap_project_dashboards where project_id = " . PROJECT_ID;
		$q = db_query($sql);
		$row = db_fetch_assoc($q);
		db_free_result($q);
		if ( ($row['actual'] != $row['ideal']) || ($row['min'] != '1') || ($row['max'] != $row['dash_count']) )
		{
			return $this->fixDashOrder();
		}
	}

	// Fixes the dashboard order of all dashboards (if somehow their numbering gets off)
	public function fixDashOrder()
	{
		// Set all dash_orders to null
		$sql = "select @n := 0";
		db_query($sql);
		// Reset field_order of all fields, beginning with "1"
		$sql = "update redcap_project_dashboards
				set dash_order = @n := @n + 1 where project_id = ".PROJECT_ID."
				order by dash_order, dash_id";
		if (!db_query($sql))
		{
		    // If unique key prevented easy fix, then do manually via looping
			$sql = "select dash_id from redcap_project_dashboards
                    where project_id = ".PROJECT_ID."
                    order by dash_order, dash_id";
			$q = db_query($sql);
			$dash_order = 1;
			$dash_orders = array();
			while ($row = db_fetch_assoc($q)) {
				$dash_orders[$row['dash_id']] = $dash_order++;
			}
			// Reset all orders to null
			$sql = "update redcap_project_dashboards set dash_order = null where project_id = ".PROJECT_ID;
			db_query($sql);
			foreach ($dash_orders as $dash_id=>$dash_order) {
			    // Set order of each individually
				$sql = "update redcap_project_dashboards
                        set dash_order = $dash_order 
                        where dash_id = $dash_id";
				db_query($sql);
			}
		}
		// Return boolean on success
		return true;
	}

	// Delete a report
	public function deleteDash($dash_id)
	{
	    $title = $this->getDashboardName($dash_id);
		// Delete report
		$sql = "delete from redcap_project_dashboards where project_id = ".PROJECT_ID." and dash_id = $dash_id";
		$q = db_query($sql);
		if (!$q) return false;
		// Fix ordering of reports (if needed) now that this report has been removed
		$this->checkDashOrder();
		// Logging
		Logging::logEvent($sql, "redcap_project_dashboards", "MANAGE", $dash_id, "dash_id = $dash_id", "Delete project dashboard" . " - \"$title\"");
		// Return success
		return true;
	}

	// Obtain a dashboard's title/name
	public function getDashboardName($dash_id)
	{
		// Delete report
		$sql = "select title from redcap_project_dashboards where project_id = ".PROJECT_ID." and dash_id = $dash_id";
		$q = db_query($sql);
		$title = strip_tags(label_decode(db_result($q, 0)));
		return $title;
	}

	// Copy the report and return the new dash_id
	public function copyDash($dash_id)
	{
		// Set up all actions as a transaction to ensure everything is done here
		db_query("SET AUTOCOMMIT=0");
		db_query("BEGIN");
		$errors = 0;
		// List of all db tables relating to reports, excluding redcap_project_dashboards
		$tables = array('redcap_project_dashboards_access_dags', 'redcap_project_dashboards_access_roles', 'redcap_project_dashboards_access_users');
		// First, add row to redcap_project_dashboards and get new report id
		$table = getTableColumns('redcap_project_dashboards');
		// Get report attributes
		$dash = $this->getDashboards(PROJECT_ID, $dash_id);
		// Remove dash_id from arrays to prevent query issues
		unset($dash['dash_id'], $table['dash_id'], $dash['hash'], $table['hash'], $dash['short_url'], $table['short_url']);
		// Append "(copy)" to title to differeniate it from original
		$dash['title'] .= " (copy)";
		// Increment the report order so we can add new report directly after original
		$dash['dash_order']++;
		// Move all report orders up one to make room for new one
		$sql = "update redcap_project_dashboards set dash_order = dash_order + 1 where project_id = ".PROJECT_ID."
				and dash_order >= ".$dash['dash_order']." order by dash_order desc";
		if (!db_query($sql)) $errors++;
		// Loop through report attributes and add to $table to input into query
		foreach ($dash as $key=>$val) {
			if (!array_key_exists($key, $table)) continue;
			$table[$key] = $val;
		}
		// If users must request that dashboards be public, and the current user is not an admin, then set the dashboard as not public
        if (!UserRights::isSuperUserNotImpersonator() && $GLOBALS['project_dashboard_allow_public'] != '1') {
			$table['is_public'] = '0';
		}
		// Insert into dashboards table
		$sqlr = "insert into redcap_project_dashboards (".implode(', ', array_keys($table)).") values (".prep_implode($table, true, true).")";
		$q = db_query($sqlr);
		if (!$q) return false;
		$new_dash_id = db_insert_id();
		// Now loop through all other report tables and add
		foreach ($tables as $table_name) {
			// Get columns/defaults for table
			$table = getTableColumns($table_name);
			// Remove dash_id from $table_cols since we're manually adding it to the query
			unset($table['dash_id']);
			// Convert columns to comma-delimited string to input into query
			$table_cols = implode(', ', array_keys($table));
			// Insert into table
			$sql = "insert into $table_name select $new_dash_id, $table_cols from $table_name where dash_id = $dash_id";
			if (!db_query($sql)) $errors++;
		}
		// If errors, do not commit
		$commit = ($errors > 0) ? "ROLLBACK" : "COMMIT";
		db_query($commit);
		// Set back to initial value
		db_query("SET AUTOCOMMIT=1");
		if ($errors == 0) {
			// Just in case, make sure that all report orders are correct
			$this->checkDashOrder();
			// Logging
			Logging::logEvent($sqlr, "redcap_project_dashboards", "MANAGE", $new_dash_id, "dash_id = $new_dash_id\nCopied from dash_id = $dash_id", "Copy project dashboard" . " - \"".$this->getDashboardName($dash_id)."\"");
		}
		// Return dash_id of new report, else FALSE if errors occurred
		return ($errors == 0) ? $new_dash_id : false;
	}

	public function reorderDashboards()
    {
        extract($GLOBALS);

        // Validate ids
		if (!isset($_POST['dash_ids'])) exit('0');

        // Remove comma on end
		if (substr($_POST['dash_ids'], -1) == ',') $_POST['dash_ids'] = substr($_POST['dash_ids'], 0, -1);

        // Create array of dash_ids
		$new_dash_ids = explode(",", $_POST['dash_ids']);

        // Get existing list of reports to validate and compare number of items
		$old_dash_ids = array();
		foreach($this->getDashboardNames() as $attr) {
			$old_dash_ids[] = $attr['dash_id'];
		}

        // Determine if any new dash_ids were maliciously added
		$extra_dash_ids = array_diff($new_dash_ids, $old_dash_ids);
		if (!empty($extra_dash_ids)) exit('0');

        // Determine if any new reports were added by another user simultaneously and are not in this list
		$append_dash_ids = array_diff($old_dash_ids, $new_dash_ids);

        // Set up all actions as a transaction to ensure everything is done here
		db_query("SET AUTOCOMMIT=0");
		db_query("BEGIN");
		$errors = 0;
        // Set all dash_orders to null
		$sql = "update redcap_project_dashboards set dash_order = null where project_id = $project_id";
		if (!db_query($sql)) $errors++;
        // Loop through dash_ids and set new dash_order
		$dash_order = 1;
		foreach ($new_dash_ids as $this_dash_id) {
			$sql = "update redcap_project_dashboards set dash_order = ".$dash_order++."
			where project_id = $project_id and dash_id = $this_dash_id";
			if (!db_query($sql)) $errors++;
		}
        // Deal with orphaned dash_ids added simultaneously by other user while this user reorders
		foreach ($append_dash_ids as $this_dash_id) {
			$sql = "update redcap_project_dashboards set dash_order = ".$dash_order++."
			where project_id = $project_id and dash_id = $this_dash_id";
			if (!db_query($sql)) $errors++;
		}
        // If errors, do not commit
		$commit = ($errors > 0) ? "ROLLBACK" : "COMMIT";
		db_query($commit);
		if ($errors > 0) exit('0');
        // Set back to initial value
		db_query("SET AUTOCOMMIT=1");

        // Logging
		Logging::logEvent("", "redcap_projects", "MANAGE", $project_id, "dash_id = ".$_POST['dash_ids'], "Reorder project dashboards");

        // Return Value: If there are some extra reports that exist that are not currently in the list, then refresh the user's page
		print (!empty($append_dash_ids)) ? '2' : '1';
    }

	public function loadDashJS()
    {
        global $lang;
		// JavaScript
		loadJS('Libraries/jquery_tablednd.js');
		loadJS('Libraries/clipboard.js');
		loadJS('ProjectDashboards.js');
		?>
        <script type="text/javascript">
            var langNoTitle = '<?php print js_escape($lang['dash_15']) ?>';
            var langNoBody = '<?php print js_escape($lang['dash_17']) ?>';
            var langNoUserAccessSelected = '<?php print js_escape($lang['dash_16']) ?>';
            var langBtn1 = '<?php print js_escape($lang['dash_20']) ?>';
            var langBtn2 = '<?php print js_escape($lang['dash_21']) ?>';
            var langBtn3 = '<?php print js_escape($lang['dash_22']) ?>';
            var langCopy = '<?php print js_escape($lang['report_builder_46']) ?>';
            var langCopyReport = '<?php print js_escape($lang['dash_23']) ?>';
            var langCopyDashboardConfirm = '<?php print js_escape($lang['dash_24']) ?>';
            var langQuestionMark = '<?php print js_escape($lang['questionmark']) ?>';
            var closeBtnTxt = '<?php print js_escape($lang['global_53']) ?>';
            var langDelete = '<?php print js_escape($lang['global_19']) ?>';
            var langDeleteReport = '<?php print js_escape($lang['dash_25']) ?>';
            var langDeleteDashboardConfirm = '<?php print js_escape($lang['dash_26']) ?>';
            var langDragReport = '<?php print js_escape($lang['dash_27']) ?>';
            var langCreateCustomLink = '<?php print js_escape($lang['dash_46']) ?>';
        </script>
		<?php
	}

    /**
     * Return html to render the TITLE of left-hand menu panel for dashboards
     *
     * @return array
     */
	public function outputDashboardPanelTitle()
	{
		global $user_rights;
		$dashList = '';
		//Build menu item for each separate report
		$menu_id = 'projMenuDashboards';
		$dashListCollapsed = UIState::getMenuCollapseState(PROJECT_ID, $menu_id);
		$imgCollapsed = $dashListCollapsed ? "toggle-expand.png" : "toggle-collapse.png";
		$dashList .= "<div style='float:left;'>".RCView::tt('global_182')."</div>
                        <div class='opacity65 projMenuToggle' id='$menu_id'>"
                            . RCView::a(array('href'=>'javascript:;'),
                                RCView::img(array('src'=>$imgCollapsed))
                            ) . "
                      </div>";
        if (isset($user_rights['design']) && $user_rights['design']) {
            $dashList .= "<div class='opacity65' id='menuLnkEditDashboards' style='float:right;margin-right:1px;'>"
                . RCView::i(array('class' => 'fas fa-pencil-alt fs10', 'style' => 'color:#000066;margin-right:1px;'), '')
                . RCView::a(array('href' => APP_PATH_WEBROOT . "index.php?pid=" . PROJECT_ID . "&route=ProjectDashController:index", 'style' => 'font-size:11px;text-decoration:underline;color:#000066;font-weight:normal;'), RCView::tt('global_27'))
                . "</div>";
            // Dashboard Folders button
            $dashList .= "<div class='opacity65' id='menuLnkProjectDashboardFolders' style='float:right;margin-right:6px;'>"
                . RCView::i(array('class' => 'fas fa-folder-open fs10', 'style' => 'color:#014101;margin-right:1px;'), '')
                . RCView::a(array('onclick' => "openDashboardFolders();", 'href' => 'javascript:;', 'style' => 'font-size:11px;text-decoration:underline;color:#014101;font-weight:normal;'), RCView::tt('control_center_4516'))
                . "</div>";
        }
            // Search
        $dashList .= "<div id='searchDashboardDiv' style='float:right;margin-right:2px;display:none;'>"
                . RCView::text(array('id'=>'searchDashboards', 'class'=>'x-form-text x-form-field', 'style'=>'padding:1px 5px;width: 120px;', 'placeholder'=>RCView::tt_js2('dash_132')))
                . RCView::a(array('onclick'=>"closeSearchDashboards();", 'href'=>'javascript:;', 'style'=>'margin-right:3px;text-decoration:underline;font-weight:normal;'),
                    RCView::i(array('class'=>'fas fa-times', 'style'=>'font-size:13px;top:2px;margin-left:3px;'), '')
                )
                . "</div>";
        $dashList .= "<div class='opacity65' id='menuLnkSearchDashboards' style='float:right;margin-right:6px;'>"
                . RCView::a(array('onclick'=>"openSearchDashboards();", 'href'=>'javascript:;', 'style'=>'font-size:11px;text-decoration:underline;color:#000066;font-weight:normal;'),
                    RCView::i(array('class'=>'fas fa-search fs10', 'style'=>'margin-right:1px;'), '') .
                    RCView::tt('control_center_439')
                )
                . "</div>";
        // Setup dialog
        $dashList .= RCView::div(array('id'=>'dashboard_folders_popup', 'class'=>'simpleDialog', 'title'=>"<div style='color:#008000;'><span class='fas fa-folder-open' style='margin-right:4px;'></span> ".RCView::tt('dash_133')."</div>"), '');
            // Return values
		return array($dashList, $dashListCollapsed);
	}

	// Return html to render the left-hand menu panel for dashboards
	public function outputDashboardPanel()
	{
		global $lang;
		$dashsList = '';
		$dashsMenuList = $this->getDashboardNames(null, !UserRights::isSuperUserNotImpersonator(), true, true);
		if (!empty($dashsMenuList)) {
			$dashsList .= "<div class='menubox'>";
			$i = 1;
            $folder = null;
			foreach ($dashsMenuList as $attr) {
				$this_dash_id = $attr['dash_id'];
				$dashLink = APP_PATH_WEBROOT . "index.php?route=ProjectDashController:view&dash_id=$this_dash_id&pid=".PROJECT_ID;

                $attr['collapsed'] = ($attr['folder_id'] != '' && UIState::getUIStateValue(PROJECT_ID, 'dashboard_folders', $attr['folder_id']) == '1') ? '1' : '0';
                // Dashboard Folders
                if ($folder != $attr['folder_id']) {
                    $faClass = $attr['collapsed'] ? "fa-plus-square" : "fa-minus-square";
                    $dashsList .= "<div onclick='updateDashboardPanel({$attr['folder_id']},{$attr['collapsed']});' class='hangf'><i class='far $faClass' style='text-indent:0;margin-right:4px;'></i>".RCView::escape($attr['folder'])."</div>";
                    $i = 1;
                }
                $num = "<span class='reportnum'>".$i++.")</span>";
                if (!$attr['collapsed']) {
                    if ($attr['folder'] != "") {
                        $margin = " style='margin-left:20px;'";
                    } else {
                        $margin = "";
                    }
                    $this_report_name = $attr['title'];
                    $this_public_link = $attr['is_public'] ? RCView::a(['href'=>$attr['public_url'], 'target'=>'_blank', 'title'=>$lang['dash_52'], 'class'=>'fs12 ms-1 align-middle'], RCView::fa("fas fa-link text-primaryrc")) : "";
                    $dashsList .= "<div class='hangr'$margin>$num <a href='$dashLink'>".RCView::escape($this_report_name)."$this_public_link</a></div>";
                }
                // Set for next loop
                $folder = $attr['folder_id'];

			}
			$dashsList .= "</div>";
		}
		return $dashsList;
	}

    /**
     * Get all dashboards assigned to folder
     *
     * @param int $folder_id
     * @return array
     */
    public function getDashboardsAssignedToFolder($folder_id)
    {
        $sql = "SELECT pd.dash_id, pd.title 
				FROM redcap_project_dashboards_folders_items i, redcap_project_dashboards pd
				WHERE i.folder_id = '".db_escape($folder_id)."' AND pd.dash_id = i.dash_id
				ORDER BY pd.dash_order";
        $q = db_query($sql);
        $dashboards = array();
        while ($row = db_fetch_assoc($q)) {
            $dashboards[$row['dash_id']] = strip_tags(label_decode($row['title']));
        }
        return $dashboards;
    }

    /**
     * Obtain array of all dashboards assigned to a ANOTHER Dashboard Folder (i.e. a folder other than the one provided)
     *
     * @param int $folder_id
     * @return array
     */
    public function getDashboardsAssignedToOtherFolder($folder_id)
    {
        $sql = "select pd.dash_id, pd.title
				from redcap_project_dashboards_folders_items i, redcap_project_dashboards pd
				where i.folder_id != '".db_escape($folder_id)."' and pd.dash_id = i.dash_id
				order by pd.dash_order";
        $q = db_query($sql);
        $dashboards = array();
        while ($row = db_fetch_assoc($q)) {
            $dashboards[$row['dash_id']] = strip_tags(label_decode($row['title']));
        }
        return $dashboards;
    }

    /**
     * Assign Dashboard to a folder
     *
     * @return boolean
     */
    public function dashboardFolderAssign()
    {
        $folder_id = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : 0;
        if (empty($folder_id)) exit;
        // Check single
        if (!isset($_POST['checkAll'])) {
            $dash_id = isset($_POST['dash_id']) ? (int)$_POST['dash_id'] : 0;
            if (empty($dash_id)) exit;
            if ($_POST['checked'] == '1') {
                $sql = "replace into redcap_project_dashboards_folders_items (folder_id, dash_id) values
						('".db_escape($folder_id)."', '".db_escape($dash_id)."')";
            } else {
                $sql = "delete from redcap_project_dashboards_folders_items
						where folder_id = '".db_escape($folder_id)."' and dash_id = '".db_escape($dash_id)."'";
            }
            if (db_query($sql)) {
                // Logging
                Logging::logEvent($sql, "redcap_project_dashboards_folders_items", "MANAGE", $folder_id, "folder_id = ".$folder_id, "Assign/unassign Project dashboard(s) to dashboard folder");
                return '1';
            }
            return '0';
        }
        // Check all
        else {
            $ids = explode(',', $_POST['ids']);
            if (count($ids) > 0)
            {
                $checkAll = (isset($_POST['checkAll']) && $_POST['checkAll'] == 'true');
                // Add all to table
                if ($checkAll) {
                    foreach ($ids as $dash_id) {
                        $dash_id = (int)$dash_id;
                        if (!is_numeric($dash_id) || empty($dash_id)) continue;
                        $sql = "replace into redcap_project_dashboards_folders_items (folder_id, dash_id) values
								('".db_escape($folder_id)."', '".db_escape($dash_id)."')";
                        if (!db_query($sql)) exit('0');
                    }
                } else {
                    // Remove all from table
                    $sql = "delete from redcap_project_dashboards_folders_items
							where folder_id = '".db_escape($folder_id)."' and dash_id in (".prep_implode($ids).")";
                    if (!db_query($sql)) exit('0');
                }
            }
            // Logging
            Logging::logEvent($sql, "redcap_project_dashboards_folders_items", "MANAGE", $folder_id, "folder_id = ".$folder_id, "Assign/unassign Project dashboard(s) to dashboard folder");
            return '1';
        }
    }
}