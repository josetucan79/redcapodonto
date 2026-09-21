<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStats;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\FhirStats\ChartDataMaker;
use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStatsCollector;

include dirname(__DIR__).'/header.php';
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);
if (!ACCESS_SYSTEM_CONFIG) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); });</script>";

// Initialize variables
$errors = [];
$search_made = isset($_GET['search']) && $_GET['search'] == '1';
$chartData = [];
$ehrSystems = FhirSystem::getEhrSystems();
$ehr_ids = [];
$results = [];

foreach ($ehrSystems as $system) {
    $ehr_ids[$system->ehr_id] = $system->ehr_name;
}

if ($search_made) {
    // Set default date range to the last 7 days if not provided
    if (empty($_GET)) {
        $date_end = date('Y-m-d');
        $date_start = date('Y-m-d', strtotime('-7 days'));
        $type = '';
        $ehr_id = '';
    } else {
        // Get form input and sanitize
        $date_start = isset($_GET['date_start']) ? trim($_GET['date_start']) : '';
        $date_end = isset($_GET['date_end']) ? trim($_GET['date_end']) : '';
        $type = isset($_GET['type']) ? $_GET['type'] : '';
        $ehr_id = isset($_GET['ehr_id']) ? trim($_GET['ehr_id']) : '';
    }

    // Validate input
    if ($date_start && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_start)) {
        $errors[] = 'Invalid start date format. Use YYYY-MM-DD.';
    }
    if ($date_end && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_end)) {
        $errors[] = 'Invalid end date format. Use YYYY-MM-DD.';
    }
    if ($ehr_id && !array_key_exists($ehr_id, $ehr_ids)) {
        $errors[] = 'Invalid EHR ID selected.';
    }

    if (empty($errors)) {
        // Prepare search parameters
        $params = [];
        if ($date_start) {
            $params['date_start'] = $date_start;
        }
        if ($date_end) {
            $params['date_end'] = $date_end;
        }
        if ($type) {
            $params['type'] = $type;
        }
        if ($ehr_id) {
            $params['ehr_id'] = $ehr_id;
        }

        // Instantiate FhirStats with the parameters
        $fhirStats = new FhirStats($params);

        // Get counts
        $results = $fhirStats->getCounts();

        // make charts data
        $chartDataMaker = new ChartDataMaker($results);
        $chartData = $chartDataMaker->prepareTotalCountsChartData($results);
    }
} else {
    // Initialize variables with empty values
    $date_end = date('Y-m-d');
    $date_start = date('Y-m-d', strtotime('-7 days'));
    $type = '';
    $ehr_id = '';
}
?>
<div class="form-container">
    <h4>
        <i class="fas fa fa-fire fa fw"></i>
        <span><?= Language::tt('dashboard_126') ?></span>
    </h4>
    <div class="statistics-info mt-3">
        <p><?= Language::tt('cc_fhir_statistics_description') ?></p>
        <ul>
            <li><?= Language::tt('cc_fhir_statistics_cdp') ?></li>
            <li><?= Language::tt('cc_fhir_statistics_cdm') ?></li>
        </ul>
        <p><?= Language::tt('cc_fhir_statistics_note') ?></p>
    </div>
    <?php
    // Display errors if any
    if (!empty($errors)) {
        echo '<div class="errors"><ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul></div>';
    }
    ?>
    <div class="border rounded p-2 bg-light">
        <form method="get" action="">
            <input type="hidden" name="search" value="1">
            <div class="d-flex gap-2">
                <div>
                    <label class="form-label" for="date_start"><?= Language::tt('cc_fhir_statistics_start_date') ?>:</label><br>
                    <input class="form-control form-control-sm" type="date" id="date_start" name="date_start" value="<?php echo htmlspecialchars($date_start); ?>">
                </div>
                <div>
                    <label class="form-label" for="date_end"><?= Language::tt('cc_fhir_statistics_end_date') ?>:</label>
                    <input class="form-control form-control-sm" type="date" id="date_end" name="date_end" value="<?php echo htmlspecialchars($date_end); ?>">
                </div>
                <div>
                    <label class="form-label" for="type"><?= Language::tt('cc_fhir_statistics_type') ?>:</label>
                    <select class="form-select form-select-sm" id="type" name="type">
                        <option value="" <?php if ($type == '') echo 'selected'; ?>>All</option>
                        <option value="<?= FhirStatsCollector::REDCAP_TOOL_TYPE_CDM ?>" <?php if ($type == FhirStatsCollector::REDCAP_TOOL_TYPE_CDM) echo 'selected'; ?>>CDM</option>
                        <option value="<?= FhirStatsCollector::REDCAP_TOOL_TYPE_CDP ?>" <?php if ($type == FhirStatsCollector::REDCAP_TOOL_TYPE_CDP) echo 'selected'; ?>>CDP</option>
                        <option value="<?= FhirStatsCollector::REDCAP_TOOL_TYPE_CDP_INSTANT ?>" <?php if ($type == FhirStatsCollector::REDCAP_TOOL_TYPE_CDP_INSTANT) echo 'selected'; ?>>CDP – instant adjudcation</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="ehr_id"><?= Language::tt('cc_fhir_statistics_ehr_system') ?>:</label>
                    <select class="form-select form-select-sm" id="ehr_id" name="ehr_id">
                        <option value="" <?php if ($ehr_id == '') echo 'selected'; ?>>All</option>
                        <?php foreach ($ehr_ids as $id => $name): ?>
                            <option value="<?= htmlspecialchars($id) ?>" <?php if ($ehr_id == $id) echo 'selected'; ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-flex">
                <div class="ms-auto">
                    <label class="form-label">&nbsp;</label><br>
                    <button type="button" class="btn btn-xs btn-secondary" id="clear-button">
                        <i class="fas fa-refresh fa-fw"></i>
                        <span><?= Language::tt('cc_fhir_statistics_clear') ?></span>
                    </button>
                    <button type="submit" class="btn btn-xs btn-primary" >
                        <i class="fas fa-chart-simple fa-fw"></i>
                        <span><?= Language::tt('cc_fhir_statistics_get_statistics') ?></span>
                    </button>
                    <!-- <input class="btn btn-sm btn-primary" type="submit" value="Get Statistics"> -->
                </div>
            </div>
        </form>
    </div>
</div>
<?php if ($search_made && isset($results)): ?>
    <div class="results-container mt-2 d-flex flex-column gap-2">

        <div class="border rounded p-2">
            <!-- Display total counts -->
            <h5><?= Language::tt('cc_fhir_statistics_total_counts') ?></h5>
            <table class="table table-sm table-bordered table-striped table-hover">
                <tr>
                    <th><?= Language::tt('cc_fhir_statistics_redcap_category') ?></th>
                    <th><?= Language::tt('cc_fhir_statistics_fhir_resource') ?></th>
                    <th><?= Language::tt('cc_fhir_statistics_count') ?></th>
                </tr>
                <?php
                foreach ($results['data']['total'] as $resource => $categories) {
                    foreach ($categories as $category => $count) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($category) . '</td>';
                        echo '<td>' . htmlspecialchars($resource) . '</td>';
                        echo '<td>' . htmlspecialchars($count) . '</td>';
                        echo '</tr>';
                    }
                }
                ?>
            </table>
    
            <canvas id="totalCountsChart" width="400" height="200"></canvas>
        </div>
        




        <div class="border-top py-2">
            <!-- Export Link -->
            <button type="button" class="btn btn-sm btn-primary p-2" id="export-button">
                <i class="fas fa-file-zipper fa-fw fa-2xl"></i>
                <span><?= Language::tt('cc_fhir_statistics_download_csv') ?></span>
            </button>
        </div>
    </div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script type="module">

function clearSearch() {
    var url = window.location.pathname;
    window.location.href = url;
}
function exportData() {
    var url = "<?= htmlspecialchars($results['metadata']['export_link'] ?? ''); ?>";
    window.location.href = url;
}

const clearButton = document.querySelector('#clear-button')
clearButton.addEventListener('click', () => clearSearch())
const exportButton = document.querySelector('#export-button')
if(exportButton) {
    exportButton.addEventListener('click', () => exportData())
}

const printTotalCountsCharts = () => {
    // Pass PHP data to JavaScript
    const canvasElement = document.getElementById('totalCountsChart')
    if(!canvasElement) return
    var ctx = canvasElement.getContext('2d')

    var chartData = <?= json_encode($chartData); ?>
    
    // Get the context of the canvas
    
    // Create the chart
    var totalCountsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Total Counts',
                data: chartData.counts,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true
        }
    });
}

printTotalCountsCharts()

</script>


<?php include dirname(__DIR__).'/footer.php'; ?>
