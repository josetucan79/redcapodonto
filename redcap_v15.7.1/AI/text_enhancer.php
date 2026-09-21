<?php
use Vanderbilt\REDCap\Classes\OpenAI\ChatGPTSummary;
use Vanderbilt\REDCap\Classes\OpenAI\Prompts;
use Vanderbilt\REDCap\Classes\GeminiAI\GeminiSummary;

if (isset($_GET['pid'])) {
    include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
} else {
    include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
    if (!ACCESS_CONTROL_CENTER) exit($lang['global_01']);
}
$output = ['status' => 0, 'message'   => ''];

if ($ai_services_enabled_global == '1') { // OpenAI Service
    $aiObj = new ChatGPTSummary();
} elseif ($ai_services_enabled_global == '2') { // GeminiAI service
    $aiObj = new GeminiSummary();
}
$data = '';
if (isset($_POST['action'])) {
    $param = $_POST['param'];

    switch ($_POST['action']) {
        case "check_grammer":
            $text = $_POST['content_str'];
            $prompt = Prompts::PROMPT_CHECK_GRAMMAR . "\n" . Prompts::SYS_TEXT_ENHANCER_SPECIFIC_QUESTION . "\n---\n" . $text;
            $selectedResponse = $aiObj->generateResponse($_POST['content_str'], $prompt, \AI::$callTypeWritingTools, 0.1);
            $data = $selectedResponse['response'];
            break;

        case "fix_grammar":
            $text = $_POST['content_str'];
            $prompt = Prompts::PROMPT_FIX_GRAMMAR;
            $selectedResponse = $aiObj->generateResponse($_POST['content_str'], $prompt, \AI::$callTypeWritingTools, 0.2);
            $data = $selectedResponse['response'];
            break;

        case "get_reading_level":
        case "get_suggestion_reading_level":
            $text = $_POST['content_str'];
            $prompt = Prompts::PROMPT_GET_READING_LEVEL ."\n" . Prompts::SYS_TEXT_ENHANCER_SPECIFIC_QUESTION. "\n---\n" . $text;
            $selectedResponse = $aiObj->generateResponse($_POST['content_str'], $prompt, \AI::$callTypeWritingTools, 0.1);
            $data = js_escape2($selectedResponse['response']);
            break;

        case "set_reading_level":

            if (in_array($param, ['5th', '6th', '7th'])) {
                $promptText = str_replace("[TARGET-AUDIENCE]", $param." grade",Prompts::PROMPT_SET_READING_LEVEL_PREFIX);
            } else if ($param == '8_9') {
                $promptText = str_replace("[TARGET-AUDIENCE]", "8th & 9th grade",Prompts::PROMPT_SET_READING_LEVEL_PREFIX);
            } else if ($param == '10_12') {
                $promptText = str_replace("[TARGET-AUDIENCE]", "10th to 12th grade",Prompts::PROMPT_SET_READING_LEVEL_PREFIX);
            } else if ($param == 'college') {
                $promptText = str_replace("[TARGET-AUDIENCE]", "College",Prompts::PROMPT_SET_READING_LEVEL_PREFIX);
            } else if ($param == 'college_grad') {
                $promptText = str_replace("[TARGET-AUDIENCE]", "College graduate",Prompts::PROMPT_SET_READING_LEVEL_PREFIX);
            } else if ($param == 'professional') {
                $promptText = str_replace("[TARGET-AUDIENCE]", "Professional",Prompts::PROMPT_SET_READING_LEVEL_PREFIX);
            }

            $promptText .= "\n".Prompts::SYS_TEXT_ENHANCER_SPECIFIC_QUESTION. " ". Prompts::PROMPT_PLACEHOLDERS_TEXT. "\n---\n[STRING]";
            $selectedResponse = $aiObj->generateResponse($_POST['content_str'], $promptText, \AI::$callTypeWritingTools, 0.8);
            $data = $selectedResponse['response'];
            break;
        case "set_length":
            if ($param == '25') {
                $promptText = Prompts::PROMPT_REDUCE_BY_25;
            } else if ($param == '50') {
                $promptText = Prompts::PROMPT_REDUCE_BY_50;
            } else if ($param == '75') {
                $promptText = Prompts::PROMPT_REDUCE_BY_75;
            } else if ($param == 'one_paragraph') {
                $promptText = Prompts::PROMPT_TO_ONE_PARAGRAPH;
            } else if ($param == '25+') {
                $promptText = Prompts::PROMPT_INCREASE_BY_25;
            } else if ($param == '50+') {
                $promptText = Prompts::PROMPT_INCREASE_BY_50;
            } else if ($param == '75+') {
                $promptText = Prompts::PROMPT_INCREASE_BY_75;
            }
            $promptText .= "\n" . Prompts::SYS_TEXT_ENHANCER_SPECIFIC_QUESTION. " ". Prompts::PROMPT_PLACEHOLDERS_TEXT. "\n---\n[STRING]";
            $selectedResponse = $aiObj->generateResponse($_POST['content_str'], $promptText, \AI::$callTypeWritingTools, 0.8);
            $data = $selectedResponse['response'];
            break;
        case "change_tone":
            if ($param == 'formal') {
                $promptText = Prompts::PROMPT_TONE_FORMAL;
            } else if ($param == 'friendly') {
                $promptText = Prompts::PROMPT_TONE_FRIENDLY;
            } else if ($param == 'encourage') {
                $promptText = Prompts::PROMPT_TONE_ENCOURAGE;
            } else if ($param == 'professional') {
                $promptText = Prompts::PROMPT_TONE_PROFESSIONAL;
            }
            $promptText .= "\n" . Prompts::SYS_TEXT_ENHANCER_SPECIFIC_QUESTION. " ". Prompts::PROMPT_PLACEHOLDERS_TEXT. "\n---\n[STRING]";
            $selectedResponse = $aiObj->generateResponse($_POST['content_str'], $promptText, \AI::$callTypeWritingTools, 0.8);
            $data = nl2br($selectedResponse['response']);
            break;

        case "custom_prompt":
            $selectedResponse = $aiObj->generateResponse('', $_POST['custom_prompt_str'], \AI::$callTypeWritingTools);
            $data = nl2br(htmlspecialchars($selectedResponse['response']));
            break;
    }

    $output = ['status' => 1, 'message' => $data];
    if (isset($selectedResponse['errors'])) {
        $output['errors'] = $selectedResponse['errors'];
    }
}

echo json_encode($output);