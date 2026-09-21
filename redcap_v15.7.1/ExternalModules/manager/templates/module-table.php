<?php namespace ExternalModules; ?>

<input type='hidden' name='version' value='<?= $version ?>'>

<div class='external-modules-title'><span class='module-name'><?=$config['name']?></span><?=' - ' . $version ?>
	<?php
	if (ExternalModules::getSystemSettingCache()[$prefix][ExternalModules::KEY_ENABLED] ?? false) {
		print "<span class='label label-warning badge badge-warning'><!--= Enabled for All Projects -->" . ExternalModules::tt("em_manage_22") . "</span>";
	}
	if (ExternalModules::definesApiActions($config)) {
		print \RCView::span([
			"class" => "label label-info badge badge-info ms-1"
		], "API&nbsp;" );
		print \RCView::span([
			"class" => "em-api-module-prefix ms-1"
		], ExternalModules::tt("em_manage_160"). " " . $prefix);
	}

	if (ExternalModules::getSystemSettingCache()[$prefix][ExternalModules::KEY_DISCOVERABLE] ?? false == true) {
		print "<span class='label label-info badge badge-info badge-info_a11y'><!--= Discoverable -->" . ExternalModules::tt("em_manage_23") . "</span>";
	}
	?>
</div>
<div class='external-modules-description'>
	<?php echo $config['description'] ?? '';?>
</div>
<div class='external-modules-byline'>
	<?php
		$pid = ExternalModules::getProjectId();
		if (ExternalModules::isAdminWithModuleInstallPrivileges() && !isset($pid)) {
			if (isset($config['authors'])) {
				$names = array();
				foreach ($config['authors'] as $author) {
					$name = $author['name'];
					$institution = empty($author['institution']) ? "" : " <span class='author-institution'>({$author['institution']})</span>";
					if ($name) {
						if ($author['email']) {
							$names[] = "<a href='mailto:".$author['email']."?subject=".rawurlencode(strip_tags($config['name'])." - ".$version)."'>".$name."</a>$institution";
						} else {
							$names[] = $name . $institution;
						}
					}
				}
				if (count($names) > 0) {
					echo "by ".implode(", ", $names);
				}
			}
		}

		$documentationUrl = ExternalModules::getDocumentationUrl($prefix);
		if(!empty($documentationUrl)){
			?><a href="<?=htmlentities($documentationUrl, ENT_QUOTES)?>" style="display: block; margin-top: 7px" target="_blank">
				<i class='fas fa-file' style="margin-right: 5px"></i>
				<!--= View Documentation -->
				<?=ExternalModules::tt("em_manage_24")?>
			</a><?php
		}
	?>
</div>