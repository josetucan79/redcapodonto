<?php
require_once __DIR__ . '/../../redcap_connect.php';

use ExternalModules\ExternalModules;

try{
    ExternalModules::requireDesignRights();
    
    $prefixes = $_POST['prefixes'] ?? null;
    if(empty($prefixes)){
        //= You must select at least one module to export.
        throw new Exception(ExternalModules::tt("em_manage_97"));
    }

    $pid = ExternalModules::requireProjectId();
    $project = ExternalModules::getREDCapProjectObject($pid);

    $path = stream_get_meta_data(tmpfile())['uri'];
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    
    $superUserSettingsOmitted = [];
    $edocIds = [];
    $moduleNames = [];
    foreach($prefixes as $prefix){
        $moduleName = ExternalModules::getConfig($prefix)['name'];
        $moduleNames[$moduleName] = true;

        $sql = ExternalModules::getSettingExportQuery('*', $pid);
        $sql .= ' and external_module_id in (select external_module_id from redcap_external_modules where directory_prefix = ?)';
        $result = ExternalModules::query($sql, $prefix);

        $settings = [];
        while($row = $result->fetch_assoc()){
            $key = $row['key'];
            $details = ExternalModules::getSettingDetails($prefix, $key);
            $type = $details['type'] ?? null;

            if(($details['super-users-only'] ?? null) === true){
                $superUserSettingsOmitted[$moduleName][$details['name']] = true;
                continue;
            }
            else if($key === ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST){
                $files = json_decode($row['value'], true);
                foreach($files as $file){
                    $edocIds[$file['edocId']] = true;
                }
            }
            else{
                $row = ExternalModules::processNestedSettingValuesForRow($row, function($value) use ($project, $type, &$edocIds){
                    if($type === 'file'){
                        $edocIds[$value] = true;
                    }

                    return ExternalModules::convertSettingValueForExport($project, $type, $value);
                });
            }

            $settings[] = $row;
        }

        $zip->addFromString("modules/$prefix/settings.json", json_encode($settings));
    }

    $result = ExternalModules::queryEDocs(array_keys($edocIds));
    $tempFiles = [];
    while($row = $result->fetch_assoc()){
        $edocId = $row['doc_id'];
        $edocPath = \Files::copyEdocToTemp($edocId);
        if($edocPath === false){
            continue;
        }

        if(!$zip->addFile($edocPath, "edocs/$edocId/{$row['doc_name']}")){
            throw new Exception('Error adding edoc to export zip');
        }

        $tempFiles[] = $edocPath;
    }

    if(!$zip->close()){
        throw new Exception('Error closing export zip');
    }

    foreach($tempFiles as $tempFilePath){
        if(!unlink($tempFilePath)){
            throw new Exception('Error removing export temp file: ' . basename($tempFilePath));
        }
    }

    ExternalModules::setExportedSettingsPath($path);

    $warningMessages = [];
    if(!empty($superUserSettingsOmitted)){
        $warningMessages[] = ExternalModules::tt("em_manage_108");
        foreach($superUserSettingsOmitted as $moduleName=>$settings){
            $warningMessages[] = [$moduleName];
            $warningMessages[] = [array_keys($settings)];
        }
    }

    $response = ExternalModules::getSettingExportResponse(array_keys($moduleNames), $warningMessages);
    $response['downloadUrl'] = 'ajax/download-exported-settings.php';

    echo json_encode($response);
}
catch(Throwable $t){
    ExternalModules::errorLog($t->__toString());

    echo json_encode([
        'message' => $t->getMessage()
    ]);
}