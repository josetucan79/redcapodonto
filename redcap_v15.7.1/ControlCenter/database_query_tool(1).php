<?php
use PHPSQLParser\PHPSQLParser;

// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

if (!ADMIN_RIGHTS && !SUPER_USER && !ACCESS_SYSTEM_CONFIG) exit($lang['system_config_884']);


// Get order number of queries
$queryIdOrder = [];
$sql = "select qid from redcap_custom_queries order by qid";
$q = db_query($sql);
$i = 0;
while ($row = db_fetch_assoc($q)) {
    $queryIdOrder[$row['qid']] = $i++;
}

// Get saved queries
$customQueries = [];
$sql = "select q.qid, q.title, q.query, af.folder_id, af.name as folder, af.position
        from redcap_custom_queries q
        left join redcap_custom_queries_folders_items ai on ai.qid = q.qid
		left join redcap_custom_queries_folders af on af.folder_id = ai.folder_id 
        order by af.position, q.qid";
$q = db_query($sql);
while ($row = db_fetch_assoc($q)) {
    $row['order_num'] = $queryIdOrder[$row['qid']];
    $customQueries[] = $row;
}

$baseUrl = PAGE_FULL;
$doExport = ((isset($_POST['query']) || (isset($_GET['q']) && isinteger($_GET['q']))) && !$isAjax && isset($_GET['export']));

// Custom queries being saved
if ($isAjax && isset($_POST['save_custom_queries']))
{
    $qids = [];
    foreach ($customQueries as $query) {
        $qids[] = $query['qid'];
    }
    $customQueries = [];
    foreach ($_POST as $key=>$val) {
        if (strpos($key, "qid_") === 0) {
            list ($nothing, $num) = explode("_", $key, 2);
            $customQueries[$num]['id'] = trim($val);
        }
        if (strpos($key, "qtitle_") === 0) {
            list ($nothing, $num) = explode("_", $key, 2);
            $customQueries[$num]['title'] = trim($val);
        } elseif (strpos($key, "qsql_") === 0) {
            list ($nothing, $num) = explode("_", $key, 2);
            $customQueries[$num]['query'] = trim($val);
        } else {
            continue;
        }
    }
    // If only have one query submitted and it is blank, then we're erasing all custom queries
    if (count($customQueries) === 1 && $customQueries[$num]['title'] == '' && $customQueries[$num]['query'] == '') {
        $customQueries = [];
    }
    // Begin transaction
    db_query("SET AUTOCOMMIT=0");
    db_query("BEGIN");
    $errors = 0;
    // Add new rows
    $sql_all = $updatedIds = [];
    foreach ($customQueries as $attr) {
        if ($attr['id'] == '') {
            $sql_all[] = $sql = "INSERT INTO redcap_custom_queries (title, query) VALUES ('".db_escape($attr['title'])."', '".db_escape($attr['query'])."')";
            if (!db_query($sql)) {
                $errors++;
            }
        } else {
            $updatedIds[] = $attr['id'];
            $sql_all[] = $sql = "UPDATE redcap_custom_queries SET title = '".db_escape($attr['title'])."', query = '".db_escape($attr['query'])."'
                                WHERE qid = '".$attr['id']."'";
            if (!db_query($sql)) {
                $errors++;
            }
        }
    }
    $qids_to_delete = array_diff($qids, $updatedIds);
    if (!empty($qids_to_delete)) {
        $sql_all[] = $sql = "DELETE FROM redcap_custom_queries 
                                WHERE qid IN (" . prep_implode($qids_to_delete) . ")";
        if (!db_query($sql)) {
            $errors++;
        }
    }
    // Commit changes
    if ($errors > 0) {
        db_query("ROLLBACK");
        db_query("SET AUTOCOMMIT=1");
        exit("0");
    }
    // LOGGING
    Logging::logEvent(implode(";\n",$sql_all),"redcap_custom_queries","MANAGE","", json_encode_rc($customQueries),"Add/edit custom queries for the Database Query Tool");
    db_query("COMMIT");
    db_query("SET AUTOCOMMIT=1");
    exit("1");
}
// Custom queries being viewed
elseif ($isAjax && isset($_POST['view_custom_queries']))
{
    // Build the content for the "add saved query" dialog
    $dialogHtml = RCView::hidden(['name'=>'save_custom_queries', 'value'=>'1']);
    $key = 1;
    // Add placeholder for empty first row
    if (empty($customQueries)) {
        $customQueries[] = ['qid' => '', 'title'=>'', 'query'=>''];
    } else {
        // Sort by query id
        foreach ($customQueries as $q) {
            $arr[$q['qid']] = ['qid' => $q['qid'], 'title' => $q['title'], 'query' => $q['query']];
        }
        asort($arr);
        $customQueries = $arr;
    }
    // Loop through all and render
    foreach ($customQueries as $attr) {
        $dialogHtml .= RCView::div(['class'=>'query-row mt-3 pt-3 nowrap', 'style'=>'border-top:1px dashed #bbb;'],
            RCView::span(['style'=>'width:30px;', 'class'=>'align-top query-num fs14 text-dangerrc font-weight-bold'], "$key) ") .
            RCView::span(['class'=>'align-top font-weight-bold'], $lang['control_center_4821']).
            RCView::hidden(['name'=>'qid_'.$key, 'value'=>$attr['qid']]) .
            RCView::text(['name'=>'qtitle_'.$key, 'class'=>'ms-2 align-top x-form-text x-form-field fs13', 'style'=>'width:450px;max-width:450px;', 'value'=>$attr['title']]) .
            RCView::button(['class'=>'ms-3 align-top btn btn-link text-danger py-0 btn-sm fs16', 'onclick'=>"deleteRow(this);return false;", 'title'=>$lang['control_center_4823']], '<i class="fas fa-times"></i>') .
            RCView::div(['class'=>'ms-3 mt-2'],
                RCView::b($lang['control_center_4822']).RCView::SP.
                RCView::textarea(['name'=>'qsql_'.$key, 'class'=>'align-top x-form-field notesbox fs12', 'style'=>'width:450px;max-width:450px;margin-left:52px;font-family:monospace;resize:auto;line-height:1.1;padding:5px;'], $attr['query'])
            )
        );
        $key++;
    }
    $dialogHtml .= RCView::div(['class'=>'mt-3', 'id'=>'add-row-parent'],
        RCView::button(['class'=>'ms-3 btn btn-success btn-xs fs12', 'onclick'=>"addRow();return false;"], '<i class="fas fa-plus"></i> ' . $lang['control_center_4824'])
    );
    print   RCView::div(['class'=>'mb-3'], $lang['control_center_4817']) .
            RCView::form(['id'=>'saved-query-dialog-form', 'method'=>'post', 'action'=>$baseUrl], $dialogHtml);
    exit;
}

elseif ($isAjax && isset($_GET['action']) && $_GET['action'] == 'queryFoldersDialog')
{
    print DataExport::outputReportFoldersDialog('custom_query');
    exit;
}
elseif ($isAjax && isset($_GET['action']) && $_GET['action'] == 'queryFolderCreate')
{
    print DBQueryTool::queryFolderCreate();
    exit;
}
elseif ($isAjax && isset($_GET['action']) && $_GET['action'] == 'queryFolderDisplayTable')
{
    print DataExport::outputReportFoldersTable('custom_query');
    exit;
}
elseif ($isAjax && isset($_GET['action']) && $_GET['action'] == 'queryFolderDisplayDropdown')
{
    print DataExport::outputReportFoldersDropdown('custom_query');
    exit;
}
elseif ($isAjax && isset($_GET['action']) && $_GET['action'] == 'queryFolderResort')
{
    print DBQueryTool::queryFolderResort($_POST['data']);
    exit;
}
elseif ($isAjax && isset($_GET['action']) && $_GET['action'] == 'queryFolderEdit')
{
    print DBQueryTool::queryFolderEdit();
    exit;
}
elseif ($isAjax && isset($_GET['action']) && $_GET['action'] == 'queryFolderDelete')
{
    print DBQueryTool::queryFolderDelete();
    exit;
}
elseif ($isAjax && isset($_GET['action']) && $_GET['action'] == 'queryFolderDisplayTableAssign')
{
    print DataExport::outputReportFoldersTableAssign($_POST['folder_id'], $_POST['hide_assigned'], 'custom_query');
    exit;
}
elseif ($isAjax && isset($_GET['action']) && $_GET['action'] == 'queryFolderAssign')
{
    print DBQueryTool::queryFolderAssign();
    exit;
}
elseif ($isAjax && isset($_GET['action']) && $_GET['action'] == 'queryFolderAssign')
{
    print DataExport::outputReportFoldersTableAssign($_POST['folder_id'], $_POST['hide_assigned'], 'custom_query');
    exit;
}
elseif ($isAjax && isset($_GET['action']) && $_GET['action'] == 'viewpanel')
{
    // If user is collapsing a Dashboard Folder, then set in database
    if (isset($_POST['collapse']) && isset($_POST['folder_id']) && is_numeric($_POST['folder_id'])) {
        DataExport::collapseReportFolder($_POST['folder_id'], $_POST['collapse'], 'custom_query');
    }
    print DBQueryTool::outputCustomQueriesPanel($customQueries);

    exit;
}
elseif (isset($_GET['action']) && $_GET['action'] == 'download_csv')
{
    $result = DBQueryTool::csvDownload();
    $filename = "Custom_Queries_".date("Y-m-d_Hi").".csv";
    header('Pragma: anytextexeptno-cache', true);
    header("Content-type: application/csv");
    header('Content-Disposition: attachment; filename=' . $filename);

    echo $result;
    exit;
}
elseif (isset($_GET['action']) && $_GET['action'] == 'upload_csv')
{
    DBQueryTool::csvUpload();
    exit;
}
// Header
if (!$doExport) include 'header.php';
if (!$doExport) loadJS('CustomQueryFolders.js');

// Get formatted timestamp for NOW
list ($nowDate, $nowTime) = explode(" ", NOW, 2);
$nowTS = (method_exists('DateTimeRC', 'format_user_datetime')) ? DateTimeRC::format_user_datetime($nowDate, 'Y-M-D_24', null) . " $nowTime" : NOW;

$dqt_ctx_items = [
    "project-id" => [
        "type" => "number",
    ],
    "record-name" => [
        "type" => "search",
    ],
    "instrument-name" => [
        "type" => "search",
    ],
    "event-id" => [
        "type" => "number",
    ],
    "current-instance" => [
        "type" => "number",
    ],
    "user-name" => [
        "type" => "search",
    ],
];
// Get context from $_POST / $_GET
$dqt_use_context = false;
foreach ($dqt_ctx_items as $ctx_item_name => $ctx_item) {
    if (!empty($_POST["dqt_ctx_item_$ctx_item_name"])) {
        $dqt_ctx_items[$ctx_item_name]["value"] = $_POST["dqt_ctx_item_$ctx_item_name"];
        $dqt_use_context = true;
    }
    else if (!empty($_GET[$ctx_item_name])) {
        $dqt_ctx_items[$ctx_item_name]["value"] = $_GET[$ctx_item_name];
        $dqt_use_context = true;
    }
    else {
        $dqt_ctx_items[$ctx_item_name]["value"] = null;
    }
    if ($dqt_ctx_items[$ctx_item_name]["value"] !== null) {
        if ($ctx_item["type"] == "number") {
            $dqt_ctx_items[$ctx_item_name]["value"] = intval($dqt_ctx_items[$ctx_item_name]["value"]);
        }
        else {
            $dqt_ctx_items[$ctx_item_name]["value"] = prep(trim($dqt_ctx_items[$ctx_item_name]["value"]));
        }
    }
}
if (!$doExport)
{
    // Context (smart) variables
    addLangToJS([
        "control_center_4935",
        "control_center_4936",
    ]);
    loadCSS("database_query_tool.css");
    ?>
    <script type="text/javascript">
    var baseUrl = '<?=js_escape($baseUrl)?>';
    function showMore(link) {
        if(link === null){
            showProgress(1, 0)
            $(() => {
                $('.rcp').hide();
                $('.rcf').show();
                showProgress(0)
            })
        }
        else{
            const rcp = $(link).closest('.rcp')
            rcp.hide()
            rcp.parent().find('.rcf').show()
        }
    }
    function loadCustomQuery(querynum) {
        showProgress(1,1);
        window.location.href = baseUrl+'?q='+querynum;
    }
    function formatRows()
    {
        // Re-number the rows
        var i = 1;
        $('#saved-query-dialog .query-row').each(function(){
            $('.query-num', this).html(i+") ");
            $('input[type=hidden]', this).prop('name', 'qid_'+i);
            $('input[type=text]', this).prop('name', 'qtitle_'+i);
            $('textarea', this).prop('name', 'qsql_'+i);
            i++;
        });
    }
    function deleteRow(ob)
    {
        // If there's only one row, then just clear out the text boxes
        if ($('.query-row').length == 1) {
            $('.query-row :input').val('');
            $('.query-row:first :input:first').focus();
        } else {
            var thisRow = $(ob).closest('.query-row');
            thisRow.fadeOut(500);
            setTimeout(function(){
                thisRow.remove();
                formatRows();
            }, 550);
        }
    }
    function addRow()
    {
        var $div = $('.query-row:last').clone();
        $('#add-row-parent').before($div);
        $('.query-row:last :input').val('');
        formatRows();
        fitDialog($('#saved-query-dialog'));
    }
    $(function(){

        $('button').click(function(){
            setTimeout("$('#dqt-form').prop('action',window.location.href);",100);
            setTimeout("$('#dqt-form').prop('action',window.location.href);",1000);
        });

        var errors = 0;
       $('#open-saved-query-dialog').click(function(){
           $.post(app_path_webroot+page, { view_custom_queries: 1 }, function(data){
               // Display dialog to enter saved queries
               initDialog('saved-query-dialog');
               $('#saved-query-dialog').html(data);
               formatRows();
               $('#saved-query-dialog').dialog({ bgiframe: true, modal: true, width: 700, title: '<?=RCView::tt_js('control_center_4812')?>', buttons: {
                   '<?=RCView::tt_js('global_53')?>': function () {
                       $(this).dialog('close');
                   },
                   '<?=RCView::tt_js('control_center_4818')?>': function () {
                       errors = 0;
                       // Save Queries: Validate data
                       $('#saved-query-dialog input:visible, #saved-query-dialog textarea').each(function() {
                           if (errors > 0) return;
                           $(this).val($(this).val().trim());
                           var targetTag = $(this).get(0).tagName.toLowerCase();
                           // Make sure there is a value
                           if ($(this).val() == '' && $('.query-row').length > 1) {
                               simpleDialog('<?=RCView::tt_js('control_center_4820')?>');
                               errors = 1;
                           } else
                           // Make sure query begins with select or show
                           if ($(this).val() != '' && targetTag == 'textarea' && ($(this).val().toLowerCase().indexOf('select') !== 0 && $(this).val().toLowerCase().indexOf('show') !== 0 && $(this).val().toLowerCase().indexOf('explain') !== 0)) {
                               simpleDialog('<?=RCView::tt_js('control_center_4825')?>');
                               errors = 1;
                           }
                       });
                       if (errors > 0) return;
                       // AJAX to save
                       $(this).dialog('close');
                       showProgress(1);
                       $.post(app_path_webroot+page, $('#saved-query-dialog-form').serializeObject(), function(data2){
                           showProgress(0,0);
                           if (data2 == '1') {
                               simpleDialog('<div class="darkgreen"><i class="fas fa-check"></i> <?=RCView::tt_js('control_center_4819')?></div>', '<?=RCView::tt_js('global_79')?>', null, null, "showProgress(1);window.location.reload();", '<?=RCView::tt_js('design_401')?>');
                           } else {
                               alert(woops);
                               //window.location.reload();
                           }
                       });
                   } }
               });
               fitDialog($('#saved-query-dialog'));
               $('.ui-dialog-buttonpane button:eq(1)').css('font-weight','bold');
           });
       });
    });

    // Check file upload extension
    function checkFileUploadExt() {
        var fileName = trim($('#queriesFile').val());
        if (fileName.length < 1) {
            alert('<?=RCView::tt_js('design_128')?>');
            return false;
        }
        var file_ext = getfileextension(fileName.toLowerCase());
        if (file_ext != 'csv') {
            $('#filetype_mismatch_div').dialog({ bgiframe: true, modal: true, width: 530, buttons: { Close: function() { $(this).dialog('close'); } }});
            return false;
        }
        return true;
    }
    </script>

    <div style="padding-left:10px;">
    <h4 style="color:#A00000;margin:0 0 10px;"><i class="fas fa-database"></i> <?=RCView::tt('control_center_4803')?></h4>
    <p style="margin:20px 0;max-width:1000px;"><?=RCView::tt('control_center_4804')?></p>
    <?php
}

// Page must be enabled via the back-end
if ($database_query_tool_enabled != '1')
{
    print RCView::p(['class'=>'red'],
            RCView::tt('control_center_4805')
        ) .
        RCView::code([], "UPDATE redcap_config SET value = '1' WHERE field_name = 'database_query_tool_enabled';");
    include APP_PATH_DOCROOT . 'ControlCenter/footer.php';
    exit;
}

if (!SUPER_USER) {
    print RCView::p(['class'=>'red'],
            RCView::tt('control_center_4806')
        );
    include APP_PATH_DOCROOT . 'ControlCenter/footer.php';
    exit;
}

## DEFAULT SETTINGS
$query_limit = 500;
$query = "";
$display_result = "";



// Get list of tables in db
$q = db_query("show tables");
$table_list = array();
while ($row = db_fetch_array($q)) 
{
	$table_list[] = $row[0];
}

// If clicked a saved query
if ($_SERVER['REQUEST_METHOD'] != 'POST' && isset($_GET['q']) && isinteger($_GET['q'])) {
    // Loop to find the right query where order_num=$_GET['q']
    foreach ($customQueries as $attr) {
        if ($attr['order_num'] == $_GET['q']) {
            $query = trim(html_entity_decode($attr['query'], ENT_QUOTES));
            break;
        }
    }
}
// If query was submitted, then execute it
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['query']) && !$isAjax)
{
	// Sanitize query
	$query = trim(html_entity_decode($_POST['query'], ENT_QUOTES));
}
// Do select * of selected table
elseif (isset($_GET['table']) && in_array($_GET['table'], $table_list, true))
{
    $table = $_GET['table'];
    $wheres = [];
    if ($dqt_use_context) {
        /**
         * This is a map of table fields that can be added to the where clause of a query
         * when the appropriate context for a matching smart variable is available.
         * Lookup queries are used to get the value for some fields.
         */
        $tablefield_smartvar_map = [
            // Project ID
            "project_id" => [
                "requires" => ["project-id"],
                "smart" => "[project-id]",
            ],
            // Record ID
            "record" => [
                "requires" => ["record-name"],
                "smart" => "[record-name]",
            ],
            "pk" => [
                "requires" => ["record-name"],
                "smart" => "[record-name]",
            ],
            // Form name
            "form_name" => [
                "requires" => ["instrument-name"],
                "smart" => "[instrument-name]",
            ],
            "instrument" => [
                "requires" => ["instrument-name"],
                "smart" => "[instrument-name]",
            ],
            "repeat_instrument" => [
                "requires" => ["instrument-name"],
                "smart" => "[instrument-name]",
            ],
            "instrument_id" => [ // redcap_instrument_zip
                "requires" => ["instrument-name"],
                "smart" => "[instrument-name]",
            ],
            // Event ID
            "event_id" => [
                "requires" => ["event-id"],
                "smart" => "[event-id]",
            ],
            "form_name_event" => [
                "requires" => ["event-id"],
                "smart" => "[event-id]",
            ],
            // Instance
            "instance" => [
                "requires" => ["current-instance"],
                "smart" => "[current-instance]",
            ],
            // Survey ID
            "survey_id" => [
                "requires" => ["project-id", "instrument-name"],
                "smart" => "[survey-id]",
            ],
            // User 
            "user" => [
                "requires" => ["user-name"],
                "smart" => "[user-name]",
            ],
            "username" => [
                "requires" => ["user-name"],
                "smart" => "[user-name]",
            ],
            "processed_by" => [ // redcap_mycap_messages 
                "requires" => ["user-name"],
                "smart" => "[user-name]",
            ],
            "ui_id" => [
                "requires" => ["user-name"],
                "smart" => "[user-id]",
            ],
            "userid" => [ // redcap_events_calendar_feed 
                "requires" => ["user-name"],
                "smart" => "[user-id]",
            ],
            "user_id" => [ // redcap_data_import
                "requires" => ["user-name"],
                "smart" => "[user-id]",
            ],
            "redcap_userid" => [ // redcap_ehr_user_map 
                "requires" => ["user-name"],
                "smart" => "[user-id]",
            ],
            "author_user_id" => [ // redcap_messages 
                "requires" => ["user-name"],
                "smart" => "[user-id]",
            ],
            "recipient_user_id" => [ // redcap_messages_recipients
                "requires" => ["user-name"],
                "smart" => "[user-id]",
            ],
            "ui_id_requester" => [ // redcap_metadata_prod_revisions (prioritizing over ui_id_approver)
                "requires" => ["user-name"],
                "smart" => "[user-id]",
            ],
            "created_by" => [ // redcap_projects (prioritizing over others), redcap_multilanguage_snapshots (prioritizing over deleted_by)
                "requires" => ["user-name"],
                "smart" => "[user-id]",
            ],
            "request_from" => [ // redcap_todo_list
                "requires" => ["user-name"],
                "smart" => "[user-id]",
            ],
            // User Role
            "role_id" => [
                "requires" => ["project-id", "user-name"],
                "smart" => "[user-role-id]",
            ],
            // DAG
            "group_id" => [
                [
                    "requires" => ["project-id", "record-name"],
                    "smart" => "[record-dag-id]",
                ],
                [
                    "requires" => ["project-id", "user-name"],
                    "smart" => "[user-dag-id]",
                ],
            ],
            "dag_id" => [
                [
                    "requires" => ["project-id", "record-name"],
                    "smart" => "[record-dag-id]",
                ],
                [
                    "requires" => ["project-id", "user-name"],
                    "smart" => "[user-dag-id]",
                ],
            ],
        ];
        // Sets helper queries for custom smart variables only working in DQT
        // pipe: true = uses Piping::replaceSpecialTags(), false = uses str_replace()
        $dqt_context_lookups = [
            "[survey-id]" => [
                "requires" => ["project-id", "instrument-name"],
                "sql" => "select survey_id as v from redcap_surveys where project_id = [project-id] and form_name = [instrument-name]",
                "pipe" => true,
            ],
            "[user-id]" => [
                "requires" => ["user-name"],
                "sql" => "select ui_id as v from redcap_user_information where username = [user-name]",
                "pipe" => false,
            ],
        ];
        // Get table fields
        $table_fields = array_keys(getTableColumns($table));
        // Build WHERE clause for all fields where context is available
        foreach ($table_fields as $field) {
            $maps = array_key_exists($field, $tablefield_smartvar_map) ? $tablefield_smartvar_map[$field] : false;
            if (!$maps) continue;
            $map = false;
            if (isset($maps["requires"])) $maps = [ $maps ];
            foreach ($maps as $this_map) {
                $ctx_available = true;
                foreach ($this_map["requires"] as $item) {
                    if (empty($dqt_ctx_items[$item]["value"])) {
                        $ctx_available = false;
                        break;
                    }
                }
                if ($ctx_available) {
                    $map = $this_map;
                    break;
                }
            }
            if ($map) {
                $wheres[] = $field . " = " . $map["smart"];
            }
        }
        // Add custom smart variables that can be resolved
        $custom_smarts = [];
        foreach ($dqt_context_lookups as $cs => $cs_info) {
            $ctx_available = true;
            foreach ($cs_info["requires"] as $item) {
                if (empty($dqt_ctx_items[$item]["value"])) {
                    $ctx_available = false;
                    break;
                }
            }
            if ($ctx_available) {
                $custom_smarts[] = $cs;
            }
        }
        // In some cases, change the table
        if ($table == "redcap_data") {
            if (!empty($dqt_ctx_items["project-id"]["value"])) {
                $table = "[data-table]";
            }
        }
        else if ($table == "redcap_log_event") {
            if (!empty($dqt_ctx_items["project-id"]["value"])) {
                $table = \Logging::getLogEventTable($dqt_ctx_items["project-id"]["value"]);
            }
        }
    }
    else {
        if (isset($_GET['field']) && isset($_GET['value'])) {
            $_GET['field'] = preg_replace("/[^0-9a-zA-Z_]/", "", $_GET['field']);
            $wheres[] = prep($_GET['field']) . " = '" . prep($_GET['value']) . "'";
        }
    }
    $where = (count($wheres) > 0) ? ("where " . implode(" and ", $wheres)) : "";
    $query = trim("select * from $table $where");
}
elseif (isset($_GET["sql-field"])) {
    // Sanitize query
	$query = trim(html_entity_decode($_GET['sql-field'], ENT_QUOTES));
}
elseif (isset($_GET["recent-errors-report"])) {
    $query = DBQueryTool::getRecentErrorsQuery($_GET['external-module-prefix'] ?? null);
    $query .= 'order by error_id desc';
}

// Trim semi-colon from the end, if needed
$query = rtrim(trim($query), ";");
$queryHasLimit = ($query != "" && preg_match("/\blimit\s+\d+/i", $query));

if ($query != "")
{
	// Add query limit (unless already exists in query)
	$query_executed = $queryHasLimit ? $query : "$query limit 0,$query_limit";
	// Execute query
	$foreign_key_array = array();
	$mtime = explode(" ", microtime());
	$starttime = $mtime[1] + $mtime[0];
    $query_errno = "";

	$allowedQueryTypes = [
		'select',
		'show',
		'explain'
	];

	if (DBQueryTool::isQueryType($query, $allowedQueryTypes))
	{
		// SELECT
		if (DBQueryTool::isQueryType($query, 'select'))
		{
            // Apply context?
            if ($dqt_use_context) {
                // Find values for any custom smart variables
                foreach ($custom_smarts as $cs) {
                    $lookup = $dqt_context_lookups[$cs];
                    $lookup_sql = $lookup["sql"];
                    if ($lookup["pipe"]) {
                        $lookup_sql = Piping::pipeSpecialTags(
                            $lookup_sql,
                            $dqt_ctx_items["project-id"]["value"],
                            $dqt_ctx_items["record-name"]["value"],
                            $dqt_ctx_items["event-id"]["value"],
                            $dqt_ctx_items["current-instance"]["value"],
                            $dqt_ctx_items["user-name"]["value"],
                            true,
                            null, // participant_id
                            $dqt_ctx_items["instrument-name"]["value"], // form
                            true, // replaceWithUnderlineIfMissing
                            true // escapeSql
                        );
                    }
                    else {
                        foreach ($dqt_ctx_items as $item => $_) {
                            $lookup_sql = str_replace("[{$item}]", "'". prep($dqt_ctx_items[$item]["value"]) . "'", $lookup_sql);
                        }
                    }
                    $lookup_q = db_query($lookup_sql);
                    $lookup_value = "";
                    if (db_num_rows($lookup_q) == 1) {
                        $lookup_value = db_fetch_assoc($lookup_q)["v"];
                    }
                    $query_executed = str_replace($cs, "'" . prep($lookup_value) . "'", $query_executed);
                }
                if (Piping::containsSpecialTags($query_executed)) {
                    // Piping special tags requires a project id
                    if (empty($dqt_ctx_items["project-id"]["value"])) {
                        // No project id ... we can only replace the special tags where a value is explicitly provided
                        foreach ($dqt_ctx_items as $key => $item) {
                            if (!empty($item["value"])) {
                                $query_executed = str_replace("[{$key}]", "'". prep($item["value"]) . "'", $query_executed);
                            }
                        }
                    }
                    else {
                        // Let REDCap's piping handle the special tags
                        $query_executed = Piping::pipeSpecialTags(
                            $query_executed,
                            $dqt_ctx_items["project-id"]["value"],
                            $dqt_ctx_items["record-name"]["value"],
                            $dqt_ctx_items["event-id"]["value"],
                            $dqt_ctx_items["current-instance"]["value"],
                            $dqt_ctx_items["user-name"]["value"],
                            true,
                            null, // participant_id
                            $dqt_ctx_items["instrument-name"]["value"], // form
                            true, // replaceWithUnderlineIfMissing
                            true // escapeSql
                        );
                    }
                }
                // Special case: instance = '1' or instance = '' must be replaced with isnull(instance)
                if (strpos($query_executed, "instance = '1' ") !== false || strpos($query_executed, "instance = '' ") !== false) {
                    $query_executed = str_replace(["instance = '1' ", "instance = '' "], "isnull(instance) ", $query_executed);
                }
            }
			// Find total rows that could be returned
            $q = db_query($query_executed);
			$mtime = explode(" ", microtime());
			$endtime = $mtime[1] + $mtime[0]; 
			// Check for errors
			$query_error = db_error();
			$query_errno = db_errno();
			
			## FOREIGN KEYS
            if (!$doExport)
            {
                // Place all SQL into strings, segregating create table statements and foreign key statements
                $foreign_key_array = $query_tables = array();
                $parser = new PHPSQLParser($query);
                if (isset($parser->parsed['FROM'])) {
                    foreach ($parser->parsed['FROM'] as $attr) {
                        $query_tables[] = $attr['table'];
                    }
                }
                // Now do "create table" to obtain all the FK for each table
                foreach ($query_tables as $this_table) {
                    // Do create table
                    $q3 = db_query("show create table `$this_table`");
                    if (!$q3) continue;
                    $row3 = db_fetch_assoc($q3);
                    $create_table_statement = $row3['Create Table'];
                    // Make sure all line breaks are \n and not \r
                    $create_array = explode("\n", str_replace(array("\r\n", "\r", "\n\n"), array("\n", "\n", "\n"), trim($create_table_statement)));
                    // Check each line
                    foreach ($create_array as $line) {
                        // Trim the line
                        $line = trim($line);
                        // If a foreign key
                        if (substr($line, 0, 11) == 'CONSTRAINT ') {
                            // Format the line
                            $fkword_pos = strpos($line, "FOREIGN KEY ");
                            $fkline = trim(substr($line, $fkword_pos));
                            if (substr($fkline, -1) == ',') $fkline = substr($fkline, 0, -1);
                            // Isolate the field names
                            $first_paren_pos = strpos($fkline, "(") + 1;
                            $fk_field = trim(str_replace("`", "", substr($fkline, $first_paren_pos, strpos($fkline, ")") - $first_paren_pos)));
                            // Get reference table
                            $fkword_pos = strpos($line, "REFERENCES `");
                            $fkline = trim(substr($line, $fkword_pos + strlen("REFERENCES `")));
                            $fk_ref_table = trim(substr($fkline, 0, strpos($fkline, "`")));
                            // Get reference field
                            $ref_field = trim(substr($fkline, strpos($fkline, "(`") + strlen("(`"), strpos($fkline, "`)") - strpos($fkline, "(`") - strlen("(`")));
                            // Add FK line to FK array
                            $foreign_key_array[$this_table][$fk_field] = array('ref_table' => $fk_ref_table, 'ref_field' => $ref_field);
                        }
                    }
                }
            }
		}
		// SHOW or EXPLAIN
		else
		{
			$q = db_query($query);
			$mtime = explode(" ", microtime());
			$endtime = $mtime[1] + $mtime[0];
			// Check for errors
			$query_error = db_error();
			$query_errno = db_errno();
		}
	} 
	else 
	{
		$query_error = $lang['control_center_4816']." " . strtoupper(implode(', ', $allowedQueryTypes));
	}
    $total_execution_time = isset($endtime) ? round($endtime - $starttime, 4) : 0;
	// Query failed
	if (!$q || $query_error != "")
	{
		$display_result .= "<div class='red' style='font-family:arial;'><b>".db_get_server_type()." error $query_errno:</b><br>$query_error</div>";
	}
	// Successful query, give results
	else
	{
        $query_field_info = db_fetch_fields($q);
        $num_rows = db_num_rows($q);
        $num_cols = db_num_fields($q);

        // Perform CSV export
        if ($doExport)
        {
            // If this is a saved query, use that as part of the file name
            $filename = "query_tool_export_".date("Y-m-d_Hi").".csv";
            if (isset($_GET['q']) && isinteger($_GET['q'])) {
                // Loop to find the right query where order_num=$_GET['q']
                foreach ($customQueries as $attr) {
                    if ($attr['order_num'] == $_GET['q']) {
                        $filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($attr['title'], ENT_QUOTES)))), 0, 30)."_".date("Y-m-d_Hi").".csv";
                        break;
                    }
                }
            }
            // Get headers
            $headers = [];
            foreach ($query_field_info as $attr) {
                $headers[] = $attr->name;
            }
            // Open connection to create file in memory and write to it
            $fp = fopen('php://memory', "x+");
            // Add header row to CSV
            fputcsv($fp, $headers, User::getCsvDelimiter(), '"', '');
            // Loop through array and output line as CSV
            while ($row = db_fetch_assoc($q))
            {
                foreach ($row as &$val) {
                    if ($val === null) $val = "";
                }
                fputcsv($fp, $row, User::getCsvDelimiter(), '"', '');
            }
            unset($val);
            // Open file for reading and output to user
            fseek($fp, 0);
            $output = trim(stream_get_contents($fp));
            fclose($fp);
            // Disable output buffering
            if (ob_get_level()) ob_end_clean();
            // Output to file
	        header('Content-Description: File Transfer');
	        header('Pragma: anytextexeptno-cache', true);
            header("Content-type: application/csv");
            header("Content-Disposition: attachment; filename=$filename");
            print addBOMtoUTF8($output);
            // Logging
            Logging::logEvent($query,"redcap_log_event","MANAGE",'',$query,"Export query results in Database Query Tool");
            exit;
        }

        // Logging
        Logging::logEvent($query,"redcap_log_event","MANAGE",'',$query,"Execute query in Database Query Tool");

        // If the rows returned = query_limit and limit does not exist in query, then remind admin that more rows might exist
        $moreRowsText = "";
        if ($num_rows == $query_limit && !$queryHasLimit) {
            $moreRowsText = "<div style='font-size:11px;line-height:1.1;margin:5px 5px 15px;color:#C00000;'><i class=\"fa-solid fa-circle-exclamation mr-1\"></i>NOTICE: 
                            More rows might exist that are not displayed here because \"<b>LIMIT 0,500</b>\" was auto-added to limit the size of the results.</div>";
        }

        // Display webpage
		$display_result .= "<p>
								Returned <b>$num_rows</b> rows
								<i>(executed in $total_execution_time seconds)</i> $moreRowsText
							</p>";
		
		$display_result .= "<table class='dt2' style='font-family:Verdana;font-size:11px;' border='1'>
								<tr class='grp2'>
									<td colspan='$num_cols'>
									    <div style='max-width:800px;'>
                                            <div class='float-end m-1 ms-3'>
                                                <button class='btn btn-xs btn-primaryrc fs11' onclick=\"$('#dqt-form').prop('action', $('#dqt-form').prop('action')+'?export=1'+(getParameterByName('q')==''?'':('&q='+getParameterByName('q'))));$('#dqt-form').submit();\"><i class=\"fas fa-download\"></i> ".RCView::tt('control_center_4826')."</button>
                                            </div>
                                            <div class='float-start mt-1' style='display:inline;max-width:600px;font-size:12px;font-weight:normal;padding:5px 5px 8px;color:#C00000;font-family:monospace;'>
                                                " . htmlentities($query_executed, ENT_QUOTES) . "
                                            </div>
                                        </div>
									</td>
								</tr>
								<tr class='hdr2' style='white-space:normal;'>";
			
		// Display column names as table headers
		for ($i = 0; $i < $num_cols; $i++) {			
			$this_fieldname = db_field_name($q,$i);			
			//Display the Label and Field name
			$display_result .= "	<td style='padding:5px;font-size:10px;'>".htmlspecialchars($this_fieldname, ENT_QUOTES)."</td>";
		}			
		$display_result .= "    </tr>";	
		
		// Display each table row
		$j = 1;
        $class = "odd";
		while ($row = db_fetch_array($q)) 
		{
			$class = ($j%2==1) ? "odd" : "even";
			$display_result .= "<tr class='$class'>";			
			for ($i = 0; $i < $num_cols; $i++) 
			{
				// Display value
				if ($row[$i] === null) {
					$this_display = $this_value = "<i style='color:#aaa;'>NULL</i>";
				} else {
					$this_value = nl2br(htmlspecialchars($row[$i], ENT_QUOTES));
					if (strlen($this_value) > 200) {
						$this_display = "<div class='rcp'>
											" . substr($this_value, 0, strpos(wordwrap($this_value, 200), "\n")) . "<br>
											(...show more for
											<a href='#' onclick='showMore(this); return false'>this row</a>
											or
											<a href='#' onclick='showMore(null); return false'>all rows</a>)
										 </div>
										 <div class='rcf'>$this_value</div>";
					} else {
						$this_display = $this_value;
						// Foreign Key linkage: Get this column's table and field name
						if (isset($foreign_key_array[$query_field_info[$i]->orgtable][$query_field_info[$i]->orgname])) {
							$ref_table = $foreign_key_array[$query_field_info[$i]->orgtable][$query_field_info[$i]->orgname]['ref_table'];
							$ref_field = $foreign_key_array[$query_field_info[$i]->orgtable][$query_field_info[$i]->orgname]['ref_field'];
							// Make value into link to other table
							$this_display = "<a href='$baseUrl?table=$ref_table&field=$ref_field&value=".htmlspecialchars($this_display, ENT_QUOTES)."'>$this_display</a>";
						}
					}
				}
				// Cell contents
				$display_result .= "<td class='query_cell'>$this_display</td>";
			}			
			$display_result .= "</tr>";
			$j++;
		}
		// If returned nothing
		if ($j == 1)
		{
			$display_result .= "<tr class='$class'>
									<td colspan='$num_cols' style='color:#777;padding:3px;border-top:1px solid #CCCCCC;font-size:10px;'>
										Zero rows returned
									</td>
								</tr>";
		
		}
			
		$display_result .= "</table>";
	}
}

$folderPopup = RCView::div(array('id'=>'query_folders_popup', 'class'=>'simpleDialog', 'title'=>"<div style='color:#008000;'><span class='fas fa-folder-open' style='margin-right:4px;'></span> ".RCView::tt('control_center_4918')."</div>"), '');

$importBtn = RCView::a(array('href'=>'javascript:;', 'style'=>'color:#8A5502;', 'onclick'=>"simpleDialog(null,null,'importQueryDialog',500,null,'".js_escape($lang['calendar_popup_01'])."',\"$('#importQueryForm').submit();\",'".js_escape($lang['design_530'])."');$('.ui-dialog-buttonpane button:eq(1)',$('#importQueryDialog').parent()).css('font-weight','bold');"),
                RCView::img(array('src'=>'arrow_up_sm_orange.gif')) .
                    RCView::SP . $lang['data_access_groups_27']
                );
// Hidden import dialog div
$queryString = '';
if (isset($_GET['q']) && $_GET['q'] != '') {
    $queryString = "&q=".$_GET['q'];
}

$hiddenImportDialog = RCView::div(array('id'=>'importQueryDialog', 'class'=>'simpleDialog', 'title'=>RCView::tt_attr('control_center_4920')),
    RCView::div(array(), RCView::tt('control_center_4921')) .
    RCView::div(array('style'=>'margin-top:15px;margin-bottom:5px;font-weight:bold;'), RCView::tt('control_center_4922')) .
    RCView::form(array('id'=>'importQueryForm', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'ControlCenter/database_query_tool.php?action=upload_csv'.$queryString, 'onsubmit' => 'javascript: return checkFileUploadExt();'),
        RCView::input(array('type'=>'file', 'name'=>'file', 'id'=>'queriesFile'))
    )
);

$hiddenImportDialog .= RCView::div(array('id'=>'importQueryDialog2', 'class'=>'simpleDialog', 'title'=> RCView::tt('control_center_4923')." - ".RCView::tt('design_654')),
    RCView::div(array(), RCView::tt('api_125')) .
    RCView::div(array('id'=>'custom_queries_preview', 'style'=>'margin:15px 0'), '') .
    RCView::form(array('id'=>'importQueryForm2', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'ControlCenter/database_query_tool.php?action=upload_csv'.$queryString),
        RCView::textarea(array('name'=>'csv_content', 'style'=>'display:none;'), (isset($_SESSION['csv_content']) ? htmlspecialchars($_SESSION['csv_content'], ENT_QUOTES) : ""))
    )
);

Design::alertRecentImportStatus();
?>
<table style="width:100%;">
	<tr>
		<td valign="top" id="west2">
			<!-- TABLE MENU -->
			<div style="width:95%;">
                <div class="dqt-context-toggle">
                    <label for="dqt-context-toggle"><?=RCView::tt('control_center_4930')?></label>
                    <input type="checkbox" class="form-check-switch" id="dqt-context-toggle" <?=$dqt_use_context ? "checked" : ""?> onclick="toggleDQTContext();">
                    <a class="help fs10" href="javascript:;" onclick="simpleDialog(window.lang.control_center_4936, window.lang.control_center_4935, null, 600);">?</a>
                </div>
                <div style="font-weight:bold;padding:0 3px 5px 0;"><?=RCView::tt('control_center_4811')?></div>
                <!-- Div for displaying popup dialog for file extension mismatch  -->
                <div id="filetype_mismatch_div" title="<?php print RCView::tt_js2('random_12'); ?>" style="display:none;">
                    <p>
                        <?php print RCView::tt('data_import_tool_160') ?>
                        <a href="https://support.office.com/en-us/article/Import-or-export-text-txt-or-csv-files-5250ac4c-663c-47ce-937b-339e391393ba" target="_blank"
                           style="text-decoration:underline;"><?php print RCView::tt('data_import_tool_116') ?></a>
                        <?php print RCView::tt('design_134') ?>
                    </p>
                </div>
                <?php print $hiddenImportDialog; ?>
                <div id="query_panel">
                    <?php print DBQueryTool::outputCustomQueriesPanel($customQueries); ?>
                </div>
                <div class="ml-1">
                    <div class="btn-group" role="group" aria-label="<?=RCView::tt_js2('control_center_4928')?>">
                        <div class="btn-group" role="group">
                            <button id="btnGroupDrop1" type="button" class="btn btn-defaultrc btn-xs fs11 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-gear"></i> <?=RCView::tt('control_center_4928','')?>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="btnGroupDrop1">
                                <li><a class="dropdown-item text-black" href="javascript:;" id="open-saved-query-dialog" style="position:relative;left:-1px;"><i class="fas fa-plus mr-1"></i><?=RCView::tt('control_center_4812','')?></a></li>
                                <li><a class="dropdown-item" href="javascript:;" onclick="openQueryFolders();" style="color:green;position:relative;left:-3px;"><i class="fas fa-folder-open mr-1 fs12"></i><?=RCView::tt('control_center_4929','')?></a></li>
                                <li><a class="dropdown-item" href="javascript:;" onclick="window.location.href='<?=APP_PATH_WEBROOT.'ControlCenter/database_query_tool.php?action=download_csv'?>'"><i class="fas fa-file-download mr-1"></i><?=RCView::tt('control_center_4924','')?></a></li>
                                <li><a class="dropdown-item text-dangerrc" href="javascript:;" onclick="simpleDialog(null,null,'importQueryDialog',600,null,'<?php echo js_escape($lang['calendar_popup_01'])?>','$(\'#importQueryForm\').submit();','<?php echo js_escape($lang['design_530'])?>');$('.ui-dialog-buttonpane button:eq(1)',$('#importQueryDialog').parent()).css('font-weight','bold');"><i class="fas fa-file-upload mr-1"></i><?=RCView::tt('control_center_4920','')?></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
				<hr>
				<div style="font-weight:bold;padding:0 3px 5px 0;"><?=RCView::tt('control_center_4813')?></div>
                <div class="input-group input-group-sm mb-2 dqt-search">
                    <input type="text" class="form-control dqt-filter-text fs12" placeholder="<?=RCView::tt_attr("report_builder_31") // Filter?>">
                    <span class="input-group-text fs12"><i class="fa-solid fa-filter"></i></span>
                    <button class="btn btn-secondary btn-clear-search fs12" type="button"><i class="fa-solid fa-filter-circle-xmark"></i></button>
                </div>
                <div class="database-tables-list">
                    <?php foreach ($table_list as $this_table) { ?>
                        <div class="ps-1" style="line-height:1.2;">
                            <a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick="DQT_reloadWithTable('<?=$this_table?>');"><?=$this_table?></a>
                        </div>
                    <?php } ?>
                </div>
			</div>
		</td>
		<td valign="top" id="center2">
			<!-- MAIN WINDOW -->
			<form action="<?php echo $baseUrl ?>" enctype="multipart/form-data" target="_self" method="post" name="dqt-form" id="dqt-form">
                <div class="dqt-context" <?=$dqt_use_context ? "" : "style=\"display:none;\""?>>
                    <div class="dqt-context-title">
                        <b><?=RCView::tt("control_center_4931") // Smart Variables Context: ?></b>
                        <button class="btn btn-xs btn-rcgreen btn-rcgreen-light ml-2" style="margin-right:6px;font-size:11px;padding:0px 3px 1px;line-height:14px;" onclick="DQT_smartVariableExplainPopup();return false;">[<i class="fas fa-bolt fa-xs" style="margin:0 1px;"></i>] <?=RCView::tt("global_146") // Smart Variables?></button> <i class="fa-solid fa-circle-info text-secondary" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("control_center_4934")?>"></i>
                    </div>
                    <div id="dqt-context-items">
                        <?php foreach ($dqt_ctx_items as $ctx_item_name => $ctx_item): ?>
                        <div class="dqt-context-item">
                            <label for="dqt_ctx_item_<?= $ctx_item_name ?>">[<?=$ctx_item_name?>]</label> = 
                            <input type="<?=$ctx_item["type"]?>" <?=$ctx_item["type"]=="number" ? "min=\"1\" step=\"1\"" : ""?> name="dqt_ctx_item_<?=$ctx_item_name?>" id="dqt_ctx_item_<?=$ctx_item_name?>" value="<?=$ctx_item["value"]?>" placeholder=" " <?=$ctx_item_name == "project-id" ? "required" : ""?>>
                            <?=$ctx_item_name == "project-id" ? "<i class=\"dqt-warn-no-pid fa-solid fa-triangle-exclamation\" data-bs-toggle=\"tooltip\" data-bs-placement=\"bottom\" title=\"".RCView::tt_attr("control_center_4933")."\"></i>" : ""?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div style="font-weight:bold;margin-bottom:2px;"><?=RCView::tt('control_center_4814')?></div>
				<textarea id="query" name="query" style="font-family:monospace;resize:auto;width:100%;max-width:800px;font-size:14px;height:200px;padding:5px;" placeholder="select * from redcap_config"><?php echo htmlentities($query, ENT_QUOTES) ?></textarea>
				<div class="">
					<button class="btn btn-sm btn-primaryrc fs15" onclick="showProgress(1,1);$('#dqt-form').submit();"><?=RCView::tt('control_center_4815')?></button>
                    <?php if ($dqt_use_context): ?>
                    <button id="dqt-toggle-last-query" type="button" class="btn btn-sm btn-secondary fs15" onclick="DQT_toggleLastExecutedQuery(); return false;" data-bs-placement="right" data-bs-toggle="tooltip" title="<?=RCView::tt_attr('control_center_4932')?>"><i class="fa-solid fa-shuffle"></i></button>
                    <?php endif; ?>
				</div>
			</form>
			<!-- RESULT -->
			<?php if ($display_result != "") echo "<div style='padding:20px 0;margin-top:30px;border-top:1px solid #aaa;'>$display_result</div>"; ?>		
		</td>
	</tr>
</table>
<?php print $folderPopup; ?>
</div>
<script type="text/javascript">
    const searchTextCookieName = 'dqt-filter';
    $('.btn-clear-search').on('click', () => $('input.dqt-filter-text').val('').trigger('keyup'));
    $('input.dqt-filter-text').on('keyup', function(e) {
        const searchText = e.target.value.toLowerCase();
        const items = document.querySelectorAll('div.database-tables-list div');
        items.forEach((e) => e.classList[searchText == '' || e.textContent.includes(searchText) ? 'remove' : 'add']('hide'));
        // Store
        setCookie(searchTextCookieName, searchText);
    });
    // Set filter and width
    const prevSearch = getCookie(searchTextCookieName) ?? '';
    $(function() {
        const $list = $('div.database-tables-list');
        $list.css('min-width', $list[0].offsetWidth+10+'px');
        if (prevSearch != '') {
            $('input.dqt-filter-text').val(prevSearch).trigger('keyup');
        }
    });
    // Context toggle
    function toggleDQTContext() {
        const state = $('#dqt-context-toggle').prop('checked');
        console.log(state);
        let contextPresent = false;
        $('#dqt-context-items input').each(function() { 
            contextPresent = contextPresent || ($(this).val() ?? '') != ''; 
        });
        if (!state && contextPresent) {
            if (confirm('Turning off Query Context will clear the context. Are you sure you want to clear the context?')) {
                $('#dqt-context-items input').val('');
                $('div.dqt-context').hide();
            }
            else {
                $('#dqt-context-toggle').prop('checked', true);
            }
        }
        else {
            $('div.dqt-context')[state ? 'show' : 'hide']();
        }
    }
    // Popup to explain Smart Variables
    function DQT_smartVariableExplainPopup() {
        const url = new URL(app_path_webroot_full+'redcap_v'+redcap_version+'/Design/smart_variable_explain.php');
        if (typeof super_user != 'undefined' && super_user == 1) url.searchParams.append('su', '1');
        if (isProjectPage) url.searchParams.append('pid', pid);
        $.get(url.toString(), { },function(data){
            var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
            if (json_data.length < 1) {
                alert(woops);
                return false;
            }
            simpleDialog(json_data.content,json_data.title,'smart_variable_explain_popup',1000);
            fitDialog($('#smart_variable_explain_popup'));
        });
    }
    const DQT_lastQuery = <?=json_encode($query_executed??"")?>;
    var DQT_originalQuery = '';
    function DQT_toggleLastExecutedQuery() {
        const $query = $('#query');
        if (DQT_originalQuery == '') {
            DQT_originalQuery = '' + $query.val();
            $query.val(DQT_lastQuery);
            $('#dqt-toggle-last-query').addClass('btn-danger');
            $query.css('outline', '1px solid red');
        }
        else {
            $query.val(DQT_originalQuery);
            DQT_originalQuery = '';
            $('#dqt-toggle-last-query').removeClass('btn-danger');
            $query.css('outline', 'none');
        }
    }
    function DQT_reloadWithTable(table) {
        const url = new URL(<?=json_encode($baseUrl)?>, app_path_webroot_full);
        url.searchParams.set('table', table);
        if ($('#dqt-context-toggle').prop('checked')) {
            $('#dqt-context-items input').each(function() {
                const val = '' + $(this).val();
                if (val != '') url.searchParams.append($(this).attr('name').replace('dqt_ctx_item_',''), val);
            });
        }
        window.location.href = url.toString();
    }
    // Auto-suggest for admin rights privileges
    $('#dqt_ctx_item_user-name').autocomplete({
        source: function(request, response) {
            const pid = $('#dqt_ctx_item_project-id').val() ?? '';
            const url = new URL(app_path_webroot+'UserRights/search_user.php', app_path_webroot_full);
            url.searchParams.set('term', request.term);
            url.searchParams.set('dqt', 1);
            url.searchParams.set('searchSuspended', 1);
            if (pid) url.searchParams.set('pid', pid);
            $.get(url.toString(), function(data) {
                response(JSON.parse(data));
            });
        },
        minLength: 1,
        delay: 150,
        select: function(event, ui) {
            $(this).val(ui.item.value);
            return false;
        }
    })
    .data('ui-autocomplete')._renderItem = function(ul, item) {
        $('#new_admin_userid').val('');
        return $("<li></li>")
            .data("item", item)
            .append("<a>"+item.label+"</a>")
            .appendTo(ul);
    };
    $('[data-bs-toggle="tooltip"]').each(function() {
        new bootstrap.Tooltip(this)
    });
</script>
<?php
include APP_PATH_DOCROOT . 'ControlCenter/footer.php';
