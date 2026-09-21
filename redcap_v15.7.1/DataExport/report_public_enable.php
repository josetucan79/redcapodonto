<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if ($user_rights['user_rights'] != '1' || !isset($_POST['report_id']) || !isinteger($_POST['report_id'])) {
	exit("ERROR");
}

$de = new DataExport();

switch ($_GET['action'])
{
    case 'enable':
		list ($userCanMakeReportPublic, $hasRightsToMakePublic, $userViewedReport, $identifierFieldsInReport) = DataExport::canMakeReportPublic($_POST['report_id'], PROJECT_ID, USERID);
		$sendConfirmationEmailToUser = (UserRights::isSuperUserNotImpersonator() && isset($_POST['request_approval_by_admin']) && $_POST['request_approval_by_admin'] == '1' && isset($_POST['user'])) ? $_POST['user'] : null;
		if ($userCanMakeReportPublic) {
			$de->publicEnable($_POST['report_id'], $sendConfirmationEmailToUser);
		}
		break;

    case 'shorturl':
        if (!isset($_POST['hash']) || !isset($_POST['custom_url']) || !isset($_POST['report_id'])) exit("0");
        DataExport::saveShortUrl($_POST['hash'], $_POST['custom_url'], $_POST['report_id']);
        break;

    case 'remove_shorturl':
        if (!isset($_POST['report_id']) || !isinteger($_POST['report_id'])) exit("0");
        DataExport::removeShortUrl($_POST['report_id']);
        break;

    default:
    	print "0";
        break;
}
