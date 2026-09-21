<?php
use Vanderbilt\REDCap\Classes\Rewards\Facades\CriteriaManager;

$userID = defined('UI_ID') ? UI_ID : null;
$criteriaManager = new CriteriaManager($project_id, $userID, $lang);
$results = $criteriaManager->checkCriteria($valid);

ob_start();
?>
<?php if(!$valid) : ?>
<div class="alert alert-warning">
    <span>This feature is currently unavailable because not all required criteria have been met. Please address the issues listed below before returning to this page. If you are unable to perform any of the necessary steps, please contact the project administrator for assistance.</span>
</div>
<?php endif; ?>
<div data-rewards-criteria class="d-flex flex-column gap-2">
<?php foreach ($results as $result) : ?>
    <div>
        <?php if($result->isMet()) : ?>
            <i class="fas fa-check-circle text-success"></i>
        <?php else : ?>
            <i class="fas fa-ban text-danger"></i>
        <?php endif; ?>
        <strong><?= $result->getTitle() ?></strong>
        <?php if(true || !$result->isMet()) : ?>
        <span class="d-block"><small><?= $result->getDescription() ?></small></span>
            <?php if($errors = $result->getErrors()) : ?>
            <details class="small">
                <summary>Details...</summary>
                <ul>
                <?php foreach($errors as $error) : ?>
                    <li><?= $error->getMessage() ?></li>
                <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<?php
$legend = ob_get_contents();
ob_end_clean();

return [
    'results' => $results,
    'valid' => $valid,
    'legend' => $legend,
];
