<?php

class NCIQuestionLibrary extends FieldBank
{
    // NCI API Urls
    private $apiSearchUrl = "https://cadsrapi.nci.nih.gov/cadsrapi4/GetXML";

    private $cdeExists = [];
    private $cacheData = [];
    /**
     * Perform http_get and return response
     * @param string $url
     * @return string
     */
    public function getAPIResponse($url)
    {
        $response = http_get($url);
        return $response;
    }

    /**
     * Get CDE listing from API response for keyword and org passed
     * @param string $keyword
     * @param string $org
     * @param int $page_num
     * @return string|boolean
     */
    public function search($keyword, $org="", $page_num = 1)
    {
        $this->apiSearchUrl .= "?query=gov.nih.nci.cadsr.domain.DataElement&gov.nih.nci.cadsr.domain.DataElement";
        if ($keyword != '') {
            $this->apiSearchUrl .= "[@longName=*".urlencode($keyword)."*]";
        }
        $this->apiSearchUrl .= "[@latestVersionIndicator=Yes]";
        $resultPerPage = $this->getItemsPerPage();
        $startIndex = ($page_num-1) * $resultPerPage;
        $this->apiSearchUrl .= "&startIndex=".$startIndex."&pageSize=".$resultPerPage."&resultCounter=1000";

        // API request
        $xmlResponse = $this->getAPIResponse($this->apiSearchUrl);
        return $xmlResponse;
    }

    /**
     * Render/format search array from API response
     * @param string $xmlResponse
     * @return array|string
     */
    public function renderSearch($xmlResponse)
    {
        $xml = simplexml_load_string($xmlResponse);
        $xmlResponse = (array) $xml->queryResponse;
        $returnOverview = [];
        $returnItems = [];
        $returnOverview['totalNumber'] = $xmlResponse['recordCounter'][0];
        $returnOverview['serviceName'] = parent::SERVICE_NCI;

        foreach ($xmlResponse['class'] as $key => $class) {
            foreach ($class->children() as $child) {
                $name = (array)$child->attributes()->{'name'};
                $childArr = (array)$child;
                if ($name[0] == 'publicID') $publicId = $childArr[0];
                if ($name[0] == 'longName') $longName = $childArr[0];
                if ($name[0] == 'version') $version = $childArr[0];
                if ($name[0] == 'dateModified') $cdeUpdatedOn = date("Y-m-d\TH:i:s.000\Z", strtotime($childArr[0]));

                if ($name[0] == 'preferredDefinition') $definition = $childArr[0];
                //$returnItems[$key] = ['publicId' => $publicId, 'name' => $longName, 'definition' => $definition, 'steward' => ""];

                $returnItems[$key]['publicId'] = $publicId;
                $returnItems[$key]['name'] = $longName;
                $returnItems[$key]['definition'] = $definition;
                $returnItems[$key]['version'] = $version;
                $returnItems[$key]['steward'] = "";

                // Org - Used By
                $returnItems[$key]['usedBy'] = "";

                // Check if publicId choices are already stored in cde_cache table
                if ($publicId != '') {
                    $this->cdeExists[$publicId] = $this->isCDECacheExists($publicId);
                }

                // If yes, check if cde cache is latest or not
                $isLatestCache = false;
                if ($this->cdeExists[$publicId] == true) {
                    $cacheUpdatedOn = $this->fetchCDECache($publicId, 'updated_on');
                    $cacheUpdatedOn = date("Y-m-d\TH:i:s.000\Z", strtotime($cacheUpdatedOn));

                    $isLatestCache = ($cacheUpdatedOn > $cdeUpdatedOn);
                }
                $element_enum_str = "";
                if ($name[0] == 'valueDomain') {
                    if ((bool)$this->cdeExists[$publicId] == true && $isLatestCache == true) {
                        $choices = $this->fetchCDECache($publicId, 'choices');
                        $choices = parseEnum($choices);
                        foreach ($choices as $choiceValue => $choiceLabel) {
                            // Set label as value if getting blank label from API response
                            $choiceLabel = ($choiceLabel != '') ? $choiceLabel : $choiceValue;
                            $returnItems[$key]['choices'][$choiceValue] = $choiceLabel;
                        }
                        $returnItems[$key]['datatype'] = $this->fetchCDECache($publicId, 'datatype');
                    } else {
                        $valueDomainArr = (array)$child->attributes('http://www.w3.org/1999/xlink');
                        $getResponse = $this->getAPIResponse($valueDomainArr['@attributes']['href']);
                        $xmlGetResponse = simplexml_load_string($getResponse);
                        $getResponse = (array)$xmlGetResponse->queryResponse;

                        foreach ($getResponse['class'] as $key1 => $class1) {
                            $name1 = (array)$class1->attributes()->{'name'};
                            $childArr1 = (array)$class1;

                            if ($name1['0'] == 'valueDomainPermissibleValueCollection') {
                                $returnItems[$key]['datatype'] = 'value list';
                                $pVArr = (array)$class1->attributes('http://www.w3.org/1999/xlink');
                                $PVsArr = $this->getPermissibleValues($pVArr['@attributes']['href']);
                                $element_enum = [];
                                foreach ($PVsArr as $val => $label) {
                                    $returnItems[$key]['choices'][$val] = $label;
                                    $element_enum[] = $val . ", " . $label;
                                }
                                $element_enum_str = implode("\n", $element_enum);
                            } else {
                                if ($name1['0'] == 'datatypeName') {
                                    $returnItems[$key]['datatype'] = $childArr1[0];
                                }
                                if ($name1['0'] == 'highValueNumber') {
                                    $returnItems[$key]['max'] = $childArr1[0];
                                    $returnItems[$key][$name1[0]] = $childArr1[0];
                                }
                                if ($name1['0'] == 'lowValueNumber') {
                                    $returnItems[$key]['min'] = $childArr1[0];
                                    $returnItems[$key][$name1[0]] = $childArr1[0];
                                }
                            }
                        }
                        $this->cacheData[$publicId]['datatype'] = $returnItems[$key]['datatype'];
                        $this->cacheData[$publicId]['element_enum_str'] = $element_enum_str;
                        $this->cacheData[$publicId]['steward'] = "";
                    }
                }

                if ($name['0'] == 'questionCollection') {
                    if ((bool)$this->cdeExists[$publicId] == true && $isLatestCache == true) {
                        $questionCache = $this->fetchCDECache($publicId, 'question');
                        $questionsArr = ($questionCache != '') ? explode("##", $questionCache) : [];
                    } else {
                        $questionColArr = (array)$child->attributes('http://www.w3.org/1999/xlink');
                        $questionsArr = $this->getQuestionTexts($questionColArr['@attributes']['href']);
                    }
                    $questions = [];
                    foreach ($questionsArr as $question) {
                        if (!isset($returnItems[$key]['questionTexts']) || !in_array($question, $returnItems[$key]['questionTexts'])) {

                            if ((bool)$this->cdeExists[$publicId] == false) {
                                $questions[] = $question;
                            }
                            $returnItems[$key]['questionTexts'][] = $question;
                        }
                    }
                    $questionStr = implode("##", $questions);
                    $this->cacheData[$publicId]['questions'] = $questionStr;
                }
            }

        }
        if (!empty($this->cdeExists)) {
            foreach ($this->cdeExists as $thisId=>$cdeExist) {
                if ((bool)$cdeExist == false) {
                    $this->updateCDECache($thisId, $this->cacheData[$thisId]['datatype'], $this->cacheData[$thisId]['questions'], $this->cacheData[$thisId]['element_enum_str'], $this->cacheData[$thisId]['steward'], $cdeExist);
                }
            }
        }
        return array('overview' => $returnOverview, 'result' => $returnItems);
    }

    /**
     * Returns Permissible values from API call
     * @param string $url
     * @return array
     */
    public function getPermissibleValues($url) {
        $returnPV = $this->renderGetApiResponse($url, 'pvs');
        $returnItem = [];
        foreach ($returnPV as $pv) {
            foreach ($pv as $val => $label) {
                $returnItem[$val] = $label;
            }
        }
        return $returnItem;
    }

    /**
     * Render API response for PVs and questions from response
     * @param string $url
     * @param string $flag
     * @return array
     */
    public function renderGetApiResponse($url, $flag = "pvs") {

        $getResponse = $this->getAPIResponse($url);
        $xml = simplexml_load_string($getResponse);
        $xmlResponse = (array) $xml->queryResponse;

        $returnItems = [];
        if (isset($xmlResponse['class'])) {
			foreach ($xmlResponse['class'] as $class) {
				foreach ($class->children() as $key => $child) {
					$name = (array)$child->attributes()->{'name'};
					$childArr = (array)$child;

					$valueDomainArr = (array)$child->attributes('http://www.w3.org/1999/xlink');
					$href = $valueDomainArr['@attributes']['href'];
					$returnArr[$name[0]]['name'] = $name[0];
					$returnArr[$name[0]]['val'] = $childArr[0];
					$returnArr[$name[0]]['href'] = $href;
					if ($name[0] == "questionText" && $flag == 'questions') {
						$returnItems[] = $childArr[0];
					}

					if ($name[0] == "permissibleValue" && $flag == 'pvs') {
						$returnItems[] = $this->renderGetPVApiResponse($href);
					}
				}
			}
		}
        return $returnItems;
    }

    /**
     * Render API response for PVs from response
     * @param string $url
     * @return array
     */
    public function renderGetPVApiResponse($url) {
        $getResponse = $this->getAPIResponse($url);
        $xml = simplexml_load_string($getResponse);
        $xmlResponse = (array) $xml->queryResponse;

        $returnArr = [];
        foreach ($xmlResponse['class'] as $class) {
            $name = (array) $class->attributes()->{'name'};
            $childArr = (array)$class;
            $valueDomainArr = (array) $class->attributes('http://www.w3.org/1999/xlink');
            $href = $valueDomainArr['@attributes']['href'];

            if ($name[0] == 'value') {
                $returnArr[$childArr[0]] = $childArr[0];
            }

        }
        return $returnArr;
    }

    /**
     * Get alternate questionTexts
     * @param string $url
     * @return array
     */
    public function getQuestionTexts($url) {

        $returnItem = $this->renderGetApiResponse($url, 'questions');
        return $returnItem;
    }

    /**
     * Insert/Update to redcap_cde_cache table
     * @param string $publicId
     * @param string $datatype
     * @param string $questions
     * @param string $element_enum_str
     * @param string $steward
     * @param boolean $cdeExists
     * @return void
     */
    public function updateCDECache($publicId, $datatype, $questions, $element_enum_str, $steward, $cdeExists) {
        $element_enum_str = DataEntry::autoCodeEnum($element_enum_str);
        if ($cdeExists) {
            // Update CDE cache
            $sql = "UPDATE redcap_cde_cache SET "
                . "datatype = '".db_escape($datatype)."', "
                . "question = '".db_escape($questions)."', "
                . "choices = '".db_escape($element_enum_str)."', "
                . "updated_on = '".NOW."'"
                . "WHERE publicId = '$publicId'";
            db_query($sql);
        } else {
            // Insert into CDE cache
            $sql = "INSERT INTO redcap_cde_cache (publicId, datatype, question, steward, choices, updated_on)
                    VALUES
                    ('".$publicId."',
                    '".$datatype."',
                    '".$questions."',
                    '".$steward."',
                    '".$element_enum_str."',
                    '".NOW."')";
            db_query($sql);
        }
    }

    /**
     * Check if CDE exists in redcap_cde_cache table
     * @param string $publicId
     * @return boolean
     */
    public function isCDECacheExists($publicId) {
        return db_result(db_query("SELECT COUNT(1) FROM redcap_cde_cache WHERE publicId = '".db_escape($publicId)."' LIMIT 1"), 0);
    }

    /**
     * Fetch specific field from redcap_cde_cache table by publicId
     * @param string $publicId
     * @param string $field
     * @return string
     */
    function fetchCDECache($publicId, $field='') {
        if ($publicId == '') return false;
        if ($field == '') $field = "*";
        $sql = "SELECT $field
                FROM redcap_cde_cache
                WHERE publicId = '$publicId'";
        return db_result(db_query($sql), 0);
    }
}
