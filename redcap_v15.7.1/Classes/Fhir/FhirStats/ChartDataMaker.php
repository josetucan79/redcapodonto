<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirStats;

class ChartDataMaker
{
    private $results;

    public function __construct($results)
    {
        $this->results = $results;
    }

    /**
     * Prepare data for the total counts chart.
     *
     * @return array The chart data for total counts.
     */
    public function prepareTotalCountsChartData()
    {
        $chartData = [
            'labels' => [],
            'counts' => []
        ];

        foreach ($this->results['data']['total'] as $resource => $categories) {
            foreach ($categories as $category => $count) {
                $chartData['labels'][] = "$category ($resource)";
                $chartData['counts'][] = $count;
            }
        }

        return $chartData;
    }

    /**
     * Prepare data for the daily counts chart.
     *
     * @return array The chart data for daily counts.
     */
    public function prepareDailyCountsChartData()
    {
        $dailyCountsData = $this->results['data']['daily']; // An associative array: date => [resource => [category => count]]
        
        // Initialize chart data structure
        $chartDataDaily = [
            'labels' => [], // Dates
            'datasets' => [] // Datasets for each "category (resource)"
        ];
        
        // Get all unique "category (resource)" combinations
        $categoryResources = [];
        foreach ($dailyCountsData as $date => $resources) {
            foreach ($resources as $resource => $categories) {
                foreach ($categories as $category => $count) {
                    $key = "$category ($resource)";
                    if (!in_array($key, $categoryResources)) {
                        $categoryResources[] = $key;
                    }
                }
            }
        }
        
        // Sort the dates
        $dates = array_keys($dailyCountsData);
        sort($dates);
        $chartDataDaily['labels'] = $dates;
        
        // Initialize datasets
        foreach ($categoryResources as $categoryResource) {
            $chartDataDaily['datasets'][$categoryResource] = [
                'label' => $categoryResource,
                'data' => [], // Counts per date
                'fill' => false,
                'borderColor' => '', // We will set colors later
                'tension' => 0.1, // Optional: Line smoothing
            ];
        }
        
        // Define colors for each "category (resource)"
        $borderColors = [
            'rgba(54, 162, 235, 1)', // Blue
            'rgba(255, 99, 132, 1)', // Red
            'rgba(255, 206, 86, 1)', // Yellow
            'rgba(75, 192, 192, 1)', // Green
            'rgba(153, 102, 255, 1)', // Purple
            'rgba(255, 159, 64, 1)', // Orange
            // Add more colors if needed
        ];
        
        // Assign colors
        $colorIndex = 0;
        foreach ($categoryResources as $categoryResource) {
            $color = $borderColors[$colorIndex % count($borderColors)];
            $chartDataDaily['datasets'][$categoryResource]['borderColor'] = $color;
            $chartDataDaily['datasets'][$categoryResource]['backgroundColor'] = $color; // For point colors
            $colorIndex++;
        }
        
        // Fill the data
        foreach ($dates as $date) {
            foreach ($categoryResources as $categoryResource) {
                // Parse the category and resource from the label
                [$category, $resource] = explode(' (', rtrim($categoryResource, ')'));
                $count = isset($dailyCountsData[$date][$resource][$category]) ? $dailyCountsData[$date][$resource][$category] : 0;
                $chartDataDaily['datasets'][$categoryResource]['data'][] = $count;
            }
        }
        
        // Re-index the datasets array to be numeric, as required by Chart.js
        $chartDataDaily['datasets'] = array_values($chartDataDaily['datasets']);
        
        return $chartDataDaily;
    }

}
