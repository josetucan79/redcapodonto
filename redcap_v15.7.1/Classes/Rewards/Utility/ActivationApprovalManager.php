<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Utility;

use Project;
use ToDoList;
use User;

class ActivationApprovalManager {
    const TODO_TYPE = 'Participant Compensation';

    private $project_id;

    public function __construct($project_id) {
        $this->project_id = $project_id;
    }

    function createToDo($userid, $project_contact_email, $enable=true) {
		$project_id = $this->project_id;
		$userInfo = User::getUserInfo($userid);
		$ui_id = $userInfo['ui_id'] ?? null;
        $redcap_version = REDCAP_VERSION;
		$action_url = APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/Rewards/process_activation_request.php?pid={$project_id}&enable={$enable}";
		ToDoList::insertAction($ui_id, $project_contact_email, self::TODO_TYPE, $action_url, $project_id);
	}

    // Check if request for enable mycap is pending for project
    public function getLastRequestStatus(): ?string
    {
        $sql = "SELECT `status` FROM `redcap_todo_list`
            WHERE todo_type = ? AND project_id = ?  ORDER BY request_time DESC LIMIT 1";
        $result = db_query($sql, [self::TODO_TYPE, $this->project_id]);
        return (db_result($result, field:'status')) ?? null;
    }

    function processRequest($request_id, $userid, bool $accept, bool $enable) {
        // get request variables
        $project_id = $this->project_id;
        $requestor_ui_id = ToDoList::getRequestorByRequestId($request_id);
        
        // get user info
        $approverInfo = User::getUserInfo($userid);
        $approver_ui_id = $approverInfo['ui_id'] ?? null;
        
        
        $request_exists = ToDoList::checkIfRequestExist($project_id, $requestor_ui_id, self::TODO_TYPE) > 0;
        if(!$request_exists) return;

        $updated = ToDoList::updateTodoStatus($project_id, self::TODO_TYPE, 'completed', $requestor_ui_id, $request_id);
        if($updated) {
            if($accept) {
                RewardsProjectService::toggleRewards($project_id, $enable, requestedBy: $userid);
                $this->sendApprovedEmail($approver_ui_id, $requestor_ui_id, $enable);
            } else {
                $this->sendRejectionEmail($approver_ui_id, $requestor_ui_id, $enable);
            }
        }
        return $updated && $enable;
      }
      
      protected function getUserInfoForEmail($approver_ui_id, $requestor_ui_id)
      {
          $project_id = $this->project_id;
          $project = new Project($project_id);
      
          $project_title = $project->project['app_title'] ?? "Project ID $project_id";
      
          $approverInfo = User::getUserInfoByUiid($approver_ui_id);
          $approver_email = $approverInfo['user_email'] ?? null;
          $approver_first_name = $approverInfo['user_firstname'] ?? '';
          $approver_last_name = $approverInfo['user_lastname'] ?? '';
          $approver_full_name = "$approver_last_name $approver_first_name" ?? 'REDCap';
      
          $requestorInfo = User::getUserInfoByUiid($requestor_ui_id);
          $requestor_email = $requestorInfo['user_email'] ?? null;
          
          return [
              'project_title' => $project_title,
              'approver_email' => $approver_email,
              'approver_full_name' => $approver_full_name,
              'requestor_email' => $requestor_email
          ];
      }
      
      protected function generateEmailTemplate($subject, $message_content, $project_title, $project_id, $include_link = false)
      {
          global $lang;
          
          ob_start();
          ?>
          <html>
              <head><title><?php echo $subject ?></title></head>
              <body style='font-family:arial,helvetica;'>
                  <?= $lang['global_21'] ?>
                  <br><br>
                  <?= $message_content ?>
                  <b><?= html_entity_decode($project_title, ENT_QUOTES) ?></b>.
                  <br><br>
                  <?php if ($include_link): ?>
                  <a href="<?= APP_PATH_WEBROOT_FULL . "?pid=$project_id" ?>" target="_blank">Manage your rewards</a>
                  <?php endif; ?>
              </body>
          </html>
          <?php
          $contents = ob_get_contents();
          ob_end_clean();
          
          return $contents;
      }
      
      protected function createEmailMessage($from_email, $from_name, $to_email, $subject, $content)
      {
          $email = new \Message();
          $email->setFrom($from_email);
          $email->setFromName($from_name);
          $email->setTo($to_email);
          $email->setBody($content);
          $email->setSubject($subject);
          
          return $email;
      }

      protected function sendRejectionEmail($approver_ui_id, $requestor_ui_id, bool $isEnabled=true)
      {
          $emailInfo = $this->getUserInfoForEmail($approver_ui_id, $requestor_ui_id);
          $status = $isEnabled ? 'enable' : 'disable';
          
          $emailSubject = "[REDCap] Participant Compensation Rejected";
          $messageContent = "The request to $status the participant compensation feature has been rejected for project: ";
          
          $emailContent = $this->generateEmailTemplate(
              $emailSubject, 
              $messageContent, 
              $emailInfo['project_title'], 
              $this->project_id, 
              false
          );
          
          $email = $this->createEmailMessage(
              $emailInfo['approver_email'],
              $emailInfo['approver_full_name'],
              $emailInfo['requestor_email'],
              $emailSubject,
              $emailContent
          );
          
          return $email->send();
      }
      
      protected function sendApprovedEmail($approver_ui_id, $requestor_ui_id, bool $isEnabled=true)
      {
          $emailInfo = $this->getUserInfoForEmail($approver_ui_id, $requestor_ui_id);
          $status = $isEnabled ? 'enable' : 'disable';
          
          $emailSubject = "[REDCap] Participant Compensation Approved";
          $messageContent = "The request to $status the participant compensation feature has been approved for project: ";
          
          $emailContent = $this->generateEmailTemplate(
              $emailSubject, 
              $messageContent, 
              $emailInfo['project_title'], 
              $this->project_id, 
              true
          );
          
          $email = $this->createEmailMessage(
              $emailInfo['approver_email'],
              $emailInfo['approver_full_name'],
              $emailInfo['requestor_email'],
              $emailSubject,
              $emailContent
          );
          
          return $email->send();
      }
    
}