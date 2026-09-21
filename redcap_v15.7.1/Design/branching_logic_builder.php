<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Use correct metadata table depending on status
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

// Validate field as a multiple choice field and get value of branching logic and label
if (!isset($_POST['field_name'])) exit('0');
if ($_POST["field_name"] == "--QUICK-EDIT--") {
	$stored_branching_logic = $_POST["action"] == "get-logic" ? trim(html_entity_decode($_POST['quick_logic'], ENT_QUOTES)) : "";
	$field_label = "Quick Edit Logic";
}
else {
	$sql = "select branching_logic, element_label from $metadata_table where project_id = $project_id
			and field_name = '" . db_escape($_POST['field_name']) . "' limit 1";
	$q = db_query($sql);
	$field_exists = (db_num_rows($q) > 0);
	if (!$field_exists) {
		exit('0');
	} else {
		$stored_branching_logic = trim(html_entity_decode(db_result($q, 0, "branching_logic")."", ENT_QUOTES));
		$field_label = strip_tags(label_decode(db_result($q, 0, "element_label").""));
	}
}

if ($_POST['action'] == 'get-logic' && isset($_POST['field_name']))
{
	$any_field_with_same_logic = false;
	$row_num = 0;
	$branching_status = "0";
	if ($_POST['field_name'] !== "--QUICK-EDIT--") {
		$form_name = ($status > 0) ? $Proj->metadata_temp[$_POST['field_name']]['form_name'] : $Proj->metadata[$_POST['field_name']]['form_name'];
		$sql = "select branching_logic,field_name from $metadata_table where project_id = $project_id and branching_logic ='" . db_escape($stored_branching_logic) . "' and form_name ='" . db_escape($form_name) . "'";
		$q = db_query($sql);
		$row_num = db_num_rows($q);
		if($row_num > 1){
			$any_field_with_same_logic = true;
		}
	
		if(isset($_SESSION[USERID][$_GET['pid']]["branching_status"]) && $_SESSION[USERID][$_GET['pid']]["branching_status"] != ""){
			$branching_status = $_SESSION[USERID][$_GET['pid']]['branching_status'];
		} else {
			$_SESSION[USERID][$_GET['pid']]['branching_status'] = "0";
			$branching_status = "0";
		}
	}
    header("Content-Type: application/json");
    print json_encode_rc(array('logic'=>$stored_branching_logic, 'label'=>$field_label, 'same_logic_field'=>$any_field_with_same_logic, 'num_same_logic_field'=>$row_num, 'branching_status'=>$branching_status));
}

elseif ($_POST['action'] == 'logic-alert-status')
{
    $_SESSION[USERID][$_GET['pid']]['branching_status'] = "1";
    print "1";
}

## Display logic builder popup
elseif ($_POST['action'] == 'view' && isset($_POST['form_name']))
{
	// Loop through all fields and collect info to place in Logic Build pane
	$fields = array();
	$fields_raw = array();
	$fields_form = array();
	$counter = 1;
	$sql = "select * from $metadata_table where project_id = $project_id and field_name != '".db_escape($_POST['field_name'])."'
			order by field_order";
	$q = db_query($sql);
	while ($attr = db_fetch_assoc($q))
	{
		$field_name = $attr['field_name'];
		// Treat different field types differently
		switch ($attr['element_type']) {
			case "checkbox":
			case "select":
			case "radio":
				foreach (parseEnum($attr['element_enum']) as $code=>$label)
				{
					// Remove all html and other bad characters
					$label = strip_tags(label_decode($label));
					$varAndLabel = "$field_name = $label";
					if (mb_strlen($varAndLabel) > 70) {
						$varAndLabel = mb_substr($varAndLabel, 0, 68) . "...";
					}
					if ($attr['element_type'] == "checkbox") {
						$fields_raw[$counter] = "[$field_name($code)] = '1'";
					} else {
						$fields_raw[$counter] = "[$field_name] = '$code'";
					}
					$fields_form[$counter] = $attr['form_name'];
					$fields[$counter++] = "$varAndLabel ($code)";
				}
				break;
			case "truefalse":
				$fields_raw[$counter] = "[$field_name] = '1'";
				$fields_form[$counter] = $attr['form_name'];
				$fields[$counter++] = $field_name.' = '.$lang['design_186'].' (1)';
				$fields_raw[$counter] = "[$field_name] = '0'";
				$fields_form[$counter] = $attr['form_name'];
				$fields[$counter++] = $field_name.' = '.$lang['design_187'].' (0)';
				break;
			case "yesno":
				$fields_raw[$counter] = "[$field_name] = '1'";
				$fields_form[$counter] = $attr['form_name'];
				$fields[$counter++] = $field_name.' = '.$lang['design_100'].' (1)';
				$fields_raw[$counter] = "[$field_name] = '0'";
				$fields_form[$counter] = $attr['form_name'];
				$fields[$counter++] = $field_name.' = '.$lang['design_99'].' (0)';
				break;
			case "text":
			case "textarea":
			case "slider":
			case "calc":
				$fields_raw[$counter] = "[$field_name] = ".js_escape($lang['design_411']);
				$fields_form[$counter] = $attr['form_name'];
				$fields[$counter++] = $field_name." = ".js_escape($lang['design_411']);
				break;
		}
	}

	// If project has more than one form, then display drop-down with form list, which will show/hide fields from that form
	$instrumentDropdown = "";
	if (count($Proj->forms) > 1)
	{
		// Render drop-down
		$instrumentDropdown .= "<div style='overflow:hidden;'>
									<b>{$lang['design_229']}</b><br>
									<select id='brFormSelect' onchange=\"displayBranchingFormFields(this);\">";
		foreach ($Proj->forms as $form_name=>$attr)
		{
			// Decide which form to pre-select
			$isSelected = ($_POST['form_name'] == $form_name) ? "selected" : "";
			// Render option
			$instrumentDropdown .= "<option value='$form_name' $isSelected>".strip_tags(label_decode($attr['menu']))."</option>";
		}
		$instrumentDropdown .= "	</select>
								</div>";
	}

	?>
	<!-- Drop-down for choosing instruments, if applicable -->
	<?php echo $instrumentDropdown ?>
	<table cellspacing="0">
		<tr>
			<td valign="bottom" style="width:290px;padding:20px 2px 2px;">
				<!-- Div containing options to drag over -->
				<b><?php echo $lang['design_234'] ?></b><br>
				<?php echo $lang['design_235'] ?><br>
				<div class="listBox" id="nameList" style="height:150px;overflow:auto;cursor:move;">
					<ul id="ulnameList">
					<?php foreach ($fields as $count=>$this_field) { ?>
						<li <?php if ($_POST['form_name'] != $fields_form[$count]) echo 'style="display:none;"'; ?> class="dragrow brDrag br-frm-<?php echo $fields_form[$count] ?>" val="<?php echo $fields_raw[$count] ?>"><?php echo $this_field ?></li>
					<?php } ?>
					</ul>
				</div>
				<div style="font-size:11px;">&nbsp;</div>
			</td>
			<td valign="middle" style="text-align:center;font-weight:bold;font-size:11px;color:green;padding:0px 20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>arrow_right.png"><br><br>
				<?php echo $lang['design_236'] ?><br>
				<?php echo $lang['global_43'] ?><br>
				<?php echo $lang['design_237'] ?><br><br>
				<img src="<?php echo APP_PATH_IMAGES ?>arrow_right.png">
			</td>
			<td valign="bottom" style="width:290px;padding:0px 2px 2px;">
				<!-- Div where options will be dragged to -->
				<b><?php echo $lang['design_227'] ?></b><br>
				<input type="radio" name="brOper" id="brOperAnd" value="and" onclick="updateAdvBranchingBox();" checked> <?php echo $lang['design_238'] ?><br>
				<input type="radio" name="brOper" id="brOperOr" value="or" onclick="updateAdvBranchingBox();"> <?php echo $lang['design_239'] ?><br>
				<div class="listBox" id="dropZone1" style="height:150px;overflow:auto;">
					<ul id="mylist" style="list-style:none;">
					</ul>
				</div>
				<div style="text-align:right;">
					<a id="linkClearDrag" style="font-family:tahoma;font-size:11px;text-decoration:underline;" href="javascript:;" onclick="
						$('#dropZone1').html('');
						updateAdvBranchingBox();
					"><?php echo $lang['design_232'] ?></a>
				</div>
			</td>
		</tr>
	</table>
	<?php
}



## Save branching logic to field
elseif ($_POST['action'] == 'save' && isset($_POST['branching_logic']))
{
	// Demangle post
	$new_branching_logic = trim(html_entity_decode($_POST['branching_logic'], ENT_QUOTES));
	$field_name = $_POST['field_name'];
	$same_logic_fields = $_POST['any_same_logic_fields'] == "true";
	$simulate = $field_name == "--QUICK-EDIT--";

	try {
		$response = BranchingLogic::save($field_name, $new_branching_logic, $same_logic_fields, $project_id, $simulate);
	}
	catch (Exception $e) {
		$response = "ERROR";
	}
	print $response;
	exit;
}

// ERROR
else
{
	exit('0');
}
