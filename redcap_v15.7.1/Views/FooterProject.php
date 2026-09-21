<?php
// Prevent view from being called directly

require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
System::init();

// Output the JavaScript to display all Smart Charts on the page
print Piping::outputSmartChartsJS();

// Construct footer links
$link_items = array("<a href='https://projectredcap.org/' target='_blank' style='text-decoration:underline;font-size:11px;'>The REDCap Consortium</a>",
					"<a href='https://redcap.vumc.org/consortium/cite.php' target='_blank' style='text-decoration:underline;font-size:11px;'>Citing REDCap</a>");
foreach (explode("\n", $GLOBALS['footer_links']) as $value)
{
	if (trim($value) != "") {
		if(strstr($value, ','))
		{
			list ($this_url, $this_text) = explode(",", $value, 2);
		}
		else
		{
			$this_text = $value;
			$this_url = $value;
		}
		$link_items[] = "<a href='" . htmlspecialchars(decode_filter_tags(trim($this_url)), ENT_QUOTES) . "' target='_blank' style='text-decoration:underline;'>" . htmlspecialchars(decode_filter_tags(trim($this_text)), ENT_QUOTES) . "</a>";
	}
	$link_items_html = implode(" &nbsp;|&nbsp; ", $link_items);
}

// Close main window div
?>
			<div class="clear"></div>
			<div id="south">
				<table>
					<tr>
						<td>
							<div><?php echo $link_items_html ?></div>
							<div style="margin-top: 2px;"><?php echo filter_tags(label_decode($GLOBALS['footer_text'])) ?></div>
						</td>
						<td style="text-align:right;">
							<span class="nowrap"><a href="https://projectredcap.org" style="color:#888;" target="_blank">REDCap <?php echo REDCAP_VERSION ?></a></span>
                            <span class="mx-1">-</span>
							<span class="nowrap">&copy; <?php echo date("Y") ?> Vanderbilt University</span>
                            <span class="mx-1">-</span>
                            <span class="nowrap"><a href="javascript:;" style="color:#888;" onclick="getCookieUsagePolicy('<?=RCView::tt_js('global_304')?>');"><?=RCView::tt('global_304','')?></a></span>
						</td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</div>
</body>
</html>