<?php

if (isset($_GET['pid'])) {
	include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
} else {
	include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
}

$content =
    RCView::p(['class'=>'mt-0', 'style'=>'max-width:100%;'],
        RCView::tt('design_1018')
    ) .
    RCView::p(['class'=>'my-4 text-center fs16 boldish text-dangerrc', 'style'=>'max-width:100%;'],
        RCView::tt('design_1022')
    ) .
    // Table
    RCView::div(array('style'=>'margin-bottom:100px;'),
        RCView::table(array('style'=>'table-layout:fixed;width:100%;border-bottom:1px solid #ccc;line-height:1.2;'),
            RCView::tr(array(),
                RCView::td(array('style'=>'width:200px;text-align:right;background-color:#e5e5e5;padding:7px;font-weight:bold;border:1px solid #bbb;border-bottom:0;position:sticky;top:0;'),
                    ""
                ) .
                RCView::td(array('style'=>'color:#000066;font-size:14px;text-align:center;background-color:#e5e5e5;padding:12px 10px;font-weight:bold;border:1px solid #bbb;border-bottom:0;border-left:0;position:sticky;top:0;'),
                    '<i class="fas fa-redo"></i> '.$lang['design_1023']
                ) .
                RCView::td(array('style'=>'color:#C00000;font-size:14px;text-align:center;background-color:#e5e5e5;padding:12px 10px;font-weight:bold;border:1px solid #bbb;border-bottom:0;border-left:0;position:sticky;top:0;'),
                    '<i class="fas fa-envelope"></i> '.$lang['design_1019']
                ) .
                RCView::td(array('style'=>'color:green;font-size:14px;text-align:center;background-color:#e5e5e5;padding:12px 10px;font-weight:bold;border:1px solid #bbb;border-bottom:0;border-left:0;position:sticky;top:0;'),
                    '<i class="fas fa-bell"></i> '.$lang['global_154']
                )
            ) .
            // Description
            RCView::tr(array(),
                RCView::td(array('style'=>'width:200px;background-color:#f9f9f9;padding:7px;border:1px solid #bbb;', 'class'=>'fs14 text-dangerrc'),
                    $lang['global_20']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1024']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1031']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1030']
                )
            ) .
            // Activation process
            RCView::tr(array(),
                RCView::td(array('style'=>'width:200px;background-color:#f9f9f9;padding:7px;border:1px solid #bbb;', 'class'=>'fs14 text-dangerrc'),
                    $lang['design_1032']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1033']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1021']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1035']
                )
            ).
            // Works with repeating instruments and/or events?
            RCView::tr(array(),
                RCView::td(array('style'=>'width:200px;background-color:#f9f9f9;padding:7px;border:1px solid #bbb;', 'class'=>'fs14 text-dangerrc'),
                    $lang['design_1034']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1027']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1028']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1028']
                )
            ) .
            // Common uses
            RCView::tr(array(),
                RCView::td(array('style'=>'width:200px;background-color:#f9f9f9;padding:7px;border:1px solid #bbb;', 'class'=>'fs14 text-dangerrc'),
                    $lang['design_1042']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1044']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1043']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1043']
                )
            ) .
            // Requires email?
            RCView::tr(array(),
                RCView::td(array('style'=>'width:200px;background-color:#f9f9f9;padding:7px;border:1px solid #bbb;', 'class'=>'fs14 text-dangerrc'),
                    $lang['design_1038']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1039']
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1040'] .
                    (($GLOBALS['twilio_enabled_global'] != '1' && $GLOBALS['mosio_enabled_global'] != '1') ? '' : " ".$lang['design_1063'])
                ) .
                RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                    $lang['design_1040'] .
                    (($GLOBALS['twilio_enabled_global'] != '1' && $GLOBALS['mosio_enabled_global'] != '1') ? '' : " ".$lang['design_1063'])
                )
            ) .
            // Twilio SMS/Phone?
            (($GLOBALS['twilio_enabled_global'] != '1' && $GLOBALS['mosio_enabled_global'] != '1') ? '' :
                RCView::tr(array(),
                    RCView::td(array('style'=>'width:200px;background-color:#f9f9f9;padding:7px;border:1px solid #bbb;', 'class'=>'fs14 text-dangerrc'),
                        $lang['design_1091']
                    ) .
                    RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                        $lang['design_1036']
                    ) .
                    RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                        $lang['design_1037']
                    ) .
                    RCView::td(array('style'=>'background-color:#f9f9f9;padding:7px;border:1px solid #bbb;'),
                        $lang['design_1041']
                    )
                )
            )
        )
    )
;


if ($isAjax) {
    // Return JSON
    header("Content-Type: application/json");
    print json_encode_rc(array('content'=>$content, 'title'=>$lang['design_1026']));
} else {
    $objHtmlPage = new HtmlPage();
    $objHtmlPage->PrintHeaderExt();
    ?><style type="text/css">
        #pagecontainer { max-width:1100px; }
    </style><?php
    print 	RCView::div(array('class'=>'clearfix'),
            RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:10px 0 0;'),
                $lang['design_1026']
            ) .
            RCView::div(array('style'=>'text-align:right;float:right;'),
                RCView::img(array('src'=>'redcap-logo.png'))
            )
        ) .
        RCView::div(array('style'=>'margin:10px 0;font-size:13px;'),
            $content
        );
    $objHtmlPage->PrintFooterExt();
}