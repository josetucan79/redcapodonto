<?php
$page = new HtmlPage();
$page->PrintHeaderExt();

$module = new TableauConnector();
$module->printConnectorPageContent();

print '</body></html>';