<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Default response
$response = "";

foreach($_POST['field_names'] as $fieldName){
	if (isset($fieldName) && (($status == 0 && isset($Proj->metadata[$fieldName])) || ($status > 0 && isset($Proj->metadata_temp[$fieldName]))))
	{
		//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
	
		// Check if the table_pk is being deleted. If so, give back different response so as to inform the user of change.
		$sql = "select field_name, element_label from $metadata_table where project_id = $project_id and (
				(element_type = 'calc' and
					(element_enum like '%[{$fieldName}]%' or element_enum like '%[{$fieldName}(%)]%')
				) or
				(branching_logic like '%[{$fieldName}]%') or
				(branching_logic like '%[{$fieldName}(%)]%')
				) order by field_order";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			$response .=  RCView::br() . $lang['design_274'] . " (<b>{$fieldName}</b>) " . $lang['design_275'] . RCView::br();
			
			while ($row = db_fetch_assoc($q))
			{
				$response .= RCView::SP . RCView::SP . "-" . RCView::SP . RCView::b($row['field_name']) . RCView::SP
						   . "-" . RCView::SP . RCView::escape($row['element_label']) . RCView::br();
			}
		}
	}
}

if(!empty($response)){
	$response = RCView::p(array(), $lang['design_273'] . RCView::br() . $response);
}

//Give response back
print $response;