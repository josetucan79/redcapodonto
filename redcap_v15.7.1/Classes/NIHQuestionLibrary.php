<?php

class NIHQuestionLibrary extends FieldBank
{
    // NIH API Urls
    private $apiUrl = "https://cde.nlm.nih.gov/api/";
    private $apiSearchUrl = "https://cde.nlm.nih.gov/server/de/search";

    private $apiSearchParams = [
        "excludeAllOrgs"=>false,
        "excludeOrgs"=>[],
        "includeAggregations"=>true,
        "nihEndorsed"=>false,
        "page"=>"",
        "resultPerPage"=>20,
        "searchTerm"=>"",
        "searchToken"=>"",
        "selectedDatatypes"=>[],
        "selectedElements"=>[],
        "selectedElementsAlt"=>[],
        "selectedOrg"=>"",
        "selectedStatuses"=>[],
        "visibleStatuses"=>["Preferred Standard", "Standard", "Qualified"]
    ];

    // We are ignoring these orgs
    private $ignoreOrgList = ["TEST", "PhenX", "PROMIS / Neuro-QOL"];
    private $ignoreStewardList = ["TEST", "PhenX", "PROMIS", "PROMIS / Neuro-QOL"];

    // UMLS API Settings
    private $utsApiUrl = "https://utslogin.nlm.nih.gov/cas/v1/api-key";
    private $ticketService = "http://umlsks.nlm.nih.gov";

    private $cdeExists = [];

    const PRIVATE_PV_MESSAGE = "Login to see the value.";
    const QUESTION_TAG_TEXT = "Question Text";
    const PREFERRED_QUESTION_TAG_TEXT = "Preferred Question Text";

    /**
     * Get single CDE detail from API response by tinyId
     * @param string $tinyId
     * @return array|boolean
     */
    public function getSingleCDE($tinyId)
    {
        $apiKey = $GLOBALS['fieldbank_nih_cde_key'];

        $url = $this->apiUrl."de/$tinyId";
        // Confirmed from NIH: retired the use of TGT / ST, and should use apiKey instead
        $url .= ($apiKey != '') ? "?apiKey=$apiKey" : "";
        $response = http_get($url);
        $array = json_decode($response, true);
        return is_array($array) ? $array : false;
    }

    /**
     * Get CDE listing from API response for keyword and org passed
     * @param string $keyword
     * @param string $org
     * @param int $page_num
     * @return array|boolean
     */
    public function search($keyword, $org="", $page_num = 1, $nih_endorsed=false)
    {
        $prefixAnd = "";
        $keywordSearchTerm = "";
        $excludeStewardSearchTerm = "";
        $includeOrgSearchTerm = "";
        $excludeOrgSearchTerm = "";

        // Set search values
        if ($keyword != '') {
            // Set keyword search param
            $keywordSearchTerm = "(".$keyword . ")";
        }
        // Exclude Steward from search
        if (!empty($this->ignoreStewardList)) {
            foreach ($this->ignoreStewardList as $excludeStewardName) {
                $excludeStewardSearchTerm .= $prefixAnd.'NOT steward:"'.$excludeStewardName.'"';
                $prefixAnd = " AND ";
            }
        }

        if (isset($_SESSION['orgOptions']) && !empty($_SESSION['orgOptions']) && ($keyword != '' || $org != '')) {
            $prefixOr = "";
            foreach ($_SESSION['orgOptions'] as $includeOrg) {
                $includeOrgSearchTerm .= $prefixOr.'classification.stewardOrg.name:"'.$includeOrg['key'].'"';
                $prefixOr = " OR ";
            }
        } elseif (!empty($this->ignoreOrgList)) {
            $prefixAnd = "";
            // Exclude Classifications from search
            foreach ($this->ignoreOrgList as $excludeOrgName) {
                $excludeOrgSearchTerm .= $prefixAnd.'NOT classification.stewardOrg.name:"' . $excludeOrgName . '"';
                $prefixAnd = " AND ";
            }
        }

        // Build searchTerm param
        $prefixAnd = "";
        if ($keywordSearchTerm != '') {
            $this->apiSearchParams["searchTerm"] .= $keywordSearchTerm;
            $prefixAnd = " AND ";
        }
        if ($excludeStewardSearchTerm != '') {
            $this->apiSearchParams["searchTerm"] .= $prefixAnd."(".$excludeStewardSearchTerm.")";
            $prefixAnd = " AND ";
        }
        if ($includeOrgSearchTerm != '') {
            $this->apiSearchParams["searchTerm"] .= $prefixAnd."(".$includeOrgSearchTerm.")";
            $prefixAnd = " AND ";
        } elseif ($excludeOrgSearchTerm != '') {
            $this->apiSearchParams["searchTerm"] .= $prefixAnd."(".$excludeOrgSearchTerm.")";
        }

        $this->apiSearchParams["selectedOrg"] = $org;
        $this->apiSearchParams["page"] = $page_num;
        $this->apiSearchParams["searchToken"] = Session::sessionId();
        $this->apiSearchParams["nihEndorsed"] = $nih_endorsed;

        // API request
        $response = http_post($this->apiSearchUrl, $this->apiSearchParams, 30, 'application/json');
        $array = json_decode($response, true);

        if ((!empty($keyword) || !empty($org)) && !empty($array)) {
            // Replace "Login to see.." text from PVs values
            $this->replacePV($array);
        }

        return is_array($array) ? $array : false;
    }

    /**
     * Replace PV Values if contains text "Login to see.." and store in db with updated PVs
     * @param array $array
     * @return void
     */
    public function replacePV(&$array) {
        foreach ($array['cdes'] as $key => $cde) {
            $tinyId = $cde['tinyId'];

            $noOfPVs = $cde['valueDomain']['nbOfPVs'];
            if ($noOfPVs > 0) {
                $thisPVs = $cde['valueDomain']['permissibleValues'];
                $replace_pvs = false;

                // Check if tinyId choices are already stored in cde_cache table
                $this->cdeExists[$tinyId] = $this->isCDECacheExists($tinyId);

                // If yes, check if cde cache is latest or not
                $isLatestCache = false;
                if ($this->cdeExists[$tinyId] == true) {
                    $cacheUpdatedOn = $this->fetchCDECache($tinyId, 'updated_on');
                    $cacheUpdatedOn = date("Y-m-d\TH:i:s.000\Z", strtotime($cacheUpdatedOn));

                    $cdeUpdatedOn = $cde['updated'];
                    $isLatestCache = ($cacheUpdatedOn > $cdeUpdatedOn);
                }
                $isPartialPVs = (($noOfPVs > count($thisPVs)) && ($isLatestCache == false));
                // If cde cache is not latest, update with new values from NLM API response
                if ($isLatestCache == false || $isPartialPVs == true) {
                    foreach ($thisPVs as $k => $thisPV) {
                        // Check if PVs contain "Login to see..." text
                        if (@in_array(self::PRIVATE_PV_MESSAGE, array($thisPV['permissibleValue'], $thisPV['valueMeaningCode'], $thisPV['codeSystemName'], $thisPV['valueMeaningName']))) {
                            $replace_pvs = true;
                            break;
                        }
                    }
                    if ($replace_pvs == true || $isPartialPVs == true) {
                        $result = $this->getSingleCDE($tinyId);
                        $element_enum = [];
                        $number = 0;
                        $iterateArray = ($isPartialPVs == true) ? $result['valueDomain']['permissibleValues'] : $thisPVs;
                        foreach ($iterateArray as $k => $thisPV) {
                            $number++;
                            // Generate value from code
                            $value = isset($result['valueDomain']['permissibleValues'][$k]['valueMeaningCode']) ? $this->generateValue($result['valueDomain']['permissibleValues'][$k]['valueMeaningCode']) : "";
                            //if generated value is blank, generate from value
                            if ($value == '') {
                                $value = $this->generateValue($result['valueDomain']['permissibleValues'][$k]['permissibleValue']);
                            }
                            //if generated from value is blank, assign default auto incremented number
                            if ($value == '') {
                                $value = $number;
                            }

                            $pv_value = isset($result['valueDomain']['permissibleValues'][$k]['permissibleValue']) ? $result['valueDomain']['permissibleValues'][$k]['permissibleValue'] : "";

                            $array['cdes'][$key]['valueDomain']['permissibleValues'][$k]['permissibleValue'] = $value;
                            $array['cdes'][$key]['valueDomain']['permissibleValues'][$k]['valueMeaningCode'] = isset($result['valueDomain']['permissibleValues'][$k]['valueMeaningCode']) ? $result['valueDomain']['permissibleValues'][$k]['valueMeaningCode'] : "";
                            $array['cdes'][$key]['valueDomain']['permissibleValues'][$k]['codeSystemName'] = isset($result['valueDomain']['permissibleValues'][$k]['codeSystemName']) ? $result['valueDomain']['permissibleValues'][$k]['codeSystemName'] : "";
                            $array['cdes'][$key]['valueDomain']['permissibleValues'][$k]['valueMeaningName'] = isset($result['valueDomain']['permissibleValues'][$k]['valueMeaningName']) ? $result['valueDomain']['permissibleValues'][$k]['valueMeaningName'] : $pv_value;

                            // If valueMeaningName is blank set label to permissibleValue
                            $label = isset($result['valueDomain']['permissibleValues'][$k]['valueMeaningName']) ? $result['valueDomain']['permissibleValues'][$k]['valueMeaningName'] : $pv_value;

                            $element_enum[] =  $value. ", " . $label;
                        }
                        $element_enum_str = implode("\n", $element_enum);
                        $this->updateCDECache($tinyId, $element_enum_str, $cde['steward'], $this->cdeExists[$tinyId]);
                    }
                }
            }
        }
    }

    /**
     * Check if CDE exists in redcap_cde_cache table
     * @param string $tinyId
     * @return boolean
     */
    public function isCDECacheExists($tinyId) {
        return db_result(db_query("SELECT COUNT(1) FROM redcap_cde_cache WHERE tinyId = '".db_escape($tinyId)."' LIMIT 1"), 0);
    }

    /**
     * Insert/Update to redcap_cde_cache table
     * @param string $tinyId
     * @param string $element_enum_str
     * @param string $steward
     * @param boolean $cdeExists
     * @return void
     */
    public function updateCDECache($tinyId, $element_enum_str, $steward, $cdeExists) {
        $element_enum_str = DataEntry::autoCodeEnum($element_enum_str);

        // Prevent caching invalid values
        if (strpos($element_enum_str, NIHQuestionLibrary::PRIVATE_PV_MESSAGE) !== false) {
            return;
        }

        if ($cdeExists) {
            // Update CDE cache
            $sql = "UPDATE redcap_cde_cache SET "
                . "choices = '".db_escape($element_enum_str)."', "
                . "updated_on = '".NOW."'"
                . "WHERE tinyId = '$tinyId'";
            db_query($sql);
        } else {
            // Insert into CDE cache
            $sql = "INSERT INTO redcap_cde_cache (tinyId, steward, choices, updated_on)
                    VALUES
                    ('".$tinyId."',
                    '".$steward."',
                    '".$element_enum_str."',
                    '".NOW."')";
            db_query($sql);
        }
    }

    /**
     * Fetch specific field from redcap_cde_cache table by tinyId
     * @param string $tinyId
     * @param string $field
     * @return string
     */
    function fetchCDECache($tinyId, $field='') {
        if ($tinyId == '') return false;
        if ($field == '') $field = "*";
        $sql = "SELECT $field
                FROM redcap_cde_cache
                WHERE tinyId = '$tinyId'";
        return db_result(db_query($sql), 0);
    }

    /**
     * Render/format search array from API response
     * @param array $searchItems
     * @return array|string
     */
    public function renderSearch($searchItems)
    {
        if (!is_array($searchItems)) return "ERROR: Something went wrong. Please try again.";

        $returnOverview = [];
        $questionTextsAllowed = array(self::QUESTION_TAG_TEXT, self::PREFERRED_QUESTION_TAG_TEXT);
        // Result Overview Information
        if (isset($searchItems['totalNumber'])) {
            // Store total number of records from search result
            $returnOverview['totalNumber'] = $searchItems['totalNumber'];
        }
        if (isset($searchItems['aggregations'])) {
            // Orgs list for this search
            if (isset($searchItems['aggregations']['orgs']['orgs']['buckets']) && is_array($searchItems['aggregations']['orgs']['orgs']['buckets'])) {
                foreach ($searchItems['aggregations']['orgs']['orgs']['buckets'] as $orgs) {
                    $returnOverview['orgOptions'][] = $orgs;
                }
            }
        }

        $returnOverview['serviceName'] = "nih";

        $returnItems = array();
        foreach ($searchItems['cdes'] as $key=>$item) {
            $tinyId = $item['tinyId'];
            // Basic info
            $returnItems[$key] = ['tinyId' => $tinyId, 'name' => (isset($item['designations'][0]['designation']) ? $item['designations'][0]['designation'] : ""), 'definition' => (isset($item['definitions'][0]['definition']) ? $item['definitions'][0]['definition'] : ""), 'steward' => $item['steward']];
            // Data type
            $returnItems[$key]['datatype'] = isset($item['valueDomain']['datatype']) ? $item['valueDomain']['datatype'] : "";
            $returnItems[$key]['min'] = isset($item['valueDomain']['datatypeNumber']['minValue']) ? $item['valueDomain']['datatypeNumber']['minValue'] : "";
            $returnItems[$key]['max'] = isset($item['valueDomain']['datatypeNumber']['maxValue']) ? $item['valueDomain']['datatypeNumber']['maxValue'] : "";

            // Org - Used By
            $returnItems[$key]['usedBy'] = "";
            $usedByArr = [];
            if (isset($item['classification']) && !empty($item['classification'])) {
                foreach ($item['classification'] as $classification) {
                    if (!in_array($classification['stewardOrg']['name'], $this->ignoreOrgList)) {
                        $usedByArr[] = $classification['stewardOrg']['name'];
                    }
                }
                $returnItems[$key]['usedBy'] = implode(";", $usedByArr);
            }
            //Question Texts
            $returnItems[$key]['questionTexts'] = [];

            foreach ($item['designations'] as $designations) {
                if (array_intersect($questionTextsAllowed, $designations['tags']) && $designations['designation'] != $item['designations'][0]['designation']) {
                    $returnItems[$key]['questionTexts'][] = $designations['designation'];
                }
            }
            // Multiple choice codings
            if (isset($item['valueDomain']['permissibleValues']) && is_array($item['valueDomain']['permissibleValues'])) {
                $cdeExists = (isset($this->cdeExists[$tinyId])) ? $this->cdeExists[$tinyId] : false;
                if ($cdeExists == true) {
                    $choices = $this->fetchCDECache($item['tinyId'], 'choices');
                    $choices = parseEnum($choices);
                    foreach ($choices as $choiceValue => $choiceLabel) {
                        // Set label as value if getting blank label from API response
                        $choiceLabel = ($choiceLabel != '') ? $choiceLabel : $choiceValue;

                        $returnItems[$key]['choices'][$choiceValue] = $choiceLabel;
                    }
                } else {
                    $number = 0;

                    foreach ($item['valueDomain']['permissibleValues'] as $thisPV) {
                        $code = '';
                        if (isset($thisPV['valueMeaningCode'])) {
                            $code = $thisPV['valueMeaningCode'];
                        }

                        // Generate value from code
                        $value = $this->generateValue($code);
                        //if generated value is blank, generate from value
                        if ($value == '') {
                            $value = $this->generateValue($thisPV['permissibleValue']);
                        }
                        //if generated from value is blank, assign default auto incremented number
                        if ($value == '') {
                            $number++;
                            $value = $number;
                        }

                        // Set label as value if getting blank label from API response
                        $thisPV['valueMeaningName'] = ($thisPV['valueMeaningName'] != '') ? $thisPV['valueMeaningName'] : $value;

                        $returnItems[$key]['choices'][$value] = $thisPV['valueMeaningName'];
                    }
                }
            }
        }
        return array('overview' => $returnOverview, 'result' => $returnItems);
    }

    /**
     * Generate value part for given code
     * @param string $code
     * @return string
     */
    public function generateValue($code) {
        if ($code != '') {
            // check if space or comma found in string
            if (strpos($code, " ") !== false || strpos($code, ",") !== false) {
                return "";
            } else {
                return $code;
            }
        } else {
            return "";
        }
    }
}
