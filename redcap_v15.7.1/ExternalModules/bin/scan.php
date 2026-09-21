<?php namespace ExternalModules;

/**
 * This scan used to be web based and run via the vanderbilt_external_modules_submission_v1.0 module.
 * We transitioned to a command line scan because:
 *  - The web based scan was always a bit hacky, and seemed to randomly miss taints present on the command line (e.g. email alerts 2.4.2).
 *  - Some modules include circular dependencies that cause Psalm to run indefinitely.  Killing run away processes is easier on the command line, and safer when they don't occur in PROD in the first place.
 */

/**
 * This function exists partially to limit the number of exit() references
 * so DisallowedFunctionSniff does not have to be updated when running this script.
 */
$exit = function($code, $message = null){
    if($message !== null){
        echo "$message\n";
    }

    exit($code);
};


/**
 * We used to avoid REDCap connect here via the following code,
 * but paths didn't quite work right when running EM tests from REDCap core's ExternalModules dir (WITHOUT an external_modules dev dir present).
 * Also, the goal was to avoid requiring a DB connection for psalm scans, but that is moot currently since we
 * set redcap_connect.php as Psalm's autoloader.  We may revisit this in the future.
 * 
 * $emRoot = __DIR__ . '/../';
 * $docRoot = $emRoot . '../'; // This isn't right.  It doesn't include the REDCap version dir, even thought it should.
 * if(basename(dirname(__DIR__)) !== 'external_modules'){
 *     $docRoot .= '../';
 * }
 * 
 * define('APP_PATH_DOCROOT', $docRoot);
 * define('APP_PATH_TEMP', "$docRoot/temp");
 * define('APP_PATH_EXTMOD', $emRoot);
 * define('APP_PATH_WEBROOT', 'not used, but must be defined to avoid redcap_connect.php require in ExternalModules.php');
 */
require_once __DIR__ . '/../redcap_connect.php';

if(!ExternalModules::installScanScriptIfNecessary()){
    $exit(1, "This command requires write access to the following file:\n" . ExternalModules::getScanScriptPath());
}

if(!is_writable(APP_PATH_TEMP)){
    $exit(1, 'The following temp directory must exist and be writable by the user running this command: ' . APP_PATH_TEMP);
}

require_once __DIR__ . '/../classes/ExternalModules.php';
require_once __DIR__ . '/../classes/Scan.php';

if (!function_exists('str_starts_with')) {
    function str_starts_with (string $haystack, string $needle)
    {
        return empty($needle) || strpos($haystack, $needle) === 0;
    }
}

$outputFile = false;

// array_values() is used to reset numeric indices to sequential (array_filter() leaves gaps)
$argv = array_values(array_filter($argv, function($arg) use ($exit, &$outputFile){
    $parts = explode('--', $arg);
    if($parts[0] === ''){
        // This is an argument starting in '--'.
        if($arg === '--output=html-file'){
            $outputFile = true;
        }
        else if($arg === '--prompt'){
            do{
                /**
                 * Writing the prompt to STDERR is required inside VUMC's deploy script
                 * to see it before readline() finishes.
                 */
                $message = "\nWould you like to run the security scan (y/n)? ";
                $message = "\033[1;33m{$message}\033[0m"; // Color the message yellow like the deploy script questions
                fwrite(STDERR, $message);
                $value = readline();
            }
            while(!in_array($value, ['y', 'n']));

            if($value === 'n'){
                $exit(0, "Scan cancelled.");
            }
        }
        else if($arg === Scan::SKIP_LONG_RUNNING_CHECKS){
            Scan::$performLongRunningChecks = false;
        }
        else if($arg === Scan::SKIP_CLEAN_REPO_CHECK){
            Scan::$performCleanRepoCheck = false;
        }
        else if ($arg === '--debug') {
            return true;
        }
        else {
            $exit(1, "The following argument is not supported: $arg");
        }

        return false;
    }
    else{
        return true;
    }
}));

Scan::verifyCleanGitDirs(); // Do this before downloading the module

$modulePath = $argv[1] ?? null;
$isHttp = str_starts_with($modulePath ?? '', 'https://');
if (empty($modulePath) || (!$isHttp && !file_exists($modulePath))){
    $exit(1, "
Please specify a directory path or HTTPS zip URL for an external module, plugin, or hook.
To write output to an HTML file instead of printing it, add the '--output=html-file' argument.
");
}

if($outputFile){
    if($isHttp){
        $parts = explode('/', $modulePath);
        $filename = pathinfo(end($parts), PATHINFO_FILENAME);
        $outputFile = $parts[4] . '_' . $filename;
    }
    else{
        /**
         * realpath() is used in case the $modulePath is something like "."
         */
        $outputFile = basename(realpath($modulePath));
    }

    $outputFile = getcwd() . "/redcap-scan_$outputFile.html";

    if(file_exists($outputFile)){
        echo "The file '$outputFile' already exists.  Please remove, move, or rename it in order to run a new scan.\n";
        return;
    }
}

$tempPath = realpath(tempnam(APP_PATH_TEMP, 'module-scan-'));
unlink($tempPath);

if(
    $isHttp
    ||
    // It could be a local path to a zip file
    !is_dir($modulePath)
){
    mkdir($tempPath);
    ExternalModules::downloadModuleZip($modulePath, $tempPath, $exit);
    if(Scan::isManuallyUploadedGitHubAssetUrl($modulePath)){
        Scan::verifyManuallyUploadedGitHubAsset($modulePath, $tempPath, $exit);
    }
}
else{
    /**
     * We tried scanning modules in place, but we really need write access to 
     * add/overwrite psalm.xml in the same directory to make sure psalm works as expected.
     * We can't guarantee that with modules owned by apache, so it's easier to just copy the module dir.
     */
    ExternalModules::copyRecursively($modulePath, $tempPath);
}

$cleanup = function() use ($tempPath) {
    chdir('..'); // On Windows, free up the directory to be removed.
    ExternalModules::rrmdir($tempPath);
};

// Cleanup when the process ends normally or fails for some reason
register_shutdown_function($cleanup);

if(function_exists('pcntl_signal')){
    // Cleanup after Ctrl-C on *nix.
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function() use ($exit, $cleanup){
        $cleanup();
        $exit(1, ""); // The new line simulates the behavior without this pcntl_signal() call.
    });
}

$returnCode = Scan::run($outputFile, $tempPath);
$exit($returnCode);
