<?php


require dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Must be accessed via POST AJAX
if (!$isAjax || $_SERVER['REQUEST_METHOD'] != 'POST') exit;

// Content
$content = 	RCView::div(array('style'=>'font-weight:bold;'),
				$lang['design_600']
			) .
			RCView::div(array('style'=>'margin:10px 0;'),
				$lang['design_598']
			) .
			RCView::div(array('style'=>'margin:10px 0;color:#A00000;font-weight:bold;'),
				$lang['design_601']
			) .
			RCView::div(array('style'=>'margin:10px 0;font-size:11px;color:#777;line-height:1.2;'),
				$lang['design_905']
			) .
			RCView::div(array('style'=>'margin:10px 0 5px;'),
				$lang['design_599']
			) .
			RCView::div(array('style'=>''),
				RCView::img(array('src'=>'ontology_auto_suggest.png', 'style'=>'height:270px;border:1px solid #ccc;'))
			);
$content = RCView::div(array('style'=>'font-size:13px;'), $content);

// Return JSON
header("Content-Type: application/json");
print json_encode_rc(array('content'=>$content, 'title'=>$lang['design_583']));