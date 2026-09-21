@php
$urls = [
    [
        'link' => 'Rewards/index.php',
        'title' => 'Eligible Participants',
        'classes' => 'fas fa-person-circle-check',
    ],
    [
        'link' => 'Rewards/reward_options.php',
        'title' => 'Reward Options',
        'classes' => 'fas fa-icons',
    ],
    [
        'link' => 'Rewards/orders.php',
        'title' => 'Orders',
        'classes' => 'fas fa-folder-open',
    ],
    [
        'link' => 'Rewards/settings.php',
        'title' => 'Settings',
        'classes' => 'fas fa-cog',
    ],
    [
        'link' => 'Rewards/logs.php',
        'title' => 'Logs',
        'classes' => 'fas fa-file-lines',
    ],
    [
        'link' => 'Rewards/email_template.php',
        'title' => 'Email template',
        'classes' => 'fas fa-envelope',
    ],
    [
        'link' => 'Rewards/permissions.php',
        'title' => 'Permissions',
        'classes' => 'fas fa-envelope',
    ],
];
@endphp

    <div id="sub-nav" class="d-none d-sm-block" style="margin:5px 0 20px;">
        <ul>
        @foreach($urls as $url)
            <li class="{{ ($url['link'] === $PAGE) ? 'active' : '' }}">
                <a href="{{ $APP_PATH_WEBROOT.$url['link'] }}?pid={{$project_id}}" style="font-size:13px;color:#393733;padding:6px 9px 7px 10px;"><i class="<?= $url['classes'] ?>"></i> {{ $url['title'] }}</a>
            </li>
        @endforeach
        </ul>
    </div>
