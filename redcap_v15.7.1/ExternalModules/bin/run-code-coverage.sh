#!/bin/sh

XDEBUG_MODE=coverage php `php bin/get-phpunit-path.php` --coverage-filter classes/ --coverage-clover cov.xml