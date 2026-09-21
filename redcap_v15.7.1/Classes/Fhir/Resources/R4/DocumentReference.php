<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Files;
use Exception;
use ZipArchive;
use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry\PropertySetInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Traits\CanConvertMimeTypeToExtension;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodeableConcept;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class DocumentReference extends AbstractResource
{
  use CanNormalizeTimestamp;
  use CanConvertMimeTypeToExtension;

  const TOO_LARGE_NOTICE = 'DATA TOO LARGE, TRUNCATED';

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';
  
  /**
   *
   * @var FhirClient
   */
  protected $fhirClient;

  /**
   *
   * @param FhirClient $fhirClient
   */
  public function __construct($payload, $fhirClient) {
    parent::__construct($payload);
    $this->fhirClient = $fhirClient;
  }

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  /**
   * When this document reference was created
   *
   * @return string
   */
  public function getDate()
  {
    return $this->scraper()->date->join('');
  }

  /**
   * Who and/or what authored the document
   *
   * @param integer $index
   * @return void
   */
  public function getAuthor($index=0) {
    return $this->scraper()->author[$index]->getData();
  }

  public function getAuthorType($index=0) {
    return $this->scraper()->author[$index]->type->join('');
  }

  public function getAuthorReference($index=0) {
    return $this->scraper()->author[$index]->reference->join('');
  }

  public function getAuthorDisplay($index=0) {
    return $this->scraper()->author[$index]->display->join('');
  }

  public function getAttachment($index=0) {
    return $this->scraper()->content[$index]->attachment->getData();
  }

  /**
   * get list of data for each attachment
   *
   * @return array
   */
  public function getAttachments() {
    $attachments = $this->scraper()->content->attachment->getData();
    return $attachments;
  }

  /**
   * get codeable concept
   *
   * @return CodeableConcept
   */
  public function getType() {
    $codingsPayload = $this->scraper()->type[0]->getData();
    return new CodeableConcept($codingsPayload);
  }

  public function getTypeText() {
    $type = $this->getType();
    if(!($type instanceof CodeableConcept)) return '';
    return $type->getText();
  }

  public function getPracticeSettingText() {
    return $this->scraper()->context->practiceSetting->text->join('');
  }

  public function getLoincCodes()
  {
    return $this->scraper()
      ->type->coding
      ->where('system', 'like', CodingSystem::LOINC)
      ->code->getData();
  }

  /**
   *
   * @param FhirClient $fhirClient
   * @param String $destinationFolder
   * @return void
   */
  public function saveAttachments()
  {
    if(!$this->fhirClient) return;
    $zipPath = $this->createAttachmentsZip();
    try {
      $file = Files::makeFileStructure('attachments.zip', $zipPath, 'application/zip');
      $projectID = $this->fhirClient->getProjectID();
      $fileID = Files::uploadFile($file, $projectID);
      return $fileID;
    } finally {
      // Ensure the file is always deleted
      if (file_exists($zipPath)) unlink($zipPath);
    }
  }

  /**
   *
   * @param string $URL
   * @return string
   */
  private function getBinaryData($URL, $contentType=null) {
    if(!$this->fhirClient) return;
    $request = $this->fhirClient->getFhirRequest($relative_url=$URL, $method='GET', $options=[]);
    // update the request default options to change the accept header
    $requestDefaultOptions = $request->getDefaultOptions();
    $requestDefaultOptions['headers']['Accept'] = $contentType;
    // unset($requestDefaultOptions['headers']['Accept']);
    $request->setDefaultOptions($requestDefaultOptions);

    $token = $this->fhirClient->getToken();
    $response = $request->send($token->getAccessToken());
    $data = strval($response);
    return $data;
  }

  /**
   * create a zip file in the temp folder with all attachments
   * for a document reference resource
   *
   * @return string|null|false return the path to the zip file on success,
   * null if no attachments are available,
   * or false if the zip file cannot be created
   */
  public function createAttachmentsZip() {
    $attachments = $this->getAttachments();
    if(count($attachments)===0) return null;
    $destinationFolder = rtrim(APP_PATH_TEMP, '/');
    $zip = new ZipArchive();
    $zipTmpName = Files::generateUniqueFileName($destinationFolder);
    $zipPath = $destinationFolder.DIRECTORY_SEPARATOR.$zipTmpName;
    $zip->open($zipPath, ZipArchive::CREATE);
  
    foreach ($attachments as $attachment) {
      $contentType = $attachment['contentType'];
      $title = $attachment['title'] ?? 'untitled';
      $URL = $attachment['url'];
      $data = $this->getBinaryData($URL, $contentType);
      $extension = $this->mime2ext($contentType);
      $fileName = $title . ($extension ? ".$extension" : '');
      $zip->addFromString($fileName, $data);
    }
    $zipCreated = $zip->close();
    if(!$zipCreated) return false;
    return $zipPath;
  }

  /**
   * get the HTML version of the file if available
   *
   * @return string
   */
  public function getHTML($sanitize=false) {
    $noData = '';
    if(!$this->fhirClient) return $noData;
    $attachments = $this->getAttachments();
    $validExtensions = ['html'];

    foreach ($attachments as $attachment) {
      $contentType = $attachment['contentType'];
      $extension = strtolower($this->mime2ext($contentType));
      if (!in_array($extension, $validExtensions)) continue;

      $URL = $attachment['url'];
      $data = $this->getBinaryData($URL, $contentType);
      if($sanitize) $data = filter_tags($data);
      return $data;
    }
    return $noData;
  }

  /**
   * save links to the binary files
   * as an HTML file
   *
   * @param integer $projectID
   * @return integer ID of the uploaded HTML file
   */
  public function saveLinks($projectID) {
    $getLinksHtml = function($links) {
      $html = "<ul>";
      foreach ($links as $link) {
        $html .= PHP_EOL."<li>{$link}</li>";
      }
      $html .= PHP_EOL."</ul>";
      return $html;
    };
    $links = $this->getLinks($projectID);
    $html = $getLinksHtml($links);
    
    $uploadPath = Files::uploadFromString($html, APP_PATH_TEMP);
    $file = Files::makeFileStructure('links.html', $uploadPath, 'text/html');
    $fileID = Files::uploadFile($file, $projectID);
    return $fileID;
  }

  public function getLinks($projectID) {
    $getAttachmentProxyURL = function($projectID, $url) {
      $query = http_build_query([
        'pid' => $projectID,
        'route' => 'FhirProxyController:forward',
        'url' => $url,
      ]);
      $redcapBase = rtrim(APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION, '/');
      $proxyURL = $redcapBase."/?{$query}";
      return $proxyURL;
    };

    $typeText = null;
    $type = $this->getType();
    if($type instanceof CodeableConcept) $typeText = $type->getText();
    $attachments = $this->getAttachments();
    $links = [];
    foreach ($attachments as $attachment) {
      $URL = $attachment['url'];
      $proxyURL = $getAttachmentProxyURL($projectID, $URL);
      $title = $attachment['title'] ?? ($typeText ?? 'untitled');
      $contentType = $attachment['contentType'];
      $link = sprintf('<a href="%s">%s (%s)</a>', $proxyURL, $title, $contentType);
      $links[] = $link;
    }
    return $links;
  }


  /**
   * create a zip file with the attachments of the resource
   *
   * @param array $files
   * @param [type] $destinationFolder
   * @return array|null a file structure or null on error
   * @throws Exception if the system cannot create zip files
   */
  private function storeFiles($destinationFolder, $files=[])
  {
    if(empty($files)) return;

    if (!Files::hasZipArchive()) {
      throw new Exception('ERROR: ZipArchive is not installed. It must be installed to use this feature.', 0);
    }

    $zip = new ZipArchive();
    $destinationFolder = rtrim($destinationFolder, '/');
    $zipTmpName = Files::generateUniqueFileName($destinationFolder);
    $zipPath = $destinationFolder.DIRECTORY_SEPARATOR.$zipTmpName;
    $zip->open($zipPath, ZipArchive::CREATE);
    foreach ($files as $file) {
      $filePath = $file['tmp_name'];
      $fileName = $file['name'];
      $zip->addFile($filePath, $fileName);
    }
    $zipped = $zip->close();
    if(!$zipped) return;
    $file =[
      'fhir_id' => $this->getFhirID(),
      'name' => $zipName = 'attachments',// name: "Unknown-1.png"
      'type' => 'application/zip',// type: "image/png"
      'tmp_name' => $zipPath, // tmp_name: "/tmp/php9IO1Qy"
      'error' => UPLOAD_ERR_OK,// error: 0
      'size' => $zipSize = filesize($zipPath),// size: 185269
    ];
    return $file;
  }

  public function getNormalizedTimestamp()
  {
    $timestamp = $this->getDate();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getNormalizedLocalTimestamp() {
    $timestamp = $this->getDate();
    return $this->getLocalTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getData(): array
  {
    $keys = [
      'type',
      'date',
      'practice_setting',
      'normalized_timestamp',
      'local_timestamp',
      'author_type',
      'author_display',
      'loinc_codes',
      'html',
    ];
    $data = $this->getDataOnly($keys);
    // add attachments
    $attachments = $this->getAttachments();
    foreach ($attachments as $index => $attachment) {
      $data['attachment-'.($index+1)] = $attachment;
    }
    return $data;
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts an AllergyIntolerance resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
   return [
      'type'                  => fn(self $resource) => $resource->getTypeText(),
      'date'                  => fn(self $resource) => $resource->getDate(),
      'practice_setting'      => fn(self $resource) => $resource->getPracticeSettingText(),
      'normalized_timestamp'  => fn(self $resource) => $resource->getNormalizedTimestamp(),
      'local_timestamp'       => fn(self $resource) => $resource->getNormalizedLocalTimestamp(),
      'author_type'           => fn(self $resource) => $resource->getAuthorType(),
      'author_display'        => fn(self $resource) => $resource->getAuthorDisplay(),
      'loinc_codes'           => fn(self $resource) => $resource->getLoincCodes(),
      'html'                  => fn(self $resource) => $resource->getHTML(),
      'sanitize_html'          => fn(self $resource) => $resource->getHTML(),
      'attachments'           => fn(self $resource) => $resource->getAttachments(),
   ];
  }
  
}