<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Shared;

use Vanderbilt\REDCap\Classes\JsonParser\Nodes\EmptyNode;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;

class OperationOutcome extends AbstractResource
{

  /**
   * explanation
   *
   * @return string
   */
  public function getDiagnostics() {
    $diagnostic = $this->scraper()->issue->diagnostics ?? '';
    return strval($diagnostic);
  }

  /**
   * FHIR BGT token used to break the glass
   *
   * @return string
   */
  public function getFhirBgtToken() {
    $extension = $this->scraper()->issue->extension->where('url','~','fhir-btg-token$');
    $token = $extension->valueString;
    if($token instanceof EmptyNode) return;
    return strval($token);
  }

  public function getIssueSeverity($index=0) {
    return $this->scraper()->issue[$index]->severity->join('');
  }

  public function getIssueText($index=0) {
    return $this->scraper()->issue[$index]->details->text->join('');
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts a OperationOutcome resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
    $data = [
      'diagnostics' => fn(self $resource) => $resource->getDiagnostics(),
      'fhir-bgt-token' => fn(self $resource) => $resource->getFhirBgtToken(),
      'issue_severity_1' => fn(self $resource) => $resource->getIssueSeverity(0),
      'issue_text_1' => fn(self $resource) => $resource->getIssueText(0),
    ];
    return $data;
  }
  
}