<?php
require_once(dirname(__FILE__, 5).'/redcap_connect.php');

header('Content-Type: text/javascript');
$manifestPath = __DIR__ . '/dist/.vite/manifest.json';
$manifest = json_decode(file_get_contents($manifestPath), true);
$entryKey = 'src/libs.js';
$jsFile = $manifest[$entryKey]['file'];   // e.g. libs.es.js
$cssFile = $manifest[$entryKey]['css'][0]; // e.g. libs.css
$root = APP_PATH_JS . basename(__DIR__);

ob_start();
?>
import { useAssetLoader } from '<?= $root ?>/dist/<?php echo $jsFile; ?>';
const assetLoader = useAssetLoader();
const styleUrl = new URL('<?= $root ?>/dist/<?php echo $cssFile; ?>', import.meta.url).href;
assetLoader.loadStylesheet(styleUrl);
export * from '<?= $root ?>/dist/<?php echo $jsFile; ?>';
<?php
$scriptTag = ob_get_clean();
echo $scriptTag;
