<?php
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);

/**
 * On redcap.vumc.org, a single date can have about 10,000 cron history entries as of 10/27/2021.
 * We limit queries to a day at a time, since that's probably enough rows to pull at one time.
 */
$start = $_GET['date'];
// Ensure that date is in Y-M-D format
if (!preg_match("/^(((\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00))([-\/])02(\6)29)|(\d{4}([-\/])((0[1-9]|1[012])(\9)(0[1-9]|1\d|2[0-8])|((0[13-9]|1[012])(\9)(29|30))|((0[13578]|1[02])(\9)31))))$/", $start)) {
	$start = TODAY;
}
$end = (new DateTime($start))->add(date_interval_create_from_date_string('1 days'))->format('Y-m-d');

$result = \ExternalModules\ExternalModules::query('
    select
        h.cron_run_start,
        h.cron_run_end,
        concat(c.cron_name, if(m.directory_prefix is null, "", concat("<br>(", m.directory_prefix, ")"))),
	    c.cron_description,
		cast(TIMESTAMPDIFF(SECOND, h.cron_run_start, h.cron_run_end) AS SIGNED),
        cast(c.cron_max_run_time/60 AS SIGNED) -- cast to make it numerically sortable
    from redcap_crons_history h
    join redcap_crons c
        on c.cron_id = h.cron_id
    left join redcap_external_modules m
        on m.external_module_id = c.external_module_id
    where
        cron_run_start >= ?
        and (cron_run_end < ? or cron_run_end is null)
    order by cron_run_start desc
', [$start, $end]);

$rows = [];
while($row = $result->fetch_row()){
    $rows[] = $row;
}

echo json_encode(['data' => $rows], JSON_PRETTY_PRINT);
