<?php

class MySQLTuner
{
	private $config = [];
	private $recs = [];

	public function __construct()
	{
		$this->setConfigValues();
	}

	private function setConfigValues()
	{
		$q = db_query("show global variables");
		while ($row = db_fetch_assoc($q)) {
			$this->config[$row['Variable_name']] = $row['Value'];
		}
		$q = db_query("show global status");
		while ($row = db_fetch_assoc($q)) {
			$this->config[$row['Variable_name']] = $row['Value'];
		}
		natcaseksort($this->config);
	}

    public static function invisiblePkEnabled()
    {
        $enabled = false;
		$q = db_query("show global variables like 'sql_generate_invisible_primary_key'");
        if (db_num_rows($q)) {
            $val = db_fetch_assoc($q)['Value'] ?? null;
            if ($val !== null) {
                $enabled = (strtoupper($val) == 'ON');
            }
        }
        return $enabled;
    }

	public function getRecommendations()
	{
		$this->applyRules();
		return $this->formatRecommendations();
	}

	private function uptimeLessThanOneDay()
	{
		$q = db_query("show global status like 'Uptime'");
		$row = db_fetch_assoc($q);
		return ($row['Value'] < 86400);
	}

	private function formatRecommendations()
	{
		global $lang;
		if (empty($this->recs)) return "";
		$noticeToWaitOneDay = $this->uptimeLessThanOneDay() ? RCView::div(array('class'=>'font-weight-bold mt-2 text-danger'), $lang['system_config_672']) : "";
		$html = "<div class='yellow'>
					<img src='".APP_PATH_IMAGES."exclamation_orange.png'> <b>".strtoupper(db_get_server_type())." {$lang['system_config_669']}</b> 
					<div>{$lang['system_config_670']} <i class=\"far fa-lightbulb\"></i> {$lang['system_config_674']} ".db_get_server_type()." {$lang['system_config_675']}</div>
					$noticeToWaitOneDay
					<div class='mt-2 font-weight-bold'>{$lang['system_config_673']} </div>
					<ol class='my-2'><li>" . implode("</li><li>", $this->recs) . "</li></ol>
					{$lang['system_config_671']}
				 </div>";
		return $html;
	}

	private function applyRules()
	{
		$db_version = db_get_version(true);
		// Query cache (exclude MySQL 5.7.20+, where query cache is deprecated)
		$ableToEnableQueryCache = !(db_get_server_type() == "MySQL" && version_compare($db_version, '5.7.20', '>='));
		if ($ableToEnableQueryCache)
		{
			$queryCacheEnabled = !($this->config['query_cache_size'] == 0 || $this->config['query_cache_type'] == '0' || $this->config['query_cache_type'] == 'OFF');
			if (!$queryCacheEnabled) {
				$this->recs[] = "The query_cache is not turned on. It should be turned on by setting it with new value(s) - recommended initial value: query_cache_size=16777216 and query_cache_type=1.";
			}
            if ($queryCacheEnabled && ($this->config['Qcache_not_cached']/$this->config['Com_select']*100) > 20) {
				$this->recs[] = "Query cache not efficient, consider increasing query_cache_limit (current value={$this->config['query_cache_limit']}).";
			}
			if ($queryCacheEnabled && $this->config['query_cache_limit'] == 1048576) {
				$this->recs[] = "The query_cache_limit is the default of 1 MB. Changing this may increase efficiency (recommended initial value: query_cache_limit=16777216).";
			}
		}
		// Temp tables
		if ($this->config['tmp_table_size'] != $this->config['max_heap_table_size']) {
			$this->recs[] = "tmp_table_size (current value={$this->config['tmp_table_size']}) and max_heap_table_size (current value={$this->config['max_heap_table_size']}) are not the same. They should be set to the same value.";
		}
		if ($this->config['tmp_table_size'] == 0) {
			$this->recs[] = "There is something wrong with the value of tmp_table_size.";
		}
		if ($this->config['max_heap_table_size'] == 0) {
			$this->recs[] = "There is something wrong with the value of max_heap_table_size.";
		}
		if (($this->config['Created_tmp_disk_tables'] / ($this->config['Created_tmp_tables'] + $this->config['Created_tmp_disk_tables']) * 100) > 25) {
			$this->recs[] = "Too many temporary tables are being written to disk. Increase max_heap_table_size and tmp_table_size (note: keep their values the same as each other as you increase them both).";
		}
		// Sorts
		if (($this->config['Sort_scan'] + $this->config['Sort_range']) < 0) {
			$this->recs[] = "Something is wrong with the Sort_scan and/or Sort_range values.";
		}
		if (($this->config['Sort_merge_passes']/($this->config['Sort_scan']+$this->config['Sort_range'])*100) > 10) {
			$this->recs[] = "Too many sorts are causing temporary tables. Consider increasing sort_buffer_size and/or read_rnd_buffer_size.";
		}
		if ($this->config['sort_buffer_size'] == 0) {
			$this->recs[] = "There is something wrong with the value of sort_buffer_size.";
		}
		if ($this->config['read_rnd_buffer_size'] == 0) {
			$this->recs[] = "There is something wrong with the value of read_rnd_buffer_size.";
		}
		// Other caches
		if ($this->config['Threads_created'] < 1) {
			$this->recs[] = "There is something wrong with the value of Threads_created.";
		}
		// if (($this->config['Threads_created'] / $this->config['Connections'] * 100) > 20) {
			// $this->recs[] = "Thread cache is not efficient. Increase the thread_cache_size.";
		// }
		// Connections
		if (($this->config['Max_used_connections'] / $this->config['max_connections'] * 100) > 80) {
			$this->recs[] = "The high water mark for database connections used is getting close to the value of max_connections. Increase max_connections (current value={$this->config['max_connections']}).";
		}
		if ($this->config['Max_used_connections'] < 1) {
			$this->recs[] = "There is something wrong with the value of Max_used_connections.";
		}
		if ($this->config['max_connections'] < 1) {
			$this->recs[] = "There is something wrong with the value of max_connections.";
		}
	}
}