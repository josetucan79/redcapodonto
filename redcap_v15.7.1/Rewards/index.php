<?php include __DIR__.'/partials/header.php'; ?>
<?php include __DIR__.'/partials/tabs.php'; ?>
<div style="width: auto; max-width: 800px;">
    <?php include __DIR__.'/partials/rewards_feature.php'; ?>
    <hr>
    <div class="text-right mt-2">
		<i class="fas fa-clipboard-check"></i>
		<a href="<?= APP_PATH_WEBROOT ?>Rewards/configuration_check.php?pid=<?= $project_id?>" target="_self">Configuration Check...</a>
	</div>
</div>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
