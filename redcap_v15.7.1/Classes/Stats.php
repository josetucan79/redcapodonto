<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStatsCollector;
use MultiLanguageManagement\MultiLanguage;

/**
 * Stats is a class of functions used to output general dashboard stats for the REDCap system
 */
class Stats
{
	private const IGNORED_PROJECT_WHERE_CLAUSE = "(projects.purpose = '0' or projects.count_project = 0 or projects.date_deleted is not null)";

	// Store comma-delimited list of project_id's that should be ignored in some counts
	// (not counted or 'just for fun' purpose or 'deleted' projects)
	private $ignored_project_ids = null;

	// Get delimited list of project_id's that should be ignored in some counts (not counted or 'just for fun' purpose or 'deleted' projects)
	public function getIgnoredProjectIds()
	{
		if ($this->ignored_project_ids == null) {
			$sql = "select project_id from redcap_projects projects where " . static::IGNORED_PROJECT_WHERE_CLAUSE;
			$this->ignored_project_ids = pre_query($sql);
		}
		return $this->ignored_project_ids;
	}

	// Return array of project_id's using DDP
	public function getDDpProjectIds($fhir=false)
	{
		$ddp_project_ids = array();
		$type = ($fhir !== false) ? "FHIR" : "CUSTOM";
		$sql = "select project_id from redcap_projects where realtime_webservice_enabled = 1
				and project_id not in (".$this->getIgnoredProjectIds().") and realtime_webservice_type = '$type'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$ddp_project_ids[$row['project_id']] = $row['project_id'];
		}
		return $ddp_project_ids;
	}

	// Return array of project_id's using Data Mart
	public function getDataMartProjectIds()
	{
		$dm_project_ids = [];
		$ignoredPids = $this->getIgnoredProjectIds();
		if(empty($ignoredPids)) return $dm_project_ids;
		$sql = "SELECT project_id FROM redcap_projects WHERE datamart_enabled = 1
				AND project_id NOT IN ($ignoredPids)";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$dm_project_ids[] = $row['project_id'];
		}
		return $dm_project_ids;
	}
	// Get count of records that have had data imported from the EHR via Data Mart
	public function getDataMartRecordsImported()
	{
		// Get count of projects using Data Mart
		$dm_project_ids = $this->getDataMartProjectIds();
		$total_data_mart_records_imported = 0;
		if(empty($dm_project_ids)) return $total_data_mart_records_imported;

		$params = array_merge([FhirStatsCollector::REDCAP_TOOL_TYPE_CDM], $dm_project_ids);
		$placeholders = dbQueryGeneratePlaceholdersForArray($dm_project_ids);
		$sql = "SELECT COUNT(DISTINCT(CONCAT(m.project_id, '-', m.record)))
				FROM redcap_projects d, redcap_ehr_resource_imports m 
				WHERE d.project_id = m.project_id AND m.type = ? AND d.project_id IN ($placeholders)";
		$temp = db_result(db_query($sql, $params), 0);
		$total_data_mart_records_imported += ($temp == '' ? 0 : $temp);
		return $total_data_mart_records_imported;
	}

	/**
	 * Calculates the total number of values imported for projects of type "CDM" (Data Mart).
	 *
	 * @param array $dm_project_ids An array of project IDs to include in the calculation.
	 * @return int The total count of values imported across the specified projects.
	 */
	public function getTotalDataMartValuesImported($project_ids)
	{
		// Initialize the total values count
		$total_data_mart_values_imported = 0;
		if(empty($project_ids)) return $total_data_mart_values_imported;

		// Prepare the SQL query for the updated table structure
		$sql = "SELECT m.project_id, SUM(d.count) AS total_values_imported
			FROM redcap_projects p
			JOIN redcap_ehr_resource_imports m ON p.project_id = m.project_id
			JOIN redcap_ehr_resource_import_details d ON m.id = d.ehr_import_count_id
			WHERE m.type = 'CDM' 
			AND p.project_id IN (" . prep_implode($project_ids) . ")
			GROUP BY m.project_id";

		// Execute the query
		$q = db_query($sql, [], null, MYSQLI_USE_RESULT);
		while ($row = db_fetch_assoc($q)) {
			$total_data_mart_values_imported += (int)$row['total_values_imported'];
		}

		// Return the total count of values imported
		return $total_data_mart_values_imported;
	}


	// Get count of users that were active in the past X days
	public static function getActiveUsers($days=null)
	{
		if (!is_numeric($days) && $days != null) return false;
		// If null, then return ALL active users since beginning (exclude suspended users)
		$sql_interval = ($days == null) ? "" : "and DATE_SUB('".TODAY."', INTERVAL $days DAY) <= user_lastactivity";
		$sql = "select count(1) from redcap_user_information where username != '' $sql_interval
				and user_lastactivity is not null and user_suspended_time is null order by username";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// Get count of users who have logged in to REDCap in the past X days
	public static function getUserLogins($days=null)
	{
		if (!is_numeric($days) && $days != null) return false;
		// If null, then return ALL users who have logged in since beginning
		$sql_interval = ($days == null) ? "" : "and DATE_SUB('".TODAY."', INTERVAL $days DAY) <= user_lastlogin";
		$sql = "select count(1) from redcap_user_information where username != '' $sql_interval
				and user_lastlogin is not null order by username";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// RANDOMIZATION: Exclude "practice" projects
	public static function randomizationCounts()
	{
        // Total count of prod projects using randomization and have a prod alloc table uploaded
		$sql = "select 1 from redcap_projects p, redcap_randomization r, redcap_randomization_allocation a
				where p.status > 0 and p.count_project = 1 and (p.purpose is null or p.purpose > 0)
				and r.project_id = p.project_id and r.rid = a.rid and a.project_status = 1 
				group by p.project_id";
        $numProjects = db_num_rows(db_query($sql));

        // Total count of prod projects using randomization with MORE THAN ONE randomization model and have a prod alloc table uploaded
		$sql = "select 1 from (
                    select p.project_id, 
                    (select count(*) from redcap_randomization_allocation a where r.rid = a.rid and a.project_status = 1) as num_prod_alloc
                    from redcap_projects p, redcap_randomization r
                    where p.status > 0 and p.count_project = 1 and (p.purpose is null or p.purpose > 0)
                    and r.project_id = p.project_id
                    group by p.project_id having count(*) > 1
                ) x
                where x.num_prod_alloc > 0";
        $numProjectsMultiRand = db_num_rows(db_query($sql));

        // Total count of prod projects using BLINDED randomization and have a prod alloc table uploaded
		$sql = "select 1 from redcap_projects p, redcap_randomization r, redcap_randomization_allocation a, redcap_metadata m
                where p.status > 0 and p.count_project = 1 and (p.purpose is null or p.purpose > 0)
				and m.field_name = r.target_field and m.project_id = r.project_id and m.element_type = 'text'
                and r.project_id = p.project_id and r.rid = a.rid and a.project_status = 1
                group by p.project_id";
        $numProjectsBlindedRand = db_num_rows(db_query($sql));

        // Total count of prod projects using a logic-based randomization trigger and have a prod alloc table uploaded
		$sql = "select 1 from redcap_projects p, redcap_randomization r, redcap_randomization_allocation a
				where p.status > 0 and p.count_project = 1 and (p.purpose is null or p.purpose > 0)
				and r.project_id = p.project_id and r.rid = a.rid and a.project_status = 1 
                and r.trigger_option is not null
				group by p.project_id";
        $numProjectsLogicBasedTrigger = db_num_rows(db_query($sql));

		return [$numProjects, $numProjectsMultiRand, $numProjectsBlindedRand, $numProjectsLogicBasedTrigger];
	}

	// MOBILE APP: Get count of all users that have some kind of Mobile App activity in a project (e.g., init project in app)
	// and still have "mobile_app" privileges.
	// Exclude "practice" projects.
	public function mobileAppUserCount($pastXmonths=null)
	{
		$pastXmonthsSql = "";
		if (is_numeric($pastXmonths)) {
			$xMonthsAgo = date("YmdHis", mktime(date("H"),date("i"),date("s"),date("m")-$pastXmonths,date("d"),date("Y")));
			$sql = "select min(log_event_id) from redcap_log_event where ts > $xMonthsAgo";
			$log_event_id = db_result(db_query($sql), 0);
			if ($log_event_id != '') $pastXmonthsSql = "and log_event_id >= $log_event_id";
		}
		$sql = "select count(distinct(ui_id)) from redcap_mobile_app_log 
				where project_id not in (" . $this->getIgnoredProjectIds() . ") $pastXmonthsSql";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// MOBILE APP: Get count of all projects where a user has initialized a project in the app.
	// Exclude "practice" projects.
	public function mobileAppInitProjectCount()
	{
		$sql = "select count(distinct(project_id)) from redcap_mobile_app_log
				where event = 'INIT_PROJECT' and project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// MOBILE APP: Get count of all projects where a user has synced/imported data from the app.
	// Exclude "practice" projects.
	public function mobileAppSyncDataProjectCount()
	{
		$sql = "select count(distinct(project_id)) from redcap_mobile_app_log
				where event = 'SYNC_DATA' and project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}

    // MyCap: Get count of all projects where a user has enabled MyCap.
    // Exclude "practice" projects.
    public function mycapInitProjectCount()
    {
        if (!$GLOBALS['mycap_enabled_global']) return 0;
        $sql = "select count(project_id) from redcap_projects
				where mycap_enabled = 1 and project_id not in (" . $this->getIgnoredProjectIds() . ")";
        $q = db_query($sql);
        return db_result($q, 0);
    }

    // MyCap: Get count of all participants that have joined a MyCap-enabled project.
    // Exclude "practice" projects.
    public function mycapParticipantJoinedCount()
    {
        // Return 0 count if MyCap is not enabled globally
        if (!$GLOBALS['mycap_enabled_global']) return 0;

        // Execute the query to determine the count
        $sql = "SELECT
                    count(*)
                FROM
                    redcap_mycap_participants m, redcap_projects p
                WHERE
                    p.project_id = m.project_id AND
                    p.mycap_enabled = 1 AND
                    m.join_date IS NOT NULL AND
                    m.is_deleted = 0 AND
                    p.project_id NOT IN (" . $this->getIgnoredProjectIds() . ")";
        $q = db_query($sql);

        // MyCap Participant count
        $count = db_result($q, 0);

        // Return the count
        return $count;
    }

    // MyCap: Get count of all MyCap tasks in MyCap-enabled projects (excluding Active Tasks).
    // Exclude "practice" projects.
    public function mycapTaskCount()
    {
        if (!$GLOBALS['mycap_enabled_global']) return 0;
        $sql = "select count(*) from 
                (select distinct m.project_id, m.form_name from redcap_mycap_tasks m, redcap_projects p, redcap_metadata a
                    where p.project_id = m.project_id and p.mycap_enabled = 1 
                    and a.project_id = m.project_id and m.form_name = a.form_name
                    and m.enabled_for_mycap = 1 and m.question_format in ('.Questionnaire', '.Form')
                    and p.project_id not in (" . $this->getIgnoredProjectIds() . ")
                ) x";
        $q = db_query($sql);
        return db_result($q, 0);
    }

    // MyCap: Get count of all MyCap active tasks (created from ResearchKit Only) in MyCap-enabled projects.
    // Exclude "practice" projects.
    public function mycapActiveTaskCount()
    {
        if (!$GLOBALS['mycap_enabled_global']) return 0;

        $researchkit_formats = \Vanderbilt\REDCap\Classes\MyCap\ActiveTask::getResearchKitActiveTasksFormats();
        $sql = "select count(*) from 
                (select distinct m.project_id, m.form_name from redcap_mycap_tasks m, redcap_projects p, redcap_metadata a
                    where p.project_id = m.project_id and p.mycap_enabled = 1 
                    and a.project_id = m.project_id and m.form_name = a.form_name
                    and m.enabled_for_mycap = 1 and m.question_format in (" . prep_implode($researchkit_formats) . ")
                    and p.project_id not in (" . $this->getIgnoredProjectIds() . ")
                ) x";
        $q = db_query($sql);
        return db_result($q, 0);
    }

    // MyCap: Get count of all MyCap active tasks (created from MTB only) in MyCap-enabled projects.
    // Exclude "practice" projects.
    public function mycapMTBTaskCount()
    {
        if (!$GLOBALS['mycap_enabled_global']) return 0;

        $mtb_formats = \Vanderbilt\REDCap\Classes\MyCap\ActiveTask::getResearchKitActiveTasksFormats('mtb');
        $sql = "select count(*) from 
                (select distinct m.project_id, m.form_name from redcap_mycap_tasks m, redcap_projects p, redcap_metadata a
                    where p.project_id = m.project_id and p.mycap_enabled = 1 
                    and a.project_id = m.project_id and m.form_name = a.form_name
                    and m.enabled_for_mycap = 1 and m.question_format in (" . prep_implode($mtb_formats) . ")
                    and p.project_id not in (" . $this->getIgnoredProjectIds() . ")
                ) x";
        $q = db_query($sql);
        return db_result($q, 0);
    }

    // MyCap: Get count of all PROMIS measures enabled for MyCap in MyCap-enabled projects.
    // Exclude "practice" projects.
    public function mycapPromisTaskCount()
    {
        if (!$GLOBALS['mycap_enabled_global']) return 0;

        $promis_format = \Vanderbilt\REDCap\Classes\MyCap\Task::PROMIS;
        $sql = "select count(*) from 
                (select distinct m.project_id, m.form_name from redcap_mycap_tasks m, redcap_projects p, redcap_metadata a
                    where p.project_id = m.project_id and p.mycap_enabled = 1 
                    and a.project_id = m.project_id and m.form_name = a.form_name
                    and m.enabled_for_mycap = 1 and m.question_format = '".$promis_format."'
                    and p.project_id not in (" . $this->getIgnoredProjectIds() . ")
                ) x";
        $q = db_query($sql);
        return db_result($q, 0);
    }
    // MyCap: Get count of all MyCap announcements and all messages sent and received.
    // Exclude "practice" projects.
    public function mycapMessagesSentReceivedCount()
    {
        if (!$GLOBALS['mycap_enabled_global']) return 0;
        $sql = "select count(*) from redcap_mycap_messages m, redcap_projects p
                where p.project_id = m.project_id and p.mycap_enabled = 1
                and p.project_id not in (" . $this->getIgnoredProjectIds() . ")";
        $q = db_query($sql);
        return db_result($q, 0);
    }

	// AI: Return the current global API service, if enabled
    // Exclude "practice" projects.
	public function aiService()
	{
		if ($GLOBALS['ai_services_enabled_global'] != '1') return "";
        return \AI::$serviceAzureOpenAI; // Currently the only service available
	}

	// AI: Return a count of projects that have utilized AI at any time
    // Exclude "practice" projects.
	public function aiApiCallProjects()
	{
		if ($GLOBALS['ai_services_enabled_global'] != '1') return "0";
        $sql = "select count(distinct(project_id)) from redcap_ai_log
                where project_id is not null and project_id not in (" . $this->getIgnoredProjectIds() . ")";
        return db_result(db_query($sql));
	}

    // AI: Return array of count of projects that have utilized AI at any time for EACH AI service
    // Exclude "practice" projects.
    public function aiApiCallProjectsServices()
    {
        $serviceCounts = ['ai_projects_datasum'=>'', 'ai_projects_editor'=>'', 'ai_projects_mlm'=>''];
        if ($GLOBALS['ai_services_enabled_global'] != '1') return $serviceCounts;
        $sql = "SELECT type, COUNT(DISTINCT project_id) AS c
                FROM redcap_ai_log
                WHERE project_id IS NOT NULL AND project_id NOT IN (" . $this->getIgnoredProjectIds() . ")
                GROUP BY type";
        $q = db_query($sql);
        foreach ($serviceCounts as &$val) $val = 0; // seed with 0's
        while ($row = db_fetch_assoc($q)){
            if ($row['type'] == 'EditorWritingTools') {
                $serviceCounts['ai_projects_editor'] += $row['c'];
            } elseif ($row['type'] == 'MLMTranslator') {
                $serviceCounts['ai_projects_mlm'] += $row['c'];
            } elseif ($row['type'] == 'ReportDataSummary') {
                $serviceCounts['ai_projects_datasum'] += $row['c'];
            }
        }
        return $serviceCounts;
    }

    // AI: Return a count of total API calls made in the past
    public function aiApiCalls($includePracticeProjects=true)
    {
        if ($GLOBALS['ai_services_enabled_global'] != '1') return "0";
        $sql = "select count(*) from redcap_ai_log";
        if (!$includePracticeProjects) {
            $sql .= " where (project_id is null or project_id not in (" . $this->getIgnoredProjectIds() . "))";
        }
        return db_result(db_query($sql));
    }

    // AI: Return array of count of projects that have utilized AI at any time for EACH AI service
    // Exclude "practice" projects.
    public function aiApiCallsServices($includePracticeProjects=true)
    {
        $serviceCounts = ['ai_calls_datasum'=>'', 'ai_calls_editor'=>'', 'ai_calls_mlm'=>''];
        if ($GLOBALS['ai_services_enabled_global'] != '1') return $serviceCounts;
        $sql = "select type, count(*) as c from redcap_ai_log";
        if (!$includePracticeProjects) {
            $sql .= " where (project_id is null or project_id not in (" . $this->getIgnoredProjectIds() . "))";
        }
        $sql .= " group by type";
        $q = db_query($sql);
        foreach ($serviceCounts as &$val) $val = 0; // seed with 0's
        while ($row = db_fetch_assoc($q)){
            if ($row['type'] == 'EditorWritingTools') {
                $serviceCounts['ai_calls_editor'] += $row['c'];
            } elseif ($row['type'] == 'MLMTranslator') {
                $serviceCounts['ai_calls_mlm'] += $row['c'];
            } elseif ($row['type'] == 'ReportDataSummary') {
                $serviceCounts['ai_calls_datasum'] += $row['c'];
            }
        }
        return $serviceCounts;
    }

	// TWILIO: Get count of all projects using Twilio telephony services for app.
	// Exclude "practice" projects.
	public function twilioProjectCount()
	{
		if (!$GLOBALS['twilio_enabled_global']) return 0;
		$sql = "select count(project_id) from redcap_projects
				where twilio_enabled = 1 and twilio_account_sid is not null and twilio_auth_token is not null and twilio_from_number is not null 
                and project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// Mosio: Get count of all projects using Mosio telephony services for app.
	// Exclude "practice" projects.
	public function mosioProjectCount()
	{
		if (!$GLOBALS['mosio_enabled_global']) return 0;
		$sql = "select count(project_id) from redcap_projects
				where twilio_enabled = 1 and mosio_api_key is not null and project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// SendGrid: Get count of all projects using SendGrid e-mail services for Alerts & Notifications.
	// Exclude "practice" projects.
	public function sendgridProjectCount()
	{
		if (!$GLOBALS['sendgrid_enabled_global']) return 0;
		$sql = "select count(project_id) from redcap_projects
				where sendgrid_enabled = 1 and project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}
	
	// e-Consent: Count projects using e-Consent Framework
	public function countProjectsUsingEconsent()
	{
		$sql = "select count(distinct(p.project_id)) from redcap_projects p, redcap_econsent s 
				where s.project_id = p.project_id and s.active = 1
				and p.project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}
	
	// e-Consent: Count projects using e-Consent Framework
	public function countEconsentPdfsStored()
	{
		$sql = "select count(1) from redcap_surveys s, redcap_edocs_metadata e, redcap_surveys_pdf_archive a
				where a.consent_id is not null and e.project_id = s.project_id
				and a.doc_id = e.doc_id and a.survey_id = s.survey_id and e.delete_date is null
				and s.project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// SURVEY TEXT-TO-SPEECH: Get count of all surveys using the survey TTS feature.
	// Exclude "practice" projects.
	public function surveyTextToSpeechCount()
	{
		$sql = "select count(distinct(s.survey_id)) from redcap_projects p, redcap_surveys s, redcap_metadata m
				where s.text_to_speech = 1 and p.project_id = s.project_id and m.form_name = s.form_name
				and m.project_id = p.project_id and p.project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// PUBLICATION MATCHES: Send to consortium the list of pub IDs that have been matched to REDCap projects
	// Parameter $returnParamsOnly will make Post request if FALSE, but if TRUE will not make request but
	// merely return the parameters for that request.
	public static function sendPubMatchList($returnParamsOnly=false)
	{
		// Set alternative hostname if we know the domain name in the URL is internal (i.e. without dots)
		$alt_hostname = (strpos(SERVER_NAME, ".") === false) ? SERVER_NAME : "";
		// Set URL to call
		$url = CONSORTIUM_WEBSITE . "collect_stats_pubs.php?rnd982g45av390p9&app=0&hostname=".SERVER_NAME."&ip=".getServerIP()."&alt_hostname=$alt_hostname&hostkey_hash=".Stats::getServerKeyHash();
		// Query table to get matches
		$sql = "select distinct s.pubsrc_name, a.pub_id from
				redcap_pub_matches m, redcap_pub_articles a, redcap_pub_sources s
				where m.matched = 1 and m.article_id = a.article_id
				and a.pubsrc_id = s.pubsrc_id order by s.pubsrc_name, a.pub_id";
		$q = db_query($sql);
		$pubsrc_matches = array();
		while ($row = db_fetch_assoc($q))
		{
			$pubsrc_matches[$row['pubsrc_name']][] = $row['pub_id'];
		}
		// Convert sub-array into comma delimited string for each pub src
		foreach ($pubsrc_matches as $src=>$pubids)
		{
			$pubsrc_matches[$src] = implode(",", $pubids);
		}
		// Return params or the result of the Post request
		if ($returnParamsOnly) {
			return array('url'=>$url, 'params'=>$pubsrc_matches);
		} else {
			// Send stats via Post request
			return http_post($url, $pubsrc_matches);
		}
	}


	// Get all the options used in Two Factor Authentication delimited by commas.
	// If not use 2FA, return 'DISABLED'
	public static function getTwoFactorAuthOptionsUsed()
	{
		// Enabled
		if (!$GLOBALS['two_factor_auth_enabled']) return "DISABLED";
		// Init array for storing options
		$options = array();
		if ($GLOBALS['two_factor_auth_duo_enabled']) 			$options[] = "DUO";
		if ($GLOBALS['two_factor_auth_twilio_enabled']) 		$options[] = "TWILIO";
		if ($GLOBALS['two_factor_auth_authenticator_enabled']) 	$options[] = "GOOGLE_AUTHENTICATOR";
		if ($GLOBALS['two_factor_auth_email_enabled']) 			$options[] = "EMAIL";
		// Return options
		return implode(",", $options);
	}

	// SEND SHARED LIBRARY STATS: Obtain local library stats to send to consortium
	// ALSO SEND EXTERNAL LIBRARY STATS (FOR INSTRUMENT ZIPS)
	// Parameter $returnParamsOnly will make Post request if FALSE, but if TRUE will not make request but
	// merely return the parameters for that request.
	public static function sendSharedLibraryStats($returnParamsOnly=false)
	{
		// Set alternative hostname if we know the domain name in the URL is internal (i.e. without dots)
		$alt_hostname = (strpos(SERVER_NAME, ".") === false) ? SERVER_NAME : "";
		// Set URL to call
		$url = CONSORTIUM_WEBSITE . "collect_stats_library.php?rnd982g45av390r1&app=0&hostname=".SERVER_NAME."&ip=".getServerIP()."&alt_hostname=$alt_hostname&hostkey_hash=".Stats::getServerKeyHash();
		// Initialize vars
		$params = array("total"=>array("dev_up"=>0, "dev_down"=>0, "prod_up"=>0, "prod_down"=>0));
		// Uploads for dev projects
		$sql = "select l.library_id, count(1) as count from redcap_library_map l, redcap_projects p where p.project_id = l.project_id
				and p.status = 0 and l.type = 2 group by l.library_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params[$row['library_id']]['dev_up'] = $row['count'];
			$params['total']['dev_up'] += $row['count'];
		}
		// Downloads for dev projects
		$sql = "select l.library_id, count(1) as count from redcap_library_map l, redcap_projects p where p.project_id = l.project_id
				and p.status = 0 and l.type = 1 group by l.library_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params[$row['library_id']]['dev_down'] = $row['count'];
			$params['total']['dev_down'] += $row['count'];
		}
		// Uploads for prod projects
		$sql = "select l.library_id, count(1) as count from redcap_library_map l, redcap_projects p where p.project_id = l.project_id
				and p.status = 1 and l.type = 2 group by l.library_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params[$row['library_id']]['prod_up'] = $row['count'];
			$params['total']['prod_up'] += $row['count'];
		}
		// Downloads for prod projects
		$sql = "select l.library_id, count(1) as count from redcap_library_map l, redcap_projects p where p.project_id = l.project_id
				and p.status = 1 and l.type = 1 group by l.library_id order by l.library_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params[$row['library_id']]['prod_down'] = $row['count'];
			$params['total']['prod_down'] += $row['count'];
		}
		// Convert array to string for passing below
		$params2 = array();
		foreach ($params as $lib_id=>$values)
		{
			$params2[$lib_id] = (isset($values['dev_up'])    ? $values['dev_up']    : "0") . ","
							  . (isset($values['dev_down'])  ? $values['dev_down']  : "0") . ","
							  . (isset($values['prod_up'])   ? $values['prod_up']   : "0") . ","
							  . (isset($values['prod_down']) ? $values['prod_down'] : "0");
		}

		// Get instrument zip stats for external libraries
		$params2['instrument_zip_ext_libs'] = array();
		$params2['instrument_zip_origins'] = array();
		$sql = "select a.author_name, z.instrument_id, z.upload_count
				from redcap_instrument_zip_authors a, redcap_instrument_zip z where a.iza_id = z.iza_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params2['instrument_zip_ext_libs'][$row['author_name']][$row['instrument_id']] = $row['upload_count'];
		}
		$sql = "select * from redcap_instrument_zip_origins";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params2['instrument_zip_origins'][$row['server_name']] = $row['upload_count'];
		}
		$params2['instrument_zip_ext_libs'] = serialize($params2['instrument_zip_ext_libs']);
		$params2['instrument_zip_origins'] = serialize($params2['instrument_zip_origins']);


		// Return params or the result of the Post request
		if ($returnParamsOnly) {
			return array('url'=>$url, 'params'=>$params2);
		} else {
			// Send stats via Post request
			return http_post($url, $params2);
		}
	}

	// Return a one-way hash of the server's unique key (i.e. $salt) for server identification purposes
	public static function getServerKeyHash()
	{
		global $salt;
		return md5($salt);
	}

	// Return URL for reporting stats to consortium. Includes all calculated stat counts.
	public function cacheStatsReportingUrl()
	{
		global $auto_report_stats;
		$url = $this->getUrlReportingStats(!$auto_report_stats, true);
		// Return TRUE if returned a URL
		return ($url != '');
	}

	// Return array of external modules counts with the modules installed and how many dev/prod projects have them enabled.
	// Note: These counts ONLY include those that have been downloaded from the REDCap Repo.
	public function getExternalModuleCounts()
	{
		$modules = array();
		$sql = "
			select
				system_modules.*,
				enabled_settings.value as enabled,
				if(
					enabled_settings.project_id is null,
					null,
					if(projects.status > 0, 1, 0)
				)  as adjusted_status,
				count(1) as status_count
			from (
				select 
					versions.external_module_id as local_id,
					downloads.module_id as repo_id,
					prefixes.directory_prefix as prefix
				from (
					select *
					from redcap_external_module_settings
					where `key` = 'version'
				) versions
				join redcap_external_modules prefixes
					on versions.external_module_id = prefixes.external_module_id
				join redcap_external_modules_downloads downloads
					on downloads.module_name = concat(prefixes.directory_prefix, '_', versions.value)
			) system_modules
			left join redcap_external_module_settings enabled_settings
				on enabled_settings.external_module_id = system_modules.local_id
					and enabled_settings.key = 'enabled'
					and enabled_settings.value = 'true'
					and
						(
							enabled_settings.project_id is null
							or
							enabled_settings.project_id not in (
								select project_id
								from redcap_projects projects
								where " . static::IGNORED_PROJECT_WHERE_CLAUSE . "
							)
						)
			left join redcap_projects projects
				on projects.project_id = enabled_settings.project_id
			group by
				system_modules.repo_id,
				adjusted_status
			order by
				system_modules.repo_id,
				adjusted_status is null, -- important for php loop logic
				adjusted_status
		";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$repoId = $row['repo_id'];

			$status = $row['adjusted_status'];
			if($status === null){
				if($row['enabled'] === 'true'){
					$status = 'ALL';
					
					/**
					 * Clear project status counts.
					 * The "order by" clause ensures NULL project ids are last.
					 */
					$modules[$repoId] = [];
				}
				else{
					$status = 'SYSTEM';
				}

				$count = '1';
			}
			else{
				$status = $row['adjusted_status'];
				$count = $row['status_count'];
			}

			$modules[$repoId][$status] = $count;
		}
		return $modules;
	}

    // Get count of alerts and projects utilizing them
    public function getAlertCounts()
    {
        $sql = "SELECT count(*) as alerts, count(distinct(a.project_id)) as alerts_projects 
                FROM redcap_alerts a WHERE a.email_deleted = 0 AND a.project_id not in (" . $this->getIgnoredProjectIds() . ")";
        $q = db_query($sql);
        $total_alerts = db_result($q, 0, 'alerts');
        $total_alerts_projects = db_result($q, 0, 'alerts_projects');
        $sql = "SELECT count(distinct(a.project_id))
                FROM redcap_alerts a, redcap_projects p 
                WHERE a.email_deleted = 0 AND a.project_id not in (" . $this->getIgnoredProjectIds() . ")
                and a.project_id = p.project_id and p.status > 0";
        $q = db_query($sql);
        $total_alerts_projects_prod = db_result($q, 0);
        return array($total_alerts, $total_alerts_projects, $total_alerts_projects_prod);
    }

	// Get count of fields imported to a project via the Field Bank
	public function fieldBankCounts()
	{
		$sql = "SELECT count(tinyId) as nlm, count(publicId) as nci, count(questionId) as redcap 
                FROM redcap_cde_field_mapping
                WHERE project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_fetch_assoc($q);
	}

    // CDP related stats
    public function getCDPstats()
	{
		// Get count of projects using Dynamic Data Pull (DDP)
		$ddp_project_ids = $this->getDDpProjectIds(true);

		// Get count of all adjudicated data points that have been imported from source system in DDP-enabled projects
		$total_ddp_values_adjudicated = 0;
		$total_ddp_records_imported = [];
		$total_ddp_projects_adjudicated = [];

		if(!empty($ddp_project_ids)) {
			$ddp_project_ids_placeholders = dbQueryGeneratePlaceholdersForArray($ddp_project_ids);
			$params = array_merge([FhirStatsCollector::REDCAP_TOOL_TYPE_CDP], $ddp_project_ids);
			$sql = "SELECT m.project_id, m.record, SUM(d.count) AS total_values
			FROM redcap_projects p
			JOIN redcap_ehr_resource_imports m ON p.project_id = m.project_id
			JOIN redcap_ehr_resource_import_details d ON m.id = d.ehr_import_count_id
			WHERE m.type = ? 
			AND p.project_id IN ($ddp_project_ids_placeholders)
			GROUP BY m.project_id, m.record";

			$q = db_query($sql, $params, null, MYSQLI_USE_RESULT);

			while ($row = db_fetch_assoc($q)) {
				$total_ddp_projects_adjudicated[$row['project_id']] = true;
				$total_ddp_records_imported[$row['project_id'] . "-" . $row['record']] = true;
				$total_ddp_values_adjudicated += (int)$row['total_values'];
			}
		}
		$total_ddp_projects_adjudicated = count($total_ddp_projects_adjudicated);
		$total_ddp_records_imported = count($total_ddp_records_imported);

		// For consortium stats purposes (return projects with CDP enabled - not related to adjudicated values, get record count from ddp_records_data)
//		if (!empty($ddp_project_ids)) {
//			$sql = "select count(distinct(concat(p.project_id, '-', r.record)))
//					from redcap_projects p, redcap_ddp_records r, redcap_ddp_records_data d
//					where p.status > 0 and p.realtime_webservice_enabled = 1 and realtime_webservice_type = 'FHIR'
//					and p.project_id = r.project_id and r.mr_id = d.mr_id
//					and p.project_id not in (" . $this->getIgnoredProjectIds() . ")";
//			$total_ddp_records_imported = db_result(db_query($sql));
//			$total_ddp_projects_adjudicated = count($ddp_project_ids);
//		}

        // Return three counts
        return [$total_ddp_projects_adjudicated, $total_ddp_records_imported, $total_ddp_values_adjudicated];
    }

    // Return MLM stats for insertion in the stats URL's query string (e.g., &mlm_sys_count=4...)
    public function getMlmStats()
    {
        // System langs
	    $sysLangs = MultiLanguage::getSystemLanguages();
        $sysLangIds = urlencode(implode(",", array_keys($sysLangs)));
        $sysLangCount = count($sysLangs);

	    $url = "&mlm_sys_count=$sysLangCount&mlm_sys_ids=$sysLangIds";

        // Project-level utilization
	    $sql = "select count(distinct(m.project_id)) from redcap_projects p, redcap_multilanguage_config m
                where p.project_id = m.project_id and m.name = 'active' and p.project_id not in (".$this->getIgnoredProjectIds().")";
	    $url .= "&mlm_proj=".db_result(db_query($sql));

        // Projects with 1, 2, 3, or more languages active
        $queries = ['1'=>'=1', '2'=>'=2', '3'=>'=3', '4ormore'=>'>=4'];
        foreach ($queries as $name=>$sqlp) {
	        $sql = "select m.project_id from redcap_projects p, redcap_multilanguage_config m
                    where p.project_id = m.project_id and m.name = 'active' and p.project_id not in (".$this->getIgnoredProjectIds().")
                    group by p.project_id having count(*) $sqlp";
	        $url .= "&mlm_proj_langs".$name."=".db_num_rows(db_query($sql));
        }

        // Avg/median/max number of langs in MLM enabled projects
        $sql = "select count(*) as num_langs 
                from redcap_projects p, redcap_multilanguage_config m
                where p.project_id = m.project_id and m.name = 'active' and p.project_id not in (".$this->getIgnoredProjectIds().")
                group by p.project_id";
        $q = db_query($sql);
        $langs_per_project = [];
        while ($row = db_fetch_assoc($q)) {
	        $langs_per_project[] = $row['num_langs'];
        }
	    $url .= "&mlm_avg_langs_per_proj=".round(mean($langs_per_project));
	    $url .= "&mlm_mdn_langs_per_proj=".round(median($langs_per_project));
	    $url .= "&mlm_max_langs_per_proj=".round(maxRC($langs_per_project));

        // Return URL params
        return $url;
    }

	// Return URL for reporting stats to consortium. Includes all calculated stat counts.
	public function getUrlReportingStats($reportManually=false, $forceGenerateNew=false)
	{
		global $dts_enabled_global, $realtime_webservice_global_enabled, $auth_meth_global, $redcap_version, $fhir_ddp_enabled, $fhir_data_mart_create_project,
			   $institution, $homepage_contact, $homepage_contact_email, $site_org_type, $report_stats_url, $aws_quickstart, $azure_quickstart, $project_contact_email;
			   
		// If we're not force-generating a new url, then return the cached URL
		if (!$forceGenerateNew && $report_stats_url != '')
		{
			return $report_stats_url;
		}

		// Get server's IP address
		$server_ip = getServerIP();

		// Set alternative hostname if we know the domain name in the URL is internal (i.e. without dots)
		$alt_hostname = (strpos(SERVER_NAME, ".") === false) ? SERVER_NAME : "";

		// Get project count
		$num_prototypes = 0;
		$num_production = 0;
		$num_inactive   = 0;
		$num_archived   = 0;
		$q = db_query("select status, count(status) as count from redcap_projects where project_id not in (".$this->getIgnoredProjectIds().") group by status");
		while ($row = db_fetch_assoc($q)) {
			if ($row['status'] == '0') $num_prototypes = $row['count'];
			if ($row['status'] == '1') $num_production = $row['count'];
			if ($row['status'] == '2') $num_inactive   = $row['count'];
			if ($row['status'] == '3') $num_archived   = $row['count'];
		}

		// Get counts of project purposes
		$purpose_other = 0;
		$purpose_research = 0;
		$purpose_qualimprove = 0;
		$purpose_operational = 0;
		$q = db_query("select purpose, count(purpose) as count from redcap_projects where project_id not in (".$this->getIgnoredProjectIds().") group by purpose");
		while ($row = db_fetch_array($q))
		{
			switch ($row['purpose'])
			{
				case '1': $purpose_other = $row['count']; break;
				case '2': $purpose_research = $row['count']; break;
				case '3': $purpose_qualimprove = $row['count']; break;
				case '4': $purpose_operational = $row['count']; break;
			}
		}

		// DTS: Get count of production projects utilizing DTS
		$dts_count = 0;
		if ($dts_enabled_global)
		{
			$q = db_query("select count(1) from redcap_projects where status > 0 and dts_enabled = 1 and project_id not in (".$this->getIgnoredProjectIds().")");
			$dts_count = db_result($q, 0);
		}

		// DDP: Get count of production projects utilizing DDP
		$ddp_count = $ddp_records_imported = 0;
		if ($realtime_webservice_global_enabled)
		{
			$q = db_query("select count(1) from redcap_projects where status > 0 and realtime_webservice_enabled = 1 and realtime_webservice_type = 'CUSTOM' and project_id not in (".$this->getIgnoredProjectIds().")");
			$ddp_count = db_result($q, 0);
			// Get count of records that have had data imported from source system in DDP-enabled projects
			$sql = "select count(distinct(concat(p.project_id, '-', r.record)))
					from redcap_projects p, redcap_ddp_records r, redcap_ddp_records_data d
					where p.status > 0 and p.realtime_webservice_enabled = 1 and realtime_webservice_type = 'CUSTOM' 
					and p.project_id = r.project_id and r.mr_id = d.mr_id
					and p.project_id not in (".$this->getIgnoredProjectIds().")";
			$ddp_records_imported = db_result(db_query($sql), 0);
		}

		// CDP: Get count of production projects utilizing CDP
		$ddp_fhir_count = $ddp_fhir_records_imported = $ddp_fhir_values_adjud = "";
		if ($fhir_ddp_enabled)
		{
			list ($ddp_fhir_count, $ddp_fhir_records_imported, $ddp_fhir_values_adjud) = $this->getCDPstats();
		}

		// Randomization: Get count of production projects using the randomization module (and have a prod alloc table uploaded)
		list ($rand_count, $rand_count_multi, $rand_count_blinded, $rand_count_logic_trigger) = Stats::randomizationCounts();

		// Get user count
		$num_users = db_result(db_query("select count(1) from redcap_user_information"), 0);

		// Get count of projects using Double Data Entry module (production only)
		$sql = "select count(1) from redcap_projects where status > 0 and double_data_entry = 1 and project_id not in (".$this->getIgnoredProjectIds().")";
		$total_dde = db_result(db_query($sql), 0);

		// Count CAT assessment responses (partial and completed)
		$cat_responses_dev = $cat_responses_prod = 0;
		$sql = "select p.status, count(1) as count
				from redcap_library_map l, redcap_surveys s, redcap_projects p, redcap_surveys_participants sp, redcap_surveys_response r
				where p.project_id not in (".$this->getIgnoredProjectIds().") and l.promis_key is not null
				and l.promis_key != '' and s.project_id = l.project_id and p.project_id = s.project_id
				and s.survey_id = sp.survey_id and sp.participant_id = r.participant_id
				and r.first_submit_time is not null and s.form_name = l.form_name
				group by p.status";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if ($row['status'] == '0') {
				// Dev
				$cat_responses_dev += $row['count'];
			} else {
				// Prod (includes Inactive and Archived)
				$cat_responses_prod += $row['count'];
			}
		}
        // Get count of alerts and projects utilizing them
        list ($total_alerts, $total_alerts_projects, $total_alerts_projects_prod) = $this->getAlertCounts();
		// Data Mart
		$data_mart_projects = $data_mart_records = $data_mart_values = "";
		if ($fhir_data_mart_create_project) {
			$data_mart_project_ids = $this->getDataMartProjectIds();
			$data_mart_projects = count($data_mart_project_ids);
			$data_mart_records = $this->getDataMartRecordsImported();
			$data_mart_values = $this->getTotalDataMartValuesImported($data_mart_project_ids);
		}

		// Send site stats to the REDCap Consortium and get response back
		$url = CONSORTIUM_WEBSITE."collect_stats.php?hostname=".SERVER_NAME."&ip=$server_ip"
			 . "&alt_hostname=$alt_hostname&hostkey_hash=".Stats::getServerKeyHash()
			 . "&num_prots=$num_prototypes&num_prods=$num_production&num_archived=$num_archived"
			 . "&rnd982g4078393ae839z1".($reportManually ? "" : "_auto")
			 . "&purposes=$purpose_other,$purpose_research,$purpose_qualimprove,$purpose_operational"
			 . "&num_inactive=$num_inactive&num_users=$num_users&auth_meth=$auth_meth_global&version=$redcap_version"
			 . "&activeusers1m=".Stats::getActiveUsers(30)."&activeusers6m=".Stats::getActiveUsers(183)."&activeuserstotal=".Stats::getActiveUsers()
			 . "&usersloggedin1m=".Stats::getUserLogins(30)."&usersloggedin6m=".Stats::getUserLogins(183)."&usersloggedintotal=".Stats::getUserLogins()
			 . "&hostlabel=" . urlencode($institution)
			 . "&homepage_contact=".urlencode($homepage_contact)."&homepage_contact_email=$homepage_contact_email&admin_email=$project_contact_email"
			 . "&dts=$dts_count&ddp=$ddp_count&ddp_records=$ddp_records_imported&ddp_fhir=$ddp_fhir_count&ddp_fhir_records=$ddp_fhir_records_imported&ddp_fhir_values_adjud=$ddp_fhir_values_adjud"
			 . "&data_mart=$data_mart_projects&data_mart_records=$data_mart_records&data_mart_values=$data_mart_values"
			 . "&rand=$rand_count&rand_count_multi=$rand_count_multi&rand_count_blinded=$rand_count_blinded&rand_count_logic_trigger=$rand_count_logic_trigger"
             . "&dde=$total_dde&parentchild=0"
			 . "&cats_dev=$cat_responses_dev&cats_prod=$cat_responses_prod&mobile_app_users=".$this->mobileAppUserCount()
			 . "&mobile_app_users1m=".$this->mobileAppUserCount(1)."&mobile_app_users6m=".$this->mobileAppUserCount(6)
			 . "&mobile_app_projects_init=".$this->mobileAppInitProjectCount()."&mobile_app_projects_sync=".$this->mobileAppSyncDataProjectCount()
			 . "&two_factor=".Stats::getTwoFactorAuthOptionsUsed()
             . "&twilio_projects=".$this->twilioProjectCount()
             . "&mosio_projects=".$this->mosioProjectCount()
             . "&sendgrid_projects=".$this->sendgridProjectCount()
			 . "&full_url=".urlencode(APP_PATH_WEBROOT_FULL)."&site_org_type=".urlencode($site_org_type)
			 . "&econsent_projects=".$this->countProjectsUsingEconsent()."&econsent_files=".$this->countEconsentPdfsStored()
             . "&aws_quickstart=$aws_quickstart&azure_quickstart=$azure_quickstart"
             . "&alerts=$total_alerts&alerts_projects=$total_alerts_projects&alerts_projects_prod=$total_alerts_projects_prod"
			 . "&php_version=".System::getPhpVersion()."&mysql_version=".db_get_version()
			 . "&fieldbank=".urlencode(json_encode($this->fieldBankCounts()))
             // MyCap stats
			 . "&mycap_projects=".urlencode($this->mycapInitProjectCount())
			 . "&mycap_part_joined=".urlencode($this->mycapParticipantJoinedCount())
			 . "&mycap_tasks=".urlencode($this->mycapTaskCount())
			 . "&mycap_active_tasks=".urlencode($this->mycapActiveTaskCount())
			 . "&mycap_messages=".urlencode($this->mycapMessagesSentReceivedCount())
             // MLM stats
			 . $this->getMlmStats()
        ;

        // AI stats
        $url .= "&ai_service=".urlencode($this->aiService())
              . "&ai_projects=".urlencode($this->aiApiCallProjects())
              . "&ai_calls=".urlencode($this->aiApiCalls());
        foreach ($this->aiApiCallProjectsServices() as $param=>$pcount) {
            $url .= "&".$param."=".$pcount;
        }
        foreach ($this->aiApiCallsServices() as $param=>$pcount) {
            $url .= "&".$param."=".$pcount;
        }

        // Modules: Get EM string and check if it makes URL too long
        $modules = "&modules=".urlencode(json_encode($this->getExternalModuleCounts()));
        if (mb_strlen($url.$modules) < 4000) {
            $url .= $modules;
        }

		// Save value in redcap_config table
		$sql = "update redcap_config set value = '".db_escape($url)."' where field_name = 'report_stats_url'";
		db_query($sql);

		return $url;
	}

	// Check if need to report institutional stats to REDCap consortium
	public static function checkReportStats()
	{
		global $auto_report_stats, $auto_report_stats_last_sent;
		// If auto stat reporting is set, check if more than 7 days have passed in order to report current stats
		// Only do checking when user is on a project's index page
		if ($auto_report_stats && (PAGE == "index.php" || PAGE == "ProjectSetup/index.php" || strpos(PAGE, "ControlCenter") === 0))
		{
			list ($yyyy, $mm, $dd) = explode("-", $auto_report_stats_last_sent);
			$daydiff = ceil((mktime(0, 0, 0, date("m"), date("d"), date("Y")) - mktime(0, 0, 0, $mm, $dd, $yyyy)) / (3600 * 24));
			// If not reported in 7 days, trigger AJAX call to report them
			if ($daydiff >= 7)
			{
				// Instantiate Stats object
				$Stats = new Stats();
				// Render javascript for AJAX call
				?>
				<script type='text/javascript'>
                    $(function(){
                        reportStatsAjax('<?php print js_escape($Stats->getUrlReportingStats(false)) ?>');
                    });
				</script>
				<?php
			}
		}
	}

    // SEND MTB TASKS STATS: Obtain local library stats to send to consortium
    public static function sendMyCapMTBTasksStats($returnUrl=false)
    {
        // Set alternative hostname if we know the domain name in the URL is internal (i.e. without dots)
        $alt_hostname = (strpos(SERVER_NAME, ".") === false) ? SERVER_NAME : "";
        // Set URL to call
        $url = CONSORTIUM_WEBSITE . "collect_stats_mtb.php?rnd431bor5a966fors&app=0&hostname=".SERVER_NAME."&ip=".getServerIP()."&alt_hostname=$alt_hostname&hostkey_hash=".Stats::getServerKeyHash();
        // Get query string counts
        $url .= self::getMyCapMTBTasksStatsQueryString();
        // Send GET request or return URL
        return $returnUrl ? $url : http_get($url);
    }

    // OBTAIN MTB TASKS STATS QUERY STRING
    public static function getMyCapMTBTasksStatsQueryString()
    {
	    $Stats = new Stats();

        // Get count of Projects with any MTB task created
        $url = "&total_projects=".urlencode($Stats->mycapProjectsCountWithMTBTasks());

        // Get array of all count of unique projects that each MTB task is enabled for MyCap
        $mtbTaskProjCounts = $Stats->mycapProjectsCountForEachMTBTasks();

        foreach ($mtbTaskProjCounts as $mtbTaskFormat => $mtbTaskProjCount) {
            $mtbTaskFormat = str_ireplace(array("FNAME", "Mtb"), array("Fname", ""), $mtbTaskFormat);
            $flag = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $mtbTaskFormat)); // Exa. Converts "MtbArrows to mtb_arrows"

            $url .= "&".$flag."=".urlencode($mtbTaskProjCount);
        }
        // Return query string
        return $url;
    }

    // MyCap: Return count of Projects with any MTB task
    // Exclude "practice" projects.
    public function mycapProjectsCountWithMTBTasks()
    {
        if (!$GLOBALS['mycap_enabled_global']) return 0;

        $mtb_formats = \Vanderbilt\REDCap\Classes\MyCap\ActiveTask::getResearchKitActiveTasksFormats("mtb");
        $sql = "select count(*) from 
                (select distinct m.project_id from redcap_mycap_tasks m, redcap_projects p, redcap_metadata a
                    where p.project_id = m.project_id and p.mycap_enabled = 1 
                    and a.project_id = m.project_id and m.form_name = a.form_name
                    and m.enabled_for_mycap = 1 and m.question_format in (" . prep_implode($mtb_formats) . ")
                    and p.project_id not in (" . $this->getIgnoredProjectIds() . ")
                ) x";

        $q = db_query($sql);
        return db_result($q, 0);
    }

    // MyCap: Return count unique projects that each MTB task is enabled for mycap
    // Ex. Faces and Names is added 3x on 1 project and 4x on another project - count will equal 2
    // Exclude "practice" projects.
    public function mycapProjectsCountForEachMTBTasks()
    {
        if (!$GLOBALS['mycap_enabled_global']) return [];

        $result = [];
        $mtb_formats = \Vanderbilt\REDCap\Classes\MyCap\ActiveTask::getResearchKitActiveTasksFormats("mtb");
        foreach ($mtb_formats as $mtb_format) {
            $sql = "select count(*) from 
                (select distinct m.project_id from redcap_mycap_tasks m, redcap_projects p, redcap_metadata a
                    where p.project_id = m.project_id and p.mycap_enabled = 1 
                    and a.project_id = m.project_id and m.form_name = a.form_name
                    and m.enabled_for_mycap = 1 and m.question_format = '" . $mtb_format . "'
                    and p.project_id not in (" . $this->getIgnoredProjectIds() . ")
                ) x";

            $q = db_query($sql);
            $result[$mtb_format] = db_result($q, 0);
        }
        return $result;
    }
}