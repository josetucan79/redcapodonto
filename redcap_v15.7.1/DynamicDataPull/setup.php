<?php

use Vanderbilt\REDCap\Classes\MyCap\DynamicLink;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
?>


<?php
// Header
include APP_PATH_DOCROOT  . 'ProjectGeneral/header.php';
renderPageTitle("<i class=\"fas fa-database\"></i> " . ($DDP->isEnabledInProjectFhir() ? $lang['ws_210'] : $lang['ws_51']) . " " . $DDP->getSourceSystemName());

$realtime_webservice_type = $Proj->project['realtime_webservice_type'] ?? '';
function getTellMeMoreLink($realtime_webservice_type) {
	$url = APP_PATH_WEBROOT . 'DynamicDataPull/info.php?';
	$links = [
		DynamicDataPull::WEBSERVICE_TYPE_FHIR => 'type=fhir',
		DynamicDataPull::WEBSERVICE_TYPE_CUSTOM => 'type=custom',
	];
	return $url . ($links[$realtime_webservice_type] ?? '');
}
?>
<div style="max-width: 1000px; font-size: .9rem;">
	<div class="d-flex flex-column gap-2 mb-2">
		<span class="d-block">
			<?php if($realtime_webservice_type===DynamicDataPull::WEBSERVICE_TYPE_FHIR) : ?>
			<?= Language::tt('ws_288') ?>
			<?php elseif($realtime_webservice_type===DynamicDataPull::WEBSERVICE_TYPE_CUSTOM) : ?>
			<?= Language::tt('ws_37') ?>
			<?php endif; ?>
		</span>
		<span class="d-block"><?= Language::tt('cdp_setup_intro') ?></span>
		<ul class="my-0">
			<li>
				<span class="fw-bold"><?= Language::tt('cdp_setup_settings_title') ?></span> –
				<?= Language::tt('cdp_setup_settings_description') ?>
			</li>
			<li>
				<span class="fw-bold"><?= Language::tt('cdp_record_identifier_title') ?></span> –
				<?= Language::tt('cdp_record_identifier_description') ?>
			</li>
			<li>
				<span class="fw-bold"><?= Language::tt('cdp_create_mappings_title') ?></span> –
				<?= Language::tt('cdp_create_mappings_description') ?>
			</li>
		</ul>
		<span class="d-block"><?= Language::tt('cdp_data_import_info') ?></span>
		<span class="d-block">
			<a href="<?= getTellMeMoreLink($realtime_webservice_type) ?>" target="_blank">
				<i class="fas fa-up-right-from-square fa-fw"></i>
				<span><?= Language::tt('global_58') ?>...</span>
			</a>
		</span>
	</div>
	<div id="cdp-mapping-container"></div>
</div>
<style>
	@import url('<?=APP_PATH_JS?>vue/components/dist/style.css');
</style>
<script type="module">
	import { CdpMapping } from '<?= getJSpath('vue/components/dist/lib.es.js') ?>'

	CdpMapping('#cdp-mapping-container')
</script>

<?php

// Render page
// print $DDP->renderSetupPage();

// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
