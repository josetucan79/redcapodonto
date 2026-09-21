<?php

use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\QueueManager;

include __DIR__.'/partials/header.php';

$page = $_GET['page'] ?? null;
$perPage = $_GET['per-page'] ?? null;
$queueManager = new QueueManager($project_id);
$queuedRecords = $queueManager->getQueuedRecords($page, $perPage, $metadata);
?>
<?php include __DIR__.'/partials/tabs.php'; ?>
<div style="max-width: 800px;">
    <div>
        <?= $lang['cdp_dashboard_queued_description'] ?>
    </div>
    <?php if(count($queuedRecords)===0) : ?>
        <table class="table table-sm table-bordered table-hover table-striped">
            <tbody>
                <tr>
                    <td>
                        <span class="fst-italic"> <?= $lang['cdp_dashboard_no_records'] ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
    <?php else : ?>
    <div>
        <div id="pagination-container" class="my-2"></div>
        <table class="table table-sm table-bordered table-hover table-striped">
            <thead>
                <tr>
                    <th><?= $lang['cdp_dashboard_table_header_record'] ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($queuedRecords as $item) : ?>
                <tr>
                    <td>
                        <a href="<?= $item->getLink($project_id) ?>">
                            <?= $item->record ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<style>
    @import url('<?= APP_PATH_JS ?>modules/Pagination/style.css');
</style>
<script type="module">
    import Pagination from '<?= getJSpath('modules/Pagination/index.js') ?>'

    const initPagination = () => {
        const paginationElement = document.querySelector('#pagination-container')
        if(!paginationElement) return
        const currentPage = <?= $metadata->page ?>;
        const totalPages = <?= $metadata->totalPages ?>;
        const perPage = <?= $metadata->perPage ?>;
        const pagination = new Pagination(paginationElement, currentPage, totalPages, perPage);
    }
    initPagination()
</script>
<?php


// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';