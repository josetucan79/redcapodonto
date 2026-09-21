<?php

// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

if (!ACCOUNT_MANAGER) redirect(APP_PATH_WEBROOT);

// Function to get current banned ip addresses list
function getBlockedIpAddresses()
{
	$blockList = array();
	$sql = "SELECT * FROM redcap_ip_banned ORDER BY time_of_ban";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
        $blockList[$row['ip']] = $row;
	}
	return $blockList;
}

// Get current ip blocklist
$blockList = getBlockedIpAddresses();

if (isset($_GET['action']) && $_GET['action'] == 'getCurrentTime') {
    echo DateTimeRC::format_user_datetime(NOW, 'Y-M-D_24', null, true);
    exit;
}
// If making an AJAX call
if ($isAjax && isset($_POST['action']))
{
	// Delete all ip addresses from blocklist
	if ($_POST['action'] == 'deleteall')
	{
		// Save the change
		$sql = "DELETE FROM redcap_ip_banned";
		if (db_query($sql))
		{
			// Log the event
			Logging::logEvent($sql,"redcap_ip_banned","MANAGE","","","Remove all IP Addresses from blocklist");
			// Get current blocklist
            $blockList = getBlockedIpAddresses();
			// Display confirmation message to user
			?>
			<div id="deleteAllSuccess" class="darkgreen" style="text-align:center;font-weight:bold;display:none;margin-bottom:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>tick.png">
				<?php echo $lang['control_center_4781'] ?>
			</div>
			<?php
		}
	}
	// Remove ip from blocklist
	elseif ($_POST['action'] == 'remove' && isset($_POST['ip']))
	{
		// First make sure ip is actually in blocklist
		if (!isset($blockList[$_POST['ip']])) exit('0');
		// Save the change
		$sql = "DELETE FROM redcap_ip_banned WHERE ip = '" . db_escape($_POST['ip']) . "'";
		if (db_query($sql))
		{
			// Log the event
			Logging::logEvent($sql,"redcap_ip_banned","MANAGE",$_POST['ip'],"ip = '" . db_escape($_POST['ip']) . "'","Remove ip from IP Addresses Blocklist");
			// Give affirmative response
			exit('1');
		}
	}
	// Add ip addresses to blocklist
	elseif ($_POST['action'] == 'add' && isset($_POST['ips']))
	{
	    if ($_POST['time_of_ban'] == '') {
	        $_POST['time_of_ban'] = DateTimeRC::format_user_datetime(NOW, 'Y-M-D_24', null, true);
        }

        $_POST['time_of_ban'] = DateTimeRC::format_ts_to_ymd($_POST['time_of_ban']).':00';
		$sql_all = array();
		$blockListNew = array();
		$blockListFail = array();
		// Loop through all ips submitted and add to table
		foreach (explode("\n", trim($_POST['ips'])) as $thisIP)
		{
			// Clean the IP and check for blanks
            $thisIP = trim(decode_filter_tags($thisIP));
			if ($thisIP == '') continue;
			// Check format of ip address
			$thisIP2 = filter_var($thisIP2, FILTER_VALIDATE_IP);
            if (!in_array($thisIP, array_keys($blockList)))
			{
				// Save the change
				$sql = "INSERT INTO redcap_ip_banned VALUES ('" . db_escape($thisIP) . "', '" . db_escape($_POST['time_of_ban']) . "')";
				if (db_query($sql)) {
					$sql_all[] = $sql;
					// Add to block list so that it shows up in table rendered below
					$blockList[$thisIP] = $blockListNew[$thisIP] = "";
				}
			}
			else
			{
				// Add to list of IPs that couldn't be added
				$blockListFail[$thisIP] = "";
			}
		}
		// Log the event
		if (!empty($sql_all))
		{
			Logging::logEvent(implode(";\n",$sql_all),"redcap_ip_banned","MANAGE","","","Add IP addresses to blocklist");
            // Get current blocklist
            $blockList = getBlockedIpAddresses();
			// Display confirmation message to user
			?>
			<div id="addSuccess" class="darkgreen" style="font-weight:bold;display:none;margin-bottom:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>tick.png">
				<?php echo $lang['control_center_4784'] ?>
				<div style="font-weight:normal;padding-top:10px;">
					<?php echo $lang['control_center_4785'] ?><br> &bull;
					<?php echo implode("<br> &bull; ", array_keys($blockListNew)) ?>
				</div>
				<?php if (!empty($blockListFail)) { ?>
				<div style="font-weight:normal;padding-top:10px;">
					<?php echo $lang['control_center_4786'] ?><br> &bull;
					<?php echo implode("<br> &bull; ", array_keys($blockListFail)) ?>
				</div>
				<?php } ?>
			</div>
			<?php
		}
		else
		{
			?>
			<div id="addSuccess" class="red" style="text-align:center;font-weight:bold;display:none;margin-bottom:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>cross.png">
				<?php echo $lang['control_center_4787'] ?>
			</div>
			<?php
		}
	}
}
else
{
	// Regular page display
	include 'header.php';
	?>

	<style type="text/css">
        .head {
            font-family: Verdana;
            font-size: 9pt;
            border: 1px solid #CCCCCC;
            padding:4px 10px;
            font-weight:normal;
            background:#eee;
            font-weight: bold;
        }
        .data2 { padding: 1px 10px; }
	</style>

	<script type="text/javascript">
        var langDelete1 = '<?php print js_escape($lang['control_center_4789']) ?>';
        var langDelete2 = '<?php print js_escape($lang['control_center_4790']) ?>';
        var langDeleteAll = '<?php print js_escape($lang['control_center_4791']) ?>';

        // Validate IP Address
        function validateIPAddress(ipaddress)
        {
            return (/((^\s*((([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))\s*$)|(^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$))/.test(ipaddress));
        }

	    // AJAX call to remove a ip from blocklist
        function removeIPFromList(ip, pos) {
            if (confirm(langDelete1 + " '" + ip + "' " + langDelete2)) {
                $.post(app_path_webroot+page, { ip: ip, action: 'remove' }, function(data){
                    if (data=='0') {
                        alert(woops);
                    } else {
                        highlightTableRow('banned-ip-'+pos,3000);
                        setTimeout(function(){
                            $('#banned-ip-'+pos).remove();
                        },500);
                    }
                });
            }
        }

        // AJAX call to delete all ips from blocklist
        function deleteAllIpsFromBlockList() {
            if (confirm(langDeleteAll)) {
                $.post(app_path_webroot+page, { action: 'deleteall' }, function(data){
                    if (data=='0') {
                        alert(woops);
                    } else {
                        $('#bannedlist_table').html(data);
                        initWidgets();
                        $('#deleteAllSuccess').show('blind');
                        setTimeout(function(){
                            $('#deleteAllSuccess').hide('blind');
                        },5000);
                    }
                });
            }
        }

        // Dialog for adding ips to blocklist
        function addIpsToBlockList() {
            // Update time of ban value to current time each time popup is opened
            $.get(app_path_webroot+page+'?action=getCurrentTime', function(time_now){
                $('#time_of_ban').val(time_now);
            });
            $('#ipBannedListAdd').dialog({ bgiframe: true, modal: true, width: 500, buttons: {
                '<?=js_escape($lang['global_53'])?>': function() { $('#newIPs').val(''); $('#time_of_ban').val(''); $(this).dialog('close'); },
                '<?=js_escape($lang['control_center_4782'])?>': function () {
                    $('#newIPs').val(trim($('#newIPs').val()));
                    var errors = new Array();
                    if ($('#newIPs').val().length < 1) {
                        errors.push('<?=js_escape($lang['control_center_4780'])?>');
                    } else {
                        var ips = $('#newIPs').val().split(/\r\n|\n|\r/);
                        $.each(ips, function(k, ip){
                            if (!validateIPAddress(ip)) {
                                errors.push('<?=js_escape($lang['control_center_4795'])?> '+ip);
                            }
                        });
                    }
                    if ($('#time_of_ban').val().length < 1) {
                        errors.push('<?=js_escape($lang['control_center_4794'])?>');
                    }

                    if (errors.length > 0) {
                        var errorsList = " &bull; " + errors.join("<br> &bull; ");
                        simpleDialog(errorsList, 'Errors!', null, 500);
                    } else {
                        $.post(app_path_webroot+page, { action: 'add', ips: $('#newIPs').val(), 'time_of_ban': $('#time_of_ban').val()  }, function(data){
                            if (data=='0') {
                                alert(woops);
                            } else {
                                $('#newIPs').val('');
                                $('#ipBannedListAdd').dialog('close');
                                $('#bannedlist_table').html(data);
                                initWidgets();
                                $('#addSuccess').show('blind');
                                setTimeout(function(){
                                    $('#addSuccess').hide('blind');
                                },5000);
                            }
                        });
                    }
                }
            } });
        }
        $(function(){
            // Datepicker widget for time of ban
            $('#time_of_ban').datetimepicker({
                buttonText: 'Click to select a date/time', yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
                hour: currentTime('h'), minute: currentTime('m'), showOn: 'button', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true,
                timeFormat: 'HH:mm', constrainInput: false
            });
        });
	</script>

	<h4 style="margin-top: 0;"><i class="far fa-eye-slash"></i> <?php echo $lang['control_center_4777'] ?></h4>
	<p style='margin-top:0;'><?php echo $lang['control_center_4778'] ?></p>

    <!-- Hidden dialog for adding ips to blocklist -->
    <div id="ipBannedListAdd" title="<?php echo $lang['control_center_4782'] ?>" style="display:none;">
        <p><?php echo $lang['control_center_4783'] ?><br><br><b><?php echo $lang['control_center_4793'].$lang['colon'] ?></b></p>
        <textarea id="newIPs" style="width:95%;height:100px;"></textarea>
        <br><br><b><?=js_escape($lang['control_center_4880'])?></b>
        <input class="x-form-text x-form-field" type="text" id="time_of_ban" name="time_of_ban" onfocus="if (!$('.ui-datepicker:visible').length) $(this).next('img').click();"
               style="width: 120px;" onblur="redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);"
               onkeydown="if(event.keyCode == 13) return false;"/>
        <span class="df"><?php echo DateTimeRC::get_user_format_label() ?> H:M</span>
    </div>
    <!-- Hidden dialog for prompting super user before enabling/disabling allowlist -->
    <div id="allowlistEnableAsk" title="<?php echo $lang['control_center_161'] ?>" style="display:none;">
        <p><?php echo $lang['control_center_163'] ?></p>
        <p><?php echo $lang['control_center_165'] ?></p>
    </div>

	<div id="bannedlist_table">
<?php
}
?>
	<!-- Banned IP Addresses Table -->
	<table style='border-collapse:collapse;width:100%;max-width:650px;' border="1">
		<tr>
			<td class='labelrc' style='padding-top:10px;padding-bottom:10px;background-color:#eee;font-family:verdana;color:#800000;' colspan='3'>
				<table style='border-collapse:collapse;'  width=100%>
					<tr>
						<td style="font-size:14px;">
							<?php echo $lang['control_center_4788'] ?>
						</td>
						<td style="text-align:right;">
							<button class="btn btn-xs fs12 btn-rcgreen btn-rcgreen-light WLTableElement" onclick="addIpsToBlockList();"><?php echo $lang['control_center_4782'] ?></button> &nbsp;
							<button class="btn btn-xs fs12 btn-rcred btn-rcred-light WLTableElement" onclick="deleteAllIpsFromBlockList();"><?php echo $lang['control_center_172'] ?></button>
						</td>
					</tr>
				</table>
			</td>
		</tr>
        <tr>
            <td class='head'><?php echo $lang['survey_1221']; ?></td>
            <td class='head'><?php echo $lang['control_center_4792']; ?></td>
            <td class='head'><?php echo $lang['docs_45']; ?></td>
        </tr>
	<?php
    $count = 0;
    foreach ($blockList as $thisList) {
        $count++;
        ?>
		<tr id="banned-ip-<?php echo $count ?>">
			<td class='data2'>
				<?php echo $thisList['ip'] ?>
			</td>
            <td class='data2'>
                <?php echo DateTimeRC::format_ts_from_ymd($thisList['time_of_ban']); ?>
            </td>
			<td class='data2' style='width:30px;text-align:center;'>
				<a href="javascript:;" onclick="removeIPFromList('<?php echo $thisList['ip'] ?>', '<?php echo $count; ?>');"><img title="Remove" src="<?php echo APP_PATH_IMAGES ?>cross.png" class="WLTableElement"></a>
			</td>
		</tr>
	<?php } ?>
	<?php if (empty($blockList)) { ?>
		<tr>
			<td class='data2 fs14' colspan='3' style="padding:20px;">
				<?php echo $lang['control_center_4779'] ?>
			</td>
		</tr>
	<?php } ?>
	</table>
<?php

if (!$isAjax) {
	print "</div>";
	include 'footer.php';
}