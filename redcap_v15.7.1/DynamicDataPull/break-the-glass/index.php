<?php

use Vanderbilt\REDCap\Classes\BreakTheGlass\GlassBreaker;
use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;

require_once dirname(__DIR__, 2) . '/Config/init_project.php';

// If user does not have Project Setup/Design rights, do not show this page
// if (!$user_rights['design']) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");

include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$btgEnabled = GlassBreaker::isEnabled($project_id);
$canView = (
	FhirEhr::isCdisEnabledInSystem() &&
	FhirEhr::isFhirEnabledInProject($project_id)
);
if(!$btgEnabled || !$canView) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");

?>
<?= sprintf('<div class="projhdr"><i class="fas fa-hammer"></i> %s</div>', $lang['break_the_glass_settings_01']) ?>

<div style="max-width:800px;">

	<div>
		<h5><?= Language::tt('break_glass_overview_header') ?></h5>
		<p><?= Language::tt('break_glass_overview_text') ?><p>
		<p><?= Language::tt('break_glass_workflow_description') ?><p>
	</div>

	<div>
		<h5><?= Language::tt('break_glass_actions_header') ?></h5>
		<p><?= Language::tt('break_glass_actions_text_1') ?></p>
		<p><?= Language::tt('break_glass_actions_text_2') ?></p>
	</div>

	<div class="border rounded p-2">
		<div id="break-the-glass"></div>
	</div>

	<fieldset class="fields-fieldset">
		<legend>Fields</legend>
		<ul>
			<li>
				<span class="d-block fw-bold"><?= Language::tt('break_glass_field_patients') ?></span>
				<span><?= Language::tt('break_glass_field_patients_description') ?></span>
			</li>
			
			<li>
				<span class="d-block fw-bold"><?= Language::tt('break_glass_field_reason') ?></span>
				<span><?= Language::tt('break_glass_field_reason_description') ?></span>
			</li>
			
			<li>
				<span class="d-block fw-bold"><?= Language::tt('break_glass_field_explanation') ?></span>
				<span><?= Language::tt('break_glass_field_explanation_description') ?></span>
			</li>
			
			<li>
				<span class="d-block fw-bold"><?= Language::tt('break_glass_field_user') ?></span>
				<span><?= Language::tt('break_glass_field_user_description') ?></span>
			</li>
			
			<li>
				<span class="d-block fw-bold"><?= Language::tt('break_glass_field_user_type') ?></span>
				<span><?= Language::tt('break_glass_field_user_type_description') ?></span>
			</li>
			
			<li>
				<span class="d-block fw-bold"><?= Language::tt('break_glass_field_password') ?></span>
				<span><?= Language::tt('break_glass_field_password_description') ?></span>
			</li>
		</ul>
	</fieldset>
</div>

<style>
	@import url('<?=APP_PATH_JS?>vue/components/dist/style.css');
</style>
<script type="module">
	import { BreakTheGlass } from '<?= getJSpath('vue/components/dist/lib.es.js') ?>'
	const { eventBus } = BreakTheGlass('#break-the-glass')
	// update the btg counters in the document with the current number of patients
	const btgCounters = document.querySelectorAll('[data-btg-counter]')
	eventBus.addEventListener('patients-loaded', (e) => {
		const { patients = [] } = e.detail
		btgCounters.forEach(item => item.innerText = patients.length)
	})

</script>
<style>
	fieldset.fields-fieldset legend {
		border: revert;
		margin: revert;
		width: revert;
		float: revert;
		width: revert;
		padding: revert;
		margin-bottom: revert;
		font-size: calc(1.275rem + .3vw);
		line-height: revert;
	}
	fieldset.fields-fieldset {
		min-width: revert;
		padding: revert;
		margin: revert;
		border: revert;
	}
</style>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';