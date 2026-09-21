<?php

class Calendar
{
	// Return the record name for a given calendar ID in the calendar db table
	public static function getRecordByCalId($cal_id)
	{
        if (!isinteger($cal_id)) return false;
        $sql = "select record from redcap_events_calendar where cal_id = ?";
        $q = db_query($sql, $cal_id);
		return (db_num_rows($q) > 0 ? db_result($q, 0) : false);
	}

	// Return boolean if $record has at least one calendar event associated with it
	public static function recordHasCalendarEvents($record)
	{
		global $Proj;
		$sql = "select 1 from redcap_events_calendar where project_id = " . PROJECT_ID . " 
				and (event_id is null or event_id in (".prep_implode(array_keys($Proj->eventInfo)).")) 
				and record = '".db_escape($record)."' limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}
	
	// Return HTML table agenda for calendar events in next $daysFromNow days (optional: limit to a specific $record)
	public static function renderUpcomingAgenda($daysFromNow=7, $record=null, $returnCountOnly=false, $showTableTitle=true)
	{
		global $lang, $user_rights, $Proj, $double_data_entry;
		if (!is_numeric($daysFromNow)) return false;
		// Exclude records not in your DDE group (if using DDE)
		$dde_sql = "";
		if ($double_data_entry && isset($user_rights['double_data']) && $user_rights['double_data'] != 0) {
			$dde_sql = "and record like '%--{$user_rights['double_data']}'";
		}
		// If returning single record
		$record_sql = "";
		if ($record !== null) {
			$record_sql = "and record = '".db_escape($record)."'";
		}
		// Get calendar events
		$sql = "select * from redcap_events_calendar where project_id = " . PROJECT_ID . " 
				and (event_id is null or event_id in (".prep_implode(array_keys($Proj->eventInfo)).")) and event_date >= '" . date("Y-m-d") . "' 
				and event_date <= '" . date("Y-m-d", mktime(0, 0, 0, date("m"), date("d")+$daysFromNow, date("Y"))) . "'
				" . (($user_rights['group_id'] == "") ? "" : "and group_id = " . $user_rights['group_id']) . " 
				$dde_sql $record_sql order by event_date, event_time";
		$q = db_query($sql);

		$cal_list = array();
		$num_rows = db_num_rows($q);
		
		if ($returnCountOnly) return $num_rows;

		if ($num_rows > 0) {

			while ($row = db_fetch_assoc($q))
			{
				$caldesc = "";
				// Set image to load calendar pop-up
				$popup = "<a href=\"javascript:;\" onclick=\"popupCal({$row['cal_id']},800);\">"
						 . "<img src=\"".APP_PATH_IMAGES."magnifier.png\" style=\"vertical-align:middle;\" title=\"".js_escape2($lang['scheduling_80'])."\" alt=\"".js_escape2($lang['scheduling_80'])."\"></a> ";
				// Trim notes text
				$row['notes'] = trim($row['notes'] ?? "");
				// If this calendar event is tied to a project record, display record and Event
				if ($row['record'] != "") {
					$caldesc .= removeDDEending($row['record']);
				}
				if ($row['event_id'] != "") {
					$caldesc .= " (" . $Proj->eventInfo[$row['event_id']]['name_ext'] . ") ";
				}
				if ($row['group_id'] != "") {
					$caldesc .= " [" . $Proj->getGroups($row['group_id']) . "] ";
				}
				if ($row['notes'] != "") {
					if ($row['record'] != "" || $row['event_id'] != "") {
						$caldesc .= " - ";
					}
					$caldesc .= $row['notes'];
				}
				// Add to table
				$cal_list[] = array($popup, DateTimeRC::format_ts_from_ymd($row['event_time']), DateTimeRC::format_ts_from_ymd($row['event_date']), "<span class=\"notranslate\">".RCView::escape($caldesc)."</span>");
			}

		} else {

			$cal_list[] = array('', '', '', $lang['index_52']);

		}
		
		$title = $instructions = $divClasses = "";
		if ($showTableTitle) {
			$divClasses = (PAGE == 'index.php') ? "" : "col-12 col-md-6";
			$title = "<div style=\"padding:0;\">
					  <span style=\"color:#A00000;\"><i class=\"far fa-calendar-plus\"></i> {$lang['index_53']} &nbsp;<span style=\"font-weight:normal;\">{$lang['index_54']}</span></span></div>";
		} else {
			$instructions = RCView::div(array('style'=>'margin-bottom:10px;'), $lang['calendar_16'] . " ". RCView::b("$daysFromNow " . $lang['scheduling_25']) . $lang['period']);
		}
		$col_widths_headers = [
			array('', 'center'),
			array($lang['global_13']),
			array($lang['global_18']),
			array($lang['global_20'])
		];
		
		// Build HTML
		$html = "<div class='$divClasses'>$instructions";
		$html .= renderTable("cal_table", $title, $col_widths_headers, $cal_list, true, true, false);
		$html .= "</div>";
		
		return $html;
	}

	/**
	 * Retrieve logging-related info when adding/updating/deleting calendar events using the cal_id
	 */
	public static function calLogChange($cal_id) {
		if ($cal_id == "" || $cal_id == null || !is_numeric($cal_id)) return "";
		$logtext = array();
		$sql = "select c.*, (select m.descrip from redcap_events_metadata m, redcap_events_arms a where a.project_id = c.project_id
                and m.event_id = c.event_id and a.arm_id = m.arm_id) as descrip from redcap_events_calendar c where c.cal_id = $cal_id limit 1";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if ($row['record']     != "") $logtext[] = "Record: ".$row['record'];
			if ($row['descrip']    != "") $logtext[] = "Event: ".$row['descrip'];
			if ($row['event_date'] != "") $logtext[] = "Date: ".$row['event_date'];
			if ($row['event_time'] != "") $logtext[] = "Time: ".$row['event_time'];
			// Only display status change if event was scheduled (status is not listed for ad hoc events)
			if ($row['event_status'] != "" && $row['event_id'] != "") {
				switch ($row['event_status']) {
					case '0': $logtext[] = "Status: Due Date"; break;
					case '1': $logtext[] = "Status: Scheduled"; break;
					case '2': $logtext[] = "Status: Confirmed"; break;
					case '3': $logtext[] = "Status: Cancelled"; break;
					case '4': $logtext[] = "Status: No Show";
				}
			}
		}
		return implode(", ", $logtext);
	}

	/**
	 * RETRIEVE ALL CALENDAR EVENTS
	 */
	public static function getCalEvents($month, $year)
	{
		global $user_rights, $Proj;

		// Place info into arrays
		$event_info = array();
		$events = array();

		$year_month = (strlen($month) == 2) ? $year . "-" . $month : $year . "-0" . $month;
		$sql = "select * from redcap_events_metadata m 
                right outer join redcap_events_calendar c on c.event_id = m.event_id
                where c.project_id = " . PROJECT_ID . " and YEAR(c.event_date)= {$year} and MONTH(c.event_date)= {$month}
                " . (($user_rights['group_id'] != "") ? "and c.group_id = {$user_rights['group_id']}" : "") . "
                order by c.event_date, c.event_time, c.record";
		$query_result = db_query($sql);
		$i = 0;
		while ($info = db_fetch_assoc($query_result))
		{
			$thisday = substr($info['event_date'],-2)+0;
			$events[$thisday][] = $event_id = $i;
			$event_info[$event_id]['0'] = $info['descrip'];
			$event_info[$event_id]['1'] = $info['record'];
			$event_info[$event_id]['2'] = $info['event_status'];
			$event_info[$event_id]['3'] = $info['cal_id'];
			$event_info[$event_id]['4'] = $info['notes'];
			$event_info[$event_id]['5'] = $info['event_time'];
			// Add DAG, if exists
			if ($info['group_id'] != "" && $user_rights['group_id'] == "") {
				$event_info[$event_id]['6'] = $Proj->getGroups($info['group_id']);
			}
			$i++;
		}

		// Return the two arrays
		return array($event_info, $events);
	}

    /**
     * Output the HTML for the Calendar Sync information dialog on the Calendar page
     */
    public static function renderCalendarSyncInstructions($project_id, $record=null, $alsoRenderParticipantLink=false)
    {
        global $lang, $user_rights;
		$ui_id = UserRights::isImpersonatingUser() ? User::getUIIDByUsername(UserRights::getUsernameImpersonating()) : UI_ID;
        $icsUrl = APP_PATH_WEBROOT_FULL.'surveys/index.php?__calendar='.Calendar::getFeedHash($project_id, $record, $ui_id);
        if ($alsoRenderParticipantLink && $record != null) {
            $icsUrlParticipant = APP_PATH_WEBROOT_FULL.'surveys/index.php?__calendar='.Calendar::getFeedHash($project_id, $record, null);
        }

        //If user is in DAG, only show calendar events from that DAG and give note of that
        $dagText = "";
        if (isset($user_rights['group_id']) && $user_rights['group_id'] != "") {
            $dagText = "<p class='text-dangerrc mt-1 mb-3'>{$lang['calendar_popup_41']}</p>";
        }

        $text = '<div class="simpleDialog" id="calendarSyncInfoDialog" title="'.RCView::tt_js2('calendar_19').'">
                    <p class="mt-0 mb-2">'.$lang['calendar_popup_32']." ".$lang['calendar_popup_34'].'</p>
                    '.$dagText.'
                    
                    <div class="mt-3 p-3" style="background-color:#f7f7f7;border:1px solid #ddd;">
                        <div class="font-weight-bold fs15 mb-3"><i class="fas fa-rss-square"></i> '.$lang['calendar_popup_37'].'</div>
                        <div>'.$lang['calendar_popup_35'].'</div>
                        <div class="mt-3">
                            <input value="'.$icsUrl.'" id="ics-url" class="staticInput fs14 ms-0" readonly style="text-overflow:ellipsis;overflow:hidden;white-space:nowrap;color:#e83e8c;width:96%;max-width:96%;font-family:SFMono-Regular,Menlo,Monaco,Consolas,\'Liberation Mono\',\'Courier New\',monospace;" type="text" onclick="this.select();">
                        </div>
                        <div class="mt-1">
                            <button class="btn btn-primaryrc btn-xs btn-clipboard fs13" onclick="copyUrlToClipboard(this); return false;" data-clipboard-target="#ics-url" style="margin-top: 8px;padding:3px 8px 3px 6px;"><i class="fas fa-paste"></i> '.$lang['calendar_popup_38'].'</button>                        
                        </div>
                    </div>
                    
                    <div class="m-3 fs14 text-secondary">&mdash; ' . $lang['global_46'] . ' &mdash;</div>
                    
                    <div class="p-3" style="background-color:#f7f7f7;border:1px solid #ddd;">
                        <div class="font-weight-bold fs15 mb-3"><i class="fas fa-download"></i> '.$lang['calendar_popup_33']." ".$lang['calendar_popup_40'].'</div>
                        <div>'.$lang['calendar_popup_39'].'</div>
                        <div class="mt-2">
                            <button class="btn btn-primaryrc btn-xs fs13" onclick="window.location.href = \''.$icsUrl.'\'" title="'.js_escape2($lang['calendar_popup_40']).'" style="margin-top: 8px;padding:3px 8px 3px 6px;"><i class="fas fa-download"></i> '.$lang['calendar_popup_40'].'</button>
                        </div>        
                    </div> 
                      
                </div>';
        return $text;
    }

    // Get Event Summary text
    public static function getEventSummary($project_id, $event_info, $secondaryRecordLabels, $isSurveyParticipant, $userDag=null)
	{
        global $lang;
        $Proj = new Project($project_id);
        $summary = '';
        // Display record name, if calendar event is tied to a record
        if ($event_info['record'] != "" && !$isSurveyParticipant) {
            // If the record ID field is an Identifier, then remove it
            $thisRecordName = $Proj->table_pk_phi ? DEID_TEXT : $event_info['record'];
            if (isset($secondaryRecordLabels[$event_info['record']])) {
                $thisRecordName .= " ".$secondaryRecordLabels[$event_info['record']];
            }
            $summary .= RCView::escape($thisRecordName);
        }
        // Display the Event name, if exists
        if ($event_info['descrip'] != "" && !$isSurveyParticipant) {
            $summary = trim($summary) . " (" . RCView::escape(strip_tags($event_info['descrip']))  . ")";
        }
        // Display DAG name, if exists
        if ($userDag == null && isinteger($event_info['group_id']) && !$isSurveyParticipant) {
            $summary = trim($summary) . " [" . RCView::escape($Proj->getGroups($event_info['group_id']))  . "]";
        }
        // Display any Notes
        if ($event_info['notes'] != "") {
            if (($event_info['record'] != "" || $event_info['descrip'] != "") && !$isSurveyParticipant) {
                $summary = trim($summary) . " - ";
            }
            $summary = trim($summary) . " " . decode_filter_tags($event_info['notes']);
        }
        if ($summary == "") {
            $summary = $lang['global_141'];
        }
        return $summary;
    }

    // Obtain the calendar feed hash for a given project-arm-record-user (or generate it, if absent).
    // If arm=NULL and record=NULL, return the project-user hash (which is the project-level hash for the current user).
    // If user=NULL, then it is assumed that the survey respondent is the "user".
    public static function getFeedHash($project_id, $record=null, $userid=null)
    {
        // Must have either userid OR arm-record
        if (!isinteger($project_id)) return "ERROR";
        if ($userid != null && !isinteger($userid)) $userid = null;
        if ($record == null && $userid == null) return "ERROR";
        // Check if already exists
        $sql = "select hash from redcap_events_calendar_feed where project_id = $project_id";
        $sql .= $record == null ? " and record is null" : " and record = '".db_escape($record)."'";
        $sql .= $userid == null ? " and userid is null" : " and userid = '".db_escape($userid)."'";
        $sql .= " order by feed_id limit 1";
        $q = db_query($sql);
        if (db_num_rows($q)) {
            // Use existing hash
            $hash = db_result($q, 0);
        } else {
            // Generate a new random hash
            do {
                $hash = generateRandomHash(100);
                $sql = "insert into redcap_events_calendar_feed (project_id, record, userid, hash) values
                        ($project_id, ".checkNull($record).", ".checkNull($userid).", '$hash')";
                $hashAlreadyExists = db_query($sql);
            } while (!$hashAlreadyExists);
        }
        // Return the hash
        return $hash;
    }

	// Obtain the calendar feed's attributes (project_id, etc.) using the hash
	public static function getFeedAttributes($hash)
	{
		// Get the calendar feed's attributes (return an empty array if feed is associated with a suspended user)
		$sql = "select * from redcap_events_calendar_feed f
			left join redcap_user_information i on i.ui_id = f.userid    
			where f.hash = '".db_escape($hash)."' and i.user_suspended_time is null";
		$q = db_query($sql);
		$calFeed = db_num_rows($q) ? db_fetch_assoc($q) : [];

		// Make sure user hasn't expired on this project
		if (isset($calFeed['userid']) && $calFeed['userid'] != '')
		{
			$project_id = $calFeed['project_id'];
			$userInfo = User::getUserInfoByUiid($calFeed['userid']);
			if (is_array($userInfo) && !empty($userInfo)) {
				$user_rights2 = UserRights::getPrivileges($project_id, $userInfo['username']);
				if (isset($user_rights2[$project_id][$userInfo['username']])) {
					$user_rights = $user_rights2[$project_id][$userInfo['username']];
					// User has expired for this projct, so return empty array
					if ($user_rights['expiration'] != "" && $user_rights['expiration'] <= TODAY) {
						return [];
					}
				}
			}
		}

		return $calFeed;
	}

	/**
	* @param array $events
	* @return array
	*/
	public static function getDatetimeLimits($events) {
	   $makeDate = function($event) {
		   $date = $event['event_date'] ?? '';
		   $time = $event['event_time'] ?? '';
		   return strtotime("$date $time");
	   };
	   if(empty($events)) {
		   $now = new DateTime();
		   return [$now, $now];
	   }
	   $first = reset($events);
	   $last = end($events); // this will match the first if only one event
	   $limits = [$makeDate($first), $makeDate($last)];
	   return $limits;
   }

	/**
     * ts:1669224464
     * time:"2022-11-23T17:27:44+0000"
     * offset:-25200
     * isdst:false
     * abbr:"MST"
     * 
     * @param array $transition
     * @return array
     */
    public static function buildTimezones($timezone, $start, $end) {
		$allTransitions = $timezone->getTransitions(); // get all available transitions
        $makeZone = function($label, $name, $tzFrom, $tzTo, $dateStart) {
			/* $dateTime = new DateTime($transition['time']);
            // $dateTime->setTimezone($timezone);
            $month = $dateTime->format('n');
            $dayOfWeek =  strtoupper(substr($dateTime->format('D'), 0, 2));
			$rule = "RRULE:FREQ=YEARLY;BYMONTH=$month;BYDAY=1$dayOfWeek"; */
            $lines = [
				"BEGIN:$label",
				"TZNAME:$name",
				"TZOFFSETFROM:$tzFrom",
				"TZOFFSETTO:$tzTo",
				"DTSTART:$dateStart",
				"END:$label",
			];
			return implode("\r\n", $lines);
        };
        $getLabel = function($transition) {
            return $transition['isdst'] ? 'DAYLIGHT' : 'STANDARD';
        };
		$getPreviousTransition = function($transition) use($allTransitions) {
			$previousIndex = array_search($transition, $allTransitions);
			$previousTransition = $allTransitions[$previousIndex-1] ?? null;
			return $previousTransition;
		};
        $getOffset = function($transition) {
            $offset = $transition['offset'];
            return ($offset>=0 ? "+" : "-").gmdate('Hi', abs($offset));
        };
		$getOffsetRange = function($transition) use($getOffset, $getPreviousTransition) {
			$to = $getOffset($transition);
			$previous = $getPreviousTransition($transition);
			$from = $previous ? $getOffset($previous) : $to;
			return [$from, $to];
		};
        $getDateStart = function($transition) {
            $date = new DateTime($transition['time']);
            return $date->format('Ymd\THis'); // example: 19980118T230000
        };
		$checkOverlap = function($start1, $end1, $start2, $end2) {
			if(is_null($start2)) $start2 = $end2;
			if(is_null($end2)) $end2 = $start2;
			if(is_null($start2) && is_null($end2)) return false;
			// Check if the ranges overlap
			if ($start1 <= $end2 && $start2 <= $end1) {
				return true; // Ranges overlap
			}
			return false; // Ranges do not overlap
		};
		$getTransitions = function() use($timezone, $start, $end, $allTransitions, $getPreviousTransition, $checkOverlap) {
			$current = reset($allTransitions);
			$next = next($allTransitions);
			$transitions = [];
			// collect timezones that overlap the events
			while($current!=null) {
				$startTime =  intval($current['ts']);
				$endTime =  ($next!=false) ? intval($next['ts']) : null;
				$overlaps = $checkOverlap($start, $end, $startTime, $endTime);
				if($overlaps) {
					if($current && !in_array($current, $transitions)) $transitions[] = $current;
					if($next && !in_array($next, $transitions)) $transitions[] = $next;
				}
				// advance
				$current = $next;
				$next = next($allTransitions);
			}


			/**
			 * if only 1 transition is available for the specified range (start=> end)
			 * and if more than 1 transitions are available overall
			 * then add the previous transition to the returned list
			 */
			if(count($allTransitions)>1 && count($transitions)<=1) {
				$lastTransition = end($transitions);
				if($lastTransition==false) $transitions[] = end($allTransitions);
				else {

					$previousTransition = $getPreviousTransition($lastTransition);
					if($previousTransition) array_unshift($transitions, $previousTransition);
				}

			}
			// reset indexes
			$transitions = array_values($transitions);
			return $transitions;
		};

		$transitions = $getTransitions();
		// $transitions1 = $getTransitionsByDateRange($timezone, $start, $end);

        if(count($transitions)===1 ) {
            $transition = $transitions[0];
            $label = $getLabel($transition);
			$name = $transition['abbr'];
            $dateStart = $getDateStart($transition);
            list($tzFrom, $tzTo) = $getOffsetRange($transition);
			$zone = $makeZone($label, $name, $tzFrom, $tzTo, $dateStart);
            return [$zone];
        }

        $zones = [];
        foreach ($transitions as $transition) {
			list($tzFrom, $tzTo) = $getOffsetRange($transition);
            $label = $getLabel($transition);
            $dateStart = $getDateStart($transition);
            $name = $transition['abbr'];
            $zone = $makeZone($label, $name, $tzFrom, $tzTo, $dateStart);
            $zones[] = $zone;
        }
        return $zones;
    }

	/**
     *
     * @return CalendarEventDTO[]
     */
    public static function getEvents($project_id, $calFeed, $user_rights=[])
    {
        $Proj = new Project($project_id);
        // Build query
        $sqlCalAccess = (isset($user_rights['calendar']) && $user_rights['calendar'] == "1") ? "" : "and 1=2"; // Return nothing if user doesn't currently have calendar rights
        $sqlDag = (isset($user_rights['group_id']) && $user_rights['group_id'] != "") ? "AND (c.group_id = {$user_rights['group_id']} OR c.record IS NULL)" : "";
        $sqlRecord = ($calFeed['record'] != "") ? "AND c.record = '".db_escape($calFeed['record'])."'" : "";
        $sqlArm = ($calFeed['record'] != "" && isinteger($calFeed['arm']??null) && isset($Proj->events[$calFeed['arm']]))
                ? "AND (c.event_id IS NULL OR c.event_id in (".implode(", ", array_keys($Proj->events[$calFeed['arm']]['events']))."))"
                : "";
        $query = "SELECT * FROM redcap_events_metadata m 
        RIGHT OUTER JOIN redcap_events_calendar c ON c.event_id = m.event_id
        WHERE c.project_id = $project_id $sqlDag $sqlArm $sqlRecord $sqlCalAccess
        ORDER BY c.event_date, c.event_time, c.record";
        $query_result = db_query($query);
        $events = [];
        while($row = db_fetch_assoc($query_result)) {
            $events[] = $row;
        }
        return $events;
    }
}
