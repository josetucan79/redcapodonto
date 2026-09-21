<?php

$sql = "";

// If db structure is using utf8mb4 but connection is still configured to use utf8mb3, 
// change db_character_set and db_collation to ensure character set is aligned.
global $db_character_set, $db_collation;
if (SQLTableCheck::using_utf8mb4() && !empty($db_character_set) && in_array(strtolower($db_character_set),['utf8mb3', 'utf8'])) {
	$sql .= "\n\n-- Upgrade Client/Connection Character Set to UTF8MB4 -- \n-- Since current installation is using 4-byte UTF-8 for its database structure (utf8mb4), but is still configured to use 3-byte UTF-8 (utf8/utf8mb3) for its database connection, update the connection charset and collation configs to ensure that any values consisting of 4-byte characters are read and saved properly. (Since the current connection encoding, utf8mb3, is a subset of the current database column encoding, utf8mb4, no additional text conversion steps are needed prior to making this change.)\n";
	$sql .= "UPDATE redcap_config SET `value` = 'utf8mb4' WHERE field_name = 'db_character_set' AND lower(`value`) IN ('utf8', 'utf8mb3');\nUPDATE redcap_config SET `value` = 'utf8mb4_unicode_ci' WHERE field_name = 'db_collation' AND lower(`value`) like 'utf8%';";        
	}

print $sql;
?>
