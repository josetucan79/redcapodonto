$(function(){
	// Copy the version text to the clipboard
	$('#version-numbers-copy').click(function(){
		var $source = $(this);
		var text = $('#version-numbers').text();
		copyTextToClipboard(text);
		// Give visual cue of copy
		$source.find('i:first').removeClass('far').removeClass('fa-copy').addClass('fas').addClass('fa-check');
		setTimeout(function(){
			$source.find('i:first').removeClass('fas').removeClass('fa-check').addClass('far').addClass('fa-copy');
		}, 600);
	});

	$('#redcap_updates_community_user').change(function(){
		redcap_updates_community_user = $(this).val();
	});
	$('#redcap_updates_community_password').change(function(){
		decrypt_pass = 0;
		redcap_updates_community_password = $(this).val();
	});

	// Place cursor in first obvious textbox for certain pages
	if (page == "ControlCenter/index.php") {
		$('#goto-project-page').focus();
	} else if (page == "ControlCenter/url_shortener.php") {
		$('#long_url').focus();
	} else if (page == "ControlCenter/view_projects.php") {
		$('#user_search').focus();
	} else if (page == "ControlCenter/view_users.php") {
		$('#user_search').focus();
	} else if (page == "ControlCenter/create_user.php") {
		$('#username').focus();
	}

	$('#add_admin_btn').click(function(){
		var userid = $('#new_admin_userid').val();
		if (!isNumeric(userid) || userid+'' == '0') {
			simpleDialog(lang.control_center_4746+' "'+$('#user_search').val()+'" '+lang.control_center_4747,null,null,null,"$('#user_search').focus();");
			return;
		}
		// Gather checked attributes
		var checkboxes = $('#admin-rights-table tr:last input[type="checkbox"]:checked');
		if (!checkboxes.length) {
			simpleDialog(lang.control_center_4748);
			return;
		}
		var attrs = new Array();
		var i = 0;
		checkboxes.each(function(){
			var id_attr = $(this).prop('id').split('-');
			attrs[i++] = id_attr[1];
		});
		showProgress(1);
		// Save privileges for user
		$.post(app_path_webroot+'index.php?route=ControlCenterController:saveNewAdminPriv', { userid: userid, attrs: attrs.join(',') }, function(data){
			showProgress(0,0);
			if (data == '0') {
				alert(woops);
				$('#user_search').focus();
			} else {
				$('#new_admin_userid').val(data);
				Swal.fire({
					title: lang.rights_104 + ' "' + $('#user_search').val() + '" ' + lang.control_center_4749,
					html: lang.create_project_97,
					icon: 'success'
				}).then(function(){
					showProgress(1);
					window.location.reload();
				});
			}
		});
	});

	$('#admin-rights-table input[type="checkbox"]').change(function(){
		var cb = $(this);
		var parentTd = cb.parent('td');
		var spinner = parentTd.find('img');
		var hiddenSpan = parentTd.find('span.hidden');

		var id_attr = $(this).prop('id').split('-');
		var userid = id_attr[0];
		var attr = id_attr[1];

		if (userid == "0") return;

		cb.hide();
		spinner.show();

		var colour = '#ff3300'; // redish
		var user = cb.data('user');
		var dag = cb.data('dag');
		var enabled = cb.is(':checked');

		$.ajax({
			method: 'POST',
			url: app_path_webroot+'index.php?route=ControlCenterController:saveAdminPriv',
			data: { userid: userid, attr: attr, value: ($(this).prop('checked') ? '1' : '0') },
			dataType: 'json'
		})
			.done(function(data) {
				if (data=='1') {
					colour = '#66ff99'; // greenish
				} else {
					enabled = !enabled; // changing the selection failed so change it back to what it waa
				}
				// If ALL checkboxes have been unchecked for a user, then let the user know
				if (!$('#admin-rights-table tr#user'+userid+' input[type="checkbox"]:checked').length) {
					// Remove table row for this user and display notification
					setTimeout(function(){
						// Refresh table values
						rcDataTable
							.rows()
							.invalidate()
							.draw();
						$('#admin-rights-table tr#user'+userid).remove();
					},500);
					simpleDialog(lang.control_center_4752+' - <b>'+$('tr#user'+userid+' td:first').text()+'</b>'+lang.control_center_4753, lang.global_03);
				}
			})
			.fail(function(data) {
				enabled = !enabled; // changing the selection failed so change it back to what it waa
			})
			.always(function(data) {
				cb.prop('checked', enabled);
				parentTd.effect('highlight', {color:colour}, 3000);
				spinner.hide();
				hiddenSpan.html(enabled ? '1' : '0');
				cb.show();
				// Refresh table values
				rcDataTable
					.rows()
					.invalidate()
					.draw();
			});
	});

	$('#user_search').blur(function(){
		getUserIdByUsername($(this).val(),false);
	});

	$('#go-to-add-admin').click(function(){
		$('html, body').animate({
			scrollTop: $('#user_search').offset().top-300
		}, 500);
		setTimeout(function(){
			$('#user_search').focus();
		},500);
	});

	// Add fade mouseover for EM panel links on left menu
	$(".cc_menu_section-external_modules .opacity65").mouseenter(function() {
		$(this).removeClass('opacity65');
		if (isIE) $(this).find("img").removeClass('opacity65');
	}).mouseleave(function() {
		$(this).addClass('opacity65');
		if (isIE) $(this).find("img").addClass('opacity65');
	});
});

// For read-only pages in the Control Center, disable all form elements and add "read-only" to page header
function disableAllFormElements()
{
	$(':input[type="submit"], .password-mask-reveal').remove();
	$(':input').prop('disabled',true);
	$('#control_center_window h4:first').append("<span class='browseProjPid fs12 font-weight-normal ms-3'>"+lang.control_center_4751+"</span>");
	$('#control_center_window h4:first').after("<div class='text-danger my-3 fs13' style='clear:both;'><i class=\"fas fa-exclamation-circle\"></i> "+lang.control_center_4754+"</div>");
}

// Obtain the userid of the user and add it to hidden input
function getUserIdByUsername(username,showError)
{
	$('#add_admin_btn').prop('disabled',true);
	$('#new_admin_userid').val('');
	$.post(app_path_webroot+'index.php?route=ControlCenterController:getUserIdByUsername', { username: username }, function(userid){
		$('#add_admin_btn').prop('disabled',false);
		if (userid == '0') {
			if (showError) {
				alert(woops);
				$('#user_search').focus();
			}
		} else if ($('#user'+userid).length) {
			$('#user_search').val('');
			simpleDialog(lang.control_center_4746+' "'+username+'" '+lang.control_center_4750,null,null,null,function(){
				$('html, body').animate({
					scrollTop: $('#user'+userid).offset().top-300
				}, 500);
				setTimeout(function(){
					highlightTableRow('user'+userid, 3000);
				}, 600);
			});
		} else {
			$('#new_admin_userid').val(userid);
		}
	});
}

// Auto-suggest for admin rights privileges
function enableAdminUserSearch() {
	if ($('#user_search').length) {
		$('#user_search').autocomplete({
			source: app_path_webroot+"UserRights/search_user.php?searchEmail=1&ignoreExistingAdmins=1",
			minLength: 2,
			delay: 150,
			select: function( event, ui ) {
				var username = ui.item.value;
				$(this).val(username);
				// Obtain the userid of the user and add it to hidden input
				getUserIdByUsername(username, true);
				return false;
			}
		})
		.data('ui-autocomplete')._renderItem = function( ul, item ) {
			$('#new_admin_userid').val('');
			return $("<li></li>")
				.data("item", item)
				.append("<a>"+item.label+"</a>")
				.appendTo(ul);
		};
	}
}

// Toggle displaying a secret key (for security)
function showSecret(selector) {
	$(selector).clone().attr('type','text').attr('size','60').insertAfter(selector).prev().remove();
}

// [JS] Expand Abbreviated IPv6 Addresses
// by Christopher Miller
// http://forrst.com/posts/JS_Expand_Abbreviated_IPv6_Addresses-1OR
// Modified to work with embedded IPv4 addresses
function expandIPv6Address(address)
{
	var fullAddress = "";
	var expandedAddress = "";
	var validGroupCount = 8;
	var validGroupSize = 4;

	var ipv4 = "";
	var extractIpv4 = /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})/;
	var validateIpv4 = /((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})/;

	// look for embedded ipv4
	if(validateIpv4.test(address))
	{
		var groups = address.match(extractIpv4);
		for(var i=1; i<groups.length; i++)
		{
			ipv4 += ("00" + (parseInt(groups[i], 10).toString(16)) ).slice(-2) + ( i==2 ? ":" : "" );
		}
		address = address.replace(extractIpv4, ipv4);
	}

	if(address.indexOf("::") == -1) // All eight groups are present.
		fullAddress = address;
	else // Consecutive groups of zeroes have been collapsed with "::".
	{
		var sides = address.split("::");
		var groupsPresent = 0;
		for(var i=0; i<sides.length; i++)
		{
			groupsPresent += sides[i].split(":").length;
		}
		fullAddress += sides[0] + ":";
		for(var i=0; i<validGroupCount-groupsPresent; i++)
		{
			fullAddress += "0000:";
		}
		fullAddress += sides[1];
	}
	var groups = fullAddress.split(":");
	for(var i=0; i<validGroupCount; i++)
	{
		while(groups[i].length < validGroupSize)
		{
			groups[i] = "0" + groups[i];
		}
		expandedAddress += (i!=validGroupCount-1) ? groups[i] + ":" : groups[i];
	}
	return expandedAddress;
}

// Validate IP ranges for 2FA
// If all are valid, returns true, else returns comma-delimited list of invalid IPs.
function validateIpRanges(ranges) {
	// Remove all whitespace
	ranges = ranges.replace(/\s+/, "");
	// Replace all semi-colons with commas
	ranges = ranges.replace(/;/g, ",");
	// Replace all dashes with commas (so we can treat min/max of range as separate IPs)
	ranges = ranges.replace(/-/g, ",");
	// Now split into individual IP address components to check format via regex
	var ranges_array = ranges.split(',');
	var regex_ip4 = /^((\*|[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}(\*|[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])(\/([0-9]|[1-2][0-9]|3[0-2]))?$/;
	var regex_ip6 = /^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/g;
	var bad_ips = new Array(), thisRange, thisIPv6, match_ip4, match_ip6, ipv6parts;
	if (ranges != "") {
		var k=0;
		for (var i=0; i<ranges_array.length; i++) {
			thisRange = trim(ranges_array[i]);
			match_ip4 = (thisRange.match(regex_ip4) != null);
			match_ip6 = false;
			// try IPv6 range
			if (!match_ip4 && thisRange.indexOf("/") > 0) {
				ipv6parts = thisRange.split("/");
				thisIPv6 = expandIPv6Address(ipv6parts[0]);
				match_ip6 = (thisIPv6.match(regex_ip6) != null && isNumeric(ipv6parts[1]) && ipv6parts[1] >=1 && ipv6parts[1] <= 128);
			}
			if (!match_ip4 && !match_ip6) {
				bad_ips[k++] = thisRange;
			}
		}
	}
	// Display error msg if any IPs are invalid
	if (bad_ips.length > 0) {
		return bad_ips.join(',');
	}
	return true;
}

// Test if string is a valid domain name (i.e. domain from a URL)
function isDomainName(domain) {
    // Set regex to be used to validate the domain
    var dwRegex = /^([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,63})$/i;
    // Return boolean
    return dwRegex.test(trim(domain));
}

// Opens dialog popup for viewing all users in Control Center
function openUserHistoryList() {
	var days = $('#activity-level').val();
	var title = $('#activity-level option:selected').text();
	var search_term = '';
	var search_attr = '';
	if ($('#user_list_search').length) {
		search_term = trim($('#user_list_search').val());
		search_attr = $('#user_list_search_attr').val();
	}
	// Set progress spinner and reset div
	$('#userListTable').css('visibility','hidden');
	$('#userListProgress, #userList').show();
	$('#indv_user_info').html('');
	$('#user_search').val('').trigger('blur');
	// Ajax call
	$.get(app_path_webroot+'ControlCenter/user_list_ajax.php', { d: days, search_term: search_term, search_attr: search_attr}, function(data) {
		// Inject table		
		$('#userListTable').css('visibility','visible');
		$('#userList, #userListTable').show();
		$('#userListProgress').hide();
		$('#userListTable').html(data);
	});
}

// Download CSV list of users listed in popup
function downloadUserHistoryList() {
	var days = $('#activity-level').val();
	var search_term = '';
	var search_attr = '';
	if ($('#user_list_search').length) {
		search_term = trim($('#user_list_search').val());
		search_attr = $('#user_list_search_attr').val();
	}
	window.location.href = app_path_webroot+'ControlCenter/user_list_ajax.php?download=1&d='+days+'&search_term='+encodeURIComponent(search_term)+'&search_attr='+encodeURIComponent(search_attr);
}

// Perform one-click upgrade
var selected_version, decrypt_pass=1; 
function oneClickUpgrade(version) {
	selected_version = version;
	$('#oneClickUpgradeDialogVersion').html(version);
	simpleDialog(null,null,'oneClickUpgradeDialog',650,null,'Cancel',function(){
		if (redcap_updates_community_user == '' || redcap_updates_community_password == '') {
			simpleDialog("You must enter a valid REDCap Community username/password in order to begin the upgrade process.", "ERROR",null,500,function(){
				oneClickUpgrade(selected_version);	
			});
		} else {
			oneClickUpgradeDo(version);
		}
	},'Upgrade');
}
function oneClickUpgradeDo(version) {
	selected_version = version;
	showProgress(1);
	$('#working').css('width','auto').html('<img src="'+app_path_images+'progress_circle.gif">&nbsp; Downloading & extracting REDCap '+version+'...</div>');
	$.post(app_path_webroot+'index.php?route=ControlCenterController:oneClickUpgrade',{decrypt_pass: decrypt_pass, version: version, redcap_updates_community_user: redcap_updates_community_user, redcap_updates_community_password: redcap_updates_community_password},function(data){
		if (data == '1') {			
			$('#working').html('<img src="'+app_path_images+'progress_circle.gif">&nbsp; Executing SQL upgrade script...</div>');
			$.post(app_path_webroot+'index.php?route=ControlCenterController:executeUpgradeSQL',{version: version},function(data2){
				showProgress(0,0);
				$('#working').remove();
				if (data2 == '1') {					
					simpleDialog("<div class='green'>The upgrade to REDCap "+selected_version+" has completed successfully. You will now be redirected to the Configuration Check page.</div>", "<img src='"+app_path_images+"tick.png'> <span style='color:green;'>Upgrade Complete!</span>",null,500,function(){
						window.location.href = app_path_webroot_full+'redcap_v'+selected_version+'/ControlCenter/check.php?upgradeinstall=1';
					},"Go to Configuration Check");
				} else if (data2 == '2') {
					simpleDialog("NOTICE: The upgrade to REDCap "+selected_version+" cannot be completed automatically, so you will need to finish the upgrade by navigating to the "+
								 "<a style='text-decoration:underline;' href='"+app_path_webroot_full +"upgrade.php'>REDCap Upgrade Module</a>.");
				} else if (data2 == '3') {
					// AWS: Redirect to upgrade page
					simpleDialog("<div class='green'>REDCap "+selected_version+" has downloaded successfully, but you must still <b>wait while the new "+
						"download is being deployed to all nodes/servers. This may take several seconds or several minutes.</b> Please wait on this page until this is done, after which the rest of the upgrade "+
						"process will continue automatically.</div>", 
						"<img src='"+app_path_images+"tick.png'> <span style='color:green;'>Download Complete - PLEASE WAIT - DO NOT LEAVE THIS PAGE</span>",'aws-upgrade-dialog-wait',600);
					// Hide the close buttons to encourage user to wait on the page for a bit
					$('#aws-upgrade-dialog-wait').dialog({ closeOnEscape: false });
					modifyURL(app_path_webroot_full+'redcap_v'+selected_version+'/upgrade.php?auto=1');
					$('.ui-dialog .ui-dialog-buttonpane button, .ui-dialog .ui-dialog-titlebar-close').hide();
					// Keep calling the upgrade page via AJAX for new version until it no longer returns a 404 error
					check404onUpgradePage(selected_version);
				} else {
					simpleDialog("ERROR: For unknown reasons, the upgrade could not be completed.");
				}
			});
		} else {
			showProgress(0,0);
			$('#working').remove();
			simpleDialog(data,"ERROR",null,500,function(){
				oneClickUpgrade(selected_version);	
			});
		}
	});
}

function check404onUpgradePage(version) {
	var upgradeUrl = app_path_webroot_full+'redcap_v'+version+'/upgrade.php';
	$.ajax({ 
		cache: false,
		url: upgradeUrl,
		data: {  },
		success: function (data) {
			window.location.href = upgradeUrl+'?auto=1';
		},
		error:function (xhr, ajaxOptions, thrownError){
			if(xhr.status==404) {				
				setTimeout("check404onUpgradePage('"+version+"')",3000);
			}
		}
	});
}

function autoFixTables() {
	showProgress(1);
	$.post(app_path_webroot+'index.php?route=ControlCenterController:autoFixTables',{ },function(data){	
		if (data != '1') {
			showProgress(0,0);
			alert(woops);
			return;
		}
		window.location.reload();
	});
}
function hideEasyUpgrade(hide) {
	$.post(app_path_webroot+'index.php?route=ControlCenterController:hideEasyUpgrade',{hide:hide },function(data){	
		if (data != '1') {
			alert(woops);
			return;
		}
		if (hide == '1') {
			window.location.reload();
		} else {
			$('#easy_upgrade_alert').removeClass('gray2').addClass('blue');
			$('.redcap-updates-rec').show();
		}
	});
}

// Save a new value for a config setting (super users only)
function setConfigVal(settingName,value,reloadPage) {
	$.post(app_path_webroot+'ControlCenter/set_config_val.php',{ settingName: settingName, value: value },function(data){
		if (data == '1') {
			alert("The setting has been successfully saved!");
			if (reloadPage != null && reloadPage) {
				window.location.reload();
			}
		} else {
			alert(woops);
		}
	});
}

// Show dialog of project revision history
function revHist(this_pid) {
	$.get(app_path_webroot+'ProjectSetup/project_revision_history.php?from=cc&pid='+this_pid,{},function(data){
		initDialog('revHist','<div style="height:400px;">'+data+'</div>');
		var d = $('#revHist').dialog({ bgiframe: true, title: $('#revHist #revHistPrTitle').text(), modal: true, width: 800, buttons: {
				Close: function() { $(this).dialog('close'); }
			}});
		initButtonWidgets();
		fitDialog(d);
	});
}

function viewUserSponseesList(username) {
	if (username.length < 1) return;
	$.get(app_path_webroot+'ControlCenter/user_controls_ajax.php', { user_view: 'sponsees_popup', username: username },
		function(data) {
			let sponseesList = JSON.parse(data);
			let title = '<div style=text-align: center; color: black; margin: 0 auto;">' + sponseesList['title'].replace("{0}", strip_tags(username)) + '</div>';
			let cellStyle = "border: 1px solid #555; padding: 4px 10px;";
			let html = `<div style="margin: 10px 0 5px;">`;
			html += `<table style="border-collapse: collapse; width: 100%;"><thead><tr>`;
			html += "<th style='" + cellStyle + "background-color:#eee;'>"+sponseesList['lang_username']+"</th>";
			html += "<th style='" + cellStyle + "background-color:#eee;'>"+sponseesList['lang_firstname']+"</th>";
			html += "<th style='" + cellStyle + "background-color:#eee;'>"+sponseesList['lang_lastname']+"</th>";
			html += "</tr></thead><tbody>";
			sponseesList['rows'].forEach(function(user) {
				html += '<tr>';
				html += `<td style="${cellStyle}"><a href="#" onclick="showProgress(1,1);window.location.href='${app_path_webroot}ControlCenter/view_users.php?username=${user.username}';" style="text-decoration: underline; color: #000066;">${user.username}</a></td>`;
				html += "<td style='" + cellStyle + "'>" + user.user_firstname + "</td>";
				html += "<td style='" + cellStyle + "'>" + user.user_lastname + "</td>";
				html += "</tr>";
			});
			html += '</table></tbody></div>';
			simpleDialog(html, title, 'sponsee-list', 650);
			fitDialog($('#sponsee-list'));
		}
	);
}