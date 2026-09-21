<?php
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\TermsManager;

$rid = $_GET[TermsManager::QUERY_PARAM_RID] ?? null;

// Check global rewards feature
if (!$rewards_enabled_global) {
    redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");
}

// Terms Manager Class
$TermsManager = new TermsManager($rid);

if ($TermsManager->hasErrors()) : ?>
    <div class="border rounded p-2 m-auto" style="max-width: 500px;">
        <div class="d-flex flex-column my-2">
            <span class="d-block fw-bold text-danger fs-3">There was an error processing your request.</span>
            <p>Please contact your system administrator for assistance if this issue persists.</p>
        </div>
        <details>
            <summary>Details...</summary>
            <span class="d-block fw-bold text-danger fs-6">Error: invalid parameters</span>
            <ul>
            <?php foreach ($TermsManager->errors() as $error) : ?>
                <li><?= $error ?></li>
            <?php endforeach; ?>
            </ul>
        </details>
    </div>
<?php else: ?>

<?php
// Retrieve product details
$product = $TermsManager->product();
$redeemLink = $TermsManager->redeemLink();
$referenceOrder = $TermsManager->referenceOrder();
// the last image is the biggest
$largeImageURL = is_array($product->imageUrls) ? end($product->imageUrls) : '';
?>

<div style="width: auto;">
    <div class="d-flex flex-column gap-2">
        <!-- Product Image and Name -->
        <div class="d-flex justify-content-center align-items-end gap-2">
            <div style="height: 100px; overflow: hidden;">
                <img class="img-fluid" src="<?= $largeImageURL ?>" alt="<?= htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8') ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;" />
            </div>
            <h1><?= htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>

        <!-- Redemption Link -->
        <div class="border rounded p-2">
            <h2><?= Language::tt('rewards_terms_link') ?></h2>
            <a href="<?= htmlspecialchars($redeemLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank"><?= htmlspecialchars($redeemLink, ENT_QUOTES, 'UTF-8') ?></a>
            <span class="d-block text-muted fst-italic mt-2">
                <span>Reference Order: <?= htmlspecialchars($referenceOrder, ENT_QUOTES, 'UTF-8') ?></span>
            </span>
        </div>

        <!-- Terms -->
        <div class="border rounded p-2">
            <h2><?= Language::tt('rewards_terms_terms') ?></h2>
            <p><?= filter_tags($product->terms) ?></p>
        </div>

        <!-- Redemption Instructions -->
        <div class="border rounded p-2">
            <h2><?= Language::tt('rewards_terms_instructions') ?></h2>
            <p><?= filter_tags($product->redemptionInstructions) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>