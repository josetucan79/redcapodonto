<?php
use Vanderbilt\REDCap\Classes\Utility\SessionDataUtils;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\QueueManager;

include __DIR__.'/partials/header.php';

$page = $_GET['page'] ?? null;
$perPage = $_GET['per-page'] ?? null;
$queueManager = new QueueManager($project_id);
$nonQueuableRecords = $queueManager->getNonQueuableRecords($page, $perPage, $metadata);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'queue-selected':
                $record_ids = $_POST['record_ids'] ?? [];
                foreach ($record_ids as $record_id) {
                    $queueManager->queueRecord($record_id);
                }
                break;
            case 'queue-all':
                $queueManager->queueAllRecords();
                break;
            
            default:
                # code...
                break;
        }
        SessionDataUtils::alert($lang['cdp_dashboard_alert_success'], 'info');
    } catch(Throwable $th) {
        SessionDataUtils::alert($th->getMessage(), 'danger');
    }finally {
        redirect($_SERVER['HTTP_REFERER']);
    }
}

?>

<?php include __DIR__.'/partials/tabs.php'; ?>
<div style="max-width: 800px;">
    <div>
        <?= $lang['cdp_dashboard_not_queueable_description'] ?>
    </div>
    <form action="" method="post">
        <?php if(count($nonQueuableRecords)===0) : ?>
        <table class="table table-sm table-bordered table-hover table-striped">
            <tbody>
                <tr>
                    <td>
                        <span class="fst-italic"><?= $lang['cdp_dashboard_no_records'] ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php else : ?>
        <div id="pagination-container" class="my-2"></div>
        <table class="table table-sm table-bordered table-hover table-striped">
            <thead>
                <tr>
                    <th>
                        <input type="checkbox" id="select-all" onclick="toggleSelectAll(this)">
                        <?= $lang['cdp_dashboard_table_header_select_all'] ?>
                    </th>
                    <th><?= $lang['cdp_dashboard_table_header_record'] ?></th>
                    <th><?= $lang['cdp_dashboard_table_header_reasons'] ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($nonQueuableRecords as $record_id => $item) : ?>
                <tr>
                    <td>
                        <input class="record-checkbox" type="checkbox" name="record_ids[]" value="<?= htmlspecialchars($item->record) ?>" id="record<?= htmlspecialchars($item->record) ?>">
                    </td>
                    <td>
                        <label for="record<?= htmlspecialchars($item->record) ?>">
                            <a href="<?= $item->getLink($project_id) ?>">
                                <?= htmlspecialchars($item->record) ?>
                            </a>
                        </label>
                    </td>
                    <td>
                    <?php foreach ($item->reasons as $reason) : ?>
                        <span class="d-block">• <?= $reason ?></span>
                    <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-start">
                        <div class="d-flex gap-2">
                            <input type="hidden" name="action" value="" />
                            <button data-submit-selection type="submit" class="btn btn-primary btn-xs" onclick="handleSubmit(event, 'queue-selected')"><?= $lang['cdp_dashboard_queue_selected'] ?></button>
                            <button type="submit" class="btn btn-primary btn-xs" onclick="handleSubmit(event, 'queue-all')"><?= $lang['cdp_dashboard_queue_all'] ?></button>
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </form>
    <?= SessionDataUtils::getAlerts() ?>
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
<script>
    function handleSubmit(event, action) {
        event.preventDefault(); // Prevent the default form submission
        var form = event.target.form; // Get the form element from the event
        var formData = new FormData(form); // Create a FormData object from the form
        var actionInput = form.querySelector('input[name="action"]'); 
        actionInput.value = action

        switch (action) {
            case 'queue-selected':
                if(!confirm('<?= $lang['cdp_dashboard_confirm_queue_queue_selected'] ?>')) break;
                let selectedRecords = [];
                formData.forEach((value, key) => {
                    if (key === 'record_ids[]') {
                        selectedRecords.push(value);
                    }
                });
                form.submit()
                break;
            case 'queue-all':
                if(!confirm('<?= $lang['cdp_dashboard_confirm_queue_queue_all'] ?>')) break;
                form.submit()
                break;
            default:
                break;
        }
    }

    function updateSubmitButtonState() {
        var checkboxes = document.querySelectorAll('.record-checkbox');
        var submitButton = document.querySelector('button[data-submit-selection]');
        var anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);

        submitButton.disabled = !anyChecked; // Disable if no checkboxes are selected
    }

    function toggleSelectAll(selectAllCheckbox) {
        var checkboxes = document.querySelectorAll('.record-checkbox');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = selectAllCheckbox.checked;
        });
        updateSubmitButtonState()
    }

    document.addEventListener('DOMContentLoaded', function() {
        var checkboxes = document.querySelectorAll('.record-checkbox');
        if(checkboxes.length===0) return
        checkboxes.forEach(checkbox => checkbox.addEventListener('change', updateSubmitButtonState));

        // Initial check to set the button state on page load
        updateSubmitButtonState();
    });

</script>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';