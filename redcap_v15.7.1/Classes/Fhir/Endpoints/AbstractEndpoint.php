<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\Traits\CanRemoveExtraSlashesFromUrl;

abstract class AbstractEndpoint implements EndpointInterface
{
  use CanRemoveExtraSlashesFromUrl;

  /**
   * interaction types
   * 
   * @see https://www.hl7.org/fhir/overview-dev.html#Interactions
   */
  const INTERACTION_READ = 'read';
  const INTERACTION_UPDATE = 'update';
  const INTERACTION_DELETE = 'delete';
  const INTERACTION_CREATE = 'create';
  const INTERACTION_SEARCH = 'search';
  const INTERACTION_HISTORY = 'history';
  const INTERACTION_TRANSACTION = 'transaction';
  const INTERACTION_OPERATION = 'operation';
  const INTERACTIONS = [
    self::INTERACTION_READ,
    self::INTERACTION_UPDATE,
    self::INTERACTION_DELETE,
    self::INTERACTION_CREATE,
    self::INTERACTION_SEARCH,
    self::INTERACTION_HISTORY,
    self::INTERACTION_TRANSACTION,
    self::INTERACTION_OPERATION,
  ];

  /**
   * part of the FHIR URL template
   * that identifies the resource type
   *
   * @var string
   */
  private $resource_identifier = null;

  /**
   *
   * @var string
   */
  private $base_url;

  public function __construct($base_url)
  {
    $this->base_url = $base_url;
  }

  public function getBaseUrl()
  {
    return $this->base_url;
  }

  public function getReadRequest($id)
  {
    $method = FhirRequest::METHOD_GET;
    $resource_identifier = $this->getResourceIdentifier();
    $URL = $this->removeExtraSlashesFromUrl(sprintf("%s/%s/%s", $this->base_url, $resource_identifier, $id));
    return new FhirRequest($URL, $method);
  }

  public function getSearchRequest($params=[])
  {
    $method = FhirRequest::METHOD_GET;
    $resource_identifier = $this->getResourceIdentifier();
    $URL = $this->removeExtraSlashesFromUrl(sprintf("%s/%s", $this->base_url, $resource_identifier));
    $options = ['query'=> $params];
    return new FhirRequest($URL, $method, $options);
  }

  /**
   * Undocumented function
   *
   * @param EndpointVisitorInterface $visitor
   * @return mixed
   */
  public function accept($visitor)
  {
    return $visitor->visit($this);
  }


  /**
   * Generates a comma-separated list of items extracted from the provided fields,
   * suitable for use as a value in a URL query parameter. This function is particularly
   * useful for converting mapped FHIR elements
   * into a format that can be utilized in URL query strings.
   *
   * @param array $fields An array of strings representing the mapped FHIR elements. Each element
   *                      is expected to start with one of the allowed items, followed by a hyphen
   *                      and then any character sequence. For example, 'active-problem-list', 'resolved-problem-list'.
   * @param array $allowedItems An array of allowed item names. These are used to construct a regular
   *                            expression to match against the beginning of each element in $fields.
   *                            The items in this array should correspond to valid FHIR element names
   *                            or other relevant identifiers for the application context.
   *
   * @return string A comma-separated string of matched items from the $fields array. This string is
   *                suitable for inclusion as a value in a URL query parameter. If no matches are found,
   *                an empty string is returned.
   */
  public function generateQueryParamValue($fields = [], $allowedItems = [])
  {
      // Convert the allowed items into a regular expression string
      $itemString = implode('|', array_map(function($item) {
          return preg_quote($item, '/');
      }, $allowedItems));

      $item_list = [];
      $item_reg_exp = '/^(?<item>' . $itemString . ')-.+$/i';

      foreach ($fields as $field) {
          preg_match($item_reg_exp, $field, $matches);
          $matched_item = $matches['item'] ?? null;
          if ($matched_item) $item_list[] = $matched_item;
      }

      // Generate the comma-separated string suitable for URL query parameters
      $queryParamValue = implode(',', $item_list);
      return $queryParamValue;
  }

}