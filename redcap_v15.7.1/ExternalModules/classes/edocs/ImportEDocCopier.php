<?php namespace ExternalModules;

class ImportEDocCopier extends AbstractEDocCopier
{
	private $extractionPath;
	private $newEDocIdsByOld = [];

	function __construct($pid, $prefixes, $extractionPath)
	{
		parent::__construct($pid, $prefixes);
		$this->extractionPath = $extractionPath;
	}

	protected function recreateEDoc($oldEDocId)
	{
		$newEDocId = $this->newEDocIdsByOld[$oldEDocId] ?? null;
		if($newEDocId === null){
			$path = @glob($this->extractionPath . "/edocs/$oldEDocId/*")[0];
			if($path !== null){
				$newEDocId =\Files::uploadFile([
					'name' => basename($path),
					'tmp_name' => $path,
					'size' => filesize($path)
				], $this->getProjectId());
			}
		}

		return [
			'newEdocId' => $newEDocId,
			'name' => basename($path),
		];
	}
}