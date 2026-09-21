<?php


/********************************************************************************************
This file is used for upgrading to newer versions of REDCap.
It may be used for cumulative upgrading so that incremental updates can be done all at once.
The page will guide you through the upgrade process.
********************************************************************************************/


// File with necessary functions
require_once dirname(__FILE__) . '/Config/init_functions.php';
// Change initial server value to account for a lot of processing and memory
System::increaseMaxExecTime(3600);
// Get the install version number and set the web path
if (isset($upgrade_to_version)) {
	$app_path_webroot_parent = dirname($_SERVER['PHP_SELF']);
	if ($app_path_webroot_parent == DIRECTORY_SEPARATOR) $app_path_webroot_parent = '';
	define("APP_PATH_WEBROOT_PARENT", "$app_path_webroot_parent/");
} else {
	if (basename(dirname(__FILE__)) == "codebase") {
		// If this is a developer with 'codebase' folder instead of version folder, then use JavaScript to get version from query string instead
		if (isset($_GET['version'])) {
			$upgrade_to_version = $_GET['version'];
		} else {
			// Redirect via JavaScript
			?>
			<script type="text/javascript">
			var urlChunks = window.location.href.split('/').reverse();
			window.location.href = window.location.href+'?version='+urlChunks[1].substring(8);
			</script>
			<?php
			exit;
		}
	} else {
		// Get version from above directory
		$upgrade_to_version = substr(basename(dirname(__FILE__)), 8);
	}
	$app_path_webroot_parent = dirname(dirname($_SERVER['PHP_SELF']));
	if ($app_path_webroot_parent == DIRECTORY_SEPARATOR) $app_path_webroot_parent = '';
	define("APP_PATH_WEBROOT_PARENT", "$app_path_webroot_parent/");
}
define("APP_PATH_WEBROOT", APP_PATH_WEBROOT_PARENT . "redcap_v" . $upgrade_to_version . "/");
// Set version to standard variable
$redcap_version = $upgrade_to_version;
defined("REDCAP_VERSION") or define("REDCAP_VERSION", $redcap_version);
// Declare current page with full path
define("PAGE_FULL", 			$_SERVER['PHP_SELF']);
// Declare current page
define("PAGE", 					basename(PAGE_FULL));
// Docroot will be used by php includes
define("APP_PATH_DOCROOT", 		dirname(__FILE__) . DS);
// Webtools folder path
define("APP_PATH_WEBTOOLS",		dirname(APP_PATH_DOCROOT) . "/webtools2/");
// Classes
define("APP_PATH_CLASSES",  	APP_PATH_DOCROOT . "Classes/");
// Controllers
define("APP_PATH_CONTROLLERS", 	APP_PATH_DOCROOT . "Controllers/");
// Image repository
define("APP_PATH_IMAGES",		APP_PATH_WEBROOT . "Resources/images/");
// CSS
define("APP_PATH_CSS",			APP_PATH_WEBROOT . "Resources/css/");
// External Javascript
define("APP_PATH_JS",			APP_PATH_WEBROOT . "Resources/js/");
// Webpack
define('APP_PATH_WEBPACK', 	    APP_PATH_WEBROOT . "Resources/webpack/");
// Other constants
// Get server name (i.e. domain), server port, and if using SSL (boolean)
list ($server_name, $port, $ssl, $page_full) = getServerNamePortSSL();
define("SERVER_NAME", $server_name);
define("SSL", $ssl);
define("PORT", str_replace(":", "", $port)); // Set PORT as numeric w/o colon
define("APP_PATH_WEBROOT_FULL",		(SSL ? "https" : "http") . "://" . SERVER_NAME . $port . ((strlen(dirname(APP_PATH_WEBROOT)) <= 1) ? "" : dirname(APP_PATH_WEBROOT)) . "/");
// If using alternative survey base URL for Full URL
if ($redcap_survey_base_url != '') {
	// Make sure $redcap_survey_base_url ends with a /
	$redcap_survey_base_url .= ((substr($redcap_survey_base_url, -1) != "/") ? "/" : "");
	// Full survey URL
	define("APP_PATH_SURVEY_FULL",		$redcap_survey_base_url . "surveys/");
} else {
	// Full survey URL
	define("APP_PATH_SURVEY_FULL",		APP_PATH_WEBROOT_FULL . "surveys/");
}
// Make initial connection to MySQL project
db_connect();

// Get current version number from redcap_config
$current_version = db_result(db_query("select value from redcap_config where field_name = 'redcap_version' limit 1"), 0);


// UTF8MB4 check
$fixData = ($GLOBALS['db_character_set'] == 'latin1') ||
	(isset($GLOBALS['db_fix_data_nonproject']) &&
		!($GLOBALS['db_fix_data_nonproject'] == '1' && $GLOBALS['db_fix_data_project_active'] == '1' && $GLOBALS['db_fix_data_project_inactive'] == '1' && $GLOBALS['db_fix_data_extra'] == '1')
	);
$fixStructure = !SQLTableCheck::using_utf8mb4();
if ($fixStructure || $fixData) {
    exit("<div style='max-width:800px;margin:20px;'>ERROR: You may not upgrade to REDCap 15.6.0 or higher until you perform the Unicode Transformation to your REDCap database tables in order to support full Unicode. ".
         "This is required in order to upgrade. See your Configuration Check page for further details on how to perform the Unicode Transformation process.</div>");
}

// DOWNLOAD FILE: If downloading the upgrade script file, then output the file contents here
if (isset($_GET['download_file']) || isset($_GET['sql'])) 
{
	$sql = Upgrade::getUpgradeSql($current_version, $redcap_version);
	ob_start();
	if (isset($_GET['download_file'])) {
		header("Content-type: application/octet-stream");
		header('Content-Disposition: attachment; filename=redcap_upgrade_'.Upgrade::getDecVersion($redcap_version).'.sql');
	}
	print $sql;
	// Replace all line breaks with \r\n for compatibility
	$delim = "||--RCDELIM--||";
	$sql = str_replace(array("\r\n", "\r", "\n", $delim), array($delim, $delim, $delim, "\r\n"), ob_get_clean());
	exit($sql);
}

// Determine if this is a fast upgrade, which does not require REDCap going Offline
$isFastUpgrade = Upgrade::isFastUpgrade($current_version, $redcap_version);

// Determine if an auto-upgrade can be performed
$canDoAutoUpgrade = ($isFastUpgrade && Upgrade::hasDbStructurePrivileges());

// AUTO-UPGRADE
$autoUpgradeCompleted = false;
if ($canDoAutoUpgrade && isset($_GET['auto']))
{
	$autoUpgradeSuccess = Upgrade::executeUpgradeSQL($redcap_version);
	if ($autoUpgradeSuccess != '1') {
		// Error
		redirect($_SERVER['PHP_SELF']);
	} else {
		redirect($_SERVER['PHP_SELF'] . "?" . (isset($_GET['version']) ? "version=".$_GET['version']."&" : "") . "completed=1");
	}
}

// Add global $html variable that can be utilized by PHP upgrade files for outputting text, javascript, etc. to page
$html = "";

// Initialize page display object
$objHtmlPage = new HtmlPage();
$objHtmlPage->addStylesheet("home.css", 'screen,print');
$objHtmlPage->PrintHeader();

// Page header with logo
$headerWithLogo = "<table width=100% cellpadding=0 cellspacing=0>
					<tr>
						<td valign='top' style='padding:20px 0;font-size:20px;font-weight:bold;color:#800000;'>
							REDCap $redcap_version ".$lang['upgrade_006']."
						</td>
						<td valign='top' style='text-align:right;padding-top:5px;'>
							<img src='" . APP_PATH_IMAGES . "redcap-logo.png'>
						</td>
					</tr>
				</table>";

// If just complete the auto-upgrade
if (isset($_GET['completed'])) {
	print $headerWithLogo;
	print RCView::simpleDialog("<div class='green'>".$lang['upgrade_003']." $redcap_version ".$lang['upgrade_004']."</div>","<img src='".APP_PATH_IMAGES."tick.png'> <span style='color:green;'>".$lang['upgrade_005']."</span>","auto_upgrade_complete");
			
	?>
	<script type="text/javascript">
	$(function(){
		$('#footer').hide();
		simpleDialog(null,null,'auto_upgrade_complete',500,function(){
			window.location.href = '<?php echo APP_PATH_WEBROOT_PARENT ?>redcap_v<?php echo $redcap_version ?>/ControlCenter/check.php?upgradeinstall=1';
		},"<?php echo $lang['upgrade_007'] ?>");
	});
	</script>
	<?php
	$objHtmlPage->PrintFooter();
	exit;
}
// System must be on version 3.0.0 in order to upgrade to this one
if (str_replace(".", "", Upgrade::getLeadZeroVersion($current_version)) < 30000) {
	print("$headerWithLogo<p><br><b>".$lang['upgrade_008']."</b><br>
	".$lang['upgrade_009']." $current_version.
	".$lang['upgrade_010']." $redcap_version.<br>
	".$lang['upgrade_011']." $redcap_version.<br><br></p>");
	$objHtmlPage->PrintFooter();
	exit;
}


// If the system has already been upgraded to this version, then stop here and give link back to REDCap.
if (version_compare($current_version, $redcap_version, '>='))
{
	print("$headerWithLogo<p><br><b>".$lang['upgrade_012']."</b><br>
		  ".$lang['upgrade_013']." $current_version. ".$lang['upgrade_014']."
		  <a href='" . APP_PATH_WEBROOT . "index.php' style='text-decoration:underline;font-weight:bold;'>".$lang['upgrade_015']."</a>
		  <br><br></p>");
	$objHtmlPage->PrintFooter();
	exit;
}

print $headerWithLogo;

// Do repeated ajax calls every 5 seconds until upgrade is finished via MySQL so that we can remind
// them to then go to the Configuration Check page.
?>
<script type="text/javascript">
function checkVersionAjax(version) {
	$.get(app_path_webroot+'ControlCenter/check_upgrade.php',{ version: version},function(data){
		if (data=='1') {
			$('#goToConfigTest').dialog({ bgiframe: true, modal: true, width: 500, zIndex: 4999, close: function(){ goToConfigTest() }, buttons: {
				'<?php echo $lang['upgrade_064'] ?>': function() {
					goToConfigTest();
				}
			} });
		} else {
			setTimeout("checkVersionAjax('"+version+"')",5000);
		}
	});
}
function goToConfigTest() {
	window.location.href = app_path_webroot+'ControlCenter/check.php?upgradeinstall=1';
}
$(function(){
	setTimeout("checkVersionAjax('<?php echo $upgrade_to_version ?>')",5000);
});
</script>
<style type="text/css">
#pagecontent { margin-top: 0; }
</style>

<!-- Hidden div to tell user to go to Config Test after the upgrade -->
<p id="goToConfigTest" style="display:none;" title="<img src='<?php echo APP_PATH_IMAGES ?>tick.png'> <span style='color:green;'><?php echo $lang['upgrade_005'] ?></span>">
<?php echo $lang['upgrade_017'] ?> <?php echo $upgrade_to_version ?> <?php echo $lang['upgrade_018'] ?>
</p>
<?php

// Check for OpenSSL before allowing upgrade
openssl_loaded(true);

// Get time of auto logout from redcap_config
$autologout_timer = db_result(db_query("select value from redcap_config where field_name = 'autologout_timer' limit 1"), 0);
if ($autologout_timer == "" || $autologout_timer == "0") $autologout_timer = "30";

// Instructions
if ($isFastUpgrade) {
	print  "<p style='margin:25px 0 0;padding-top:12px;border-top:1px solid #aaa;'>
				<b>1.) ".$lang['upgrade_001'].":</b><br>
				".$lang['upgrade_002']."
			</p>";
	print  "<p class='darkgreen'>
				<img src='".APP_PATH_IMAGES."tick.png'>
				".$lang['upgrade_016']."
			</p>";
} else {
	print  "<p style='margin:25px 0 0;padding-top:12px;border-top:1px solid #aaa;'>
				<b>1.) ".$lang['upgrade_001'].":</b><br>
				".$lang['upgrade_019']." $autologout_timer ".$lang['upgrade_020']."
				<a href='".APP_PATH_WEBROOT."ControlCenter/general_settings.php' target='_blank' style='text-decoration:underline;'>".$lang['upgrade_021']."</a>
				".$lang['upgrade_022'].")
			</p>";
	if ($system_offline) {
		print RCView::p(
			["class"=>"darkgreen"],
			RCIcon::CheckMark("text-success me-1") .
			RCView::lang_i("upgrade_065", [$autologout_timer])
		);
	} else {
		print RCView::p(
			["class"=>"yellow"],
			RCIcon::ErrorNotificationTriangle("text-danger me-1") .
			RCView::tt("upgrade_024")
		);
	}
}

// LANGUAGE CHECK: Check if using any non-English languages. Make sure language files exist and remind them to update their language files.
$usesOtherLanguages = false; // Default
// Account for the project_language field changing from INT to VARCHAR in v3.2.0 (0 = English)
$langValNumeric = (Upgrade::getDecVersion($current_version) < 30200);
$englishValue   = ($langValNumeric) ? '0' : 'English';
// Check if using non-English in any projects or as project default
$languagesUsed = array();
$qconfig = db_query("select value from redcap_config where field_name = 'project_language' and value != '$englishValue'");
$configNonEnglish = db_num_rows($qconfig);
$qprojects = db_query("select distinct project_language from redcap_projects where project_language != '$englishValue'");
$projectsNonEnglish = db_num_rows($qprojects);
if (($configNonEnglish + $projectsNonEnglish) > 0)
{
	// Create list of languages used
	while ($row = db_fetch_assoc($qconfig)) 	 $languagesUsed[] = $row['value'];
	while ($row = db_fetch_assoc($qprojects)) $languagesUsed[] = $row['project_language'];
	$languagesUsed = array_unique($languagesUsed);
	// If currently on version before 3.2.0, transform numeric values into varchar equivalents
	if ($langValNumeric)
	{
		foreach ($languagesUsed as $key=>$val)
		{
			$languagesUsed[$key] = ($val == '1') ? 'Spanish' : 'Japanese';
		}
	}
	// Make sure language files exist for languages used
	$languageFiles = Language::getLanguageList();
	unset($languageFiles['English']);
	// Only show section if other languages are actually being utilized
	if (!empty($languagesUsed))
	{
		// Language file directory
		$langDir = dirname(APP_PATH_DOCROOT) . DS . "languages" . DS;
		print  "<b>".$lang['upgrade_025']."</b><br>
		".$lang['upgrade_026']."
				<a href='https://redcap.vumc.org/plugins/redcap_consortium/language_library.php' target='_blank' style='text-decoration:underline;'>".$lang['upgrade_027']."</a>.
				<br><br>
				".$lang['upgrade_028']." <b>$langDir</b>. ".$lang['upgrade_029']." $redcap_version.
				<b>".$lang['upgrade_030']."
				<a href='".APP_PATH_WEBROOT."LanguageUpdater/' target='_blank' style='text-decoration:underline;'>".$lang['lang_updater_02']." </a>
				".$lang['upgrade_031']."</b>, ".$lang['upgrade_032']."<br>";
		// Check if directory exists
		if (!is_dir($langDir))
		{
			print  "<img src='".APP_PATH_IMAGES."cross.png'>
					<span style='color:#red;'><b>".$lang['upgrade_033']."</b> ".$lang['upgrade_034']." $langDir</span><br>";
		}
		// Get array of English language
		$English = Language::callLanguageFile('English');
		// Loop through all and check if each INI file exists
		foreach (array_unique(array_merge(array_keys($languageFiles), $languagesUsed)) as $this_lang)
		{
			if (isset($languageFiles[$this_lang]))
			{
				// Found the file, so now check to see if it's up to date
				$untranslated_strings = count(array_diff_key($English, Language::callLanguageFile($this_lang)));
				if ($untranslated_strings < 1) {
					print  "<img src='".APP_PATH_IMAGES."tick.png'>
							<span style='color:green;'><b>$this_lang.ini</b> ".$lang['upgrade_035']."</span><br>";
				} else {
					print  "<img src='".APP_PATH_IMAGES."exclamation.png'>
							<span style='color:#800000;'><b>$this_lang.ini</b> ".$lang['upgrade_036']."
							".$lang['upgrade_037']." $untranslated_strings ".$lang['upgrade_038']."</span><br>";
				}
			}
			else
			{
				// Could not find the language file
				print  "<img src='".APP_PATH_IMAGES."cross.png'>
						<span style='color:#red;'><b>".$lang['upgrade_033']."</b>
						".$lang['upgrade_039']." <b>" . $langDir . $this_lang. ".ini</b>.
						".$lang['upgrade_040']." \"$this_lang\" ".$lang['upgrade_041']."</span><br>";
			}
		}
	}
}

print  "<p style='margin:20px 0 20px;padding-top:12px;border-top:1px solid #aaa;'>
			<b>2.) ".$lang['upgrade_042']."</b><br>
			".$lang['upgrade_043']." <b style='color:#800000;'>".$lang['upgrade_044']." ".
			($canDoAutoUpgrade ? $lang['upgrade_045'] : $lang['upgrade_046'])."
			".$lang['upgrade_047']."</b> ".$lang['upgrade_048']." \"<b>$hostname</b>\". ".$lang['upgrade_049']."
		</p>";


// Text box for holding the SQL
print  "<div id='sqlloading' style='margin-bottom:140px;width:98%;height:215px;font-size:14px;font-weight:bold;text-align:center;border:1px solid #ccc;background-color:#eee;padding-top:20px;'>
			<div style='padding-bottom:8px;'>".$lang['upgrade_050']."</div>
			<img src='".APP_PATH_IMAGES."progress_bar.gif'>
		</div>";
print "<div id='sqlscript' style='display:none;width:98%;'>";
print "<table cellspacing='0' style='width:100%;'><tr>";
print "<td valign='top' style='width:50%;'>";
// Auto-upgrade option
if ($canDoAutoUpgrade) {
	print  "<div class='blue' style='margin:0 20px 15px 7px;'>
				<div style='font-size:13px;color:#800000;'><b>".$lang['upgrade_051']."</b></div>
				<div style='margin:10px 0;'>
					".$lang['upgrade_052']."
				</div>
				<button class='btn btn-primaryrc btn-xs' onclick=\"clickAutoUpgradeBtn();\">".$lang['upgrade_053']."</button>
			</div>";
}
// Textarea with SQL script
print "<div style='margin-left:7px;margin-bottom:5px;color:#800000;'><b style='font-size:13px;'>".$lang['upgrade_054']." ".($canDoAutoUpgrade ? "B" : "A").":</b> ".$lang['upgrade_055']."</div>";
print "<textarea style='margin:0 0 0 8px;padding: 3px 5px; background: none repeat scroll 0 0 #F6F6F6;border-color: #A4A4A4 #B9B9B9 #B9B9B9; border-radius: 3px;border-right: 1px solid #B9B9B9; border-style: solid; border-width: 1px;box-shadow: 0 1px 0 #FFFFFF, 0 1px 1px rgba(0, 0, 0, 0.17) inset;color:#444;font-size:11px;width:90%;height:210px;' readonly='readonly' onclick='this.select();'>";
print Upgrade::getUpgradeSql($current_version, $redcap_version);
print "</textarea>";
print "</td><td valign='top' style='width:50%;'>";
// Option to download SQL script as file
print "<div style='margin-left:7px;margin-bottom:5px;color:#800000;'><b style='font-size:13px;'>".$lang['upgrade_054']." ".($canDoAutoUpgrade ? "C" : "B").":</b> ".$lang['upgrade_056']."</div>";
print "<div style='margin:8px 0 8px 20px;'><button class='jqbuttonmed' onclick=\"window.location.href = '".js_escape($_SERVER['REQUEST_URI']).(strpos($_SERVER['REQUEST_URI'], "?") === false ? "?" : "&")."download_file=1';\"><img src='".APP_PATH_IMAGES."go-down.png' style='vertical-align:middle;'> <span style='vertical-align:middle;'>".$lang['upgrade_057']."</span></button></div>";
print "<div class='hang' style='margin-bottom:7px;'>
		&nbsp; 1) ".$lang['upgrade_058']." \"redcap_upgrade_".Upgrade::getDecVersion($redcap_version).".sql\".
		</div>";
print "<div class='hang' style='margin-bottom:7px;'>&nbsp; 2) ".$lang['upgrade_059']."</div>";
print "<div class='hang'>&nbsp; 3) ".$lang['upgrade_060']."</div>";
print "<div style='margin:6px 0 0 12px;'>";
print "<textarea style='margin:0 0 0 8px;padding: 3px 5px; background: none repeat scroll 0 0 #F6F6F6;border-color: #A4A4A4 #B9B9B9 #B9B9B9; border-radius: 3px;border-right: 1px solid #B9B9B9; border-style: solid; border-width: 1px;box-shadow: 0 1px 0 #FFFFFF, 0 1px 1px rgba(0, 0, 0, 0.17) inset;color:#444;font-size:12px;width:96%;height:46px;' readonly='readonly' onclick='this.select();'>";
print "mysql -u USERNAME -p -h $hostname $db < redcap_upgrade_".Upgrade::getDecVersion($redcap_version).".sql";
print "</textarea></div>";
print "</tr></table>";
// Link to test page
print  "<div class='p' style='margin:25px 0 15px;padding-top:12px;border-top:1px solid #aaa;'>
			<b>3.) ".$lang['upgrade_061']."</b><br>
			".$lang['upgrade_062']."
			<div style='margin:15px 0 10px 5px;'>
				Go to &nbsp;
				<button class='jqbuttonmed' onclick=\"window.location.href = '".APP_PATH_WEBROOT . "ControlCenter/check.php?upgradeinstall=1';\"><span style='vertical-align:middle;'>".$lang['upgrade_063']."</span></button>
			</div>
		</div>";
// Any custom HTML added from PHP upgrade files
print $html;
// close div
print "</div>";
?>
<script type="text/javascript">
function clickAutoUpgradeBtn() {	
	$('#working').remove(); // Reset this for showProgress to work fully
	showProgress(1,1);
	setTimeout(function(){
		window.location.href = '<?php echo js_escape($_SERVER['REQUEST_URI']) . (strpos($_SERVER['REQUEST_URI'], "?") === false ? "?" : "&") ?>auto=1';
	},500);
}
setTimeout(function(){
	document.getElementById('sqlscript').style.display  = 'block';
	document.getElementById('sqlloading').style.display = 'none';
},1000);
</script>
<?php
// Page footer
$objHtmlPage->PrintFooter();