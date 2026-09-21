<?php
use Vanderbilt\REDCap\Classes\MyCap\ActiveTask;
use Vanderbilt\REDCap\Classes\MyCap\Task;

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

$allIssuesHtml = '';
global $lang;

$issues_html = Task::listMyCapTasksIssues(($_GET['page']??''), 'publish');
if ($issues_html != '') {
    $allIssuesHtml .= "<p class='mt-3'><i class='fa fa-lightbulb'></i> ".$lang['mycap_mobile_app_725']." <br></b><a href='javascript:;' style='text-decoration:underline;color:green;' id='show_issues_list'>[<i class='fas fa-plus'></i> " . $lang['rights_432']."]</a><a href='javascript:;' style='text-decoration:underline;color:green;display: none;' id='hide_issues_list'>[<i class='fas fa-minus'></i> " . $lang['rights_433']."]</a></p>";
    $allIssuesHtml .= $issues_html;
}
print $allIssuesHtml;
exit;