<?php include __DIR__.'/partials/header.php'; ?>
<?php

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\LogEntity;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Utility\SessionDataUtils;

function renderPagination($totalPages, $currentPage, $baseUrl, $maxVisible = 5) {
    if ($totalPages <= 1) return;

    $html = '<nav class="pagination">';
    $html .= '<ul>';

    // First & Prev
    $html .= $currentPage > 1
        ? '<li><a href="'.$baseUrl.'_page=1">«</a></li><li><a href="'.$baseUrl.'_page='.($currentPage - 1).'">‹</a></li>'
        : '<li class="disabled">«</li><li class="disabled">‹</li>';

    $start = max(1, $currentPage - (int)($maxVisible / 2));
    $end = min($totalPages, $start + $maxVisible - 1);

    if ($start > 1) {
        $html .= '<li><a href="'.$baseUrl.'_page=1">1</a></li>';
        if ($start > 2) $html .= '<li class="ellipsis">…</li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="active">'.$i.'</li>';
        } else {
            $html .= '<li><a href="'.$baseUrl.'_page='.$i.'">'.$i.'</a></li>';
        }
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li class="ellipsis">…</li>';
        $html .= '<li><a href="'.$baseUrl.'_page='.$totalPages.'">'.$totalPages.'</a></li>';
    }

    // Next & Last
    $html .= $currentPage < $totalPages
        ? '<li><a href="'.$baseUrl.'_page='.($currentPage + 1).'">›</a></li><li><a href="'.$baseUrl.'_page='.$totalPages.'">»</a></li>'
        : '<li class="disabled">›</li><li class="disabled">»</li>';

    $html .= '</ul></nav>';

    return $html;
}

// If user does not have Project Setup/Design rights, do not show this page
if (!$user_rights['design']) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");

$entityManager = EntityManager::get();
$logRepository = $entityManager->getRepository(LogEntity::class);
/** @var LogEntity[] $logs */
$logs = $logRepository->findBy(['project_id' => $project_id]);

$page = max((int)($_GET['_page'] ?? 1), 1);
$perPage = max((int)($_GET['_per_page'] ?? 10), 1);

$total = $logRepository->createQueryBuilder('l')
    ->select('COUNT(l.log_id)')
    ->where('l.project_id = :project_id')
    ->setParameter('project_id', $project_id)
    ->getQuery()
    ->getSingleScalarResult();

$logs = $logRepository->createQueryBuilder('l')
    ->where('l.project_id = :project_id')
    ->setParameter('project_id', $project_id)
    ->orderBy('l.created_at', 'DESC')
    ->setFirstResult(($page - 1) * $perPage)
    ->setMaxResults($perPage)
    ->getQuery()
    ->getResult();

$totalPages = (int)ceil($total / $perPage);
$queryBase = http_build_query(array_merge($_GET, ['_per_page' => $perPage]));
?>
<?php include __DIR__.'/partials/tabs.php'; ?>



<?php
// Build the base URL without _page
$queryParams = $_GET;
unset($queryParams['_page']);
$baseUrl = '?' . http_build_query($queryParams);
if ($baseUrl !== '?' && substr($baseUrl, -1) !== '&') {
    $baseUrl .= '&';
}
?>

<div class="d-flex gap-2 align-items-center mb-2">
    <?= renderPagination($totalPages, $page, $baseUrl); ?>
    <?php include __DIR__.'/partials/per-page-form.php'; ?>
</div>

<div style="max-width: 800px; clear: both">
    <div class="border rounded p-2">
    <p>
        Logs of the CRUD operations
    </p>
    <table class="table table-bordered table-striped table-hover">
        <thead>
            <tr>
                <th>log_id</th>
                <th>table_name</th>
                <th>action</th>
                <th>payload</th>
                <th>username</th>
                <th>project_id</th>
                <th>created_at</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log) : ?>
                <tr>
                    <td><?= $log->getId() ?></td>
                    <td><?= $log->getTableName() ?></td>
                    <td><?= $log->getAction() ?></td>
                    <td class="fixed-width">
                        <details >
                            <summary>
                                <?= htmlspecialchars($log->getPayload(), ENT_QUOTES) ?>
                            </summary>

                        </details>
                    </td>
                    <td><?= $log->getUsername() ?></td>
                    <td><?= $log->getProjectId() ?></td>
                    <td><?= $log?->getCreatedAt()->format(LogEntity::TIMESTAMP_FORMAT) ?? '' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= SessionDataUtils::getAlerts(); ?>
</div>
<style>
:has(> table) {
  width: 100%;
  overflow-x: auto;
  max-height: 600px;
}

table {
  width: 100%;
  max-width: 100%;
  border-collapse: collapse;
}
.fixed-width {
    max-width: 150px;       /* your desired max width */
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.fixed-width:has(details:open) {
    overflow: visible;
    white-space: wrap;
    word-break: break-word;
}
details, summary {
    display: contents;
}

summary {
  list-style: none; /* removes default marker in Firefox */
  cursor: pointer;
}

summary::-webkit-details-marker {
  display: none; /* hides the default in Chrome/Safari */
}

summary::before {
  content: '▶'; /* closed state */
  display: block;
  margin-right: 0.5em;
  transition: transform 0.2s ease;
}

details[open] summary::before {
  content: '▼'; /* open state */
}
.pagination ul {
    display: flex;
    gap: 0;
    list-style-type: none;
    align-items: center;
    margin: 0;
    padding: 0;
}
.pagination li {
    border: solid 1px rgb(60 60 60 / 1);
    padding: 5px 10px;
}
.pagination li + li {
    border-left: none;
}
.pagination li:first-of-type {
    border-top-left-radius: 5px;
    border-bottom-left-radius: 5px;
}
.pagination li:last-of-type {
    border-top-right-radius: 5px;
    border-bottom-right-radius: 5px;
}
label {
    margin: 0;
}
</style>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
