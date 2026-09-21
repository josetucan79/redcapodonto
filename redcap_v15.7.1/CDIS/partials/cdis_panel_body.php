<?php
use Vanderbilt\REDCap\Classes\BreakTheGlass\GlassBreaker;
use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;
use Vanderbilt\REDCap\Classes\Fhir\MappingHelper\FhirMappingHelper;
?>
<script type="module">
	import { useModal } from '<?= APP_PATH_JS ?>Composables/index.es.js.php'
	const modal = useModal();

	async function fetchContent(urlInfo) {
		const csrfToken = window.redcap_csrf_token;
		const url = new URL(app_path_webroot, window.location.origin);
		const params = new URLSearchParams();
		params.append('route', 'ViewController:fetchContent');
		params.append('url', urlInfo);
		params.append('redcap_csrf_token', csrfToken);
		url.search = params.toString();

		const response = await fetch(url);
		if (!response.ok) throw new Error(`Failed to fetch content: ${response.statusText}`);
		return await response.text();
	}

	document.querySelectorAll('[data-modal-info]').forEach(element => {
		element.style.cursor = 'help';
		element.addEventListener('click', async () => {
			const title = element.getAttribute('data-modal-title') || 'Information';
			const info = element.getAttribute('data-modal-info');
			try {
				const htmlContent = await fetchContent(info);
				await modal.show({ title, body: htmlContent, size: 'md', cancelText: null });
			} catch (e) {
				console.error('Error fetching and displaying content:', e);
			}
		});
	});
</script>

<div class="menubox">
	<div>
		<small class="text-muted fst-italic">
			<?= $fhirSystem->getEhrName() ?>
		</small>
	</div>

	<span class="d-block">
		<i class="fas fa-rocket"></i>
		<a href="<?= APP_PATH_WEBROOT . 'ehr.php?standalone_launch=1&ehr_id=' . $fhirSystem->getEhrId() ?>"><?= Language::tt('control_center_4893') ?></a>
	</span>

	<?php

 if ($fhirUser->can_use_mapping_helper): ?>
	<span class="d-block">
		<i class="fas fa-code-branch"></i>
		<a href="<?= FhirMappingHelper::getLink($project_id) ?>"><?= Language::tt('control_center_4894') ?></a>
	</span>
	<?php endif; ?>

	<span class="d-block">
		<i class="fas fa-envelope"></i>
		<span id="parcel-link"></span>
	</span>

	<?php if (FhirEhr::canAccessCdpDashboard($project_id, UI_ID)): ?>
	<span class="d-block">
		<i class="fas fa-gauge-high"></i>
		<a href="<?= DynamicDataPull::getDashboardURL($project_id) ?>"><?= Language::tt('cdp_dashboard_label_cdp') ?></a>
	</span>
	<?php endif; ?>

	<?php if (GlassBreaker::isEnabled($project_id)) :
		$totalStoredBTGPatients = count(GlassBreaker::forProjectAndUser($project_id, USERID)->getUniqueMrnList()); ?>
	<span class="d-block">
		<i class="fas fa-hammer"></i>
		<a href="<?= GlassBreaker::getFormURL($project_id) ?>"><?= Language::tt('break_the_glass_settings_01') ?></a>
		<?php if ($totalStoredBTGPatients): ?>
			<span class="badge badge-danger" data-btg-counter><?= $totalStoredBTGPatients ?></span>
		<?php endif; ?>
	</span>
	<?php endif; ?>

	<div data-modal-title="<?= Language::tt('cdis_info_ehr_access_info_title') ?>" data-modal-info="<?= APP_PATH_WEBROOT . 'CDIS/partials/ehr_access_info.php' ?>" class="access-token-wrapper mt-1" data-accessToken="<?= $accessToken ?>" >
		<span class="d-block small text-muted">
			<?= !$accessToken
				? '<i class="fas fa-times-circle text-danger"></i>'
				: $tokenStatusHtml ?>
			<span><?= Language::tt('control_center_4895') ?></span>
		</span>
	</div>

	<div data-modal-title="<?= Language::tt('cdis_info_auto_login_title') ?>" data-modal-info="<?= APP_PATH_WEBROOT . 'CDIS/partials/auto-login_info.php' ?>" class="auto-login-wrapper">
		<span class="d-block small text-muted">
			<?php if ($mappedEhrUser): ?>
				<i data-disconnectEhr="<?= $mappedEhrUser ?>" class="fas fa-check-circle text-success"></i>
				<span title="automatic log-in enabled during a launch from EHR with the user '<?= $mappedEhrUser ?>'"><?= Language::tt('control_center_4897') ?></span>
			<?php else: ?>
				<i class="fas fa-times-circle text-danger"></i>
				<span title="no EHR user is mapped to your REDCap user"><?= Language::tt('control_center_4898') ?></span>
			<?php endif; ?>
		</span>
	</div>
</div>
