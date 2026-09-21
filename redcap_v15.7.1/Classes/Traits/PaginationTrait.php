<?php
namespace Vanderbilt\REDCap\Classes\Traits;

use Vanderbilt\REDCap\Classes\DTOs\ResultsMetadataDTO;

trait PaginationTrait {

    private function applyPagination(&$page, &$perPage, &$params) {
        // Sanitize the page and perPage numbers
        $page = $this->sanitizeNumber($page, $this->config['default_page']);
        $perPage = $this->sanitizeNumber($perPage, $this->config['default_per_page']);
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;
        return " LIMIT ? OFFSET ?";
    }

    private function sanitizeNumber($number, $default=1) {
        return max(1, intval($number ?? $default));
    }


    private function populateMetadata(string $baseQuery, array $params, ?int $page, ?int $perPage, &$metadata) {
        // Count query to get the total number of records
        $countSql = "SELECT COUNT(*) AS total FROM ($baseQuery) AS subquery";
        $countResult = db_query($countSql, $params);
        $countData = db_fetch_assoc($countResult);
        
        $metadata = new ResultsMetadataDTO([
            'total' => $total = $countData['total'],
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage),
        ]);
    }
}