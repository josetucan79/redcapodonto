<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$body = RCView::div(array(),
    $lang['mobile_app_48'] . RCView::br() . RCView::br() .
    " - " . RCView::a(array('href'=>MobileApp::URL_IOS_APP_STORE, 'style'=>'text-decoration:underline;'),
        "iOS app on App Store"
    ) .
    RCView::br() .
    " - " . RCView::a(array('href'=>MobileApp::URL_GOOGLE_PLAY_STORE, 'style'=>'text-decoration:underline;'),
        "Android app on Google Play"
    ) .
    RCView::br() .
    " - " . RCView::a(array('href'=>MobileApp::URL_AMAZON_APP_STORE, 'style'=>'text-decoration:underline;'),
        "Amazon app for Android"
    )
);

// Set up email to be sent
$email = new Message();
$email->setFrom(Message::useDoNotReply($project_contact_email));
$email->setTo($user_email);
$email->setSubject($lang['mobile_app_47']);
$email->setBody('<html><body style="font-family:arial,helvetica;">'.$body.'</body></html>');
print ($email->send() ? $lang['survey_181'] : "0");