<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirStats;

use DateTime;
use FhirStatsController;
use ZipArchive;
use FileManager;

class FhirStats
{
    /**
     * Search parameters to retrieve data from the database
     *
     * @var array
     */
    private $search_params = [];

    /**
     * Set the search parameters upon construction
     *
     * @param array $params
     */
    public function __construct(array $params = [])
    {
        $this->search_params = $params;
    }

    /**
     * Get the search parameters provided on creation
     *
     * @return array
     */
    public function getSearchParameters(): array
    {
        return $this->search_params;
    }

    /**
     * Get all users with Clinical Data Mart privileges
     *
     * @param bool $include_super_user Whether to include or not super admins in the list
     * @return array
     */
    public function getCdmUsers(bool $include_super_user = true): array
    {
        $params = [];
        $query = "SELECT * FROM redcap_user_information
                  WHERE (fhir_data_mart_create_project = 1 AND super_user = 0)";

        if ($include_super_user) {
            $query .= " OR super_user = 1";
        }

        $result = db_query($query, $params);
        $rows = [];
        while ($row = db_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Get the list of distinct resources from the counts details table
     *
     * @return array
     */
    public function getResources(): array
    {
        $query = "SELECT DISTINCT `resource` FROM redcap_ehr_resource_import_details";
        $result = db_query($query);
        $resources = [];
        while ($row = db_fetch_assoc($result)) {
            $resources[] = $row['resource'];
        }
        return $resources;
    }

    /**
     * Prepare the WHERE clause for queries based on search parameters
     *
     * @param array &$params
     * @return string
     */
    private function buildWhereClause(array &$params): string
    {
        $clauses = []; // Initialize empty array for clauses

        // Handle type filtering
        $type = $this->search_params['type'] ?? '';
        if (!empty($type)) {
            $clauses[] = "c.type = ?";
            $params[] = $type;
        }

        // Handle ehr_id filtering
        if (isset($this->search_params['ehr_id']) && is_numeric($this->search_params['ehr_id'])) {
            $clauses[] = 'c.ehr_id = ?';
            $params[] = (int) $this->search_params['ehr_id'];
        }

        // Handle date filtering
        $date_start = $this->search_params['date_start'] ?? null;
        $date_end = $this->search_params['date_end'] ?? null;

        if ($date_start) {
            $date_start = DateTime::createFromFormat('Y-m-d', $date_start);
            if ($date_start) {
                $date_start->setTime(0, 0, 0); // Start of day
                $clauses[] = 'c.ts >= ?';
                $params[] = $date_start->format('Y-m-d H:i:s');
            }
        }
        if ($date_end) {
            $date_end = DateTime::createFromFormat('Y-m-d', $date_end);
            if ($date_end) {
                $date_end->setTime(23, 59, 59); // End of day
                $clauses[] = 'c.ts <= ?';
                $params[] = $date_end->format('Y-m-d H:i:s');
            }
        }

        // Handle adjudicated filter
        if (isset($this->search_params['adjudicated'])) {
            $clauses[] = 'c.adjudicated = ?';
            $params[] = $this->search_params['adjudicated'] ? 1 : 0;
        }

        // If no clauses have been added, default to '1' to avoid syntax errors
        if (empty($clauses)) {
            $clauses[] = '1';
        }

        return implode(' AND ', $clauses);
    }

    /**
     * Get total counts per resource and category
     *
     * @return array
     */
    public function getTotal(): array
    {
        $params = [];
        $where = $this->buildWhereClause($params);

        $query = "SELECT d.resource, d.category, SUM(d.count) as total_count
                  FROM redcap_ehr_resource_imports c
                  JOIN redcap_ehr_resource_import_details d ON c.id = d.ehr_import_count_id
                  WHERE $where
                  GROUP BY d.resource, d.category";

        $result = db_query($query, $params);

        $counts = [];
        while ($row = db_fetch_assoc($result)) {
            $resource = $row['resource'];
            $category = $row['category'];
            $total_count = (int) $row['total_count'];

            if (!isset($counts[$resource])) {
                $counts[$resource] = [];
            }
            $counts[$resource][$category] = $total_count;
        }

        return $counts;
    }

    /**
     * Get counts per day per resource and category
     *
     * @return array
     */
    public function getCountsPerDay(): array
    {
        $params = [];
        $where = $this->buildWhereClause($params);

        $query = "SELECT DATE(c.ts) as day, d.resource, d.category, SUM(d.count) as total_count
                  FROM redcap_ehr_resource_imports c
                  JOIN redcap_ehr_resource_import_details d ON c.id = d.ehr_import_count_id
                  WHERE $where
                  GROUP BY day, d.resource, d.category
                  ORDER BY day ASC";

        $result = db_query($query, $params);

        $counts_per_day = [];
        while ($row = db_fetch_assoc($result)) {
            $day = $row['day'];
            $resource = $row['resource'];
            $category = $row['category'];
            $total_count = (int) $row['total_count'];

            if (!isset($counts_per_day[$day])) {
                $counts_per_day[$day] = [];
            }
            if (!isset($counts_per_day[$day][$resource])) {
                $counts_per_day[$day][$resource] = [];
            }
            $counts_per_day[$day][$resource][$category] = $total_count;
        }

        return $counts_per_day;
    }

    /**
     * Get all data related to CDP and CDM
     *
     * @return array
     */
    public function getCounts(): array
    {
        // Initialize data array
        $data = [
            'total' => $this->getTotal(),
            // 'daily' => $this->getCountsPerDay(),
        ];

        // Prepare results
        $results = [
            'data' => $data,
            'metadata' => [
                'search_params' => $this->search_params,
                'export_link' => FhirStatsController::getExportLink($this->search_params),
            ]
        ];
        return $results;
    }

    /**
     * Export CSV files to a zip archive
     *
     * @return void
     */
    public function exportData(): void
    {
        // Create zip archive
        $file = tempnam(sys_get_temp_dir(), "fhir_stats_") . ".zip";
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE) !== true) {
            throw new \Exception("Cannot create zip file");
        }

        // Add each specialized CSV to the zip
        $this->addTotalCountsToZip($zip);
        // $this->addDailyCountsToZip($zip);
        // $this->addCdmUsersCountToZip($zip);

        // Close the zip file
        $zip->close();

        // Send zip file to output
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: attachment; filename="fhir-statistics.zip"');
        readfile($file);
        unlink($file);
    }

    /**
     * Add Total Counts CSV to the zip archive
     *
     * @param ZipArchive $zip
     */
    private function addTotalCountsToZip(ZipArchive $zip): void
    {
        $results = $this->getCounts();
        $total_counts = $results['data']['total'];

        $csv_total_counts = [];
        foreach ($total_counts as $resource => $categories) {
            foreach ($categories as $category => $count) {
                $csv_total_counts[] = ['Resource' => $resource, 'Category' => $category, 'Count' => $count];
            }
        }

        $csv = FileManager::getCSV($csv_total_counts);
        $zip->addFromString("total_counts.csv", $csv);
    }

    /**
     * Add Daily Counts CSV to the zip archive
     *
     * @param ZipArchive $zip
     */
    private function addDailyCountsToZip(ZipArchive $zip): void
    {
        $results = $this->getCounts();
        $daily_counts = $results['data']['daily'];

        $csv_daily_counts = [];
        foreach ($daily_counts as $date => $resources) {
            foreach ($resources as $resource => $categories) {
                foreach ($categories as $category => $count) {
                    $csv_daily_counts[] = ['Date' => $date, 'Resource' => $resource, 'Category' => $category, 'Count' => $count];
                }
            }
        }

        $csv = FileManager::getCSV($csv_daily_counts);
        $zip->addFromString("daily_counts.csv", $csv);
    }

    /**
     * Add CDM Users Count CSV to the zip archive
     *
     * @param ZipArchive $zip
     */
    private function addCdmUsersCountToZip(ZipArchive $zip): void
    {
        $results = $this->getCounts();

        if (isset($results['data']['cdm_users_count'])) {
            $csv_cdm_users = [
                ['CDM Users Count' => $results['data']['cdm_users_count']]
            ];
            $csv = FileManager::getCSV($csv_cdm_users);
            $zip->addFromString("cdm_users_count.csv", $csv);
        }
    }




}
