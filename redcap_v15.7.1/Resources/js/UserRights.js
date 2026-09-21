'use strict';

$(function(){
    // Initialize page's JavaScript
    enablePageJS();
});

var DAG_Switcher_User_Rights = (function(window, document, $, JSON, undefined) {
    var allDagNames;

    var makePopovers = function(userDags, dagNames) {
        allDagNames = dagNames;
        // get the dag name links for each user
        $('div.dagNameLinkDiv').each(function(){
            var dagLink = $(this).children('a:first');
            var gid = (dagLink.attr('gid')==='') ? 0 : dagLink.attr('gid');
            var uid = dagLink.attr('uid');
            // does this user currently have any other dags enabled?
            var otherDags = [];
            if (userDags[uid]) {
                userDags[uid].forEach(function(enabledDagId) {
                    if (enabledDagId == null) enabledDagId = 0;
                    if (gid!=enabledDagId) { otherDags.push(enabledDagId);}
                });
                if (otherDags.length>0) { appendDagInfo(dagLink, uid, otherDags); }
            }
        });
    };

    function appendDagInfo(appendAfter, user, dagIdList) {
        var dagNames = [];
        dagIdList.forEach(function(dagId) {
            dagNames.push(allDagNames[dagId]);
        });
        dagNames.sort()
        var content = '<div style=\'font-size:75%;padding:5px;\'>User <span class=\'text-primary\'>'+user+'</span> may switch to DAGs:<ul style=\'padding-left:10px;\'>';
        dagNames.forEach(function(el) {
            content += '<li><span class=\'text-info\'>'+el+'</span></li>';
        });
        content += '</ul>';
        appendAfter.after('&nbsp;<a href="#" data-toggle="popover" data-content="'+escapeHtml(content)+'" style="font-size:75%;color:gray;">(+'+dagIdList.length+')</a>');
    };

    var activatePopovers = function() {
        $('[data-toggle="popover"]').popover({
            title: '<i class="fas fa-cube me-1"></i>DAG Switcher',
            html: true,
            trigger: 'hover',
            container: 'body',
            placement: 'right'
        });
    };

    return {
        makePopovers: function (userDags, dagNames) {
            makePopovers(userDags, dagNames);
        },
        activatePopovers: activatePopovers
    };
})(window, document, jQuery, JSON);

// Save user form via ajax
function saveUserFormAjax() {
    // Display progress bar
    showProgress(1);
    if ($('#editUserPopup').hasClass('ui-dialog-content')) $('#editUserPopup').dialog('destroy');
    // Serialize form inputs into a JSON object to send via Ajax
    var form_vars = $('form#user_rights_form').serializeObject();
    $.post(app_path_webroot+'UserRights/edit_user.php?pid='+pid, form_vars, function(data){
        showProgress(0,0);
        $('#user_rights_roles_table_parent').html(data);
        simpleDialogAlt($('#user_rights_roles_table_parent div.userSaveMsg'),1.7);
        enablePageJS();
        // If we just copied a role, then open it right afterward to allow editing.
        if ($('#copy_role_success').length) {
            setTimeout(function(){
                openAddUserPopup('',$('#copy_role_success').val());
            },1500);
        }
    });
}


// Assign user to DAG (via ajax)
function assignUserDag() {
    var this_group_id = $('#userClickDagSelect').val();
    $('#userClickDagSelect').prop('disabled',true);
    $('#tooltipDagBtn').button('disable');
    $('#tooltipDagCancel').hide();
    $('#tooltipDagProgress').show();
    $.post(app_path_webroot+'index.php?route=DataAccessGroupsController:ajax&pid='+pid+'&action=add_user&return_user_rights_table=1',{ user: $('#tooltipDagHiddenUsername').val(), group_id: this_group_id },function(data){
        $('#user_rights_roles_table_parent').html(data);
        $('.dagMsg').addClass('userSaveMsg');
        simpleDialogAlt($('#user_rights_roles_table_parent div.userSaveMsg'),1.7);
        setTimeout(function(){
            $('#userClickDagName').hide();
            $('#userClickDagSelect').prop('disabled',false);
            $('#tooltipDagBtn').button('enable');
            $('#tooltipDagCancel').show();
            $('#tooltipDagProgress').hide();
        },400);
        enablePageJS();
    });
}

//check if user rights are on for site _admin
function checkIfuserRights(username, role_id, callback){
    $.post(app_path_webroot+'UserRights/check_user_rights.php?pid='+pid,
        { 'username': username, 'role_id': role_id },
        function(data){
            if (data == ''){
                alert(woops); return;
            }else{
                callback(data);
            }
        });
}

// Open "add user/role" dialog
var add_user_dialog_btns;
function openAddUserPopup(username,role_id) {
    // Set vars
    if (role_id == null) role_id = '';
    // Ajax request
    $.post(app_path_webroot+'UserRights/edit_user.php?pid='+pid, { username: username, role_id: role_id }, function(data){
        if (data=='') { alert(woops); return; }
        // Add content to div
        $('#editUserPopup').html(data);
        // Enable expiration datepicker
        $('#expiration').datepicker({yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery});
        // If select "edit response" or "delete" checkbox, then set form-level rights radio button to View & Edit
        $('table#form_rights input[type="checkbox"]').on('click', function() {
            if ($(this).prop('checked')) {
                const form = $(this).attr('id').split('-')[2];
                // Deselect all, then select View & Edit
                $('table#form_rights input[name="form-'+form+'"][value="no-access"]').prop('checked',false);
                $('table#form_rights input[name="form-'+form+'"][value="read-only"]').prop('checked',false);
                $('table#form_rights input[name="form-'+form+'"][value="view-edit"]').prop('checked',true);
            }
        });
        // Prevent jumpiness
        const orig_focusTabbable = $.ui.dialog.prototype._focusTabbable;
        $.ui.dialog.prototype._focusTabbable = function() {};
        const scrollY = window.scrollY || document.documentElement.scrollTop;
        // Set dialog buttons
        eval($('#editUserPopup div#submit-buttons').html());
        // Set dialog title
        if ($('#editUserPopup #dialog_title').length) {
            var title = $('#editUserPopup #dialog_title').html();
            // Open dialog
            $('#editUserPopup').dialog({ bgiframe: true, modal: true, width: 1250,
                open: function(){
                    // Put bold on the Save button and set focus on it
                    $('.ui-dialog-buttonpane').find('button:last').css({'font-weight':'bold','color':'#222'}).focus();
                    // Stylize the delete and copy buttons (if displayed)
                    if ($('.ui-dialog-buttonpane button').length > 2) {
                        if ($('.ui-dialog-buttonpane button').length == 3) {
                            // Stylize the delete button
                            $('.ui-dialog-buttonpane').find('button:eq(0)').css({'color':'#C00000','font-size':'11px','margin':'9px 0 0 40px'});
                        } else {
                            // Stylize the delete button AND copy button
                            $('.ui-dialog-buttonpane').find('button:eq(0)').css({'color':'#C00000','font-size':'11px','margin':'9px 0 0 5px'});
                            $('.ui-dialog-buttonpane').find('button:eq(1)').css({'color':'#000066','font-size':'11px','margin':'9px 0 0 40px'});
                        }
                    }
                    // Fit to screen
                    fitDialog(this);
                    // Restore scroll position
                    window.scrollTo(0, scrollY);
                },
                title: title,
                buttons: add_user_dialog_btns, 
                close: function() {
                    $('#editUserPopup').html('');
                    $.ui.dialog.prototype._focusTabbable = orig_focusTabbable;
                }
            });
            $('[data-toggle="popover"]').hover(function(e) {
                // Show popup
                var p = new bootstrap.Popover(e.target, {
                    html: true,
                    content: $(this).data('content'),
                    placement: "left"
                });
                p.show();
            }, function() {
                // Hide popup
                bootstrap.Popover.getOrCreateInstance(this).dispose();
            });
        } else {
            // Error
            simpleDialog(data,'Alert');
        }
    });
}

// Set new user expiration
function setExpiration() {
    // Ajax request save
    $('#tooltipExpirationBtn').button('disable');
    $('#tooltipExpiration').prop('disabled',true);
    $('#tooltipExpirationCancel').hide();
    $('#tooltipExpirationProgress').show();
    $.post(app_path_webroot+'UserRights/set_user_expiration.php?pid='+pid, { username: $('#tooltipExpirationHiddenUsername').val(), expiration: $('#tooltipExpiration').val()},function(data){
        if (data == '0') {
            alert(woops);
        } else {
            $('#user_rights_roles_table_parent').html(data);
            setTimeout(function(){
                $('#tooltipExpiration').prop('disabled',false);
                $('#tooltipExpirationBtn').button('enable');
                $('#tooltipExpirationCancel').show();
                $('#tooltipExpirationProgress').hide();
                $('#userClickExpiration').hide();
            },400);
            enablePageJS();
        }
    });
}

// Check if a user account exists (is in user_information table)
function userAccountExists(username) {
    $.post(app_path_webroot+'UserRights/user_account_exists.php?pid='+pid, { username: username },function(data){
        // Only show "email user" checkbox if assigning new user to role
        if (data == '1') {
            $('#notify_email_role_option').show();
            $('#notify_email_role').prop('checked', true);
        } else {
            $('#notify_email_role_option').hide();
            $('#notify_email_role').prop('checked', false);
        }
    });
}

// Assign user to role (via ajax)
function assignUserRole(username,role_id) {
    showProgress(1);
    checkIfuserRights(username, role_id, function(data){
        if(data == 1){
            // Ajax request
            $.post(app_path_webroot+'UserRights/assign_user.php?pid='+pid, { username: username, role_id: role_id, notify_email_role: ($('#notify_email_role').prop('checked') ? 1 : 0), group_id: $('#user_dag').val() }, function(data){
                if (data == '') { alert(woops); return; }
                $('#user_rights_roles_table_parent').html(data);
                showProgress(0,0);
                simpleDialogAlt($('#user_rights_roles_table_parent div.userSaveMsg'),1.7);
                enablePageJS();
                setTimeout(function(){
                    if (role_id == '0') {
                        simpleDialog(lang.rights_215, lang.global_03+lang.colon+' '+lang.rights_214);
                    }
                },3200);
            });
        }else{
            //show notifications window
            showProgress(0,0);
            setTimeout(function(){
                simpleDialog(lang.rights_317, lang.global_03+lang.colon+' '+lang.rights_316);
            },500);
        }
    });
}
// Navigates to the User Information page in Control Center (admin only)
function navigateToUserInfo(username) {
    const url = new URL(app_path_webroot_full+'redcap_v'+redcap_version+'/ControlCenter/view_users.php');
    url.searchParams.set('username', username);
    window.open(url.href, '_blank');
}
// Remove a user from the project
function removeUserFromProject(username, doIt = false) {
    // Ask for confirmation
    if (!doIt) {
        $.get(app_path_webroot+'UserRights/edit_user.php?pid='+pid+'&get-remove-user-text='+username, function(body){
            simpleDialog(body, lang.rights_191 + lang.questionmark, null, 550, null, lang.global_53, () => removeUserFromProject(username, true), lang.rights_191);
        });
    }
    // Delete the user
    else {
        showProgress(1);
        const data = {
            'submit-action': 'delete_user',
            'user': username
        }
        $.post(app_path_webroot+'UserRights/edit_user.php?pid='+pid, data, function(data){
            showProgress(0,0);
            $('#user_rights_roles_table_parent').html(data);
            simpleDialogAlt($('#user_rights_roles_table_parent div.userSaveMsg'), 1.7);
            enablePageJS();
        });
    }
}

function fixUserTableHeight()
{
    // Auto-height fix for username, expiration, and DAG name divs to maintain vertical alignment across several columns
    var this_eq = 0;
    var hasDags = ($('table#table-user_rights_roles_table .dagNameLinkDiv').length);
    $('table#table-user_rights_roles_table .userNameLinkDiv').each(function(){
        // Get corresponding div elements for other columns
        if (hasDags) var dag_ob = $('table#table-user_rights_roles_table .dagNameLinkDiv').eq(this_eq);
        var exp_ob = $('table#table-user_rights_roles_table .expireLinkDiv').eq(this_eq);
        // Get height of this div and corresponding divs for this user
        var user_h = roundup($(this).height());
        var dag_h  = (hasDags ? roundup(dag_ob.height()) : 0);
        var exp_h  = roundup(exp_ob.height());
        // Get max
        var this_max = max(user_h, dag_h, exp_h);
        // Apply max height to all columns
        $(this).height(this_max);
        exp_ob.height(this_max);
        if (hasDags) dag_ob.height(this_max);
        // Increment eq counter
        this_eq++;
    });
}

// Initialize jQuery triggers on page
function enablePageJS() {

    initWidgets();

    // Adjust the table height
    fixUserTableHeight();
    setTimeout("fixUserTableHeight();fixUserTableHeight();",100);

    // Hide the user assignment drop-down if off-click it
    $(window).click(function(event){
        var parentId = $(event.target).parent().attr('id');
        // If user is clicking on checkbox inside "Assign to a role" menu, then do not close the menu
        if ((event.target.nodeName.toLowerCase() == 'input' && event.target.id == 'notify_email_role')
            || (event.target.nodeName.toLowerCase() == 'select' && event.target.id == 'user_dag')
            || (event.target.nodeName.toLowerCase() == 'option' && parentId == 'user_dag')
            || (event.target.nodeName.toLowerCase() == 'select' && event.target.id == 'user_role')
            || (event.target.nodeName.toLowerCase() == 'option' && parentId == 'user_role')
            || (event.target.nodeName.toLowerCase() == 'button' && event.target.innerText == 'Close')) {
            return;
        }
        // Hide the menus
        $('#assignUserDropdownDiv').hide();
        $('#userClickTooltip').hide();
    });

    // If user clicks user's DAG name link
    $('.dagNameLinkDiv a').click(function(event){
        hideOtherContainers();
        // Prevent $(window).click() from hiding this
        try {
            event.stopPropagation();
        } catch(err) {
            window.event.cancelBubble=true;
        }
        // Get username and group_id of user just clicked
        var this_username = $(this).attr('uid');
        var this_group_id = $(this).attr('gid');
        // If already open for this user, then close it
        if ($('#userClickDagName').css('display') != 'none' && this_username == $('#tooltipDagHiddenUsername').val()) {
            $('#userClickDagName').hide();
            return;
        }
        // Place username in hidden input inside tooltip to keep context of who we're editing
        $('#tooltipDagHiddenUsername').val( this_username );
        // Set tooltip position and display it
        $('#userClickDagName').show().position({
            my: "left center",
            at: "right center",
            of: this
        });
        // Enable expiration datepicker and set expire value
        $('#userClickDagSelect').val(this_group_id);

    });

    // If user clicks user's expiration date link
    $('.userRightsExpire, .userRightsExpireN, .userRightsExpired').click(function(event){
        // Prevent $(window).click() from hiding this
        try {
            event.stopPropagation();
        } catch(err) {
            window.event.cancelBubble=true;
        }
        // Get username and expiration of user just clicked
        var this_username = $(this).attr('userid');
        var this_expiration = $(this).attr('expire');
        // If already open for this user, then close it
        if ($('#userClickExpiration').css('display') != 'none' && this_username == $('#tooltipExpirationHiddenUsername').val()) {
            $('#userClickExpiration').hide();
            return;
        }
        // Place username in hidden input inside tooltip to keep context of who we're editing
        $('#tooltipExpirationHiddenUsername').val( this_username );
        // Set tooltip position and display it
        $('#userClickExpiration').show().position({
            my: "left center",
            at: "right center",
            of: this
        });
        // Enable expiration datepicker and set expire value
        $('#tooltipExpiration').datepicker({yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery});
        $('#tooltipExpiration').val(this_expiration);
    });

    // If user clicks to create NEW ROLE
    $('#createRoleBtn').click(function(){
        hideOtherContainers();
        // Validate role name
        var this_user = $('#new_rolename').trigger('focus').val();
        if (this_user.length == 0) {
            simpleDialog(lang.rights_161,null,null,null,"$('#new_rolename').trigger('focus')");
            return false;
        }
        // Open dialog to add new user with custom rights
        openAddUserPopup($('#new_rolename').val(),0);
    });

    // If user selects to ADD new user
    $('#addUserBtn').click(function(){
        // Validate username
        var this_user = $('#new_username').trigger('focus').val();
        if (this_user.length == 0) {
            simpleDialog(lang.rights_163,null,null,null,"$('#new_username').trigger('focus')");
            return false;
        }
        if (!chk_username(document.getElementById('new_username'),true)) {
            simpleDialog(lang.rights_443,null,null,null,"$('#new_username').trigger('focus')");
            return;
        }
        // Open dialog to add new user with custom rights
        openAddUserPopup($('#new_username').val());
    });

    // Auto-suggest for adding new users
    $('#new_username').autocomplete({
        source: app_path_webroot+"UserRights/search_user.php?ignoreExistingUsers=1&pid="+pid,
        minLength: 2,
        delay: 150,
        select: function( event, ui ) {
            $(this).val(ui.item.value);
            return false;
        }
    })
        .data('ui-autocomplete')._renderItem = function( ul, item ) {
        return $("<li></li>")
            .data("item", item)
            .append("<a>"+item.label+"</a>")
            .appendTo(ul);
    };
    $('#new_username_assign').autocomplete({
        source: app_path_webroot+"UserRights/search_user.php?ignoreExistingUsers=1&pid="+pid,
        minLength: 2,
        delay: 150,
        select: function( event, ui ) {
            $(this).val(ui.item.value);
            return false;
        }
    })
        .data('ui-autocomplete')._renderItem = function( ul, item ) {
        return $("<li></li>")
            .data("item", item)
            .append("<a>"+item.label+"</a>")
            .appendTo(ul);
    };

    // If user clicks on icon to edit user/role, open popup
    $("[id^=rightsTableUserLinkId_]").click(function() {
        var idUsername = $(this).attr('id').substring("rightsTableUserLinkId_".length);
        openAddUserPopup('',idUsername);
    });

    // Tooltip to appear when click username in a role in table
    $('.userLinkInTable').click(function(event) {
        hideOtherContainers();
        // Prevent $(window).click() from hiding this
        try {
            event.stopPropagation();
        } catch(err) {
            window.event.cancelBubble=true;
        }
        // Get username of user just clicked
        var this_username = $(this).attr('userid');
        // If already open for this user, then close it
        if ($('#userClickTooltip').css('display') != 'none' && this_username == $('#tooltipHiddenUsername').val()) {
            $('#userClickTooltip').hide();
            return;
        }
        // Place username in hidden input inside tooltip to keep context of who we're editing
        $('#tooltipHiddenUsername').val( this_username );
        // Hide buttons based upon if user is in role or not
        if ($(this).attr('inrole') == '1') {
            // User is in a role
            $('#tooltipBtnSetCustom').hide();
            $('#tooltipBtnRemoveRole').show();
            $('#tooltipBtnAssignRole').hide();
            $('#tooltipBtnReassignRole').show();
        } else {
            // User is NOT in a role
            $('#tooltipBtnSetCustom').show();
            $('#tooltipBtnRemoveRole').hide();
            $('#tooltipBtnAssignRole').show();
            $('#tooltipBtnReassignRole').hide();
        }
        // Set tooltip position and display it
        $('#userClickTooltip').show().position({
            my: "left center",
            at: "right center",
            of: this
        });
    });

    // If user selects to ASSIGN new user
    $('#assignUserBtn, #assignUserBtn2, #assignUserBtn3').click(function(event){
        // Prevent $(window).click() from hiding this
        try {
            event.stopPropagation();
        } catch(err) {
            window.event.cancelBubble=true;
        }
        // Only show "email user" checkbox if assigning new user to role
        $('#notify_email_role_option').hide();
        $('#notify_email_role').prop('checked', false);
        $('#dag_option').hide();
        $('#user_dag').val('');
        $('#user_role').val('');
        if ($(event.target).parents('button:first').attr('id') == 'assignUserBtn' || $(event.target).attr('id') == 'assignUserBtn') {
            hideOtherContainers();
            userAccountExists($('#new_username_assign').val());
            setUserDagRoleSelected($('#new_username_assign').val());
        } else {
            setUserDagRoleSelected($('#tooltipHiddenUsername').val());
        }
        // If no roles have been created yet, give message to create some
        if ($('#user_role').length == 0) {
            simpleDialog(lang.rights_186, lang.global_03);
            return;
        }
        // Set drop-down div object
        var ddDiv = $('#assignUserDropdownDiv');
        // If drop-down is already visible, then hide it and stop here
        if (ddDiv.css('display') != 'none') {
            ddDiv.hide();
            return;
        }
        // Set width
        if (ddDiv.css('display') != 'none') {
            var ebtnw = $(this).width();
            var eddw  = ddDiv.width();
            if (eddw < ebtnw) ddDiv.width( ebtnw );
        }
        // Set position
        var btnPos = $(this).offset();
        ddDiv.show().offset({ left: btnPos.left, top: (btnPos.top+$(this).outerHeight()) });
    });
    // Add click event of submit button
    $('#assignDagRoleBtn').click(function(){
        // Skip if has ignore attribute
        if ($(this).attr('ignore') != null) return false;
        // Check if we're adding a new user or re-assigning an existing one
        if ($('#userClickTooltip').css('display') == 'none') {
            // Validate username in Assign New User text box
            var this_user = $('#new_username_assign').trigger('focus').val();
            if (this_user.length == 0) {
                simpleDialog(lang.rights_163,null,null,null,"$('#new_username_assign').trigger('focus')");
                return false;
            }
            if (!chk_username(document.getElementById('new_username_assign'),true)) {
                simpleDialog(lang.rights_443,null,null,null,"$('#new_username_assign').trigger('focus')");
                return;
            }
        } else {
            // Obtain username from hidden input inside tooltip
            var this_user = $('#tooltipHiddenUsername').val();
        }
        // Obtain role_id
        var this_roleid = $('#user_role').val();

        if (this_roleid == '') {
            simpleDialog(lang.rights_400);
            return false;
        }
        // Assign user to role
        assignUserRole(this_user, this_roleid);
    });

    // If click header of user list table, then rerun JS done when page loaded
    if ($('#user_rights_roles_table .hDiv table th:first').attr('onclick').indexOf('enablePageJS();') < 0) {
        $('#user_rights_roles_table .hDiv table th').each(function(){
            var onclick = $(this).attr('onclick');
            $(this).attr('onclick', onclick+'enablePageJS();');
        });
    }

    $('#hideSusUsersBtn').click(function(){
        $('#hideSusUsersBtn').hide();
        $('#showSusUsersBtn').show();
        $('.userNameLinkDiv[data-suspended-user="1"]').css('height','auto').closest('tr').hide();
        $('.userNameLinkDiv[data-role-suspended-user="1"]').hide().css('height','auto');
        hideSusUsersRows();
        restripeUserTableRows();
        $.post(app_path_webroot+"index.php?route=UserRightsController:showHideSuspendedUsers&pid="+pid, { uistate: 'hide' });
    });

    $('#showSusUsersBtn').click(function(){
        $('#showSusUsersBtn').hide();
        $('#hideSusUsersBtn').show();
        $('.expireLinkDiv').show().css('height','auto');
        $('.dagNameLinkDiv').show().css('height','auto');
        $('.userNameLinkDiv[data-suspended-user="1"]').css('height','auto').closest('tr').show();
        $('.userNameLinkDiv[data-role-suspended-user="1"]').show().css('height','auto');
        restripeUserTableRows();
        $.post(app_path_webroot+"index.php?route=UserRightsController:showHideSuspendedUsers&pid="+pid, { uistate: 'show' });
    });
}

function hideSusUsersRows() {
    Array.from(document.querySelectorAll('[data-suspended-user="1"]')).forEach(function(item) {
        item.closest('tr').style.display = 'none';
    });
    Array.from(document.querySelectorAll('[data-role-suspended-user="1"]')).forEach(function(item) {
        item.style.display = 'none';
        var thisUser = item.querySelector('.userLinkInTable').getAttribute('userid');
        if (document.querySelector('.userRightsExpireN[userid=\"'+thisUser+'\"]') != null) {
            document.querySelector('.userRightsExpireN[userid=\"' + thisUser + '\"]').closest('.expireLinkDiv').style.display = 'none';
        }
        if (document.querySelector('.userRightsExpired[userid=\"'+thisUser+'\"]') != null) {
            document.querySelector('.userRightsExpired[userid=\"'+thisUser+'\"]').closest('.expireLinkDiv').style.display = 'none';
        }
        if (document.querySelector('.dagNameLinkDiv>a[uid=\"'+thisUser+'\"]') != null) {
            document.querySelector('.dagNameLinkDiv>a[uid=\"'+thisUser+'\"]').closest('.dagNameLinkDiv').style.display = 'none';
        }
    });
}

// Restripe the rows of the user rights table
function restripeUserTableRows() {
    $(function(){
        var i = 1;
        $("table#table-user_rights_roles_table tr:visible").each(function() {
            // Restripe table
            if (i++ % 2 == 0) {
                $(this).addClass('erow');
            } else {
                $(this).removeClass('erow');
            }
        });
    });
}

// Set dag selected for user
function setUserDagRoleSelected(username) {
    if (username != '' && username != lang.rights_421) {
        $.post(app_path_webroot + 'UserRights/get_user_dag_role.php?pid=' + pid, {username: username}, function (data) {
            var json_data = jQuery.parseJSON(data);
            if (json_data.length < 1) {
                alert(woops);
                return false;
            }
            // Show DAG selection if adding new user
            if (json_data.user_exists == false) {
                $('#dag_option').show();
            } else {
                $('#dag_option').hide();
            }
            // show "Assign to" select with dag selected if user has dag assigned
            $('#user_dag').val(json_data.group_id);
            // show "Role" select with role selected if user has role assigned
            $('#user_role').val(json_data.role_id);
        });
    }
}

// Hide other active containers so that at a time there will be only one box open on page
function hideOtherContainers() {
    $('#tooltipDagCancel').trigger('click');
}

/**
 * Set up the data viewing and export rights behaviors
 */
function setupDataViewingExportRightsBehaviors() {
	const $dlg = $('#editUserPopup');

	// Get state of "Delete Records" right
	const getDeleteRecordsState = () => $dlg.find('input[name=record_delete]').prop('checked');

	// Update DVR-Delete depending on "Delete Records"
	const updateDeleteRecordsState = function() {
		const state = getDeleteRecordsState();
    const $delchkbox = $dlg.find('input[type=checkbox][name^=form-delete-]');
    $delchkbox.each(function() {
			$(this).prop('disabled', state);
			if (state) {
				const formName = $(this).attr('name').replace('form-delete-', '');
				const canEdit = $dlg.find('input[type=radio][name=form-'+formName+'][value=view-edit]').prop('checked');
				$(this).prop('checked', canEdit);
			}
		});
    $delchkbox.css(state
        ? { cursor: 'not-allowed' }
        : { cursor: 'pointer' }
    );
		const $del = $dlg.find('[data-rc-dvr-set-all="delete"]');
		$del.css(state 
			? { cursor: 'not-allowed', 'opacity': '0.5' } 
			: { cursor: 'pointer', opacity: 1 }
		);
		// Update tooltip
		if (state) {
			$delchkbox.attr('data-bs-original-title', lang.rights_452);
			setTimeout(() => {
				$delchkbox.tooltip({ html: true });
			}, 0);
			$del.attr('data-bs-original-title', lang.rights_452);
			setTimeout(() => {
				$del.tooltip({ html: true });
			}, 0);
		}
		else {
			$del.tooltip('dispose').attr('title', null).attr('data-bs-original-title', null);
		}
	}
	updateDeleteRecordsState();

	// Watch for changes in "Delete Records" right
	$dlg.find('input[name=record_delete]').on('change', function() {
		updateDeleteRecordsState();
	});

	// Handle click on no-access, read-only to reset delete/editresp checkbox
	$dlg.find('input[type=radio][name^=form-]').on('click', function() {
		const formName = $(this).attr('name').replace('form-', '');
		const type = $(this).val();
		if (type != 'view-edit') {
			$dlg.find('input[type=checkbox][name=form-editresp-'+formName+']').prop('checked', false);
			$dlg.find('input[type=checkbox][name=form-delete-'+formName+']').prop('checked', false);
		}
		else {
			updateDeleteRecordsState();
		}
	});

	// Handle click on "Set all" column headers
	$dlg.find('[data-rc-dvr-set-all]').on('click', function(e) {
		const type = $(this).attr('data-rc-dvr-set-all');
		if (['no-access','read-only','view-edit'].includes(type)) {
			$('input[name^=form][value='+type+']').prop('checked',true).trigger('click');
			if (type != 'view-edit') {
				$('input[name^=form][type=checkbox]').prop('checked',false);
			}
		}
		else {
			if (type == 'delete' && getDeleteRecordsState()) return;
			const modifierPressed = (e.metaKey || e.ctrlKey);
			$('input[name^=form-'+type+']').prop('checked',false);
			if (!modifierPressed) {
				$('input[name^=form-'+type+']').trigger('click');
			}
		}
	});
	$dlg.find('[data-rc-der-set-all]').on('click', function() {
		const val = $(this).attr('data-rc-der-set-all');
		$('input[name^=export-form][value='+val+']').prop('checked',true);
	});
}

function showUploadDownloadCSVHelp() {
    $.get(app_path_webroot+'UserRights/import_export_users.php?action=help&pid='+pid, { }, function(data) {
        simpleDialog(data.content, data.title, 'downloadUploadRightsDialogHelp', 900, null, lang.global_53);
        fitDialog($('#downloadUploadRightsDialogHelp'));
    });
}