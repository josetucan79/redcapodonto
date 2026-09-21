<?php


include 'header.php';
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);
if (!ACCESS_SYSTEM_CONFIG) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); });</script>";

$changesSaved = false;

// If project default values were changed, update redcap_config table with new values
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ACCESS_SYSTEM_CONFIG)
{
	$changes_log = array();
	$sql_all = array();
	foreach ($_POST as $this_field=>$this_value) {
	    // Rich text editors: Remove line breaks in the HTML to support legacy non-rich-text-editor text
		if (in_array($this_field, array('homepage_announcement', 'homepage_custom_text'))) {
		    $this_value = str_replace(array("\r", "\n"), array("", ""), $this_value);
		}
		// Save this individual field value
		$sql = "UPDATE redcap_config SET value = '".db_escape($this_value)."' WHERE field_name = '".db_escape($this_field)."'";
		$q = db_query($sql);

		// Log changes (if change was made)
		if ($q && db_affected_rows() > 0) {
			if ($this_value != "" && in_array($this_field, System::$encryptedConfigSettings)) {
                $this_value = '[REDACTED]';
                $sql = "UPDATE redcap_config SET value = '".db_escape($this_value)."' WHERE field_name = '".db_escape($this_field)."'";
            }
            $sql_all[] = $sql;
			$changes_log[] = "$this_field = '$this_value'";
		}
	}

	// Log any changes in log_event table
	if (count($changes_log) > 0) {
		Logging::logEvent(implode(";\n",$sql_all),"redcap_config","MANAGE","",implode(",\n",$changes_log),"Modify system configuration");
	}

	$changesSaved = true;
}

// Retrieve data to pre-fill in form
$element_data = System::getConfigVals();

if ($changesSaved)
{
	// Show user message that values were changed
	print  "<div class='yellow' style='margin-bottom: 20px; text-align:center'>
			<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
			{$lang['control_center_19']}
			</div>";
}
?>

<h4 style="margin-top: 0;"><i class="fas fa-home"></i> <?=RCView::tt("control_center_4397") // Home Page Settings ?></h4>

<form action='homepage_settings.php' enctype='multipart/form-data' target='_self' method='post' name='form' id='form'>
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".System::getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0;">

<tr  id="homepage_contact-tr" sq_id="homepage_contact">
	<td class="cc_label"><?php echo $lang['system_config_77'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='homepage_contact' value='<?php echo htmlspecialchars($element_data['homepage_contact'], ENT_QUOTES) ?>'  />
	</td>
</tr>
<tr  id="homepage_contact_email-tr" sq_id="homepage_contact_email">
	<td class="cc_label"><?php echo $lang['system_config_78'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='homepage_contact_email' value='<?php echo htmlspecialchars($element_data['homepage_contact_email'], ENT_QUOTES) ?>'
			onblur="redcap_validate(this,'0','','hard','email');"  />
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_529'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_530'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='homepage_contact_url' value='<?php echo htmlspecialchars($element_data['homepage_contact_url'], ENT_QUOTES) ?>'  />
	</td>
</tr>
<tr  id="homepage_grant_cite-tr" sq_id="homepage_grant_cite">
    <td class="cc_label"><?php echo $lang['system_config_79'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field '  type='text' name='homepage_grant_cite' value='<?php echo htmlspecialchars($element_data['homepage_grant_cite'], ENT_QUOTES) ?>'  /><br/>
        <div class="cc_info">
            <?php echo $lang['system_config_80'] ?>
        </div>
    </td>
</tr>

<!-- Announcement text to display at top of home page -->
<tr>
	<td class="cc_label pt-5" colspan="2">
		<?php echo $lang['system_config_275'] ?>
		<div class="cc_info mb-3">
			<?php echo $lang['system_config_278'] ?>
		</div>
		<textarea id='homepage_announcement' class='x-form-field notesbox mceEditor' name='homepage_announcement' style='height:250px;'><?php echo $element_data['homepage_announcement'] ?></textarea>
		<div class="mt-2">
            <b style="font-size:12px;"><?php echo $lang['system_config_606'] ?></b>
            <select class="x-form-text x-form-field" style="margin-left:5px;font-size:12px;" name="homepage_announcement_login">
                <option value='0' <?php echo ($element_data['homepage_announcement_login'] == 0) ? "selected" : "" ?>><?php echo $lang['design_99'] ?></option>
                <option value='1' <?php echo ($element_data['homepage_announcement_login'] == 1) ? "selected" : "" ?>><?php echo $lang['design_100'] ?></option>
            </select>
		</div>
	</td>
</tr>

<!-- Announcement text to display at bottom of home page -->
<tr  id="homepage_custom_text-tr" sq_id="homepage_custom_text">
    <td class="cc_label py-5" colspan="2">
		<?php echo $lang['system_config_276'] ?>
		<div class="cc_info mb-3">
			<?php echo $lang['system_config_277'] ?>
		</div>
		<textarea id='homepage_custom_text' class='x-form-field notesbox mceEditor' name='homepage_custom_text' style='height:250px;'><?php echo $element_data['homepage_custom_text'] ?></textarea>
	</td>
</tr>
</table><br/>
<div style="text-align: center;"><input type='submit' name='' value='<?=js_escape($lang['control_center_4876'])?>' /></div><br/>
</form>

<?php include 'footer.php'; ?>