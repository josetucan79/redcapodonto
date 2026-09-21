<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Confirm the participant_id, survey_id, and event_id
$sql = "select 1 from redcap_surveys s, redcap_surveys_participants p
		where s.project_id = $project_id and s.survey_id = p.survey_id and s.survey_id = '".db_escape($_POST['survey_id'])."'
		and p.event_id = '".db_escape($_POST['event_id'])."' and p.participant_id = '".db_escape($_POST['participant_id'])."'";
$q = db_query($sql);
if (!db_num_rows($q)) exit("0");

// If using the mapped field to control invitation preference, then we need to also change that field's value
if ($Proj->project['twilio_delivery_preference_field_map'] != '' && isset($Proj->metadata[$Proj->project['twilio_delivery_preference_field_map']]))
{
	Records::updateFieldDataValueAllInstances($project_id, $_POST['record'], $Proj->project['twilio_delivery_preference_field_map'], $_POST['delivery_preference'], $Proj->eventInfo[$_POST['event_id']]['arm_num']);
}

// Set this preference on all events/surveys for this record
Survey::setInvitationPreferenceByParticipantId($_POST['participant_id'], $_POST['delivery_preference']);

// Return html for delivery preference icon
print Survey::getDeliveryPrefIcon($_POST['delivery_preference']);