<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow;

use Message;
use Project;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Utility\ProjectArmFetcher;
use Vanderbilt\REDCap\Classes\Rewards\Settings\Project\ProjectSettingsValueObject;
use Vanderbilt\REDCap\Classes\Rewards\Utility\SmartVarialblesUtility;

class OrderEmailService {


    const EMAIL_CATEGORY = 'REWARDS';
    
    /**
     *
     * @var int
     */
    private $project_id;
    
    /**
     *
     * @var int
     */
    private $user_id;

    /**
     *
     * @var Project
     */
    private $project;

    /**
     *
     * @var ProjectSettingsValueObject
     */
    private $settings;

    public function __construct(ProjectSettingsValueObject $settings, int $project_id, int $user_id) {
        $this->settings = $settings;
        $this->user_id = $user_id;
        $this->project_id = $project_id;
        $this->project = new Project($project_id);
    }

    /**
     * send an email to a user to notify a new Parcel
     *
     * @param int $arm_num
     * @param int|string $record_id
     * @param OrderEntity $order
     * @return int|null
     */
    public function sendMessage($arm_num, $record_id, $order) {
        $message  = $this->makeMessage($arm_num, $record_id, $order);
        if(!($message instanceof Message || $message instanceof PHPMailer)) return null;
        try {
            return $message->send(
                $removeDisplayName=false,
                $recipientIsSurveyParticipant=null,
                $enforceProtectedEmail=false,
                $emailCategory=self::EMAIL_CATEGORY,
                $lang_id=null
            );
            // $entity = $this->createEmailEntity($message, $order);
            // return $entity;
        } catch (Exception $e) {
            throw new Exception($message->ErrorInfo, $e->getCode(), $e);
        }
    }

    private function getRecipientAddress($arm_num, $record_id) {
        $emailsMap = $this->project->getEmailInvitationFieldValues($record_id, [], $arm_num);
        if(empty($emailsMap)) return false;
        $address = join(';', array_values($emailsMap));
        return $address;
    }

    /**
     *
     * @param int $arm_num
     * @param string|int $record_id
     * @param OrderEntity $order
     * @return PHPMailer
     */
    protected function makeMessage($arm_num, $record_id, $order) {
        $event_id = null;
        $email = $this->getRecipientAddress($arm_num, $record_id);
        if($email === false) throw new Exception("Error: please make sure to designate an email field for communications in the project setup.", 400);
        if(empty($email)) return null;

        if(!$order instanceof OrderEntity) throw new Exception("Error: a valid order was not provided..", 400);

        // make sure the event_id is set
        $event_id = ProjectArmFetcher::getProjectArmFirstEvent($this->project_id, $arm_num);

        $emailFrom = $this->settings->getEmailFrom();
        $emailFromName = 'REDCap Rewards';
        $emailTemplate = $this->settings->getEmailTemplate();
        $emailSubject = $this->settings->getEmailSubject();

        $emailSubject = SmartVarialblesUtility::replace($emailSubject, $this->project_id, $record_id, $event_id);
        $body = SmartVarialblesUtility::replace($emailTemplate, $this->project_id, $record_id, $event_id);

        // Generate the terms and conditions link
        $linkHtml = TermsManager::generateTermsAndConditionsLink($this->project_id, $order);
        // Append the link to the email body
        $body .= "\n\n" . $linkHtml;

        $message = new Message($this->project_id, $record_id, $event_id);
        $message->setBody($body, false);
        $message->setSubject($emailSubject);
        $message->setFrom($emailFrom);
        $message->setFromName($emailFromName);
        $message->setTo($email);
        return $message;

        /* $mail = new PHPMailer();
		$mail->CharSet = 'UTF-8';
        //From email address and name
        $mail->From = $emailFrom;
        $mail->FromName = $emailFromName;
        $mail->addAddress($email);
        $mail->addReplyTo($emailFrom, $emailFromName);
        //Send HTML or Plain Text email
        $mail->isHTML(true);

        $mail->Subject = $emailSubject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        return $mail; */
    }

    /**
     *
     * @param Message|PHPMailer $email
     * @param OrderEntity $order
     * @return int|null ID of the email created 
     */
    /* protected function createEmailEntity($email, $order) {
        if(!($email instanceof Message || $email instanceof PHPMailer) ) return null;
        
        $sendableType = EmailEntity::getMorphTypeValue(OrderEntity::class);
        $now = date(EmailEntity::TIMESTAMP_FORMAT);
        $emailEntity = new EmailEntity();
        $emailEntity->setSentBy($this->user_id);
        $emailEntity->setSendableType($sendableType);
        $emailEntity->setSendableId($order->getOrderId());
        $emailEntity->setSentAt($now);
        $emailEntity->setEmailSubject($email->getSubject());
        $emailEntity->setEmailContent($email->getBody());
        $emailEntity->setSenderEmail($email->getFrom());
        $emailEntity->setRecipientEmail($email->getTo());

        $emailRepo = Repository___Factory::make(EmailEntity::class);
        return $emailRepo->create($emailEntity);
    } */

}