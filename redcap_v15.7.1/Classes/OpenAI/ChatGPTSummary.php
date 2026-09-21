<?php
namespace Vanderbilt\REDCap\Classes\OpenAI;
use Vanderbilt\REDCap\Classes\OpenAI\GPT_3_Encoder\GPT3;

class ChatGPTSummary {
    const TEMPERATURE = 0.5;
	public function __construct() {
		// Other code to run when object is instantiated
	}

    /**
     * Replace placeholder [STRING] with input string from prompt text
     *
     * @param string $chatGPTString
     * @param string $promptText
     *
     * @return string
     */
    public function getContentText($chatGPTString, $promptText) {
        return str_replace('[STRING]', $chatGPTString, trim($promptText));
    }

    /**
     * Generate response by calling Azure OpenAI API endpoint
     *
     * @param string $chatString
     * @param string $promptText
     * @param string $typeOfCall
     * @param float $temperature
     *
     * @return array
     */
    public function generateResponse($chatString, $promptText, $typeOfCall, $temperature = '')
    {
        if (!empty($promptText)) {
            if (!empty($chatString)) {
                $contentText = $this->getContentText($chatString, $promptText);
            } else {
                $contentText = $promptText;
            }
        }
        if ($temperature == '') {
            $temperature = self::TEMPERATURE;
        }

        list ($openai_endpoint_url, $openai_api_key, $openai_api_version) = \AI::getServiceAttributes()['service_details'];
        $open_ai = new OpenAi($openai_api_key, $openai_endpoint_url);

       $complete = $open_ai->chat([
            "model" => $openai_api_version,
            "messages" => [
                [
                    "role" => "user",
                    "content" => $contentText
                ]
            ],
            "temperature" => $temperature,
            "max_tokens" => 4000,
            "frequency_penalty" => 0,
            "presence_penalty" => 0
        ], $openai_api_version);

        $completionDecoded = json_decode($complete,true);
        if (!is_array($completionDecoded)) {
            $completionDecoded = [];
            $response['errors'] = "ERROR - No response returned from the AI service";
        } elseif (array_key_exists("error", $completionDecoded)) {
            $response['errors'] = "ERROR - Code {$completionDecoded['error']['code']}: {$completionDecoded['error']['message']}";
        }

        $chatGptResponse = filter_tags(trim($completionDecoded["choices"][0]["message"]["content"] ?? ""));
        $response['response'] = $chatGptResponse;

        // Log the AI call in a db table
        \AI::logApiCall(\AI::$serviceAzureOpenAI, $typeOfCall, $contentText, $chatGptResponse, (defined("PROJECT_ID") ? PROJECT_ID : null));

        return $response;
    }

    /**
     * Get summarized text by passing reports fields data by calling Azure OpenAI API endpoint
     *
     * @param int $project_id
     * @param int $report_id
     * @param string $fieldName
     * @param string $prependText
     * @param string $record
     * @param boolean $startRecord
     * @param float $temperature
     *
     * @return array
     */
    public function getReportsSummaryResult($project_id, $report_id, $fieldName = null, $prependText = '', $startRecord = false, $temperature = '')
    {
        $Proj = new \Project($project_id);
        $response = [];

        $response['fieldName'] = $fieldName;
        $response['prependText'] = $prependText;

        if ($temperature == '') {
            $temperature = self::TEMPERATURE;
        }

        $recordField = $Proj->table_pk;

        // Get records belongs to a report
        $records = \Records::getRecordListForReport($project_id, $report_id, $fieldName);

        $chatGptString = "";
        if (!empty($records)) {
            $data = \REDCap::getData([
                "project_id" => $project_id,
                "records" => $records,
                "fields" => [$fieldName, $recordField],
                "return_format" => "json-array"
            ]);

            $stopRecord = false;

            $neededTokens = count(GPT3\GPT3Encoder::gpt_encode($prependText)) + 2;
            $usedTokens = 0;
            $started = ($startRecord == "");

            foreach($data as $recordDetails) {
                if(!$started && $startRecord != $recordDetails[$recordField] || empty($recordDetails[$fieldName])) {
                    continue;
                }
                $gptEncode = GPT3\GPT3Encoder::gpt_encode($recordDetails[$fieldName]);
                $neededTokens += count($gptEncode) + 2;

                if($neededTokens > 7500) {
                    $stopRecord = $recordDetails[$recordField];
                }
                if ($stopRecord === false) {
                    $usedTokens = $neededTokens;
                    $chatGptString .= $recordDetails[$fieldName] . "\n";
                }
            }
        }

        if ($chatGptString == '') {
            $response['response'] = "<div class='error'>".\RCView::tt('openai_121','')."</div>";
            return $response;
        }
        list ($openai_endpoint_url, $openai_api_key, $openai_api_version) = \AI::getServiceAttributes()['service_details'];
        $open_ai = new OpenAi($openai_api_key, $openai_endpoint_url);

        $complete = $open_ai->chat([
            "model" => $openai_api_version,
            "messages" => [
                [
                    "role" => "user",
                    "content" => $prependText.":\n".$chatGptString
                ]
            ],
            "temperature" => $temperature,
            "max_tokens" => 400,
            "frequency_penalty" => 0,
            "presence_penalty" => 0
        ], $openai_api_version);
        $completionDecoded = json_decode($complete,true);

        if (!is_array($completionDecoded)) {
            $completionDecoded = [];
            $response['errors'][] = "ERROR - No response returned from the AI service";
        } elseif (isset($completionDecoded['error']) || json_last_error() === JSON_ERROR_NONE) {
            if (array_key_exists("error", $completionDecoded)) {
                $response['errors'][] = "ERROR - Code {$completionDecoded['error']['code']}: {$completionDecoded['error']['message']}";
            }
        }

        if($stopRecord) {
            $response['errors'][] = "Had to stop before record $stopRecord due to prompt being too long";
        }

        $chatGptResponse = filter_tags(trim($completionDecoded["choices"][0]["message"]["content"]));
        $response['response'] = $chatGptResponse;
        if (isset($response['errors'])) {
            $response['errors'] = implode("<br>", $response['errors']);
        }

        // Log the AI call in a db table
        \AI::logApiCall(\AI::$serviceAzureOpenAI, \AI::$callTypeDataSummary, $prependText.":\n".$chatGptString, $chatGptResponse, $project_id);

        return $response;
    }
}