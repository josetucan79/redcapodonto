<?php


// Disable authentication so this page can be used as general documentation
define("NOAUTH", true);
include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

if ($isAjax) {
	print 	RCView::p('',
				RCView::div(array('style'=>'font-size:16px;font-weight:bold;float:left;'),
					RCView::img(array('src'=>'sendgrid.png', 'style'=>'position:relative;top:-2px;')) .
					$lang['survey_1387']
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'),
					RCView::a(array('href'=>APP_PATH_WEBROOT."Surveys/sendgrid_info.php", 'style'=>'text-decoration:underline;'),
						$lang['survey_977']
					)
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
            RCView::p(array('class'=>''), $lang['survey_1388']) .
            RCView::p(array('class'=>''), $lang['survey_1385']) .
            RCView::p(array('class'=>''), $lang['survey_1390']) .
            RCView::p(array('class'=>''), $lang['survey_1386']);
} else {
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
	print 	RCView::p('',
				RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:30px 0 0;'),
					RCView::img(array('src'=>'sendgrid.png', 'style'=>'position:relative;top:-2px;')) .
					$lang['survey_1387']
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'),
					RCView::img(array('src'=>'redcap-logo.png'))
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
			RCView::p(array('class'=>''), $lang['survey_1388']) .
			RCView::p(array('class'=>''), $lang['survey_1385']) .
			RCView::p(array('class'=>''), $lang['survey_1390']) .
			RCView::p(array('class'=>''), $lang['survey_1386']);
	?><style type="text/css">#footer { display: block; }</style><?php
	$objHtmlPage->PrintFooterExt();
}