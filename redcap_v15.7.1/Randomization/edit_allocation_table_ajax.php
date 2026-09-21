<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Default response
$response = "0";

// Superusers only Only those with Design rights can delete a project when in development, and super users can always delete
if (isset($_REQUEST['action']) && !empty($_REQUEST['action']) && 
    isset($_REQUEST['operation']) && !empty($_REQUEST['operation']) && 
    UserRights::isSuperUserNotImpersonator() )
{
	// get content to display in the pop-up
	if ($_GET['action'] == "prompt")
	{
        try {
            $response = Randomization::getEditAllocationTableDialogContent($_GET['operation'], $_GET['aid'], $_GET['seq'], $_GET['current']);
        } catch (Exception $ex) { }
	}

    // perform the action
	elseif ($_POST['action'] == "edit")
	{
        try {
            $operation = $_POST['operation'];
            $pid = intval($_GET['pid']);
            $aid = intval($_POST['data']['aid']);
            $seq = intval($_POST['data']['seq']);
            $current = htmlspecialchars($_POST['data']['current'], ENT_QUOTES);
            $newval = htmlspecialchars($_POST['data']['newval'], ENT_QUOTES);
            $reason = htmlspecialchars($_POST['data']['reason'], ENT_QUOTES);

            $response = array(
                'result' => 0,
                'aid' => $aid,
                'newval' => $newval,
                'message' => ''
            );

            $result = Randomization::editAllocationTableEntry($operation, $pid, $aid, $seq, $reason, $current, $newval);
            if ($result) {
                $response['result'] = 1;
            } else {
                throw new Exception('update failed');
            }
        } catch (Exception $ex) { 
            $response = array(
                'result' => 0,
                'aid' => $aid,
                'newval' => $newval,
                'message' => $ex->getMessage()
            );
        }
        $response = json_encode_rc($response);
	}
}

print $response;
