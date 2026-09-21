<?php namespace ExternalModules;

class ProjectCopyEDocCopier extends AbstractEDocCopier
{
	/**
	 * @return null|string
	 */
	protected function recreateEDoc($edocId)
	{
		$sql = "select * from redcap_edocs_metadata where doc_id = ? and date_deleted_server is null";
		$result = ExternalModules::query($sql, [$edocId]);
		$row = $result->fetch_assoc();
		if(!$row){
			return null;
		}

		$row = ExternalModules::convertIntsToStrings($row);

		$pid = $this->getProjectId();
		$oldPid = $row['project_id'];
		if($oldPid === $pid){
			// This edoc is already associated with this project.  No need to recreate it.
			$newEdocId = $edocId;
		}
		else{
			$newEdocId = copyFile($edocId, $pid);
		}

		return [
			'newEdocId' => (string)$newEdocId, // We must cast to a string to avoid an issue on the js side when it comes to handling file fields if stored as integers.
			'name' => $row['doc_name'],
		];
	}
}