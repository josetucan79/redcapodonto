<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata;

use Files;
use Logging;
use Exception;
use Vanderbilt\REDCap\Classes\Utility\CSVHelper;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirCustomMetadataValidator\FhirCustomMetadataValidator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirCustomMetadataValidator\FhirCustomMetadataOrderValidation;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirCustomMetadataValidator\FhirCustomMetadataHeaderValidation;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirCustomMetadataValidator\FhirCustomMetadataCategoryValidation;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirCustomMetadataValidator\FhirCustomMetadataNonEmptyValidation;

class FhirCustomMetadataService
{
  
  /**
  * list of headers in the CSV file
  */
  const MAPPING_HEADERS = ['field','label','description','temporal','category','subcategory','identifier', 'disabled', 'disabled_reason'];
  
  const CUSTOM_MAPPING_EDOC_CONFIG_NAME = 'fhir_custom_mapping_file_id';
  
  const CUSTOM_MAPPING_CACHE_PREFIX = 'custom_mapping_';

  const VALID_CATEGORIES = ['Laboratory', 'Vital Signs'];

  const FILE_UPLOAD_NAME = 'cdis_custom_mapping.csv';
  
  
  /**
  * upload a csv file with custom mappings and store a reference to the edoc ID
  * in the REDCap configuration table
  *
  * @param array $fileData in a FILE compatible structure
  * @return int
  * @throws Exception
  */
  public function uploadCustomMapping($entries) {
    $data = $this->arrayToCSVString(self::MAPPING_HEADERS, $entries);
    $fileData = $this->createTempFileForUpload(self::FILE_UPLOAD_NAME, $data, 'text/csv');
    $edocID = Files::uploadFile($fileData);
    if(!$edocID) throw new Exception("Error uploading the fle to the edocs storage", 400);
    $saved = $this->saveCustomMappingReference($edocID);
    if(!$saved) throw new Exception("Error storing the edoc ID reference in the database", 400);
    
    return $edocID;
  }

  function arrayToCSVString($headers, $items) {
    $f = fopen('php://memory', 'r+');
    // Write column headers (keys of the first array)
    fputcsv($f, $headers, ',', '"', '');

    // make an ordered entry
    $makeEntry = function($row) use($headers) {
      $entry = [];
      foreach ($headers as $key) {
        $entry[$key] = $row[$key] ?? '';
      }
      return $entry;
    };

    // Write data rows
    foreach ($items as $item) {
      $entry = $makeEntry($item);
      fputcsv($f, $entry, ',', '"', '');
    }

    // Rewind the "file" to the beginning
    rewind($f);
    $csvString = stream_get_contents($f);
    fclose($f);

    return $csvString;
  }


  function createTempFileForUpload($fileName, $content, $extension, $mimeType = 'application/octet-stream') {
    // Create a temporary file
    $makeUniqueTempName = function($extension) use (&$makeUniqueTempName){
      $tmpFilePath = tempnam(sys_get_temp_dir(), 'upload');
      $tmpFilePath .= ".$extension";
      if (!file_exists($tmpFilePath)) return $tmpFilePath;
      return $makeUniqueTempName($extension);
    };

    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $tmpFilePath = $makeUniqueTempName($extension);
    
    // Write the content to the temporary file
    file_put_contents($tmpFilePath, $content);

    // Get the file size
    $fileSize = filesize($tmpFilePath);

    // Construct the array similar to $_FILES structure
    $fileArray = [
        'name' => $fileName,
        'type' => $mimeType,
        'tmp_name' => $tmpFilePath,
        'error' => 0, // Assuming no error
        'size' => $fileSize
    ];

    return $fileArray;
}

  public function getValidCategories() {
    return self::VALID_CATEGORIES;
  }

  public function validateCustomMapping($entries) {
    $header = ['field','label','description','temporal','category','subcategory','identifier','disabled','disabled_reason'];

    $validationStrategies =[
        new FhirCustomMetadataNonEmptyValidation(),
        new FhirCustomMetadataHeaderValidation($header),
        // new FhirCustomMetadataOrderValidation($header),
        new FhirCustomMetadataCategoryValidation($this->getValidCategories()),
    ];
    $validator = new FhirCustomMetadataValidator($validationStrategies);
    return $validator->validate($entries);
  }
  
  
  /**
  * mark an edoc file as deleted
  *
  * @param integer $edocID
  * @return bool
  */
  protected function deleteEdocFile($edocID) {
    if (!is_int($edocID)) return false;
    $now = date('Y-m-d H:i:s');
    $query = "UPDATE redcap_edocs_metadata SET delete_date = ?
    WHERE doc_id = ? AND delete_date IS NULL AND project_id IS NULL";
    $result = db_query($query, [$now, $edocID]);
    
    return $result;
    
  }
  
  /**
   * remove a custom mapping file
   *
   * @return boolean
   */
  public function removeCustomMapping() {
    $edocID = $this->getCustomMappingsID();
    if(!$edocID) return;
    $deleted = $this->deleteEdocFile($edocID); // delete the edoc file
    $fileCache = new FileCache(__CLASS__);
    $key = $this->getCacheKey($edocID);
    $hasCache = $fileCache->has($key);
    if($hasCache) {
      $deleted &= $fileCache->delete($key);
    }
    // update the config
    $deleted &= $this->saveCustomMappingReference('');

    return boolval($deleted);
  }
  
  public function getCustomMappingsID() {
    $redcapConfig = REDCapConfigDTO::fromDB();
    $edocID = $redcapConfig->{self::CUSTOM_MAPPING_EDOC_CONFIG_NAME};
    if(!$edocID) return false;
    return intval($edocID);
  }
  
  /**
  * save a reference to the custom mapping ID of the
  * edoc file into the database
  *
  * @param integer $edocID
  * @return boolean
  */
  protected function saveCustomMappingReference($edocID) {
    $fieldName = self::CUSTOM_MAPPING_EDOC_CONFIG_NAME;
    $query = "UPDATE redcap_config SET value = ? WHERE field_name = '{$fieldName}'";
    $result = db_query($query, [$edocID]);
    
    // Log changes (if change was made)
    if ($result && db_affected_rows() > 0) {
      $changes_log = "$fieldName = '$edocID'";
      Logging::logEvent($query,"redcap_config","MANAGE","",$changes_log,"Modify system configuration");
      return true;
    }
    return false;
  }
  
  
  /**
  * create a template file to use as reference for custom mappings
  *
  * @return void
  */
  public function downloadTemplate() { CSVHelper::downloadCSV($filename='template.csv', $headers=self::MAPPING_HEADERS, $data = []); }
  
  
  public function downloadCurrentCustomMapping() {
    $customMappings = $this->getCustomMappings();
    
    if(!$customMappings) throw new Exception("No custom mappings file available", 1);
    $rows = CSVHelper::csvToArray($customMappings);
    if(count($rows)===0) throw new Exception("No data was found in the custom mappings file", 1);
    
    $headers = array_keys($rows[0]);
    CSVHelper::downloadCSV($filename='custom-mappings.csv', $headers, $rows);
  }
  
  /**
  * get the key used tostore and retrieve the cached
  * custom mappings
  *
  * @param [type] $edocID
  * @return void
  */
  protected function getCacheKey($edocID) { return self::CUSTOM_MAPPING_CACHE_PREFIX.$edocID; }
  
  /**
   * get the content of the custom mappings file
   *
   * @return string
   */
  public function getCustomMappings() {
    $edocID = $this->getCustomMappingsID();
    if(!$edocID) return;
    
    $fileCache = new FileCache(__CLASS__);
    $key = $this->getCacheKey($edocID);
    $cached = $fileCache->get($key);
    if(!$cached) {
      $doc_id_file_name = Files::copyEdocToTemp($edocID, true, true);
      if($doc_id_file_name==false) return; // cannot move to temp
      $content = file_get_contents($doc_id_file_name);
      $fileCache->set($key, $content);
      return $content;
    }
    return $cached;
  }

  public function getData() {
    $csvString = $this->getCustomMappings();
    if(!$csvString) return [];
    $rows = CSVHelper::csvToArray($csvString);
    $metadataItems = array_map(function($row) {
      return FhirMetadataSource::processRow($row);
    }, $rows);
    return $metadataItems;
  }
  
}