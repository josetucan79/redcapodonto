<?php

include_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

?>
<p>
	<b><?php echo $lang['esignature_25'] ?></b>
	<?=RCView::tt("esignature_26").RCIcon::ESigned("text-success ms-1")?>
	<span style="color:#008000;font-weight:bold;"><?php echo $lang['global_34'] ?></span>, <?=RCView::tt("esignature_28").RCIcon::Locked("text-warning ms-1")?>
	<span style="color:#A86700;font-weight:bold;"><?php echo $lang['esignature_29'] ?></span> <?php echo $lang['esignature_30'] ?>
</p>
<p>
	<u style="font-weight:bold;color:#800000;"><?php echo $lang['esignature_31'] ?></u><br>
	<?php echo $lang['esignature_32'] ?>
</p>
<p>
	<u style="font-weight:bold;color:#800000;"><?php echo $lang['esignature_33'] ?></u><br>
	<?php echo $lang['esignature_34'] ?>
</p>