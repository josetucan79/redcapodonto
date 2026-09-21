<?php
namespace Vanderbilt\REDCap\DynamicDataPull;

$baseURL = 'DynamicDataPull/data_fetching_queue_dashboard';
$urls = [
    'index' => [
        'link' => '/index.php',
        'title' => 'Dashboard Home',
        'classes' => 'fas fa-home',
    ],
    'not_queued' => [
        'link' => '/not_queueable.php',
        'title' => 'Not Queueable',
        'classes' => 'fas fa-not-equal',
    ],
    'queue' => [
        'link' => '/queue.php',
        'title' => 'Queue',
        'classes' => 'fas fa-list',
    ],
    'cache' => [
        'link' => '/cache.php',
        'title' => 'Cache',
        'classes' => 'fas fa-database',
    ],
];

function isActive($link) {
    return basename($link) === basename(PAGE_FULL);
}
?>

<div id="sub-nav" class="d-none d-sm-block" style="margin:5px 0 20px;">
    <ul>
    <?php foreach ($urls as $url) : ?>
        <li class="<?= (isActive($url['link'])) ? 'active' : '' ?>">
            <a href="<?= APP_PATH_WEBROOT.$baseURL.$url['link'] ?>?pid=<?= $project_id?>" style="font-size:13px;color:#393733;padding:6px 9px 7px 10px;"><i class="<?= $url['classes'] ?>"></i> <?= $url['title'] ?></a>
        </li>
    <?php endforeach; ?>
    </ul>
</div>
<div style="clear: both;"></div>