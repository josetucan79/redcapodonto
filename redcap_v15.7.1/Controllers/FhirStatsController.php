<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStats;

class FhirStatsController extends BaseController
{


    public function __construct()
    {
        parent::__construct();
    }

    /**
    * Get a link to export data
    *
    * @param array $params Search parameters
    * @return string
    */
    public static function getExportLink(array $params): string
    {
        $controllerName = (new \ReflectionClass(static::class))->getShortName();
        $query_params = array_merge(['route' => $controllerName . ":export"], $params);
        $export_link = APP_PATH_WEBROOT . "index.php?" . http_build_query($query_params);
        return $export_link;
    }

    /**
     * export data to CSV
     *
     * @return void
     */
    public function export()
    {
        $params = [
            'date_start' => $_GET['date_start'] ?? '',
            'date_end' => $_GET['date_end'] ?? '',
            'type' => $_GET['type'] ?? '',
        ];
        $fhir_stats = new FhirStats($params);
        $fhir_stats->exportData();
    }


}