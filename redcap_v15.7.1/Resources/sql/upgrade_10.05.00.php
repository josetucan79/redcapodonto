<?php


// Add Messenger system notification
$title = "New Action Tag and Special Functions";
$msg = "REDCap has a new action tag (@SETVALUE) that will set a field's value to static text or dynamic/piped text whenever a data entry form or survey page is loaded, in which it will always overwrite an existing value of the field. The format must follow the pattern @SETVALUE='????' with the desired value inside quotes. For text fields, you may pipe and concatenate values from other fields in the project - e.g., @SETVALUE='Name: [first_name] [last_name], DOB: [dob]'. To learn more, click the red 'Action Tag' button on the Project Setup or Online Designer pages in any project.

Additionally, nine new special functions have been added to REDCap: left(), right(), mid(), length(), find(), trim(), upper(), lower(), and concat(). These new functions can be specifically used when dealing with text values and may be especially useful when using them in conjunction with the @CALCTEXT action tag. To learn more and to see some practical examples of their usage, click the blue 'Special Functions' button in the Online Designer in any project.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);