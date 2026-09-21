<?php
// PHP 5.3.0 compliant path builder
define('APP_PATH_WEBROOT', realpath(__DIR__ . '/..') . '/');

// Optional dark or light theme
$theme = $_GET["theme"] ?? "light";
$theme = in_array($theme, ["light", "dark"], true) ? $theme : "light";

?>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Shibboleth Authentication</title>
    <style>
    html { background-color: whitesmoke; }
    body {
        max-width: 910px;
        border: 1px solid #d1d5da;
        border-radius: 2px;
        background-color: white;
        padding: 30px 50px;
    }
    img {
        max-width: 100%;
    }
    <?php
    $css_file = file_exists(APP_PATH_WEBROOT . "ExternalModules/manager/css/github-markdown-$theme.css") 
        ? APP_PATH_WEBROOT . "ExternalModules/manager/css/github-markdown-$theme.css"
        : APP_PATH_WEBROOT . "ExternalModules/manager/css/markdown.css";
    include $css_file;
    ?>
    .markdown-body {
        margin: 10px auto;
    }
    </style>
</head>
<body class="markdown-body">

<?php

$pagePath = APP_PATH_WEBROOT . 'Resources/misc/shib_table_auth_documentation/shib_table_readme.md';
require_once APP_PATH_WEBROOT . 'Libraries/Parsedown.php';

$Parsedown = new \Parsedown();
$html = $Parsedown->text(file_get_contents($pagePath));

$search = '<img src="';
// Cannot use APP_PATH_WEBROOT here for some reason, must be locally pathed
$replace = $search . '../Resources/misc/shib_table_auth_documentation/';
$html = str_replace($search, $replace, $html);

print($html);


?>
</body>
</html>
