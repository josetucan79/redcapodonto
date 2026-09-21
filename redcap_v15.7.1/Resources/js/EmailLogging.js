$(function(){

    // Display opt-in dialog
    if ($('#optin-dialog').length)
    {
        simpleDialog(null,null, 'optin-dialog', 650, "setTimeout('window.location.reload();', 10000);", lang.global_53, function(){
            showProgress(1);
            $.post(app_path_webroot+'index.php?route=EmailLoggingController:optin&pid='+pid, { }, function (data) {
                if (data != '1') {
                    alert(woops);
                }
                window.location.reload();
            });
        }, lang.email_users_88)
    }
    // Enable search-related things
    else
    {
        $('#search-term').keypress(function (e) {
            var code = e.keyCode || e.which;
            if (code == 13) $('#search-btn').trigger('click');
        });
        $('#search-btn').click(function () {
            showProgress(1);
            $.post(app_path_webroot + 'index.php?route=EmailLoggingController:search&pid=' + pid, {
                term: $('#search-term').val(),
                record: $('#search-record').val(),
                target: $('#search-target').val(),
                beginTime: $('#beginTime').val(),
                endTime: $('#endTime').val(),
                category: $('#search-category').val()
            }, function (data) {
                $('#search-results').html(data);
                showProgress(0, 0);
            });
        });
        $('#beginTime, #endTime').datetimepicker({
            yearRange: '-100:+10',
            changeMonth: true,
            changeYear: true,
            dateFormat: user_date_format_jquery,
            hour: currentTime('h'),
            minute: currentTime('m'),
            buttonText: 'Click to select a date/time',
            showOn: 'both',
            buttonImage: app_path_images + 'date.png',
            buttonImageOnly: true,
            timeFormat: 'HH:mm',
            constrainInput: false
        });
        $('#search-term').focus();
    }
});

function viewEmailByHash(hash)
{
    initDialog('view-email-dialog', '');
    $.post(app_path_webroot+'index.php?route=EmailLoggingController:view&pid='+pid, { hash: hash }, function(data){
        $('#view-email-dialog').html(data);
        var title = $('#view-email-dialog .email-subject:first').html().trim();
        var isSMS = (title == '');
        if (isSMS) {
            // SMS
            title = '<i class="fas fa-sms"></i> SMS';
        } else {
            // Email
            title = '<i class="far fa-envelope"></i> '+$('#view-email-dialog .email-subject:first').html();
        }
        var msg = $('#view-email-dialog .email-content:first').html();
        // Display dialog
        simpleDialog(msg, title, 'view-email-dialog', (isSMS ? 600 : 800), null, lang.bottom_90, function(){
            // Dialog to re-send the email
            simpleDialog(null, lang.email_users_95, 'resend-email-dialog', 550, null, lang.global_53, function(){
                // AJAX request to resend the email
                $.post(app_path_webroot+'index.php?route=EmailLoggingController:resend&pid='+pid, { hash: hash }, function(data){
                    if (data == '1') {
                        Swal.fire({
                            html: lang.email_users_94,
                            icon: 'success'
                        });
                    } else {
                        alert(woops);
                    }
                });
            }, lang.email_users_92);
        }, lang.email_users_92);
        // Tweak the dialog contents
        $('#view-email-dialog a').css({ 'text-decoration': 'underline', 'font-size': 'inherit' });
        $('.ui-dialog-buttonpane:visible button:first').focus();
        if (isSMS) $('.ui-dialog-buttonpane:visible button:eq(1)').remove(); // Remove the "re-send email" button for SMS messages
        fitDialog($('#view-email-dialog'));
        setTimeout(function(){ fitDialog($('#view-email-dialog')); },500);
        setTimeout(function(){ fitDialog($('#view-email-dialog')); },1500);
        // Add extra button in dialog to re-send the message
        $('.ui-dialog-buttonset:visible').css({'width':'98%'});
        $('.ui-dialog-buttonset:visible button:eq(1)').css({'float':'left', 'font-size':'13px', 'color':'#C00000'}).prepend('<i class="fas fa-share"></i> ');
    });
}