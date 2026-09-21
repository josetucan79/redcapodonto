<?php


// Disable authentication so this page can be used as general documentation
define("NOAUTH", true);
include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

$html = RCView::div(array('style'=>'margin-top:20px;'), $lang['survey_1555']) .
        RCView::div(array('style'=>'margin-top:20px;'), $lang['survey_1539']) .
        RCView::div(array('style'=>'margin-top:20px;'), $lang['survey_1540'] . " " . $lang['survey_1534']) .
        RCView::div(array('style'=>'margin-top:20px;'), $lang['survey_1541']) .
        RCView::div(array('style'=>'margin-top:20px;'), $lang['survey_1542']);

if ($isAjax) {
	print 	RCView::div('',
				RCView::div(array('style'=>'font-size:16px;font-weight:bold;float:left;'),
					RCView::img(array('src'=>'mosio.png', 'style'=>'width:16px;height:16px;')) .
                    $lang['dashboard_131']
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'),
					RCView::a(array('href'=>APP_PATH_WEBROOT.PAGE, 'style'=>'text-decoration:underline;'),
						$lang['survey_977']
					)
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
            $html .
			RCView::div(array('style'=>'margin-top:20px;'), ($twilio_enabled_by_super_users_only ? $lang['survey_1546'] : $lang['survey_1547']));
} else {
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
	print 	RCView::div('',
				RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:30px 0 0;'),
                    RCView::img(array('src'=>'mosio.png', 'style'=>'width:24px;height:24px;')) .
                    $lang['dashboard_131']
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'),
					RCView::img(array('src'=>'redcap-logo.png'))
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
			RCView::div(array('style'=>'margin:20px 0;font-size:13px;'),
                $html .
				RCView::div(array('style'=>'margin-top:20px;'), $lang['survey_1545'])
			);
	?><style type="text/css">#footer { display: block; }</style><?php
	$objHtmlPage->PrintFooterExt();
}