#!/bin/sh
echo 'Saving cachegrind files to /tmp by default...'
XDEBUG_TRIGGER=1 php `php bin/get-phpunit-path.php`