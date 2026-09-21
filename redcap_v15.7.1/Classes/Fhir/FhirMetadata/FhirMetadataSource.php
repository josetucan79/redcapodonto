<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata;

use SplFileObject;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataDecoratorInterface;
use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;

class FhirMetadataSource implements FhirMetadataDecoratorInterface
{
  /**
   *  FHIR version code used in REDCap
   * 
   * @var string
   */
  private $fhirCode;

  const FILE_NAME_DSTU2 = 'redcap_fhir_metadata_DSTU2.csv';
  const FILE_NAME_R4 = 'redcap_fhir_metadata_R4.csv';

  private $mapped_objects = [];

  /**
   * manage FHIR metadata:
   * the list of fields will be used to fetch
   * and map FHIR resources from an EHR system
   *
   * @param string $fhirCode a valid FHIR code as specified in FhirVersionManager
   */
  public function __construct($fhirCode)
  {
    $this->fhirCode = $fhirCode;    
  }

  /**
   * get a different metadata file based on
   * the specified FHIR code version (DSTU2, R4)
   *
   * @param string $fhirCode
   * @throws Exception if an invalid FHIR code is specified
   * @return string
   */
  private function getMetadataFilePath($fhirCode)
  {
    $baseMetadataFilePath = realpath(APP_PATH_DOCROOT) . "/Resources/misc/";
    switch ($fhirCode) {
      case FhirVersionManager::FHIR_DSTU2:
        $path = $baseMetadataFilePath.self::FILE_NAME_DSTU2;
        break;
      case FhirVersionManager::FHIR_R4:
        $path = $baseMetadataFilePath.self::FILE_NAME_R4;
        break;
      default:
        throw new \Exception(sprintf("Error: unable to find a metadata CSV file for the FHIR version '%s'", $fhirCode), 1);
        break;
    }
    return $path;
  }

  /**
   * process a row of metadata mapping to get a metadata object
   */
  public static function processRow($row) {
    $processed = [
      'field'       => $row['field'] ?? '',
      'temporal'    => boolval($row['temporal'] ?? ''),
      'label'       => $row['label'] ?? '',
      'description' => $row['description'] ?? '',
      'category'    => $row['category'] ?? '',
      'subcategory' => $row['subcategory'] ?? '',
      'identifier'  => boolval($row['identifier'] ?? ''),
      // metadata. used for special cases (e.g. decorators)
      'disabled'   => false,
      'disabled_reason' => '',
    ];
    if ($processed['identifier'] && $processed['field'] === 'id') {
        // Always set the source id field's cat and subcat to blank so that it's viewed separate from the other fields
        $processed['category'] = $processed['subcategory'] = '';
    }
    return $processed;
  }

  private function readMetadataFile($filePath)
  {
    if(!file_exists($filePath)) throw new \Exception(sprintf("Error: unable to find the metadata file at path '%s'", $filePath), 1);
    $file = new SplFileObject($filePath, $open_mode='r');
    $firstLine = $file->fgetcsv($delimiter=',',$enclosure='"', $escape='');
    $firstLine = null; //skip first line
    $list = [];
    while(!$file->eof()) {
      $data = $file->fgetcsv($delimiter, $enclosure, $escape);
      // Skip empty lines or malformed lines
      if ($data === false || count(array_filter($data)) === 0 || count($data) < 7) {
          continue;
      }
      
      list($field, $label, $description, $temporal, $category, $subcategory, $identifier) = $data;
      $row = compact('field','label','description','temporal','category','subcategory','identifier');
      $metadata = self::processRow($row);
      $list[$field] = $metadata;
    }
    return $list;
  }


  /**
   * order and normalize the values in the metadata array
   *
   * @return array
   */
  private function processMetadataFile()
  {
    /**
     * helper function to order fields by category,
     * subcategory,field name
     */
    $order_fields = function($fields) {
      array_multisort(
          array_column($fields, 'category'), SORT_ASC,
          array_column($fields, 'subcategory'), SORT_ASC,
          array_column($fields, 'field'), SORT_ASC,
          $fields
      );
      return $fields;
    };

    $filePath = $this->getMetadataFilePath($this->fhirCode);
    $data = $this->readMetadataFile($filePath);
    
    $ordered = $order_fields($data);
    return $ordered;
  }

  /**
   * get a list of FHIR mapping objects
   *
   * @return array
   */
  public function getList()
  {
    if(!$this->mapped_objects) {
      $this->mapped_objects = $this->processMetadataFile();
    }
    return $this->mapped_objects;
  }

  /**
   * get the available mapping fields
   * grouped by category/subcategory.
   * use the internal list or a custom one provided
   * as argument
   *
   * @return array
   */
  public function getGroups($fields=null)
  {
    $fields = $fields ?: $this->getList();
    $groups = [];
    foreach ($fields as $field) {
        $category = $field['category'] ?? '';
        if(empty($category)) {
            // this is for ID field (no category or subcategory)
            $groups[] = $field;
            continue;
        }
        // priority to sub categories
        $sub_category = $field['subcategory'] ?? null;
        if($sub_category) $groups[$category][$sub_category][] = $field;
        else $groups[$category][] = $field;
    }
    return $groups;
  }


}