<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

if(!ACCESS_CONTROL_CENTER){
    echo ExternalModules::tt('em_errors_128');
    return;
}

$items = [];
foreach(ExternalModules::getLinks() as $link){
    $prefix = $link['prefix'];
    $framework_instance = ExternalModules::getFrameworkInstance($prefix);
    $module_instance = $framework_instance->getModuleInstance();
    
    $new_link = $module_instance->redcap_module_link_check_display(null, $link);
    if ($new_link) {
        if (is_array($new_link)) {
            $link = $new_link;
        }
        
        $items[] = $framework_instance->getLinkIconHtml($link);
    }
}

echo json_encode($items);