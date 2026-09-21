<?php
class DBQueryTool
{
    /**
     * Assign Queries to a folder
     *
     * @return boolean
     */
    public static function queryFolderAssign()
    {
        $folder_id = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : 0;
        if (empty($folder_id)) exit;
        // Check single
        if (!isset($_POST['checkAll'])) {
            $q_id = isset($_POST['q_id']) ? (int)$_POST['q_id'] : 0;
            if (empty($q_id)) exit;
            if ($_POST['checked'] == '1') {
                $sql = "REPLACE INTO redcap_custom_queries_folders_items (folder_id, qid) VALUES
						('".db_escape($folder_id)."', '".db_escape($q_id)."')";
            } else {
                $sql = "DELETE FROM redcap_custom_queries_folders_items
						WHERE folder_id = '".db_escape($folder_id)."' AND qid = '".db_escape($q_id)."'";
            }
            if (db_query($sql)) {
                // Logging
                Logging::logEvent($sql, "redcap_custom_queries_folders_items", "MANAGE", $folder_id, "folder_id = ".$folder_id, "Assign/unassign Custom Query(s) to query folder");
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
                    foreach ($ids as $q_id) {
                        $q_id = (int)$q_id;
                        if (!is_numeric($q_id) || empty($q_id)) continue;
                        $sql = "REPLACE INTO redcap_custom_queries_folders_items (folder_id, qid) VALUES
								('".db_escape($folder_id)."', '".db_escape($q_id)."')";
                        if (!db_query($sql)) exit('0');
                    }
                } else {
                    // Remove all from table
                    $sql = "DELETE FROM redcap_custom_queries_folders_items
							WHERE folder_id = '".db_escape($folder_id)."' AND qid IN (".prep_implode($ids).")";
                    if (!db_query($sql)) exit('0');
                }
            }
            // Logging
            Logging::logEvent($sql, "redcap_custom_queries_folders_items", "MANAGE", $folder_id, "folder_id = ".$folder_id, "Assign/unassign Custom Query(s) to query folder");
            return '1';
        }
    }
    /**
     * Obtain array of all queries assigned to a ANOTHER Query Folder (i.e. a folder other than the one provided)
     *
     * @param int $folder_id
     * @return array
     */
    public static function getQueriesAssignedToOtherFolder($folder_id)
    {
        $sql = "select q.qid, q.title
				from redcap_custom_queries_folders_items i, redcap_custom_queries q
				where i.folder_id != '".db_escape($folder_id)."' and q.qid = i.qid
				order by q.qid";
        $q = db_query($sql);
        $queries = array();
        while ($row = db_fetch_assoc($q)) {
            $queries[$row['qid']] = strip_tags(label_decode($row['title']));
        }
        return $queries;
    }

    /**
     * Return all custom queries (unless one is specified explicitly) as an array of their attributes
     *
     * @param integer $q_id
     * @return array
     */
    public static function getCustomQueries($q_id=null)
    {
        // Array to place queries attributes
        $queries = array();
        // If qid is 0 (query doesn't exist), then return field defaults from tables
        if ($q_id === 0) {
            // Add to reports array
            $queries[$q_id] = getTableColumns('redcap_custom_queries');
            // Return array
            return $queries[$q_id];
        }

        // Get main attributes
        $sql = "SELECT * FROM redcap_custom_queries";
        if (is_numeric($q_id)) $sql .= " AND qid = $q_id";
        $sql .= " order by qid";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Add to reports array
            $queries[$row['qid']] = $row;
        }
        // If no queries, then return empty array
        if (empty($queries)) return array();

        // Return array of query(s) attributes
        if ($q_id == null) {
            return $queries;
        } else {
            return $queries[$q_id] ?? [];
        }
    }

    /**
     * Get all custom queries assigned to folder
     *
     * @param int $folder_id
     * @return array
     */
    public static function getQueriesAssignedToFolder($folder_id)
    {
        $sql = "SELECT q.qid, q.title 
				FROM redcap_custom_queries_folders_items i, redcap_custom_queries q
				WHERE i.folder_id = '".db_escape($folder_id)."' AND q.qid = i.qid
				ORDER BY q.qid";
        $q = db_query($sql);
        $queries = array();
        while ($row = db_fetch_assoc($q)) {
            $queries[$row['qid']] = strip_tags(label_decode($row['title']));
        }
        return $queries;
    }

    /**
     * Resort query folders via drag and drop
     *
     * @param string $data
     * @return void
     */
    public static function queryFolderResort($data)
    {
        $ids = explode(",", str_replace('&', ',', str_replace('rf[]=', '', $data)));
        foreach ($ids as $key=>$id) {
            if (!is_numeric($id)) unset($ids[$key]);
        }
        $sql = "
		  SELECT folder_id
		  FROM redcap_custom_queries_folders
		  ORDER BY FIELD(folder_id, ".prep_implode($ids).")
		";
        $q = db_query($sql);
        if ($q !== false)
        {
            $sql = "UPDATE redcap_custom_queries_folders
					SET position = NULL
					WHERE folder_id in (".prep_implode($ids).")";
            db_query($sql);

            $position = 1;
            while($row = db_fetch_assoc($q))
            {
                $sql = "
				  UPDATE redcap_custom_queries_folders
				  SET position = $position
				  WHERE folder_id = {$row['folder_id']}
				";
                db_query($sql);
                $position++;
            }
            // Logging
            Logging::logEvent("", "redcap_custom_queries_folders", "MANAGE", '', "", "Re-sort query folders");
            return '1';
        }
        return '0';
    }

    /**
     * Edit Query Folder
     *
     * @return string
     */
    public static function queryFolderEdit()
    {
        if (!is_numeric($_POST['folder_id']) || !isset($_POST['folder_name']) || trim($_POST['folder_name']) == '') exit('0');
        $sql = "UPDATE redcap_custom_queries_folders
				SET name = '".db_escape($_POST['folder_name'])."'
				WHERE folder_id = '".db_escape($_POST['folder_id'])."'";
        if (db_query($sql)) {
            // Logging
            Logging::logEvent($sql, "redcap_custom_queries_folders", "MANAGE", $_POST['folder_id'], "folder_id = ".$_POST['folder_id'], "Edit query folder name");
            return '1';
        }
        return '0';
    }

    /**
     * Delete Query Folder
     *
     * @return string
     */
    public static function queryFolderDelete()
    {
        if (!isset($_POST['folder_id']) || !is_numeric($_POST['folder_id'])) exit('0');
        $sql = "DELETE FROM redcap_custom_queries_folders
			WHERE folder_id = '".db_escape($_POST['folder_id'])."'";
        if (db_query($sql)) {
            // Logging
            Logging::logEvent($sql, "redcap_custom_queries_folders", "MANAGE", $_POST['folder_id'], "folder_id = ".$_POST['folder_id'], "Delete query folder");
            return '1';
        }
        return '0';
    }

    /**
     * Create Query Folder
     *
     * @return boolean
     */
    public static function queryFolderCreate()
    {
        if (!isset($_POST['folder_name']) || trim($_POST['folder_name']) == '') exit('0');
        $sql = "SELECT MAX(position) FROM redcap_custom_queries_folders";
        $q = db_query($sql);
        $position = db_result($q, 0);
        if ($position == null) {
            $position = 1;
        } else {
            $position++;
        }
        $sql = "insert into  redcap_custom_queries_folders (name, position) values
				('".db_escape($_POST['folder_name'])."', $position)";
        if (db_query($sql)) {
            $folder_id = db_insert_id();
            // Logging
            Logging::logEvent($sql, "redcap_custom_queries_folders", "MANAGE", $folder_id, "folder_id = ".$folder_id, "Create query folder");
            return '1';
        }
        return '0';
    }

    /**
     * Display left panel (showing folder + custom queries)
     *
     * @param array $customQueries
     * @return string
     */
    public static function outputCustomQueriesPanel($customQueries) {
        $output = '';
        if (!empty($customQueries)) {
            $output .= '<ol style="padding-inline-start:13px;">';
            $folder = null;
            foreach ($customQueries as $key => $cattr) {
                $cattr['collapsed'] = ($cattr['folder_id'] != '' && UIState::getUIStateValue('controlcenter', 'query_folders', $cattr['folder_id']) == '1') ? '1' : '0';
                // Dashboard Folders
                if ($folder != $cattr['folder_id']) {
                    $faClass = $cattr['collapsed'] ? "fa-plus-square" : "fa-minus-square";
                    $output .= "<div onclick='updateQueryPanel(" . $cattr['folder_id'] . "," . $cattr['collapsed'] . ");' class='hangf text-dangerrc' style='margin-left:2px;'><i class='far " . $faClass . "' style='text-indent:0;margin-right:4px;margin-top:3px;'></i>" . RCView::escape($cattr['folder']) . "</div>";
                    $i = 1;
                }
                if (!$cattr['collapsed']) {
                    if ($cattr['folder'] != "") {
                        $margin = " margin-left:20px;";
                    } else {
                        $margin = "";
                    }
                    $output .= '<li style="line-height:12px;margin:3px 0;font-size:11px;' . $margin . '">
                                    <a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick="loadCustomQuery(' . $cattr['order_num'] . ');">' . htmlspecialchars($cattr['title'], ENT_QUOTES, 'UTF-8') . '</a>
                                </li>';
                }
                // Set for next loop
                $folder = $cattr['folder_id'];
            }
            $output .= '</ol>';
        }
        return $output;
    }

    /**
     * Obtain array of all Custom Query Folders
     *
     * @return array
     */
    public static function getCustomQueryFolders()
    {
        $sql = "SELECT folder_id, name FROM redcap_custom_queries_folders ORDER BY position";
        $q = db_query($sql);
        $folders = array();
        while ($row = db_fetch_assoc($q)) {
            $folders[$row['folder_id']] = $row['name'];
        }
        return $folders;
    }

    /**
     * Get CSV contents for download CSV
     *
     * @return array
     */
    public static function csvDownload(){
        $sql = "SELECT * FROM redcap_custom_queries ORDER BY qid";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q))
        {
            $cols['query_title'] = $row['title'];
            $cols['sql_query'] = $row['query'];
            $cols['query_id'] = $row['qid'];
            $result[$row['qid']] = $cols;
        }

        $content = (!empty($result)) ? arrayToCsv($result) : 'query_title,sql_query,query_id';
        // Log this event
        Logging::logEvent($sql, "redcap_custom_queries", "MANAGE", "", "", "Download the Custom Queries List");

        return $content;
    }

    /**
     * Upload CSV contents
     *
     * @return array
     */
    public static function csvUpload() {
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

            $allQueries = self::getCustomQueries();
            $allIds = $storedQueries = [];
            foreach ($allQueries as $q) {
                $storedQueries[$q['qid']] = $q;
                $allIds[] = $q['qid'];
            }
            list ($count, $errors) = self::uploadCustomQueries($data, $allIds);
            // Build preview of changes being made
            if (!$commit && empty($errors))
            {
                $cells = "";
                foreach (array_keys($data[0]) as $this_hdr) {
                    $cells .= RCView::td(array('style'=>'padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'), $this_hdr);
                }
                $rows = RCView::tr(array(), $cells);

                foreach($data as $qdata)
                {
                    $title = trim($qdata['query_title']);
                    $query = trim($qdata['sql_query']);
                    $query_id = trim($qdata['query_id']);

                    // Check for changes
                    $old_title = $old_query = '';
                    $col1class = $col2class = $col3class = '';
                    // Assume that if $title set means that exists as its already handled while validation
                    if ($query_id != '' && in_array($query_id, $allIds)) {
                        $col1class = ($storedQueries[$query_id]['title'] != $title) ? 'yellow' : 'gray';
                        $old_title = ($col1class == 'gray') ? "" :
                            RCView::div(array('style'=>'color:#777;font-size:11px;'), "({$storedQueries[$query_id]['title']})");

                        $col2class = ($storedQueries[$query_id]['query'] != $query) ? 'yellow' : 'gray';
                        $old_query = ($col2class == 'gray') ? "" :
                            RCView::div(array('style'=>'color:#777;font-size:11px;'), "({$storedQueries[$query_id]['query']})");
                        $col3class = 'gray';
                    } else {
                        // New Query record will be added
                        $col1class = $col2class = $col3class = 'green';
                    }
                    // Add row
                    $rows .= RCView::tr(array(),
                        RCView::td(array('class'=>$col1class),
                            $title . $old_title
                        ) .
                        RCView::td(array('class'=>$col2class),
                            $query . $old_query
                        ) .
                        RCView::td(array('class'=>$col3class),
                            $query_id
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
                Logging::logEvent("", "redcap_custom_queries", "MANAGE", '', "", "Import custom queries");
            } else {
                // ERROR: Roll back all changes made and return the error message
                db_query("ROLLBACK");
                db_query("SET AUTOCOMMIT=1");
            }

            $_SESSION['imported'] = 'custom_queries';
            $_SESSION['count'] = $count;
            $_SESSION['errors'] = $errors;
            $_SESSION['csv_content'] = $csv_content;
            $_SESSION['preview'] = $preview;
        }

        $queryString = '';
        if (isset($_GET['q']) && $_GET['q'] != '') {
            $queryString = "?q=".$_GET['q'];
        }
        redirect(APP_PATH_WEBROOT . 'ControlCenter/database_query_tool.php'.$queryString);
    }

    /**
     * Upload Custom Queries
     * @param array $data
     * @param array $allIds
     *
     * @return array
     */
    public static function uploadCustomQueries($data, $allIds)
    {
        global $lang;

        $count = 0;
        $errors = array();

        // Check for basic attributes needed
        if (empty($data) || !isset($data[0]['query_title']) || !isset($data[0]['sql_query'])) {
            $errors[] = $lang['design_641'] . " query_title, sql_query";
            return array($count, $errors);
        }

        foreach($data as $qdata)
        {
            $title = trim($qdata['query_title']);
            $query = trim($qdata['sql_query']);
            $query_id = $qdata['query_id'];

            if ($title == '' && $query != '') {
                $errors[] = RCView::tt('control_center_4926');
                continue;
            }
            
            $allowedQueryTypes = [
                'select',
                'show',
                'explain'
            ];
            if (!self::isQueryType($query, $allowedQueryTypes)) {
                $errors[] = RCView::tt('control_center_4825');
                continue;
            }
            
            if ($title != '' && $query == '') {
                $errors[] = RCView::tt('control_center_4927');
                continue;
            }

            array_unique($errors);
            if (empty($errors))
            {
                if ($query_id != '' && in_array($query_id, $allIds))
                {
                    self::updateQuery($title, $query, $query_id);
                    ++$count;
                    continue;
                }

                self::addQuery($title, $query);
                ++$count;
            }
        }

        // Return count and array of errors
        return array($count, $errors);
    }

    /**
     * DB Update custom queries
     * @param string $title
     * @param string $query
     * @param string $query_id
     *
     * @return boolean
     */
    public static function updateQuery($title, $query, $query_id)
    {
        $query_id = (int)$query_id;
        $title = db_escape($title);
        $query = db_escape($query);

        $sql = "
			UPDATE 
			    redcap_custom_queries
			SET	
			    title = '$title',
			    query = '$query'
			WHERE qid = $query_id
			LIMIT 1
		";
        $q = db_query($sql);
        return ($q && $q !== false);
    }

    /**
     * DB Add custom queries
     * @param string $title
     * @param string $query
     *
     * @return boolean
     */
    public static function addQuery($title, $query)
    {
        $title = db_escape($title);
        $query = db_escape($query);

        $sql = "
			INSERT INTO redcap_custom_queries (
				title, query
			) VALUES (
				'$title', '$query'
			)
		";
        $q = db_query($sql);
        return ($q && $q !== false);
    }

    /**
     * Get the query for recent errors
     */
    public static function getRecentErrorsQuery($prefix): string
    {
        global $lang;
    
        $whereClause = '';
        if($prefix){
            $prefix = db_escape($prefix);
            $whereClause = "\nwhere error like 'External Module Prefix: $prefix\\n%'";
        }

        $comment = db_escape($lang['control_center_4938'] . " (" . RCView::lang_i('control_center_4939', [Jobs::ERROR_RETENTION_DAYS], true, '') . ")");

        return "select * -- $comment\nfrom redcap_error_log $whereClause\n";
    }

    /**
     * Determines if a SQL query is one of the provided types
     * @param string $query
     * @param mixed $types
     * @throws \Exception
     * @return bool
     */
    public static function isQueryType($query, $types): bool {
        if(!is_array($types)){
            $types = [$types];
        }

        foreach($types as $type){
            if(empty($type)){
                throw new \Exception('Empty types are not allowed');
            }

            $type = preg_quote($type);
            if(preg_match("/^$type\s/i", $query)){
                return true;
            }
        }

        return false;
    }
}