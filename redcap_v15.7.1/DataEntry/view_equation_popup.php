<?php


include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


if (preg_match("/[^a-z_0-9]/", $_GET['field'])) exit("{$lang['global_09']}!");

$draft_preview_enabled = Design::isDraftPreview();

// Decide which metadata table to query
$metadata_table = ($status > 0 && isset($_GET['metadata_table']) && $_GET['metadata_table'] == "metadata_temp") ? "redcap_metadata_temp" : $Proj->getMetadataTable();

// Is a @CALCDATE field?
$isCalcDate = (isset($_GET['calcdate']) && $_GET['calcdate'] == '1');
// Is a @CALCTEXT field?
$isCalcText = (isset($_GET['calctext']) && $_GET['calctext'] == '1');
// Is a randomization target field (with trigger logic)?
$rid = (isset($_GET['randtarget']) && $_GET['randtarget'] > 0) ? (intval($_GET['randtarget'])) : false;

//Get details for this field
if ($isCalcText) {
	$sql = "select element_label, element_enum, misc from $metadata_table 
			where project_id = $project_id and field_name = '".$_GET['field']."' and element_type = 'text' and misc like '%@CALCTEXT%'";
} elseif ($isCalcDate) {
	$sql = "select element_label, element_enum, misc from $metadata_table 
			where project_id = $project_id and field_name = '".$_GET['field']."' and element_type = 'text' and misc like '%@CALCDATE%'";
} elseif ($rid > 0) {
    $sql = "select element_label, trigger_option, trigger_instrument, trigger_event_id, trigger_logic as element_enum from $metadata_table m
            inner join redcap_randomization r on m.project_id=r.project_id and m.field_name=r.target_field
            where m.project_id = $project_id and field_name = '".$_GET['field']."' and rid = $rid";
} else {
	$sql = "select element_label, element_enum, misc from $metadata_table 
			where project_id = $project_id and field_name = '".$_GET['field']."' and element_type = 'calc' and (element_enum != '' and element_enum is not null)";
}
$q = db_query($sql);
$row = db_fetch_array($q);

// Set equation manually for @CALCDATE fields
if ($isCalcDate) {
	$row['element_enum'] = "@CALCDATE(".Form::getValueInParenthesesActionTag($row['misc'], "@CALCDATE").")";
}
// Set equation manually for @CALCTEXT fields
elseif ($isCalcText) {
	$row['element_enum'] = "@CALCTEXT(".LogicParser::removeCommentsAndSanitize(Form::getValueInParenthesesActionTag($row['misc'], "@CALCTEXT")).")";
}
// For regular equation, remove comments for display
else {
	$row['element_enum'] = LogicParser::removeCommentsAndSanitize($row['element_enum']);
}

//Now get form name of all fields involved in this field's equation
$fields_involved = array_keys(getBracketedFields($row['element_enum'], true, true, true));

$q2 = db_query("select field_name, form_name, element_label from $metadata_table where project_id = $project_id and
				   field_name in ('".implode("','",$fields_involved)."') order by field_order");

//Query metadata table to get form names for the fields involved
$involved_table =  "<table class='form_border' width='100%'>
						<tr>
							<td class='label_header' colspan=3 style='padding:7px;border: 1px solid #aaa; background-color: #ddd; font-size: 12px;'>
								{$lang['view_equation_02']}
							</td>
						</tr>
						<tr>
							<td class='label_header' style='border: 1px solid #aaa;padding: 5px;'>{$lang['global_44']}</td>
							<td class='label_header' style='border: 1px solid #aaa;padding: 5px;'>{$lang['global_40']}</td>
							<td class='label_header' style='border: 1px solid #aaa;padding: 5px;'>{$lang['global_12']}</td>
						</tr>";
while ($involved = db_fetch_array($q2))
{
	//Get form_menu_description
	$q3 = db_query("select form_menu_description from $metadata_table where project_id = $project_id and
					   form_name = '".$involved['form_name']."' and form_menu_description is not null");
	$menu_desc = db_result($q3, 0);
	$involved_table .= "<tr>
							<td class='data' style='border-top: 1px solid #ccc;padding: 5px;'><i>" . $involved['field_name'] . "</i></td>
							<td class='data' style='border-top: 1px solid #ccc;padding: 5px;'>" . $involved['element_label'] . "</td>
							<td class='data' style='border-top: 1px solid #ccc;padding: 5px;'>$menu_desc</td>
						</tr>";
}
$involved_table .= "</table>";

if (db_num_rows($q) > 0)
{
	print  "<div style='padding:10px 0 20px;'>
				<table border=0 cellpadding=0 cellspacing=8>";
    if ($rid > 0) {
        switch ($row['trigger_option']) {
            case '1': $triggerOption = RCView::tt('random_203'); break;
            case '2': $triggerOption = RCView::tt('random_204'); break;
            default: $triggerOption = ''; break;
        }
        $triggerForm = RCView::span(array(), $Proj->forms[$row['trigger_instrument']]['menu']);
        $triggerEvent = ($Proj->longitudinal && array_key_exists($row['trigger_event_id'], $Proj->eventInfo)) ? '('.$Proj->eventInfo[$row['trigger_event_id']]['name_ext'].')' : '';
        print	"	<tr>
                        <td class='nowrap' style='font-weight:bold;padding-right:10px;vertical-align:top;padding-bottom:10px;'>
                            ".RCView::tt('random_201').":
                        </td>
                        <td style='vertical-align:top;'>
                            <i>$triggerOption</i>
                        </td>
                    </tr>";
        print	"	<tr>
                        <td class='nowrap' style='font-weight:bold;padding-right:10px;vertical-align:top;padding-bottom:10px;'>
                            ".RCView::tt('global_89').":
                        </td>
                        <td style='vertical-align:top;'>
                            <i>$triggerForm $triggerEvent</i>
                        </td>
                    </tr>";
    }
	print	"		<tr>
						<td class='nowrap' style='font-weight:bold;padding-right:10px;vertical-align:top;padding-bottom:10px;'>
							{$lang['global_44']}:
						</td>
						<td style='vertical-align:top;'>
							<i>".$_GET['field']."</i>
						</td>
					</tr>
					<tr>
						<td class='nowrap' style='font-weight:bold;padding-right:10px;vertical-align:top;padding-bottom:10px;'>
							{$lang['global_40']}:
						</td>
						<td style='vertical-align:top;'>
							".$row['element_label']."
						</td>
					</tr>
					<tr>
						<td class='nowrap' style='color:#800000;font-weight:bold;padding-right:10px;vertical-align:top;'>
							{$lang['view_equation_06']}:
						</td>
						<td style='vertical-align:top;'>
							<code class='fs13'>".htmlentities($row['element_enum'])."</code>
						</td>
					</tr>
				</table>

				<div style='padding:20px;'>
					$involved_table
				</div>
			</div>";
}
else
{
	print "<b>{$lang['global_01']}!</b>";
}
