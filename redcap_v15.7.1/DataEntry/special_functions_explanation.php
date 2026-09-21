<?php


// Disable authentication so this page can be used as general documentation
define("NOAUTH", true);
include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';


// If an AJAX request, return as JSON encoded title and content for a dialog pop-up
if ($isAjax)
{
	print json_encode_rc(array('content'=>Design::renderSpecialFunctionInstructions(),
		  'title'=>$lang['design_839']));
}

// If non and AJAX request, display as regular page with header/footer
else
{
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
	print 	RCView::div(array('style'=>'margin:15px 0 50px;'), Design::renderSpecialFunctionInstructions());
	$objHtmlPage->PrintFooterExt();
}