<?php
namespace Vanderbilt\REDCap\Classes\GeminiAI;

use Vanderbilt\REDCap\Classes\OpenAI\GPT_3_Encoder\GPT3\GPT3Encoder;

class GeminiSummary {
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
     * Generate response by calling Gemini API endpoint
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

        list ($api_key, $api_model, $api_version) = \AI::getServiceAttributes()['service_details'];
        $gemini_ai = new GeminiAi($api_key, $api_model, $api_version);

        $complete = $gemini_ai->chat([
            "contents"=> [
                'parts' => [
                    'text' => $contentText
                ]
            ],
            "generationConfig" => [
                "temperature" => $temperature,
                "topK" => 1,
                "topP" => 1,
                "maxOutputTokens" => 4000,
                "stopSequences" => []
            ],
            "safetySettings" => [
                [
                    "category" => "HARM_CATEGORY_HARASSMENT",
                    "threshold" => "BLOCK_ONLY_HIGH"
                ],
                [
                    "category" => "HARM_CATEGORY_HATE_SPEECH",
                    "threshold" => "BLOCK_ONLY_HIGH"
                ],
                [
                    "category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT",
                    "threshold" => "BLOCK_ONLY_HIGH"
                ],
                [
                    "category" => "HARM_CATEGORY_DANGEROUS_CONTENT",
                    "threshold" => "BLOCK_ONLY_HIGH"
                ]
            ]
        ]);
        $completionDecoded = json_decode($complete,true);

        if (!is_array($completionDecoded)) {
            $completionDecoded = [];
            $response['errors'] = "ERROR - No response returned from the AI service";
        } elseif (array_key_exists("error", $completionDecoded)) {
            $response['errors'] = "ERROR - Code {$completionDecoded['error']['code']}: {$completionDecoded['error']['message']}";
        }

        $chatGptResponse = filter_tags(trim($completionDecoded["candidates"][0]["content"]["parts"][0]["text"]));
        $response['response'] = $chatGptResponse;

        // Log the AI call in a db table
        \AI::logApiCall(\AI::$serviceGemini, $typeOfCall, $contentText, $chatGptResponse, (defined("PROJECT_ID") ? PROJECT_ID : null));

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
        $data = \REDCap::getData([
            "project_id" => $project_id,
            "records" => $records,
            "fields" => [$fieldName, $recordField],
            "return_format" => "json-array"
        ]);

        list ($api_key, $api_model, $api_version) = \AI::getServiceAttributes()['service_details'];
        $gemini_ai = new GeminiAi($api_key, $api_model, $api_version);

        $stopRecord = false;
        $countResponse = $gemini_ai->tokenCount([
            "contents"=> [
                'parts' => [
                    'text' => $prependText
                ]
            ]
        ]);
        $neededTokens = $countResponse->totalTokens + 2;
        $usedTokens = 0;
        $chatGptString = "";
        $started = ($startRecord == "");

        foreach($data as $recordDetails) {
            if(!$started && $startRecord != $recordDetails[$recordField] || empty($recordDetails[$fieldName])) {
                continue;
            }

            $countResponse = $gemini_ai->tokenCount([
                "contents"=> [
                    'parts' => [
                        'text' => $recordDetails[$fieldName]
                    ]
                ]
            ]);
            $neededTokens += $countResponse->totalTokens;

            if($neededTokens > 7500) {
                $stopRecord = $recordDetails[$recordField];
            }
            if ($stopRecord === false) {
                $usedTokens = $neededTokens;
                $chatGptString .= $recordDetails[$fieldName] . "\n";
            }
        }

        if ($chatGptString == '') {
            $response['response'] = "<div class='error'>".\RCView::tt('openai_121','')."</div>";
            return $response;
        }

        $complete = $gemini_ai->chat([
            "contents"=> [
                'parts' => [
                    'text' => $prependText.":\n".$chatGptString
                ]
            ],
            "generationConfig" => [
                "temperature" => $temperature,
                "topK" => 1,
                "topP" => 1,
                "maxOutputTokens" => 4000,
                "stopSequences" => []
            ],
            "safetySettings" => [
                [
                    "category" => "HARM_CATEGORY_HARASSMENT",
                    "threshold" => "BLOCK_ONLY_HIGH"
                ],
                [
                    "category" => "HARM_CATEGORY_HATE_SPEECH",
                    "threshold" => "BLOCK_ONLY_HIGH"
                ],
                [
                    "category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT",
                    "threshold" => "BLOCK_ONLY_HIGH"
                ],
                [
                    "category" => "HARM_CATEGORY_DANGEROUS_CONTENT",
                    "threshold" => "BLOCK_ONLY_HIGH"
                ]
            ]
        ]);

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

        $chatGptResponse = filter_tags(trim($completionDecoded["candidates"][0]["content"]["parts"][0]["text"]));
        $response['response'] = $chatGptResponse;
        if (isset($response['errors'])) {
            $response['errors'] = implode("<br>", $response['errors']);
        }

        // Log the AI call in a db table
        \AI::logApiCall(\AI::$serviceGemini, \AI::$callTypeDataSummary, $prependText.":\n".$chatGptString, $chatGptResponse, $project_id);

        return $response;
    }
}