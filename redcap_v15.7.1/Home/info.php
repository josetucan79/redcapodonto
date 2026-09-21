<?php


// Initialize vars as global since this file might get included inside a function
global $homepage_announcement, $homepage_grant_cite, $homepage_custom_text, $sendit_enabled, $edoc_field_option_enabled, $api_enabled, $lang;

?>
<style type="text/css">
    #pagecontainer { max-width: 1100px; }
</style>
<?php

// Show custom homepage announcement text (optional)
if (trim($homepage_announcement) != "" && isset($_SESSION['username']) && !empty($_SESSION['username'])) {
	print RCView::div(array('style'=>'margin-bottom:10px;'), nl2br(decode_filter_tags($homepage_announcement)));
}

print  "<div class='row'>
			<div class='col-12 col-md-6' style='padding-bottom:20px;'>";

// Link to consortium public site (only show on login page and only for redcap.vumc.org)
if (!isset($_SESSION['username']) && SERVER_NAME == 'redcap.vumc.org')
{
	print  "<p class='blue' style='margin-bottom:20px;'>
				For more information about the global REDCap consortium, please visit
				<a target='_blank' style='text-decoration:underline;color:#800000;' href='https://projectredcap.org'>projectredcap.org</a>.
			</p>";
}

// Welcome message and instroduction
print  "<div class='".(isset($_SESSION['username']) ? 'd-block d-sm-none' : 'd-none')."' style='float:right;margin-right:20px;'>
            <button class='jqbuttonmed' onclick=\"window.location.href = '".APP_PATH_WEBROOT_PARENT."index.php?action=myprojects';\"><img src='".APP_PATH_IMAGES."folders_stack.png'> <span style='vertical-align:middle;'>{$lang['setup_45']} {$lang['bottom_03']}</span></button>
        </div>
        <div class='clear'></div>
		<p class='mt-0'>
			{$lang['info_44']}
		</p>
		<p>
			{$lang['info_35']}
		</p>
		<p>
			{$lang['info_36']}
			<i class=\"fas fa-film\"></i> <a href='javascript:;' onclick=\"popupvid('redcap_overview_brief03','Brief Overview of REDCap')\" style='text-decoration:underline;'>{$lang['info_37']}</a>{$lang['period']}
			{$lang['info_38']}
			<a href='".APP_PATH_WEBROOT_PARENT."index.php?action=training' style='text-decoration:underline;'>{$lang['info_06']}</a>
			{$lang['global_14']}{$lang['period']}<br>
		</p>";

// Show grant name to cite (if exists)
if (trim($homepage_grant_cite) != "") {
	print  "<p>
				{$lang['info_08']}
				(<b>".decode_filter_tags($homepage_grant_cite)."</b>){$lang['period']}
			</p>";
}

// Notice about usage for human subject research
?>
<p style='color:#C00000;'>
	<i><?php echo $lang['global_03'].$lang['colon'] ?></i> <?php echo $lang['info_10'] ?>
</p>

<?php

print  "<p>
			{$lang['info_11']}
			<a style='text-decoration:underline;' href='".
			(trim($homepage_contact_url) == '' ? "mailto:$homepage_contact_email" : trim($homepage_contact_url)) .
			"'>".RCView::escape($homepage_contact)."</a>{$lang['period']}
		</p>";

// Show custom text defined by REDCap administrator on System Config page
if (trim($homepage_custom_text) != "") {
	$homepage_custom_text = nl2br(decode_filter_tags($homepage_custom_text));
	print "<div class='round'
		style='background-color:#E8ECF0;border:1px solid #99B5B7;margin:15px 10px 0 0;padding:5px 5px 5px 10px;'>$homepage_custom_text</div>";
}

print  "</div>";
print  "<div class='col-11 col-md-6'>";

// Features of REDCap (right-hand side)
print  '<div class="well fs12 py-1">
				<p>
					<b>'.$lang['info_47'].'</b> - '.$lang['info_48'].'
				</p>
				<p>
					<b>'.$lang['info_15'].'</b> - '.$lang['info_49'].'
				</p>'.
                "				<p>
					<b>{$lang['info_50']}</b> - {$lang['info_28']}, {$lang['info_29']}, {$lang['info_30']}
				</p>";
if (isset($pdf_econsent_system_enabled) && $pdf_econsent_system_enabled) {
	print '		<p>
					<b>' . $lang['info_45'] . '</b> - ' . $lang['info_46'] . '
				</p>';
}
print '			<p>
                <b>'.$lang['info_51'].'</b> - '.$lang['info_52'].'
            </p>';
// Display info about Mobile App, if enabled
if ($api_enabled && isset($mobile_app_enabled) && $mobile_app_enabled) {
    print "	<p>
                <b>{$lang['global_118']}</b> - {$lang['info_43']}
            </p>";
}

// Display info about Mobile App, if enabled
if (isset($mycap_enabled_global) && $mycap_enabled_global) {
    print "	<p>
                <b>{$lang['global_260']}</b> - {$lang['info_66']}
            </p>";
}
// Data Resolution module
print "		<p>
                <b>{$lang['info_53']}</b> - {$lang['info_54']}
            </p>
            <p>
                <b>{$lang['info_55']}</b> - {$lang['info_56']}
            </p>";
print           '<p>
					<b>'.$lang['info_19'].'</b> - '.$lang['info_57'].'
				</p>';

// Display ability to upload files via Send-It, if enabled
if ($sendit_enabled != 0) {
	print "		<p>
					<b>{$lang['info_58']}</b> - {$lang['info_59']}
				</p>";
}
print "		<p>
				<b>{$lang['info_60']}</b> - {$lang['info_61']}
			</p>";
// Display info about API, if enabled
if ($api_enabled || $fhir_ddp_enabled || $fhir_data_mart_create_project) {
	print "<p>
            <b>{$lang['info_62']}</b> - {$lang['info_63']}";
	if ($api_enabled) {
		print $lang['setup_77'];
	}
	if ((isset($fhir_ddp_enabled) && $fhir_ddp_enabled) || (isset($fhir_data_mart_create_project) && $fhir_data_mart_create_project)) {
		if ($api_enabled) print $lang['comma'] . " ";
		print $lang['ws_262'];
	}
	print "{$lang['info_64']}</p>";
}

print "
		</div>";

print "
			</div>
		</div>";
