<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Utility;

use Language;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\FhirUser;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManagerFactory;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenStatusHelper;

class CdisPanelBuilder
{
    public function __construct(private int $projectId) {}

    public function buildBody()
    {
        $fhirSystem = FhirSystem::fromProjectId($this->projectId);

        if (!$fhirSystem) {
            $projectSettingsUrl = APP_PATH_WEBROOT . 'ControlCenter/edit_project.php?project='.$this->projectId;
            $warning = Language::tt('cdis_panel_no_fhir_system', optionsOrDefault:[
                'replacements' => [
                    'project-settings-url' => $projectSettingsUrl
                ]
            ]);
            return "<div class='menubox text-danger'>$warning</div>";
        }

        $tokenManager = FhirTokenManagerFactory::create($fhirSystem, UI_ID, $this->projectId);
        $fhirUser = new FhirUser(UI_ID, $this->projectId);
        $accessToken = $tokenManager->getFirstToken();
        $accessTokenStatus = $accessToken instanceof FhirTokenDTO ? $accessToken->getStatus() : false;
        
        // Provide the variables to the view template
        $tokenStatusHtml = FhirTokenStatusHelper::getIcon($accessTokenStatus);
        $mappedEhrUser = $fhirUser->getMappedEhrUser();
        $project_id = $this->projectId;

        ob_start();
        include APP_PATH_DOCROOT . "CDIS/partials/cdis_panel_body.php";
        return ob_get_clean();
    }

}
