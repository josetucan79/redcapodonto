<?php


// If downloading zip of non-versioned files, then zip them up for download
if (isset($_GET['download']) && $_GET['download'] == 'nonversioned_files')
{
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
	ControlCenter::exportNonVersionedFiles();
	exit;
}

// Begin displaying page
if (isset($_GET['upgradeinstall'])) {
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
	// Check for any extra whitespace from config files that would mess up lots of things
	$prehtml = ob_get_contents();
	// Header
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->addStylesheet("home.css", 'screen,print');
	$objHtmlPage->PrintHeaderExt();
} else {
	// Header
	include 'header.php';
}
if (!ACCESS_CONTROL_CENTER && !System::isCI()) redirect(APP_PATH_WEBROOT);
if (!ACCESS_SYSTEM_CONFIG && !ACCESS_SYSTEM_UPGRADE) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); });</script>";

?>
<script type="text/javascript">
var allow_outbound_http = '<?=$allow_outbound_http?>';
$(function(){
	// Consortium server check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_003']) ?>";
	var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2($lang['check_004']) ?>";
	checkExternalService('<?=CONSORTIUM_WEBSITE . 'ping.php'?>', 'get', 'server_ping_response_div', msgSuccess, msgError);
    // REDCap survey service check
    var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_005']) ?> (<?=APP_PATH_SURVEY_FULL?>).";
    var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2(RCView::tt_i('check_193',[APP_PATH_SURVEY_FULL]))?>";
    checkExternalService('<?=APP_PATH_SURVEY_FULL?>', 'get', 'redcap_survey_service_check', msgSuccess, msgError);
	// Twilio service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_007']) ?>";
	var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2($lang['check_008']) ?>";
	checkExternalService('https://api.twilio.com', 'post', 'twilio_service_check', msgSuccess, msgError);
	// Mosio service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_009']) ?> (<?=Mosio::API_BASE.Mosio::API_PING_ENDPOINT?>).";
	var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2($lang['check_010']) ?> (<?=Mosio::API_BASE.Mosio::API_PING_ENDPOINT?>).";
	checkExternalService('<?=Mosio::API_BASE.Mosio::API_PING_ENDPOINT?>', 'post', 'mosio_service_check', msgSuccess, msgError);
	// SendGrid service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_011']) ?>";
	var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2($lang['check_012']) ?>";
	checkExternalService('https://api.sendgrid.com/v3', 'post', 'sendgrid_service_check', msgSuccess, msgError);
	// PROMIS service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_013']) ?> (<?php print $promis_api_base_url ?>).";
	var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2($lang['check_014']) ?> (<?php print $promis_api_base_url ?>).";
	checkExternalService('<?php print $promis_api_base_url ?>', 'get', 'promis_service_check', msgSuccess, msgError);
	// BioPortal service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_015']) ?> (<?php print $bioportal_api_url ?>).";
	var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2($lang['check_016']) ?> (<?php print $bioportal_api_url ?>).";
	checkExternalService('<?php print $bioportal_api_url ?>', 'get', 'bioportal_service_check', msgSuccess, msgError);
	// Bit.ly service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_017']) ?>";
	var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2($lang['check_018']) ?>";
	checkExternalService('http://api.bit.ly/v3/shorten', 'get', 'bitly_service_check', msgSuccess, msgError);
	// IS.GD service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_019']) ?>";
	var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2($lang['check_020']) ?>";
	checkExternalService('https://is.gd', 'get', 'isgd_service_check', msgSuccess, msgError);
    // REDCAP.LINK service check
    var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_021']) ?>";
    var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2($lang['check_022']) ?> ";
    checkExternalService('https://redcap.link', 'get', 'redcaplink_service_check', msgSuccess, msgError);
    // Google Captcha service check
    var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_023']) ?>";
    var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2($lang['check_024']) ?>";
    checkExternalService('https://www.google.com/recaptcha/api/siteverify', 'get', 'googlerecaptcha_service_check', msgSuccess, msgError);
    // Field Bank NLM service check
    var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b><?=js_escape2($lang['check_001']) ?></b> - <?=js_escape2($lang['check_025']) ?>";
    var msgError = "<b><?=js_escape2($lang['check_002']) ?></b> - <?=js_escape2($lang['check_026']) ?>";
    checkExternalService('https://cde.nlm.nih.gov', 'get', 'nlm_service_check', msgSuccess, msgError);
});

function checkExternalService(serviceUrl, method, divId, msgSuccess, msgError) {
	// Ajax request
	var resp = msgError;
	var pass = false;
	if (method != 'post') method = 'get';
	if (allow_outbound_http != '1') {
        $('#'+divId).html(resp);
        configCheckSetBoxColor($('#'+divId).parent(), pass);
	    return;
    }
	var thisAjax = $.post(app_path_webroot+'ControlCenter/check_server_ping.php', { url: serviceUrl, type: method }, function(data) {
		if (data == '1') {
			pass = true;
			resp = msgSuccess;
		}
		$('#'+divId).html(resp);
		configCheckSetBoxColor($('#'+divId).parent(), pass);
	});
	// Check after 10s to see if communicated with server, in case it loads slowly. If not after 10s, then assume cannot be done.
	var resptimer = resp;
	var maxAjaxTime = 10; // seconds
	setTimeout(function(){
		if (thisAjax.readyState == 1) {
			thisAjax.abort();
			$('#'+divId).html(resptimer);
			configCheckSetBoxColor($('#'+divId).parent(), false);
		}
	},maxAjaxTime*1000);
}

function configCheckSetBoxColor(ob, pass) {
	ob.removeClass('gray');
	if (pass) {
		ob.addClass('darkgreen').css('color','green');
	} else {
		ob.addClass('red');
	}
}

function whyComponentMissing() {
	var msg = "<?=js_escape2($lang['check_027']) ?>";
	alert(msg);
}
</script>
<?php

############################################################################################

//PAGE HEADER
print RCView::h4(array('style'=>'margin-top:0;'), '<i class="fas fa-clipboard-check"></i> ' . $lang['control_center_443'] . " <span class=\"text-secondary ms-2\">(REDCap $redcap_version)</span>");
print  "<p>".$lang['check_028']."</p>";


## Basic tests
print "<p style='padding-top:10px;color:#800000;font-weight:bold;font-family:verdana;font-size:13px;'>".$lang['check_029']."</p>";


if (!System::isCI()) {
    $testInitMsg = "<b>".$lang['check_030']."</b>
				<br>".$lang['check_031']." \"" . dirname(APP_PATH_DOCROOT) . "\").";
    $missing_files = 0;
    if (substr(basename(APP_PATH_DOCROOT), 0, 8) != "redcap_v" && basename(APP_PATH_DOCROOT) != "codebase") {
        exit (RCView::div(array('class' => 'red'), "$testInitMsg<br> &bull; redcap_v?.?.? - <b>".$lang['check_032']."<p>".$lang['check_033']." - ".$lang['check_034']."</b>"));
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/temp")) {
        $testInitMsg .= "<br> &bull; temp - <b>".$lang['check_032']."</b>";
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/edocs")) {
        $testInitMsg .= "<br> &bull; edocs - <b>".$lang['check_032']."</b>";
        $missing_files = 1;
    }
    if (!is_file(dirname(APP_PATH_DOCROOT) . "/database.php")) {
        $testInitMsg .= "<br> &bull; database.php - <b>".$lang['check_032']."</b>";
        $missing_files = 1;
    }
    if (is_dir(dirname(APP_PATH_DOCROOT) . "/webtools2")) {
        // See if the webdav folder is in correct location
        if (!is_dir(dirname(APP_PATH_DOCROOT) . "/webtools2/webdav")) {
            $testInitMsg .= "<p><b>".$lang['check_033']." - ".$lang['check_035']."</b><br>".$lang['check_036'];
            $missing_files = 1;
        }
        // LDAP folder
        if (!is_file(dirname(APP_PATH_DOCROOT) . "/webtools2/ldap/ldap_config.php")) {
            $testInitMsg .= "<br> &bull; webtools2/ldap/ldap_config.php - <b>".$lang['check_032']."</b>";
            $missing_files = 1;
        }

    } else {
        $testInitMsg .= "<br> &bull; webtools2 - <b>".$lang['check_032']." &nbsp; <font color=#800000>".$lang['check_037']." \"" . dirname(APP_PATH_DOCROOT) . "\".</font></b>";
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/languages")) {
        $testInitMsg .= "<br> &bull; languages - <b>".$lang['check_032']."</b> - ".$lang['check_038']." 3.2.0.
			See the <a href='https://redcap.vumc.org/community/custom/download.php' style='text-decoration:underline;' target='_blank'>".$lang['check_039']."</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>".$lang['check_043']."</a>)";
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/api")) {
        $testInitMsg .= "<br> &bull; api - <b>".$lang['check_032']."</b> - ".$lang['check_038']." 3.3.0.
			See the <a href='https://redcap.vumc.org/community/custom/download.php' style='text-decoration:underline;' target='_blank'>".$lang['check_039']."</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>".$lang['check_043']."</a>)";
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/api/help")) {
        $testInitMsg .= "<br> &bull; api/help - <b>".$lang['check_032']."</b> - ".$lang['check_038']." 3.3.0.
			See the <a href='https://redcap.vumc.org/community/custom/download.php' style='text-decoration:underline;' target='_blank'>".$lang['check_039']."</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>".$lang['check_043']."</a>)";
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/surveys")) {
        $testInitMsg .= "<br> &bull; surveys - <b>".$lang['check_032']."</b> - ".$lang['check_038']." 4.0.0.
			See the <a href='https://redcap.vumc.org/community/custom/download.php' style='text-decoration:underline;' target='_blank'>".$lang['check_039']."</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>".$lang['check_043']."</a>)";
        $missing_files = 1;
    }

    if ($missing_files == 1) {
        exit(RCView::div(array('class' => 'red'), "$testInitMsg<br><br><b><font color=red>".$lang['check_033']."</font> - ".$lang['check_040']." \"" . dirname(APP_PATH_DOCROOT) . "\". ".$lang['check_041']));
    } else {
        $testInitMsg .= "<br><br><img src='" . APP_PATH_IMAGES . "tick.png'> <b>".$lang['check_001']."</b> - ".$lang['check_042']."";
        print RCView::div(array('class' => 'darkgreen', 'style' => 'color:green;'), $testInitMsg);
    }
}




$testMsg3 = "<b>TEST 2: Connect to the table named \"redcap_config\"</b><br><br>";
$QQuery = db_query("SHOW TABLES FROM `$db` LIKE 'redcap_config'");
if (db_num_rows($QQuery) == 1) {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), "$testMsg3 <img src='".APP_PATH_IMAGES."tick.png'> <b>".$lang['check_001']."</b> - ".$lang['check_044']." <b>".$db."</b>
			was accessed successfully.");
} else {
	exit (RCView::div(array('class'=>'red'), "$testMsg3<b>".$lang['check_033']." - ".$lang['check_045']."</b>
	<br>".$lang['check_046']." <b>".$db."</b>.".$lang['check_047']));
}



## Check if REDCap database structure is correct
$testMsg = "<b>".$lang['check_048']."</b><br><br>";
$tableCheck = new SQLTableCheck();
// Use the SQL from install.sql compared with current table structure to create SQL to fix the tables
$sql_fixes = $tableCheck->build_table_fixes();
if ($sql_fixes != '') {
	// NORMAL INSTALL: TABLES ARE MISSING OR PIECES OF TABLES ARE MISSING
	// If we are able to auto-fix this, then provide button to do so
	$autoFixDbTablesBtn = "";
	if (Upgrade::hasDbStructurePrivileges()) {
		$autoFixDbTablesBtn = RCView::div(array('style'=>'margin:15px 0 3px;'),
								RCView::button(array('class'=>'btn btn-danger btn-sm', 'style'=>'margin-right:5px;', 'onclick'=>'autoFixTables();'),
									$lang['control_center_4680']
								) .
								$lang['control_center_4681']
							) .
							RCView::div(array('style'=>'margin:10px 0 6px;font-weight:bold;'),
								"&ndash; " . $lang['global_46'] . " &ndash;"
							);
	}
	// If there are fixes to be made, then display text box with SQL fixes
	print 	RCView::div(array('class'=>'red', 'style'=>'margin-bottom:15px;'),
				RCView::img(array('src'=>'exclamation.png')) .
				"$testMsg<b>{$lang['control_center_4431']}</b><br>
				{$lang['control_center_4682']} $autoFixDbTablesBtn {$lang['control_center_4683']}
				<div class='text-dangerrc mt-2'><b><i class='fa-solid fa-circle-info'></i> {$lang['control_center_4913']}</b></div>" .
				RCView::div(array('id'=>'sql_fix_div', 'style'=>'margin:10px 0 3px;'),
					RCView::textarea(array('class'=>'x-form-field notesbox', 'style'=>'height:60px;font-size:11px;width:97%;height:100px;', 'readonly'=>'readonly', 'onclick'=>'this.select();'),
						"-- SQL TO REPAIR REDCAP TABLES\nUSE `$db`;\nSET SESSION SQL_SAFE_UPDATES = 0;\nSET FOREIGN_KEY_CHECKS = 0;\n$sql_fixes\nSET FOREIGN_KEY_CHECKS = 1;"
					)
				)
			);
} else {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), "$testMsg <img src='".APP_PATH_IMAGES."tick.png'>
			<b>".$lang['check_001']."</b> - ".$lang['check_049']."");
}



## Check if cURL is installed
$testMsg = "<b>".$lang['check_050']."</b><br><br>";
// cURL is installed
if (function_exists('curl_init'))
{
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), $testMsg." <img src='".APP_PATH_IMAGES."tick.png'> <b>".$lang['check_001']."</b> - ".$lang['check_051']."<br>");
}
// cURL not installed
else
{
	?>
    <div class="red">
		<?php echo $testMsg ?>
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
		<b><?php echo $lang['check_052'] ?></b> <?php echo $lang['check_053'] ?>
		<a href='<?php echo $lang['check_054'] ?>' target='_blank' style='text-decoration:underline;'><?php echo $lang['check_055'] ?></a>.
	</div>
	<?php
}



## Check if can communicate with REDCap Consortium server (for reporting stats)
$testMsg = "<b>".$lang['check_056']."</b> (".CONSORTIUM_WEBSITE.")<br>".$lang['check_057']."<br><br>";
// Send request to consortium server using cURL via an ajax request (in case it loads slowly)
?>
<div class="gray">
	<?php echo $testMsg ?>
	<div id="server_ping_response_div">
		<img src="<?php echo APP_PATH_IMAGES ?>progress_circle.gif">
		<b><?php echo $lang['check_058'] ?></b>
	</div>
</div>
<?php




## Check if REDCap Cron Job is running
$testMsg = "<b>".$lang['check_059']."</b><br><br>";
if (Cron::checkIfCronsActive()) {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), $testMsg." <img src='".APP_PATH_IMAGES."tick.png'> <b>".$lang['check_001']."</b> - ".$lang['check_060']."<br>");
} else {
	print RCView::div(array('class'=>'red'),
		$testMsg .
		RCView::img(array('src'=>'exclamation.png')) .
		RCView::b($lang['control_center_288']) . RCView::br() . $lang['control_center_289'] . RCView::br() . RCView::br() .
		RCView::a(array('href'=>'javascript:;','style'=>'','onclick'=>"window.location.href=app_path_webroot+'ControlCenter/cron_jobs.php';"), $lang['control_center_290'])
	);
}




/**
 * SECONDARY TESTS
 */
print "<p style='padding-top:15px;color:#800000;font-weight:bold;font-family:verdana;font-size:13px;'>".$lang['check_061']."</p>";


// Check for SSL
if (SSL || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')) {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>".$lang['check_062']."</b></div>";
} else {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_063']."</div>";
}

// Get the minimum required PHP version that is supported by REDCap
if (version_compare(System::getPhpVersion(), System::getMinPhpVersion(), '<')) {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>".$lang['check_064']." ".System::getMinPhpVersion()." ".$lang['check_065']."</b>".$lang['check_066']." ".System::getPhpVersion().".</div>";
} else {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>".$lang['check_067']." ".System::getMinPhpVersion()." ".$lang['check_068']."</b></div>";
}

// Check for MySQL 5 or higher (or MariaDB 10 or higher)
$db_version = db_get_version(true);
$db_type = db_get_server_type();
if (version_compare($db_version, System::getMinMySQLVersion(), '<')) {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>".$lang['check_069']." ".System::getMinMySQLVersion()." ".$lang['check_065']."</b> ".$lang['check_070'] . " " . $db_type . " " . $db_version ."). ".$lang['check_071']."</div>";
} else {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>Using $db_type ".System::getMinMySQLVersion()." ".$lang['check_068']."</b></div>";
}

## MySQL 8.0.30+ only: Check for sql_generate_invisible_primary_key to ensure it is disabled
if ($db_type == 'MySQL' && version_compare($db_version, '8.0.30', '>='))
{
    $sql = "select @@sql_generate_invisible_primary_key";
    $q = db_query($sql);
    if ($q) {
        $gipkEnabled = db_result($q);
        if ($gipkEnabled == '1') {
            print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>".$lang['check_191']."</b> ".$lang['check_192'] . "</div>";
        }
    }
}


## Check for DOM Document class
if (!class_exists('DOMDocument')) {
	print RCView::div(array('class'=>'red'),
			RCView::img(array('src'=>'exclamation.png')) . $lang['check_072']
		);
}


## Check for XMLReader class
if (!class_exists('XMLReader')) {
	print RCView::div(array('class'=>'red'),
			RCView::img(array('src'=>'exclamation.png')) . $lang['check_073']
		);
}


## Check for GD library (version 2 and up)
if (gd2_enabled()) {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'),
			"<img src='".APP_PATH_IMAGES."tick.png'> <b>".$lang['check_074']."</b>"
		);
} else {
	print RCView::div(array('class'=>'yellow'),
			RCView::img(array('src'=>'exclamation_orange.png')) . $lang['check_075']
		);
}

## Check for Imagick class
$imagick_error = -1;
$imagick_exception = "";
if (isset($GLOBALS['display_inline_pdf_in_pdf']) && $GLOBALS['display_inline_pdf_in_pdf'] == '1') {
	if (!PDF::iMagickInstalled()) {
		// Not installed
		$imagick_error = 1;
	} else {
		// Perform a more extensive check - try to render a minimal PDF as PNG
		// Minimal PDF (blank page):
		// https://github.com/fxcoudert/citable-data/tree/v1
		$blank_pdf = base64_decode("JVBERi0xLjEKJeLjz9MKMSAwIG9iaiAKPDwKL1BhZ2VzIDIgMCBSCi9UeXBlIC9DYXRhbG9nCj4+CmVuZG9iaiAKMiAwIG9iaiAKPDwKL01lZGlhQm94IFswIDAgNTk1IDg0Ml0KL0tpZHMgWzMgMCBSXQovQ291bnQgMQovVHlwZSAvUGFnZXMKPj4KZW5kb2JqIAozIDAgb2JqIAo8PAovUGFyZW50IDIgMCBSCi9NZWRpYUJveCBbMCAwIDU5NSA4NDJdCi9UeXBlIC9QYWdlCj4+CmVuZG9iaiB4cmVmCjAgNAowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAwMTUgMDAwMDAgbiAKMDAwMDAwMDA2NiAwMDAwMCBuIAowMDAwMDAwMTQ5IDAwMDAwIG4gCnRyYWlsZXIKCjw8Ci9Sb290IDEgMCBSCi9TaXplIDQKPj4Kc3RhcnR4cmVmCjIyMQolJUVPRgo=");
		try {
			$imagick = new Imagick();
			$imagick->setResolution(200, 200);
			$imagick->readImageBlob($blank_pdf);
			$imagick->setImageFormat('png');
			$blank_png = $imagick->getImageBlob();
			$imagick_error = 0; // All good
		} catch (Exception $e) {
			$imagick_exception = $e->getMessage();
			if ($e->getCode() == 499) {
				// Wrong security policy
				$imagick_error = 2;
			} else if (strpos($imagick_exception, "FailedToExecuteCommand") !== false &&
				(strpos($imagick_exception, "\"gs") !== false || strpos($imagick_exception, "'gs") !== false)) {
				// Ghostscript is missing
				$imagick_error = 3;
			}
		}
	}
    // Report result
    $pre_attr = [ "style" => "margin-top:.5em;" ];
    switch ($imagick_error) {
        case 0: // All good
            print RCView::div(["class" => "darkgreen", "style" => "color:green;"],
                RCView::img(["src" => APP_PATH_IMAGES."tick.png"]) .
                RCView::tt("check_077")
            );
            break;
        case 1: // Imagick PHP extension not installed
            print RCView::div(["class" => "yellow"],
                RCView::img(["src" => "exclamation_orange.png"]) .
                RCView::tt("check_078")
            );
            break;
        case 2: // Insufficient rights (policy.xml)
            $policy_line = '<policy domain="coder" rights="read" pattern="PDF" />';
            print RCView::div(["class" => "red"],
                RCView::img(["src" => "exclamation.png"]) .
                RCView::tt("check_079") .
                RCView::pre($pre_attr, htmlentities($policy_line)) .
                RCView::tt("check_080")
            );
            break;
        case 3: // Ghostscript is missing
            print RCView::div(["class" => "red"],
                RCView::img(["src" => "exclamation.png"]) .
                RCView::tt("check_076") .
                RCView::pre($pre_attr, htmlentities($imagick_exception))
            );
            break;
        default: // Unspecified error
            print RCView::div(["class" => "red"],
                RCView::img(["src" => "exclamation.png"]) .
                RCView::tt("check_081") .
                RCView::pre($pre_attr, htmlentities($imagick_exception))
            );
            break;
    }
}

## Check if Fileinfo extension is installed
// finfo is installed
if (function_exists('finfo_open')) {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'),
		RCView::img(["src" => APP_PATH_IMAGES."tick.png"]) . 
		RCView::tt("check_082")
	);
}
// cURL not installed
else
{
	?>
	<div class="yellow">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation_orange.png">
		<?php echo $lang['check_083'] ?>
	</div>
	<?php
}

/**
 * CHECK IF USING SSL WHEN THE REDCAP BASE URL DOES NOT BEGIN WITH "HTTPS"
 */
if (substr($redcap_base_url, 0, 5) == "http:") {
	?>
	<div id="ssl_base_url_check" class="red" style="display:none;padding-bottom:15px;">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
		<b><?php echo $lang['control_center_4436'] ?></b><br>
		<?php echo $lang['control_center_4437'] ?>
	</div>
	<script type="text/javascript">
	if (window.location.protocol == "https:") {
		document.getElementById('ssl_base_url_check').style.display = 'block';
	}
	</script>
	<?php
}


// Check if mcrypt PHP extension is loaded
if (!function_exists('openssl_encrypt')) {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_084']."</div>";
}

// ZIP export support for downloading uploaded files in ZIP (Check for PHP 5.2.0+ and ZipArchive)
if (!Files::hasZipArchive()) {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_085']."</div>";
}

// Must have PHP extension "mbstring" installed in order to render UTF-8 characters properly
if (!function_exists('mb_convert_encoding') || !extension_loaded('mbstring'))
{
	print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'>".$lang['check_086']."</div>";
} else {
	// Check if emails can be sent via SMTP (this check requires MBSTRING be enabled)
	$emailContents = $lang['check_087']." <b>".USERID."</b> ".$lang['check_088']." <b>".APP_PATH_WEBROOT_FULL."</b>	(".$lang['check_089']." $redcap_version).";
	$email = new Message();
	$email->setTo($test_email_address);
	$email->setFrom(\Message::useDoNotReply($GLOBALS['project_contact_email']));
	$email->setFromName($lang['check_090']);
	$email->setSubject($lang['check_091'].' '.APP_PATH_WEBROOT_FULL);
	$email->setBody($emailContents,true);
	if ($email->send()) {
		print  "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'>
				<b style='color:green;'>".$lang['check_092']."</b></div>";
	} else {
		print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>".$lang['check_093']."</div>";
	}
}

// Make sure we have a global email for the REDCap admin
if (trim($GLOBALS['project_contact_email']) == '') {
	print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_094']."</div>";
}

// Check if any whitespace has been output to the buffer unne
if ($prehtml !== false && strlen($prehtml) > 0) {
	print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_095']."</div>";
}


// Check if InnoDB engine is enabled in MySQL
if (!$tableCheck->innodb_enabled()) {
	print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_096']."</div>";
}


// Check if max_allowed_packet is large enough in MySQL
$q = db_query("SHOW VARIABLES like 'max_allowed_packet'");
if ($q && db_num_rows($q) > 0)
{
    $row = db_fetch_assoc($q);
    if ($row['Value'] < 134217728) {
        print  "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> ".$lang['check_097']."</div>";
    }
}


// Check max_input_vars
$max_input_vars = ini_get('max_input_vars');
$max_input_vars_min = 100000;
if (is_numeric($max_input_vars) && $max_input_vars < $max_input_vars_min)
{
	// Give recommendation to increase max_input_vars
	print  "<div class='yellow'>
				<img src='".APP_PATH_IMAGES."exclamation_orange.png'> ".$lang['check_098']. " $max_input_vars_min " .$lang['check_099']. " $max_input_vars_min " .$lang['check_100']. "</div>";
}

// Make sure 'upload_max_filesize' and 'post_max_size' are large enough in PHP so files upload properly
$maxUploadSize = maxUploadSize();
if ($maxUploadSize <= 2) { // <=2MB
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_101']."</div>";
} elseif ($maxUploadSize <= 10) { // <=10MB
	print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> ".$lang['check_102']."</div>";
}

// If project-level local storage of files is enabled, then check if we're able to create a test subfolder
if (Files::detectProjectLevelLocalStorageGlobal()) {
    $subfolderName = "pidTEST";
    if (Files::createProjectLevelLocalStorageSubfolder($subfolderName, false)) {
        // Subfolder was created, so now try adding/deleting files in the new subfolder
        if (!Files::createProjectLevelLocalStorageSubfolder($subfolderName, true)) {
            // Error: Cannot write/delete in new subfolder
            print "<div class='yellow'><img src='" . APP_PATH_IMAGES . "exclamation_orange.png'> " . RCView::tt_i('system_config_918', ["<code>" . EDOC_PATH . "</code>", "<code>" . EDOC_PATH . $subfolderName . DS . "</code>"], false) . "</div>";
        }
        // Delete the subfolder
        rmdir(EDOC_PATH . $subfolderName);
    } else {
        // Error
        print "<div class='yellow'><img src='" . APP_PATH_IMAGES . "exclamation_orange.png'> " . RCView::tt_i('system_config_917', ["<code>" . EDOC_PATH . "</code>"], false) . "</div>";
    }
}

// Check if we're missing any hook functions
$hook_functions_file = trim($hook_functions_file);
if (isset($hook_functions_file) && $hook_functions_file != '')
{
    require_once APP_PATH_CLASSES."Hooks.php";
	// Get list of all methods available in Hooks class
	$hookMethods = get_class_methods("Hooks");
	$hookMethodsMissing = array();
	foreach ($hookMethods as $thisMethod) {
	    if ($thisMethod == 'call') continue; // Ignore this one
        if (!function_exists($thisMethod)) {
			$hookMethodsMissing[] = $thisMethod;
        }
    }
	if (!empty($hookMethodsMissing))
	{
	    // Hook functions are missing
		print  "<div class='yellow'>
				<img src='".APP_PATH_IMAGES."exclamation_orange.png'> ".$lang['check_104']." \"<b>$hook_functions_file</b>\" ".$lang['check_105']."
				<b><ul class='my-2'><li>".implode("</li><li>", $hookMethodsMissing)."</li></ul></b>".$lang['check_106']." $hook_functions_file ".$lang['check_107']."</div>";
    }
}

// Check if all non-versioned files are up-to-date
ControlCenter::checkNonVersionedFiles();

// Make sure 'innodb_buffer_pool_size' is large enough in MySQL
$q = db_query("SHOW VARIABLES like 'innodb_buffer_pool_size'");
if ($q && db_num_rows($q) > 0)
{
	while ($row = db_fetch_assoc($q)) {
		$innodb_buffer_pool_size = $row['Value'];
	}
	$total_mysql_space = 0;
	$q = db_query("SHOW TABLE STATUS from `$db` like 'redcap_%'");
	while ($row = db_fetch_assoc($q)) {
		if (strpos($row['Name'], "_20") === false) { // Ignore timestamped archive tables
			$total_mysql_space += $row['Data_length'] + $row['Index_length'];
		}
	}
	// Set max buffer pool size that anyone would probably need
	$innodb_buffer_pool_size_max_neccessary = 1*1024*1024*1024; // 1 GB
	// Compare
	if ($innodb_buffer_pool_size <= ($innodb_buffer_pool_size_max_neccessary*0.95) && $innodb_buffer_pool_size < ($total_mysql_space*1.1))
	{
		// Determine severity (red/severe is < 20% of total MySQL space)
		$class = ($innodb_buffer_pool_size < ($total_mysql_space*.2)) ? "red" : "yellow";
		$img   = ($class == "red") ? "exclamation.png" : "exclamation_orange.png";
		// Set recommend pool size
		$recommended_pool_size = ($total_mysql_space*1.1 < $innodb_buffer_pool_size_max_neccessary) ? $total_mysql_space*1.1 : $innodb_buffer_pool_size_max_neccessary;
		// Give recommendation
		print "<div class='$class'><img src='".APP_PATH_IMAGES."$img'> ".$lang['check_108']." ".round($total_mysql_space/1024/1024).$lang['check_109']." ".round($recommended_pool_size/1024/1024).$lang['check_110']." ".round($innodb_buffer_pool_size/1024/1024).$lang['check_111']."</div>";
	}
}

// Make sure 'optimizer_switch' is set to OFF in MySQL
$q = db_query("SHOW VARIABLES like 'optimizer_switch'");
if ($q && db_num_rows($q) > 0)
{
	$row = db_fetch_assoc($q);
	if (stripos($row['Value'], 'rowid_filter=on') !== false) {
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_112']."</div>";
    }
}

// Make sure that innodb_file_per_table=ON
$row = db_fetch_array(db_query("show variables like 'innodb_file_per_table'"));
$innodb_file_per_table = !(strtolower($row[1]) == 'off' || strtolower($row[1]) != 'on');
$row = db_fetch_array(db_query("show variables like 'innodb_file_format'"));
$innodb_file_format_correct = (strtolower($row[1]) != 'antelope');
if (!$innodb_file_format_correct) {
    print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> ".$lang['check_113']."</div>";
} else {
    // Suggest to only use "dynamic" (or "compressed") row_format for the InnoDB tables
    $tablesCompact = [];
    $q = db_query("SHOW TABLES FROM `$db` LIKE 'redcap%'");
    while ($row = db_fetch_array($q)) {
        $thisTable = $row[0];
        $q2 = db_query("SHOW TABLE STATUS FROM `$db` WHERE Name='$thisTable'");
        $row2 = db_fetch_assoc($q2);
        if (isset($row2['Row_format']) && strtolower($row2['Row_format']) != 'dynamic' && strtolower($row2['Row_format']) != 'compressed') {
            $tablesCompact[] = "ALTER TABLE `$thisTable` ROW_FORMAT=DYNAMIC;";
        }
    }
    if (!empty($tablesCompact)) {
        if (!$innodb_file_per_table) {
            array_unshift($tablesCompact, "SET GLOBAL innodb_file_per_table=ON;");
        }
        // Give recommendation
        print "<div class='yellow'><img src='" . APP_PATH_IMAGES . "exclamation_orange.png'> ".$lang['check_114']." ". count($tablesCompact) . " ".$lang['check_115']."<code style='margin:5px 0;display:block;'>" . implode("<br>", $tablesCompact) . "</code></div>";
    }
}

// Database configuration suggestions
$mysql_tuner = new MySQLTuner();
$MySQLTunerRecs = $mysql_tuner->getRecommendations();
if ($MySQLTunerRecs == "") {
    // All good
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>".db_get_server_type()." ".$lang['check_116']."</b></div>";
} else {
    // Display recommendations
    print $MySQLTunerRecs;
}

// MySQL 8.X-specific: Ensure that sql_generate_invisible_primary_key is DISABLED
if (MySQLTuner::invisiblePkEnabled()) {
    print "<div class='red'><img src='" . APP_PATH_IMAGES . "exclamation.png'> ".$lang['check_184']."</div>";
}

// Check the Read Replica server (if applicable)
if (System::readReplicaConnVarsFound() && System::readReplicaEnabledInConfig())
{
	// Connect to replica
	if (db_connect(false, false, true, true, true)) {
		// Can connect to replica, so check its lag time
		$replicaLagTime = System::getReadReplicaLagTime();
		if ($replicaLagTime === false) {
			// Lag is unknown
			print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> ".$lang['check_181']."</div>";
		} elseif (System::readReplicaLagTimeIsTooHigh()) {
			// Lag is too high
			print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> ".$lang['check_175']." $replicaLagTime ".$lang['check_176']." ".System::REPLICA_LAG_MAX." ".$lang['check_190']."</div>";
		} else {
			// Lag is fine
			print "<div class='darkgreen' style='color:green;'><img src='".APP_PATH_IMAGES."tick.png'> <b>".$lang['check_180']."</b><br>".$lang['check_178']." $replicaLagTime ".$lang['check_179']."</div>";
		}
        unset($GLOBALS['rc_replica_connection']); // Kill the replica connection to prevent it from being used downstream
	} else {
		// Cannot connect to replica!
		print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> ".$lang['check_174']."</div>";
	}
}

// If using a Windows server and some gaps appear in the cron job's instances of running (meaning that it is not running every minute as it should),
// this may indicate that the Windows task is not configured correctly to run in parallel. Use SurveyInvitationEmailer cron job to test this.
if (System::isWindowsServer() && Cron::checkIfCronsActive()) {
    $daysToCheckCronInstances = 3;
	$q = db_query("show global status like 'Uptime'");
	$row = db_fetch_assoc($q);
	$mysqlUptime = $row['Value'];
	// If MySQL has been running longer than $daysToCheckCronInstances days, then perform check
	if ($mysqlUptime > 86400*$daysToCheckCronInstances) {
		$sql = "select if (count(*) >= ((1440*$daysToCheckCronInstances)-1), 1, 0) as cron_status
                from redcap_crons c, redcap_crons_history h
                where c.cron_name = 'SurveyInvitationEmailer' and c.cron_id = h.cron_id
                and h.cron_run_start >= DATE_SUB(now(), INTERVAL $daysToCheckCronInstances DAY)";
		$q = db_query($sql);
		$cron_status = db_result($q, 0);
		if ($cron_status != '1') {
			print "<div class='gray'><span style='color:#A00000;'><i class=\"fas fa-lightbulb\"></i> ".$lang['check_117']." $daysToCheckCronInstances ".$lang['check_118']."</div>";
        }
    }
}

// Check if the timezone setting is different for the cron job's PHP.INI, if a different PHP.INI
if (isset($cron_job_php_ini_file) && $cron_job_php_ini_file != '' && $cron_job_php_ini_file != php_ini_loaded_file()) {
    $timezone = (function_exists("date_default_timezone_get")) ? date_default_timezone_get() : ini_get('date.timezone');
    $iniCronParsed = parse_ini_file($cron_job_php_ini_file);
    $timezoneCron = $iniCronParsed['date.timezone'] ?? null;
    if ($timezoneCron != null && $timezone != $timezoneCron) {
        print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".RCView::tt_i("check_183", [php_ini_loaded_file(), $cron_job_php_ini_file, $timezone, $timezoneCron])."</div>";
    }
}

// Check if web server's tmp directory is writable
$temp_dir = sys_get_temp_dir();
if (isDirWritable($temp_dir)) {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>".$lang['check_119']." $temp_dir</b></div>";
} else {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_120']." $temp_dir) ".$lang['check_121']."</div>";
}

// Check if /redcap/temp is writable
$temp_dir = APP_PATH_TEMP;
if (isDirWritable($temp_dir, true)) {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>".$lang['check_195']." $temp_dir</b></div>";
    // Make sure that
    $subfolderName = "test_".date("YmdHis");
    // Set full path of new folder
    $subfolderNameFull = $temp_dir . $subfolderName;
    // Try to create the subfolder (still return true if the folder already exists, just in case)
    $createdSubfolder = (is_dir($subfolderNameFull) || mkdir($subfolderNameFull));
    if ($createdSubfolder && isDirWritable($subfolderNameFull, true)) {
        // Delete the subfolder
        rmdir($subfolderNameFull);
    } else {
        // Display error if can't create subfolder
        print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".RCView::tt_i('check_196', [$temp_dir])."</div>";
    }
} else {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_123']." $temp_dir) ".$lang['check_124']."</div>";
}

// Check if Rapid Retrieval alternate directory for cached files is writable
$cache_files_filesystem_path = trim($GLOBALS['cache_files_filesystem_path']);
if ($GLOBALS['cache_storage_system'] == 'file' && $cache_files_filesystem_path != '' && !isDirWritable($cache_files_filesystem_path)) {
    print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".RCView::tt_i('check_189', [$cache_files_filesystem_path])."</div>";
}

// Check if /edocs is writable
if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
	// LOCAL STORAGE
	$edocs_dir = EDOC_PATH;
	if (isDirWritable($edocs_dir)) {
		print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>".$lang['check_125']." ".EDOC_PATH."</b></div>";
	} else {
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_126']." $edocs_dir) ".$lang['check_127']."</div>";
	}
	// Check if using default .../redcap/edocs/ folder for file uploads (not recommended)
	if ($edoc_storage_option == '0' && trim($edoc_path) == "")
	{
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_128']."</div>";
	}
} elseif ($edoc_storage_option == '2') {
	// AMAZON S3 STORAGE
    // Try to write a file to that directory and then delete
    $test_file_name = date('YmdHis') . '_test.txt';
    $test_file_content = "test";
    try {
        $s3 = Files::s3client();
        $result = $s3->putObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$test_file_name, 'Body'=>$test_file_content, 'ACL'=>'private'));
        // Success
        print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'>
                <b style='color:green;'>".$lang['check_129']." \"$amazon_s3_bucket\"</b></div>";
        // Now delete the file we just created
        $s3->deleteObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$test_file_name));
    } catch (Aws\S3\Exception\S3Exception $e) {
        // Failed
        print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_130']."</div>";
    }
} elseif ($edoc_storage_option == '4') {
	// AZURE STORAGE
    // Try to write a file to that directory and then delete
    $test_file_name = date('YmdHis') . '_test.txt';
    $test_file_content = "test";
    try {
        $blobClient = new AzureBlob();
        $blobClient->createBlockBlob($GLOBALS['azure_container'], $test_file_name, $test_file_content);
        $blobClient->deleteBlob($test_file_name);
    } catch (Exception $e) {
        // Failed
        print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_131']." \"{$GLOBALS['azure_container']}\". ".$lang['check_132']."</div>";
    }
}elseif ($edoc_storage_option == '5') {
    // Google Cloud STORAGE
    // Try to write a file to that directory and then delete
    $test_file_name = date('YmdHis') . '_test.txt';
    $test_file_content = "test";
    try {
        $googleClient = Files::googleCloudStorageClient();
        $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);
        $result = $bucket->upload($test_file_content, array('name' => $test_file_name));
        $object = $bucket->object($test_file_name);
        $object->delete();
    } catch (Exception $e) {
        // Failed
        print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_133']." \"{$GLOBALS['google_cloud_storage_api_bucket_name']}\". ".$lang['check_134']."</div>";
    }
} 
else {
	// WEBDAV STORAGE
	// Try to write a file to that directory and then delete
	$test_file_name = date('YmdHis') . '_test.txt';
	$test_file_content = "test";
	// Store using WebDAV
	if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit($lang['check_135']." \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
	$wdc = new WebdavClient();
	$wdc->set_server($webdav_hostname);
	$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
	$wdc->set_user($webdav_username);
	$wdc->set_pass($webdav_password);
	$wdc->set_protocol(1); // use HTTP/1.1
	$wdc->set_debug(true); // enable debugging?
	if (!$wdc->open()) {
		// Send error response
		print RCView::div(["class" => "red"],
			RCView::img(["src" => "exclamation.png"]) . " " .
			RCView::tt_i("check_185", [
				dirname(APP_PATH_DOCROOT).DS."webtools2".DS."webdav".DS."webdav_connection.php",
				$webdav_hostname,
				$webdav_path
			]) . " " .
			RCView::tt_i("check_186", [ 
				$wdc->_errno,
				$wdc->_errstr
			])
		);
	}
	else {
		if (substr($webdav_path,-1) != '/') {
			$webdav_path .= '/';
		}
		$http_status = $wdc->put($webdav_path . $test_file_name, $test_file_content);
		if ($http_status == '201') {
			// Success
			print RCView::div(["class" => "darkgreen"],
				RCView::img(["src" => "tick.png"]) . " " . 
				RCView::tt_i("check_188", [
					$webdav_hostname,
					$webdav_path
				], true, "span", ["style" => "color:green;"])
			);
			// Now delete the file we just created
			$http_status = $wdc->delete($webdav_path . $test_file_name);
		} else {
			// Failed
			print RCView::div(["class" => "red"],
				RCView::img(["src" => "exclamation.png"]) . " " .
				RCView::tt_i("check_187", [
					dirname(APP_PATH_DOCROOT).DS."webtools2".DS."webdav".DS."webdav_connection.php",
					$webdav_hostname,
					$webdav_path
				]) . " " .
				RCView::tt_i("check_186", [ 
					$wdc->_errno,
					$wdc->_errstr
				])
			);
		}
		$wdc->close();
	}
}

// Check if /redcap/modules exists and is writable for External Modules
if (defined("APP_PATH_EXTMOD")) {
	$dir = dirname(APP_PATH_DOCROOT) . DS . "modules" . DS;
	if (!is_dir($dir)) {
		print "<div class='red'>".$lang['check_143']." $dir) ".$lang['check_144']." $dir).
				See the <a href='https://redcap.vumc.org/community/custom/download.php' style='text-decoration:underline;' target='_blank'>".$lang['check_039']."</a>.
				(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>".$lang['check_043']."</a>)</div>";
	} else {
		if (isDirWritable($dir)) {
			print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>".$lang['check_145']." $dir</b></div>";
		} else {
			print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_146']." $dir) ".$lang['check_147']."</div>";
		}
	}
}

// Make sure we have correct db privileges for Easy Upgrade (if applicable)
$dbPrivs = Upgrade::hasDbStructurePrivileges(true);
if (is_array($dbPrivs)) {
	if ($dbPrivs['create'] === true && ($dbPrivs['alter'] !== true || $dbPrivs['drop'] !== true || $dbPrivs['references'] !== true)) {
		if ($dbPrivs['alter'] !== true && $dbPrivs['drop'] !== true) {
			$dbPrivsText = $lang['check_148'];
		} elseif ($dbPrivs['alter'] === true) {
			$dbPrivsText = $lang['check_149'];
		} elseif ($dbPrivs['references'] !== true) {
			$dbPrivsText = $lang['check_150'];
		} else {
			$dbPrivsText = $lang['check_151'];
		}
		print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> ".$lang['check_152']." \"<b>{$dbPrivs['username']}</b>\".
				<u>$dbPrivsText</u> ".$lang['check_153']."</div>";
	}
}

// Security related: Recommend that all versions of REDCap prior to 10.3.2 (but excluding 10.0.18-10.0.99) be deleted for security vulnerability purposes
$previousRedcapDirs = getDirFiles(dirname(APP_PATH_DOCROOT).DS);
$deleteRedcapDirs = array();
if (!empty($previousRedcapDirs) && !isVanderbilt() && !isDev()) {
	foreach ($previousRedcapDirs as $this_dir) {
		if (strpos($this_dir, "redcap_v") !== 0) continue;
		$versionDec = Upgrade::getDecVersion(str_replace("redcap_v", "", $this_dir))*1;
		if ($versionDec < 100018 || ($versionDec > 100099 && $versionDec < 100302)) {
			$deleteRedcapDirs[] = dirname(APP_PATH_DOCROOT).DS."<b>$this_dir</b>".DS;
		}
	}
}
if (!empty($deleteRedcapDirs)) {
	natcasesort($deleteRedcapDirs);
	print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> ".$lang['check_154']."
				<ul class='mt-3'><li>".implode("</li><li>", $deleteRedcapDirs)."</li></ul>
				</div>";
}

// Check to ensure that the Zlib PHP extension is enabled
if (!function_exists('gzuncompress')) {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_157']."</div>";
}

// MySQL 8.4.0+: Check for the restrict_fk_on_non_standard_key and recommend it be set to OFF
$sql = "SHOW GLOBAL VARIABLES LIKE 'restrict_fk_on_non_standard_key'";
$q = db_query($sql);
if (db_num_rows($q)) {
	$row = db_fetch_assoc($q);
	if ($row && isset($row['Value']) && strtoupper($row['Value']) !== 'OFF') {
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['check_197']."</div>";
	}
}

// Check internal call to survey (for building record list cache via curl to survey endpoint)
print 	RCView::div(array('class'=>'gray'),
    RCView::b($lang['check_158']) . RCView::br() .
    RCView::div(array('id'=>'redcap_survey_service_check'),
        RCView::img(array('src'=>'progress_circle.gif')) . $lang['check_058']
    )
);
// Check external services
if ($twilio_enabled_global) {
	print 	RCView::div(array('class'=>'gray'),
				RCView::b($lang['check_159']) . RCView::br() .
				RCView::div(array('id'=>'twilio_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . $lang['check_058']
				)
			);
}
if ($mosio_enabled_global) {
	print 	RCView::div(array('class'=>'gray'),
				RCView::b($lang['check_160']) . RCView::br() .
				RCView::div(array('id'=>'mosio_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . $lang['check_058']
				)
			);
}
if ($sendgrid_enabled_global) {
	print 	RCView::div(array('class'=>'gray'),
				RCView::b($lang['check_161']) . RCView::br() .
				RCView::div(array('id'=>'sendgrid_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . $lang['check_058']
				)
			);
}
if ($promis_enabled) {
	print 	RCView::div(array('class'=>'gray'),
				RCView::b($lang['check_162']) . RCView::br() .
				RCView::div(array('id'=>'promis_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . $lang['check_058']
				)
			);
}
if ($enable_ontology_auto_suggest && $bioportal_api_token != '') {
	print 	RCView::div(array('class'=>'gray'),
				RCView::b($lang['check_163']) . RCView::br() .
				RCView::div(array('id'=>'bioportal_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . $lang['check_058']
				)
			);
}
if ($enable_url_shortener) {
	print 	RCView::div(array('class'=>'gray'),
                RCView::b($lang['check_164']) . RCView::br() .
                RCView::div(array('id'=>'redcaplink_service_check'),
                    RCView::img(array('src'=>'progress_circle.gif')) . $lang['check_058']
                )
            );
} elseif ($enable_url_shortener) {
	print 	RCView::div(array('class'=>'gray'),
				RCView::b($lang['check_165']) . RCView::br() .
				RCView::div(array('id'=>'bitly_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . $lang['check_058']
				)
			);
	print 	RCView::div(array('class'=>'gray'),
				RCView::b($lang['check_166']) . RCView::br() .
				RCView::div(array('id'=>'isgd_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . $lang['check_058']
				)
			);
}
if ($google_recaptcha_site_key != '' && $google_recaptcha_secret_key != '') {
    print 	RCView::div(array('class'=>'gray'),
                RCView::b($lang['check_167']) . RCView::br() .
                RCView::div(array('id'=>'googlerecaptcha_service_check'),
                    RCView::img(array('src'=>'progress_circle.gif')) . $lang['check_058']
                )
            );
}
if ($field_bank_enabled) {
	print 	RCView::div(array('class'=>'gray'),
                RCView::b($lang['check_168']) . RCView::br() .
                RCView::div(array('id'=>'nlm_service_check'),
                    RCView::img(array('src'=>'progress_circle.gif')) . $lang['check_058']
                )
            );
}

// If using SSL, then suggest that the cookie "secure" attribute be enabled in PHP.INI
if (SSL) {
	$cookie_params = session_get_cookie_params();
	if ($cookie_params['secure'] !== true) {
		print "<div class='gray'><span style='color:#A00000;'><i class=\"fas fa-lightbulb\"></i> ".$lang['check_169']."</div>";
	}
}

// Suggestion: Generate SQL to add primary keys to all tables, if desired (for clustering or replication needs)
$sqlTableCheck = new SQLTableCheck();
$suggestedSql = $sqlTableCheck->suggestSqlPrimaryKeyAllTables();
if ($suggestedSql != '') {
    print "<div class='mt-5 p-2' style='border:1px solid #ddd;background-color:#eeeeee5e;color:#666;'><div class='mb-2'><i class=\"fas fa-lightbulb\"></i> ".$lang['check_194']."</div>".
            RCView::textarea(array('class'=>'x-form-field notesbox', 'style'=>'color:#666;height:60px;font-size:11px;width:97%;', 'readonly'=>'readonly', 'onclick'=>'this.select();'),
                $suggestedSql
            ) .
            "</div>";

}


/**
 * CONGRATULATIONS!
 */
if (isset($_GET['upgradeinstall']))
{
	loadJS('ControlCenter.js');
	print  "<p><br><hr><p><h4 style='font-size:20px;color:#800000;'>
			<img src='".APP_PATH_IMAGES."star.png'> ".$lang['check_170']." <img src='".APP_PATH_IMAGES."star.png'></h4>
			<p>".$lang['check_171']."
			<div class='blue' style='padding:10px;'>
			<b>".$lang['check_172']."</b>&nbsp;
			<a style='text-decoration:underline;'  href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a>
			</div>";

	// Check global auth_meth value
	if ($auth_meth_global == "none")
	{
		print "<p>".$lang['check_173'];
	}
}

print "<div style='margin-bottom:100px;'> </div>";

// Create a DKIM private key and store in redcap_config if we don't already have one
/**
try {
	$dkim = new DKIM();
	$dkim->createPrivateKey();
	// For DEV/Vanderbilt testing only
	if (isDev(true) &&
		$dkim->hasPrivateKey() && !$dkim->hasDkimDnsTxtRecord())
	{
	    print $dkim->getDnsTxtRecordSuggestion();
	}
}  catch (Exception $e) { }
*/

if (isset($_GET['upgradeinstall'])) {
	$objHtmlPage->PrintFooterExt();
} else {
	include 'footer.php';
}
