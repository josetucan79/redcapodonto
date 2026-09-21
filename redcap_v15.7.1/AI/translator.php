<?php
use Vanderbilt\REDCap\Classes\OpenAI\ChatGPTSummary;
use Vanderbilt\REDCap\Classes\OpenAI\Prompts;
use Vanderbilt\REDCap\Classes\GeminiAI\GeminiSummary;

if (isset($_GET['pid'])) {
    require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
} else {
    require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
}

if (isset($_POST['action']) && $_POST['action'] == 'get_translations') {
    $texts = json_decode($_POST['texts']);
    // Translate to Spanish these N texts preserving its number: ["1. text 1", "2. text 2",...."N. text N"]
    $i = 1;
    $j = 0;
    $string = "";
    $inputArr = [];
    foreach ($texts as $text) {
        if(strpos($text, "\n") !== FALSE) {
            $text = htmlspecialchars_decode(str_replace(array("\r\n","\n"),"<br>",$text));
        }

        $string .= '"'.$i.". ". $text.'"';
        $length = strlen($string);
        if ($length < 12000) {
            $inputArr[$j][] = '"'.$i.". ". $text.'"';
            $i++;
        } else {
            $j++;
            $inputArr[$j][] = '"'.$i.". ". $text.'"';
            $string = '"'.$i.". ". $text.'"';
            $i++;
        }
    }

    $toLanguage = $_POST['languageName'];

    if ($ai_services_enabled_global == '1') { // OpenAI Service
        $aiObj = new ChatGPTSummary();
    } elseif ($ai_services_enabled_global == '2') { // GeminiAI service
        $aiObj = new GeminiSummary();
    }

    $output = [];
    $i = 1;
    foreach ($inputArr as $j => $inputs) {
        $str_to_translate = implode(", ", $inputs);
        $prompt = str_replace(["{TO_LANGUAGE}", "{STRINGS_COUNT}", "{COMMA_SEP_STRINGS}"], [$toLanguage, count($inputs), $str_to_translate], Prompts::PROMPT_TRANSLATE_STRINGS);

        $selectedResponse = $aiObj->generateResponse('', $prompt, \AI::$callTypeMLMTranslator, 0.2);
        $response = $selectedResponse['response'];

        if (isset($selectedResponse['errors'])) {
            $output['errors'] = $selectedResponse['errors'];
            exit(json_encode($output));
        }

        $lines = preg_split('/\r\n|\r|\n/', $response);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, $i.'. ')) {
                $output[$i] = htmlspecialchars_decode(substr($line, strlen($i.'. ')));
                $i++;
            }
        }
    }
}
// Output JSON response
print json_encode($output);
