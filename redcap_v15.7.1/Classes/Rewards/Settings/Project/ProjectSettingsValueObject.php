<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Settings\Project;

use Vanderbilt\REDCap\Classes\Rewards\Settings\BaseSettingsValueObject;

abstract class ProjectSettingsValueObject extends BaseSettingsValueObject
{
    const KEY_EMAIL_TEMPLATE = 'email_template';
    const KEY_EMAIL_SUBJECT = 'email_subject';
    const KEY_EMAIL_FROM = 'email_from';
    const KEY_PREVIEW_EXPRESSION = 'preview_expression';
    const KEY_PARTICIPANT_DETAILS = 'participant_details';

    public ?string $email_template = null;
    public ?string $preview_expression = null;
    public ?string $email_from = null;
    public ?string $email_subject = null;
    public ?string $participant_details = null;

    public function getEmailTemplate(): ?string { return $this->email_template; }
    public function setEmailTemplate(?string $value) { $this->email_template = $value; }

    public function getPreviewExpression(): ?string { return $this->preview_expression; }
    public function setPreviewExpression(?string $value) { $this->preview_expression = $value; }

    public function getEmailFrom(): ?string { return $this->email_from; }
    public function setEmailFrom(?string $value) { $this->email_from = $value; }

    public function getEmailSubject(): ?string { return $this->email_subject; }
    public function setEmailSubject(?string $value) { $this->email_subject = $value; }
    
    public function getParticipantDetails(): ?string { return $this->participant_details; }
    public function setParticipantDetails(?string $value) { $this->participant_details = $value; }
}