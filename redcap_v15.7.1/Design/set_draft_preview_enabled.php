<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Validate POST parameters
// Must have "state" which must be one of [0, 1]
$to_state = $_POST['toState'] ?? null;
if ($to_state == null || !in_array($to_state, ["ON", "OFF"], true)) exit("ERROR");
// The project must be in production AND in draft mode
if ($status > 0 && $draft_mode != '1') exit("ERROR");
// The session serialization method must be supported
if (!Design::canUseDraftPreview()) exit("ERROR");

// Set new draft preview state
if ($to_state == "OFF") {
	Design::cancelDraftPreview(PROJECT_ID);
	exit("OFF");
}
else {
	Design::enableDraftPreview(PROJECT_ID);
	exit("ON");
}