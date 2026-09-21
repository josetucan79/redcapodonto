<?php

$sql = "";

## Remove a column from redcap_outgoing_email_counts if it got added in previous version
$sql2 = "SHOW CREATE TABLE redcap_outgoing_email_counts";
$q = db_query($sql2);
if ($q && db_num_rows($q) == 1)
{
	// Get the 'create table' statement to parse
	$result = db_fetch_array($q);
	$createTableStatement = strtolower($result[1]);
	if (strpos($createTableStatement, "twilio_sms") !== false) {
		$sql .= "\n-- Remove column from redcap_outgoing_email_counts\n";
		$sql .= "ALTER TABLE `redcap_outgoing_email_counts` DROP `twilio_sms`;\n";
	}
}


print $sql;