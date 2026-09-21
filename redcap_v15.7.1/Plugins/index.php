<?php


// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// Display all REDCap Plugin documentation
PluginDocs::displayPluginMethods();

// Add some CSS
print RCView::style(<<<END
    .mm a {
        font-size: 13px;
    }
END);