<?php
namespace ExternalModules;

class Records
{
    	private $module;
	
	function __construct($module){
		$this->module = $module;
	}

	/**
	 * @return void
	 */
	function lock($recordIds){
		if(empty($recordIds)){
			// do nothing
			return;
		}

		$pid = $this->module->getProjectId();

		$query = ExternalModules::createQuery();
		$query->add("
			select
				record,
				event_id,
				instance,
				form_name
			from ".\Records::getDataTable($pid)." d
			join redcap_metadata m
				on
					d.project_id = m.project_id 
					and d.field_name = m.field_name
			where
				d.project_id = ?
				and
		", $pid);

		$query->addInClause('record', $recordIds);

		$query->add("group by record, event_id, instance, form_name");

		$results = $query->execute();

		$query = ExternalModules::createQuery();
		$query->add("insert ignore into redcap_locking_data (project_id, record, event_id, form_name, instance, timestamp) values");

		$addComma = false;
		while($row = $results->fetch_assoc()){
			if($addComma){
				$query->add(',');
			}
			else{
				$addComma = true;
			}

			$record = $row['record'];
			$eventId = $row['event_id'];
			$formName = $row['form_name'];
			$instance = $row['instance'];

			if($instance === null){
				$instance = 1;
			}

			$query->add("(?, ?, ?, ?, ? , now())", [$pid, $record, $eventId, $formName, $instance]);
		}

		$query->execute();
	}

	/**
	 * @return void
	 */
	function unlock($recordIds){
		$pid = $this->module->getProjectId();

		$query = ExternalModules::createQuery();
		$query->add("
			delete from redcap_locking_data
			where project_id = ?
			and
		", [$pid]);

		$query->addInClause('record', $recordIds);

		$query->execute();
	}

	/**
	 * @return bool
	 */
	function isLocked($recordId){
		$pid = $this->module->getProjectId();
		
		$result = $this->module->query("
			select 1
			from redcap_locking_data
			where 
				project_id = ?
				and record = ?
		", [$pid, $recordId]);

		return $result->fetch_assoc() !== null;
	}
}
