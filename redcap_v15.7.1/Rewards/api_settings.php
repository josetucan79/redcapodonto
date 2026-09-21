<?php include __DIR__.'/partials/header.php'; ?>
<?php

use Vanderbilt\REDCap\Classes\Utility\SessionDataUtils;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\AccessTokenEntity;
use Vanderbilt\REDCap\Classes\Rewards\Facades\PermissionsGateFacade as Gate;
use Vanderbilt\REDCap\Classes\Rewards\Facades\ProjectSettings;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories\AccessTokenRepository;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Config\TangoApiConfig;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities\TangoProjectSettingsVO;

// If user does not have Project Setup/Design rights, do not show this page
// if (!$user_rights['design']) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
if(Gate::denies(PermissionEntity::MANAGE_API_SETTINGS)) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
// if(!ACCESS_SYSTEM_CONFIG) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
include __DIR__.'/partials/tabs.php';

/** @var TangoProjectSettingsVO */
$settings = ProjectSettings::get($project_id);

include __DIR__.'/lib/UIHelper.php';
UIHelper::checkSettings($settings);

$currentEnvironment = $settings->environment();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	try {
		$environment = $_POST['environment'] ?? null;
		$baseURL = TangoApiConfig::getBaseUrl($environment);
		$tokenURL = TangoApiConfig::getTokenUrl($environment);

		$newClientId = $_POST[TangoProjectSettingsVO::KEY_CLIENT_ID] ?? null;
		$newClientSecret = $_POST[TangoProjectSettingsVO::KEY_CLIENT_SECRET] ?? null;
		$newGroupIdentifier = $_POST[TangoProjectSettingsVO::KEY_GROUP_IDENTIFIER] ?? null;
		$newCampaignIdentifier = $_POST[TangoProjectSettingsVO::KEY_CAMPAIGN_IDENTIFIER] ?? null;

		$previousSettings = clone $settings;
		
		$settings->setBaseUrl($baseURL);
		$settings->setTokenUrl($tokenURL);
		$settings->setClientId(htmlspecialchars($newClientId));
		$settings->setClientSecret(htmlspecialchars($newClientSecret));
		$settings->setGroupIdentifier(htmlspecialchars($newGroupIdentifier));
		$settings->setCampaignIdentifier(htmlspecialchars($newCampaignIdentifier));
		ProjectSettings::save($project_id, $settings);

		if($updated = !$previousSettings->equals($settings)) {
			$entityManager = EntityManager::get();
			/** @var AccessTokenRepository $atRepo */
			$atRepo = $entityManager->getRepository(AccessTokenEntity::class);
			$atRepo->deleteProjectTokens($project_id);
		}
		flash('alert-success', 'Settings saved!');
	} catch (\Throwable $th) {
		flash('alert-danger', $th->getMessage());
	} finally {
		redirect(previousURL());
	}
}
?>

<div style="max-width: 800px; clear: both">
	
	<p><?= Language::tt('tango_api_settings_intro') ?></p>
	<form class="reward-form" action="" method="POST">
		<input type="hidden" name="project_id" value="<?= $project_id ?>">

		<div>
			<details>
				<summary>
					<span class="form-label" for="tango-group-identifier"><?= Language::tt('tango_group_identifier_label') ?></span>
				</summary>
				<span class="field-description"><?= Language::tt('tango_group_identifier_description') ?></span>
			</details>
			<input data-sensitive autocomplete="new-password" class="form-control form-control-sm" type="password" name="<?= TangoProjectSettingsVO::KEY_GROUP_IDENTIFIER?>" id="tango-group-identifier" value="<?= $settings->getGroupIdentifier() ?>">
		</div>

		<div>
			<details>
				<summary>
					<span class="form-label" for="tango-campaign-identifier"><?= Language::tt('tango_campaign_identifier_label') ?></span>
				</summary>
				<span class="field-description"><?= Language::tt('tango_campaign_identifier_description') ?></span>
			</details>
			<input data-sensitive autocomplete="new-password" class="form-control form-control-sm" type="password" name="<?= TangoProjectSettingsVO::KEY_CAMPAIGN_IDENTIFIER?>" id="tango-campaign-identifier" value="<?= $settings->getCampaignIdentifier() ?>">
			<span class="field-detail"><?= Language::tt('tango_campaign_identifier_note') ?></span>
		</div>

		<div class="border rounded p-2">
			<strong><?= Language::tt('tango_api_credentials_title') ?></strong>
			<p><?= Language::tt('tango_api_credentials_description') ?></p>
			<div data-reward-fieldset >
				<div>
					<details>
						<summary>
							<span class="form-label" for="tango-environment"><?= Language::tt('tango_environment_label') ?></span>
						</summary>
						<span class="field-description">
						<?= Language::tt('tango_environment_description_1') ?>
						<?= Language::tt('tango_environment_description_2') ?>
						</span>
					</details>
					<select
						class="form-select form-select-sm"
						name="environment"
						id="tango-environment">
						<option value="">Use system Default </option>
						<option value="<?= TangoApiConfig::ENVIRONMENT_PRODUCTION ?>" <?= ($currentEnvironment === TangoApiConfig::ENVIRONMENT_PRODUCTION) ? 'selected' : '' ?> >Production</option>
						<option value="<?= TangoApiConfig::ENVIRONMENT_SANDBOX ?>" <?= ($currentEnvironment === TangoApiConfig::ENVIRONMENT_SANDBOX ) ? 'selected' : '' ?> >Sandbox</option>
					</select>
				</div>
		
				<div>
					<details>
						<summary>
							<span class="form-label" for="tango-client-id"><?= Language::tt('tango_client_id_label') ?></span>
						</summary>
						<span class="field-description"><?= Language::tt('tango_client_id_description') ?></span>
					</details>
					<input class="form-control form-control-sm" type="text" name="<?= TangoProjectSettingsVO::KEY_CLIENT_ID ?>" id="tango-client-id" value="<?= $settings->getClientId() ?>">
				</div>
		
				<div>
					<details>
						<summary>
							<span class="form-label" for="tango-client-secret"><?= Language::tt('tango_secret_label') ?></span>
						</summary>
						<span class="field-description">
							<?= Language::tt('tango_secret_description') ?>
						</span>
					</details>
					<input data-sensitive autocomplete="new-password" class="form-control form-control-sm" type="password" name="<?= TangoProjectSettingsVO::KEY_CLIENT_SECRET ?>" id="tango-client-secret" value="<?= $settings->getClientSecret() ?>">
				</div>
			</div>
		</div>

		<?php include(__DIR__.'/partials/save_buttons.php') ?>
	</form>

	<?= SessionDataUtils::getAlerts() ?>

</div>
<script type="module">
	import useSecret from '<?= getJSpath('modules/useSecret/index.js') ?>'
	useSecret('[data-sensitive]')
</script>
<style>
	@import url('<?= APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION ?>/Rewards/assets/form.css');
</style>

</style>
<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';