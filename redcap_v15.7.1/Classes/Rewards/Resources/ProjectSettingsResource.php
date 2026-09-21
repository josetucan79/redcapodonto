<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Resources;

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\BaseProviderProjectSettingsEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities\TangoProjectSettingsEntity;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities\TangoProjectSettingsVO;
use Vanderbilt\REDCap\Classes\Rewards\Settings\Project\ProjectSettingsValueObject;

class ProjectSettingsResource extends BaseResource {
    
    /**
     *
     * @var ProjectSettingsValueObject
     */
    private $settings;
    /**
     *
     * @var  PermissionEntity
     */
    private $permissions;
    private $arms;

    public function __construct($settings, $permissions, $arms) {
        $this->settings = $settings;
        $this->permissions = $permissions;
        $this->arms = $arms;
    }

    /**
     * Transforms the entity into an associative array.
     *
     * @return array The array representation of the entity.
     */
    public function toArray() {
        /** @var ProjectSettingsValueObject $entity */
        $valueObject = $this->settings;
        $data = [];
        if($valueObject instanceof TangoProjectSettingsVO) {
            $settings = [
                'email_from' => $valueObject->getEmailFrom(),
                'email_subject' => $valueObject->getEmailSubject(),
                'email_template' => $valueObject->getEmailTemplate(),
                'preview_expression' => $valueObject->getPreviewExpression(),
            ];
            
            $data['settings'] = $settings;
        }
        $data['permissions'] = $this->permissions;
        $data['arms'] = $this->arms;
        
        return $data;
    }
}