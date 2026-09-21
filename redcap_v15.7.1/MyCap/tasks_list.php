<?php
use Vanderbilt\REDCap\Classes\MyCap\ActiveTask;
use Vanderbilt\REDCap\Classes\MyCap\Task;

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

global $myCapProj, $lang, $Proj;

$title = $lang['mycap_mobile_app_536'];

foreach ($myCapProj->tasks as $task) {
    $task_ids[] = $task['task_id'];
}

if (count($task_ids) > 0) {
    $rows[] = '';
    $hdr = array();
    $hdr[] = "<b>".$lang['mycap_mobile_app_108']."</b>";
    $hdr[] = "<b>".$lang['shared_library_25']."</b>";
    $hdr[] = "<b>".$lang['mycap_mobile_app_110']."</b>";
    $hdr[] = "<b>".$lang['mycap_mobile_app_537']."</b>";

    // Retrieve task info
    $q = db_query("SELECT * FROM redcap_mycap_tasks WHERE project_id = ".$_GET['pid']." AND enabled_for_mycap = '1' AND task_id IN (".implode(",",$task_ids).")");
    $total = 0;
    if(db_num_rows($q) > 0) {
        $rows[] = $hdr;
        while ($task = db_fetch_assoc($q)) {
            $taskErrors = Task::getMyCapTaskNonFixableErrors($task['form_name']);
            // Ignore tasks having non-fixable errors
            if (empty($taskErrors)) {
                $total++;
                $helpLink = '';
                $question_format = $task['question_format'];
                $isActive = ActiveTask::isActiveTask($question_format);
                $colorCSS = ($isActive) ? 'text-success-more' : '';

                $row[$task['form_name']][] = RCView::span(array('class'=>'wrap '.$colorCSS), RCView::escape($task['task_title']));
                $row[$task['form_name']][] = RCView::span(array('class'=>'wrap '.$colorCSS), RCView::escape($Proj->forms[$task['form_name']]['menu']));

                $urlPostFix = ActiveTask::getHelpURLForTaskFormat($question_format);

                $row[$task['form_name']][] = ($isActive) ? RCView::span(array('class'=>$colorCSS), RCView::escape(ActiveTask::toString($question_format))).$helpLink
                    : RCView::escape(Task::toString($question_format))
                ;
                $row[$task['form_name']][] = RCView::span(array('class'=>'wrap '.$colorCSS), $myCapProj->tasks[$task['form_name']]['schedule_details']);

                // Make sure tasks are in form order
                $tasks_order[$task['form_name']] = $Proj->forms[$task['form_name']]['form_number'];
            }
        }

        asort($tasks_order);
        $row2 = array();
        foreach ($tasks_order as $this_form=>$order) {
            $row2 = $row[$this_form];
            $rows[] = $row2;
        }
        $widths = array(200, 180, 250, 245);

        $content = RCView::div(array('style'=>'margin:0px;'), $lang['mycap_mobile_app_538']);
        $content .= '<div style="width: 200px; float: left;" class="text-dangerrc my-3 font-weight-bold"> '.$lang['mycap_mobile_app_539'].'<span class="ms-1 fs14 text-danger">'.$total.'</span></div>';

        $content .= RCView::div(array('style'=>'float:right;', 'class'=>'my-3'),
            RCView::button(array('class'=>'jqbuttonmed',
                'onclick'=>"window.location.href='".APP_PATH_WEBROOT."MyCapMobileApp/download_schedules.php?pid=".PROJECT_ID."'"),
                RCView::img(array('src'=>'xls.gif', 'style'=>'vertical-align:middle;')) .
                RCView::span(array('style'=>'vertical-align:middle;'), $lang['mycap_mobile_app_820'])
            )
        );

        $content .= RCView::simpleGrid($rows, $widths);
    }
}

// Return title and content
echo json_encode(array(
    'title' => $title,
    'content' => $content
));