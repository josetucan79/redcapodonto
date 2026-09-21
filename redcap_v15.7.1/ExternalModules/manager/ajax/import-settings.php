<?php namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';
require_once __DIR__ . '/../../classes/edocs/ImportEDocCopier.php';

use Exception;
use Throwable;
use ZipArchive;

try{
    ExternalModules::requireDesignRights();

    $zipPath = @$_FILES['file']['tmp_name'];
    if(empty($zipPath)){
        throw new Exception(ExternalModules::tt("em_manage_100"));
    }

    $pid = ExternalModules::requireProjectId();
    $project = new \Project($pid);

    $extractionPath = ExternalModules::createTempDir();

    $zip = new ZipArchive;
    $openResult = $zip->open($zipPath);
    if($openResult !== true){
        throw new \Exception("Error opening zip file: $openResult");
    }

    ExternalModules::extractExcludingExtensions($zip, ['php','htaccess'], $extractionPath);
    $zip->close();
    unlink($zipPath);
    
    $modulesImported = [];
    $moduleNamesImported = [];
    $modulesNotEnabled = [];
    $projectSettingsOmitted = [];
    $superUserSettingsOmitted = [];
    foreach(glob("$extractionPath/modules/*") as $prefixPath){
        $prefix = basename($prefixPath);

        if(!ExternalModules::isModuleEnabled($prefix, $pid)){
            $modulesNotEnabled[] = $prefix;
            continue;
        }

        $modulesImported[] = $prefix;
        $externalModuleId = ExternalModules::getIdForPrefix($prefix);

        $moduleName = ExternalModules::getConfig($prefix)['name'];
        $moduleNamesImported[$moduleName] = true;

        $reservedKeys = [];
        foreach(ExternalModules::getReservedSettings() as $reservedSetting){
            $reservedKeys[] = $reservedSetting['key'];
        }

        $reservedKeyQuestionMarks = ExternalModules::getQuestionMarks($reservedKeys);
        $params = array_merge([$externalModuleId, $pid], $reservedKeys);

        ExternalModules::query("
            delete from redcap_external_module_settings
            where
                external_module_id = ?
                and project_id = ?
                and `key` not in ($reservedKeyQuestionMarks)
        ", $params);

        $settings = json_decode(file_get_contents("$prefixPath/settings.json"), true);
        foreach($settings as $setting){
            $details = ExternalModules::getSettingDetails($prefix, $setting['key']);
            if($details !== null){
                $settingName = $details['name'] ?? null;
                $type = $details['type'] ?? null;
                
                if($type === 'project-id'){
                    $projectSettingsOmitted[$moduleName][$settingName] = true;
                    continue;
                }
                else if(($details['super-users-only'] ?? null) === true){
                    /**
                     * Even though these settings aren't included in exports, we want to make sure
                     * they can't be imported by manually modifying an export zip.
                     */
                    $superUserSettingsOmitted[$moduleName][$settingName] = true;
                    continue;
                }
                else{
                    $setting = ExternalModules::processNestedSettingValuesForRow($setting, function($value) use ($project, $type){
                        return ExternalModules::convertSettingValueForImport($project, $type, $value);
                    });
                }
            }

            ExternalModules::query('insert into redcap_external_module_settings values (?, ?, ?, ?, ?)', [
                $externalModuleId,
                $pid,
                $setting['key'],
                $setting['type'],
                $setting['value'],
            ]);
        }
    }

    $warningMessages = [];

    if(!empty($modulesImported)){
        $edocCopier = new ImportEDocCopier($pid, $modulesImported, $extractionPath);
        $edocCopierWarnings = $edocCopier->run();
        $warningMessages = array_merge($warningMessages, $edocCopierWarnings);
    }

    if(!empty($modulesNotEnabled)){
        $modulesNotEnabledNames = [];
        foreach($modulesNotEnabled as $prefix){
            $moduleName = ExternalModules::getConfig($prefix)['name'] ?? $prefix;
            $modulesNotEnabledNames[] = $moduleName;
        }
        
        $warningMessages[] = ExternalModules::tt("em_manage_102");
        $warningMessages[] = $modulesNotEnabledNames;
    }

    if(!empty($projectSettingsOmitted)){
        $warningMessages[] = ExternalModules::tt("em_manage_104");
        foreach($projectSettingsOmitted as $moduleName=>$settings){
            $warningMessages[] = [$moduleName];
            $warningMessages[] = [array_keys($settings)];
        }
    }

    if(!empty($superUserSettingsOmitted)){
        $warningMessages[] = ExternalModules::tt("em_manage_105");
        foreach($superUserSettingsOmitted as $moduleName=>$settings){
            $warningMessages[] = [$moduleName];
            $warningMessages[] = [array_keys($settings)];
        }
    }

    $response = ExternalModules::getSettingImportResponse(array_keys($moduleNamesImported), $warningMessages);
}
catch(Throwable $t){
    if(ExternalModules::isTesting()){
        throw $t;
    }

    ExternalModules::errorLog($t->__toString());

    $response = [
        'message' => $t->getMessage()
    ];
}

echo json_encode($response);