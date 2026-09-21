<?php

$sql = "
ALTER TABLE `redcap_user_information` ADD `messaging_email_general_system` TINYINT(1) NOT NULL DEFAULT '1' AFTER `messaging_email_ts`;

-- (OPTIONAL) Uncomment the query below to disable REDCap Messenger email notifications for General/System Notifications by default for all users (otherwise will be enabled for all users)
-- update redcap_user_information set messaging_email_general_system = 0;

";

print $sql;


// Add Messenger system notification
$title = "New action tags: @CALCDATE and @CALCTEXT";
$msg = "Two new action tags have been added to REDCap. @CALCDATE and @CALCTEXT are both pseudo-calc fields, in which they are date/datetime and text fields, respectively, but they behave similarly to calculated fields.

The action tag @CALCDATE performs a date calculation by adding or subtracting a specified amount of time from a specified date or datetime field and then provides the result as a date or datetime value - e.g., @CALCDATE( [visit_date], 7, 'd' ). 

The action tag @CALCTEXT evaluates logic that is provided inside a @CALCTEXT() function and outputs the result as text, typically performed with an if(x,y,z) function - e.g., @CALCTEXT( if([age] >= 18, 'adult', 'child') ).

To learn more about these new action tags and how to use them, click the red 'Action Tag' button on the Project Setup or Online Designer pages in any project.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);