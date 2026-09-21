<?php

class REDCapQuestionLibrary extends FieldBank
{
    // REDCap Question Library API Urls
    private $apiSearchUrl = "https://redcap.vumc.org/plugins/redcap_consortium/question_bank_web_service.php";

    private $dateValidations = ['date_dmy', 'date_mdy', 'date_ymd', 'datetime_dmy', 'datetime_mdy', 'datetime_ymd', 'datetime_seconds_dmy', 'datetime_seconds_mdy', 'datetime_seconds_ymd'];

    /**
     * Get CDE listing from API response for keyword and org passed
     * @param string $keyword
     * @param string $org
     * @param int $page_num
     * @return array|boolean
     */
    public function search($keyword="", $org="", $page_num = 1)
    {
        // Set search values
        $this->apiSearchUrl .= "?search=".urlencode($keyword)."&page=".$page_num;

        // API request
        $response = http_get($this->apiSearchUrl);
        $array = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $array;
        } else {
            return $response;
        }
    }

    /**
     * Render/format search array from API response
     * @param array $searchItems
     * @return array|string
     */
    public function renderSearch($searchItems)
    {
        $returnOverview['serviceName'] = "redcap";
        $returnItems = array();
        if (!is_array($searchItems) && !empty($searchItems)) {
            $returnItems['message'] = $searchItems;
        } elseif (!is_array($searchItems)) {
            return "ERROR: Something went wrong. Please try again.";
        }

        if (is_array($searchItems)) {
            // Result Overview Information
            if (isset($searchItems['totalNumber'])) {
                $returnOverview['resultPerPage'] = $searchItems['resultPerPage'];
                // Store total number of records from search result
                $returnOverview['totalNumber'] = $searchItems['totalNumber'];
            }
            foreach ($searchItems['result'] as $key => $item) {
                $tinyId = $item['question_id'];
                // Basic info
                $returnItems[$key] = ['questionId' => $item['question_id'], 'name' => $item['name'], 'definition' => $item['description'], 'steward' => ""];
                // Data type
                $returnItems[$key]['datatype'] = $item['datatype'];
                $returnItems[$key]['min'] = $item['minimum_value'];
                $returnItems[$key]['max'] = $item['maximum_value'];
                $returnItems[$key]['field_note'] = $item['field_note'];
                $returnItems[$key]['variable_name'] = $item['variable_name'];
                $returnItems[$key]['field_validation'] = $item['field_validation'];

                if ($item['datatype'] == 'text') {
                    if (in_array($item['field_validation'], $this->dateValidations)) {
                        $returnItems[$key]['datatype'] = 'date';
                    }
                    if ($item['field_validation'] == 'time') {
                        $returnItems[$key]['datatype'] = 'time';
                    }
                }
                // Org - Used By
                $returnItems[$key]['usedBy'] = "";

                //Question Texts
                $returnItems[$key]['questionTexts'] = [];

                // Multiple choice codings
                if (isset($item['permissible_values']) && !empty($item['permissible_values'])) {
                    $pvArr = explode("\n", $item['permissible_values']);
                    foreach ($pvArr as $pvs) {
                        list($choiceValue, $choiceLabel) = explode(",", $pvs);
                        $returnItems[$key]['choices'][$choiceValue] = $choiceLabel;
                    }
                }
            }
        }
        return array('overview' => $returnOverview, 'result' => $returnItems);
    }
}