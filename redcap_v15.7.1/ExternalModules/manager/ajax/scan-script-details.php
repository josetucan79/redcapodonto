<?php namespace ExternalModules;

require_once __DIR__ . '/../../redcap_connect.php';
if(!ACCESS_CONTROL_CENTER){
    echo ExternalModules::tt('em_errors_128');
    return;
}

$getCommandPath = function($parts){
    $commandSuffix = '';
    if(PHP_OS_FAMILY === "Windows"){
        $commandSuffix = '.bat';
    }

    array_unshift($parts, '<redcap-root>');
    return htmlspecialchars(implode(DS, $parts)) . $commandSuffix;
};

?>
<style>
    .simpleDialog pre{
        padding: 6px;
    }
    .simpleDialog a{
        text-decoration: underline;
        outline:none;
    }
</style>
<p>
    The automated security scan described at the top of the <a href='https://redcap.vumc.org/consortium/modules/index.php'>REDCap Repo</a>
    can be run manually from your system's command line on any REDCap instance.
    This feature is intentionally NOT included as part of REDCap's UI, as security teams would frown on listing vulnerabilities within the application itself.
</p>
<?php

if(!file_exists(ExternalModules::getScanScriptPath())){
    $scanInstallMessage = "The scan script must be installed on your system in order to run the command below.";

    if(is_writable(ExternalModules::getREDCapRootPath())){
        $scanInstallMessage = "
            <p>$scanInstallMessage</p>
            <p>
                <button id='external-module-scan-script-install-button'>Click here to install the scan script to your REDCap root directory</button>
                <script>
                    $('#external-module-scan-script-install-button').click(() => {
                        $.get(" . json_encode(APP_URL_EXTMOD_RELATIVE . 'bin/install-scan-script.php') . ", (data) => {
                            simpleDialog(data, 'Scan Script Install Result')
                        })
                    })
                </script>
            </p>
        ";
    }
    else{
        $emDevDirName = ExternalModules::DEV_DIR_NAME;
        if(file_exists(dirname(APP_PATH_DOCROOT) . DS . $emDevDirName)){
            $extModRelativePath = $emDevDirName;
        }
        else{
            $extModRelativePath = 'redcap_v' . REDCAP_VERSION . DS . 'ExternalModules';
        }

        $scanInstallMessage .= "
            REDCap does not have permission to do this automatically on your system.
            Please run following command to install the scan script under your REDCap root directory before proceeding:
            <pre>php " . $getCommandPath([$extModRelativePath, 'bin', 'install-scan-script.php']) . "</pre>
        ";
    }

    echo "<p>$scanInstallMessage</p>";
}

?>
<p>
    To scan a module, run the following command (after replacing the portions in angle brackets):
    <pre><?=$getCommandPath(['bin', 'scan']) . htmlspecialchars(' <path-to-module>')?></pre>
</p>
<p>
    If you receive an error about "php" not being found or recognized, you likely need to
    <a href='https://www.google.com/search?q=how+do+I+add+php+to+my+PATH'>add php to your PATH environment variable</a>.
</p>
<p>
    If you run REDCap inside of a docker container, such as <a href="https://github.com/123andy/redcap-docker-compose" target="_blank">REDCap-Docker-Compose</a>, please review the <a href="https://github.com/123andy/redcap-docker-compose/blob/master/rdc/documentation/README.md#scanning-external-modules-for-repo-submission" target="_blank">documentation here</a> for assistance in running the scan script.  While docker is the preferred development setup for many people, is it worth noting that the scan script will likely be an order of magnitude slower in docker due to the performance hit from cross filesystem volume mounts.  When run natively (or in WSL), the scan script takes less than 15 seconds on most modules.  It is possible to run the scan script natively against a docker MySQL container by <a href="https://docs.docker.com/guides/docker-concepts/running-containers/publishing-ports/#use-docker-compose" target="_blank">publishing</a> the MySQL port.
</p>