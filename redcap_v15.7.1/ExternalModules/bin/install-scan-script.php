<?php namespace ExternalModules;
require_once __DIR__ . '/../redcap_connect.php';

if(!ExternalModules::isCommandLine() && !ACCESS_CONTROL_CENTER){
    echo ExternalModules::tt('em_errors_128');
    return;
}

if(ExternalModules::installScanScriptIfNecessary(true)){
    echo "The scan script has been installed.";
}
else{
    echo "Write permission to the REDCap root directory is required.";
}

echo "\n";