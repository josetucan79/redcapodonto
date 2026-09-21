<?php namespace Vanderbilt\REDCap\Classes\TopUsageReport;

const DATE_FORMAT = 'Y-m-d\\TH:i';

class TopUsageReport
{
    function getData(){
        global $lang;

        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();
    
        db_query('set @start = ?', $startTime);
        db_query('set @end = ?', $endTime);
    
        $groups = [];
        $totals = [];
    
        $countCall = function($row, &$typeDetails) use ($startTime, $endTime){
            $overlap = min(strtotime($row['call_end']), strtotime($endTime)) - max(strtotime($row['call_start']), strtotime($startTime));
            if (!isset($typeDetails['calls'])) { 
                $typeDetails['calls'] = 0;
                $typeDetails['time'] = 0;
            }
            $typeDetails['calls']++;
            $typeDetails['time'] += $overlap;
        };
    
        $userColumnName = $lang['global_17'];
        $projectColumnName = $lang['global_65'];
        $moduleColumnName = $lang['top_usage_report_08'];
        $specificURLColumnName = $lang['api_docs_050'];
        $generalURLColumnName = $lang['survey_132'];

        $excludeIncompleteHttpRequestsClause = $this->shouldIncludeIncompleteHTTPRequests() ? '' : 'and r.script_execution_time is not null';
    
        $result = db_query("
            select
                user as '$userColumnName',
                p.project_id as '$projectColumnName',
                p.app_title,
                full_url as '$specificURLColumnName',
                page = 'api/index.php' as is_api,
                ts as call_start,
                date_add(ts, interval script_execution_time second) as call_end
            from (
                select
                    r.log_view_id,
                    ts,
                    user,
                    project_id,
                    full_url,
                    page,
                    if(
                        script_execution_time,
                        script_execution_time,
                        timestampdiff(second, ts, now())
                    ) as script_execution_time
                from redcap_log_view_requests r
                left join redcap_log_view v
                    on v.log_view_id = r.log_view_id
                where
                    r.log_view_id is not null
                    $excludeIncompleteHttpRequestsClause
            ) r
            left join redcap_projects p
                on p.project_id = r.project_id
            where
                (
                    (
                        ts >= @start
                        and
                        ts < @end
                    )
                    or
                    (
                        date_add(ts, interval script_execution_time second) > @start
                        and
                        date_add(ts, interval script_execution_time second) <= @end
                    )
                )
        ", []);
    
        while($row = $result->fetch_assoc()){
            $types = [
                $userColumnName,
                $projectColumnName,
                $moduleColumnName,
                $specificURLColumnName,
                $generalURLColumnName,
            ];
    
            foreach($types as $type){
                if($type === $moduleColumnName){
                    $parts = explode('prefix=', $row[$specificURLColumnName]);
                    if(count($parts) === 1){
                        // This is not a module specific request
                        continue;
                    }
    
                    $prefix = explode('&', $parts[1])[0];
                    $identifier = $prefix;
                }
                else if(in_array($type, [$specificURLColumnName, $generalURLColumnName])){
                    $identifier = $row[$specificURLColumnName];
                    $identifier = str_replace(APP_PATH_WEBROOT_FULL, '/', $identifier);
                    $identifier = str_replace(APP_PATH_WEBROOT, '/', $identifier);
    
                    $parts = explode('?', $identifier);
                    if($type === $generalURLColumnName){
                        $identifier = $parts[0];
                    }
                    else if(count($parts) === 1){
                        /**
                         * This URL doesn't have params, and will already be counted as a general URL.
                         * It's confusing to count it again as a specific URL, so skip it.
                         */
                        continue;
                    }
                }
                else{
                    $identifier = $row[$type];
    
                    if(empty($identifier) && in_array($type, [$userColumnName, $projectColumnName])){
                        // Only count these lines for other identifiers
                        continue;
                    }
                    else if($type === $projectColumnName){
                        $identifier = $row['app_title'] . " ($identifier)";
                    }
                }
    
                $countCall($row, $groups[$row['is_api']][$type][$identifier]);
            }
    
            $countCall($row, $totals);
        }
    
        $result = db_query('
            select
                cron_name,
                directory_prefix,
                cron_run_start as call_start,
                cron_run_end as call_end
            from (
                select
                    cron_id,
                    cron_run_start,
                    if(
                        cron_run_end,
                        cron_run_end,
                        now()
                    ) as cron_run_end
                from redcap_crons_history h
            ) h
            join redcap_crons c
                on c.cron_id = h.cron_id
            left join redcap_external_modules m
                on m.external_module_id = c.external_module_id
            where
                (
                    (
                        cron_run_start >= @start
                        and
                        cron_run_start < @end
                    )
                    or
                    (
                        cron_run_end > @start
                        and
                        cron_run_end <= @end
                    )
                )
        ', []);
    
        while($row = $result->fetch_assoc()){
            $cronName = $row['cron_name'];
            $prefix = $row['directory_prefix'] ?? 'REDCap';
            $identifier = "$prefix - $cronName";
    
            $details = &$groups[false]['Cron'][$identifier];
            $countCall($row, $details);
            $countCall($row, $totals);
        }
    
        if(empty($totals['time'])){
            // Allow for testing on localhost with minimal traffic totalling 0 seconds.
            return [];
        }
    
        $thresholdTime = $totals['time'] * $this->getThreshold()/100;
        $tops = [];
        foreach($groups as $isApi=>$types){
            foreach($types as $type=>$identifiers){
                foreach($identifiers as $identifier=>$details){
                    if($details['time'] < $thresholdTime){
                        continue;
                    }
    
                    $calls = $details['calls'];
                    $time = $details['time'];
                    $displayType = $type;
    
                    if($isApi && in_array($type, [$userColumnName, $projectColumnName])){
                        $displayType = "API ($displayType)";
                    }
    
                    $tops[] = [
                        $lang['home_39'] => $displayType,
                        $lang['top_usage_report_02'] => $identifier,
                        $lang['top_usage_report_03'] => number_format($time/60/60, 1),
                        $lang['top_usage_report_04'] => number_format($time/$totals['time']*100, 1) . '%',
                        $lang['top_usage_report_05'] => number_format($calls),
                        $lang['top_usage_report_06'] => number_format($calls/$totals['calls']*100, 1) . '%',
                        $lang['top_usage_report_07'] => number_format($time/$calls, 1),
                    ];
                }
            }
        }
    
        return $tops;
    }

    function getStartTime(){
        $oneHour = 60*60;
        $oneHourAgo = time() - $oneHour;

        return htmlspecialchars($_GET['start-time'] ?? date(DATE_FORMAT, $oneHourAgo), ENT_QUOTES);
    }

    function getEndTime(){
        return htmlspecialchars($_GET['end-time'] ?? date(DATE_FORMAT, time()), ENT_QUOTES);
    }

    function getThreshold(){
        return htmlspecialchars($_GET['threshold'] ?? 1, ENT_QUOTES);
    }

    function shouldIncludeIncompleteHTTPRequests(){
        return isset($_GET['include-incomplete-http-requests']);
    }

    function getControls(){
        global $lang;

        ?>
        <label><?=$lang['survey_439']?></label><input name='start-time' type='datetime-local' value='<?=$this->getStartTime()?>' style='margin-right: 5px'> (<?=$lang['top_usage_report_20'] . ' ' . \RCView::span(['class'=>'boldish'], date('Y-m-d H:i', $this->getMinimumStartDate()+60))?>)<br>
        <label><?=$lang['survey_440']?></label><input name='end-time' type='datetime-local' value='<?=$this->getEndTime()?>'><br>
        <input name='include-incomplete-http-requests' type='checkbox' <?php if($this->shouldIncludeIncompleteHTTPRequests()) echo 'checked'?>><label><?=$lang['top_usage_report_09']?></label>
        <a href="javascript:;" class="help" onclick="simpleDialog('<?=$lang['top_usage_report_10']?>');">?</a><br>
        <?=$lang['top_usage_report_11']?> <input name='threshold' value='<?=$this->getThreshold()?>' style='width: 26px; text-align: right'>%
        <a href="javascript:;" class="help" onclick="simpleDialog('<?=$lang['top_usage_report_12']?>');">?</a>
        <br><br>
        <button><?=$lang['control_center_4877']?></button>
        <?php
    }

    static function getMinimumStartDate(){
        return mktime(date("H"),date("i"),date("s"),date("m"),date("d")-7,date("Y"));
    }
}