<?php
namespace Vanderbilt\REDCap\Classes\Traits;

trait CanCreateDirectories {

	function makeDir($path) {
        return file_exists($path) || mkdir($path, 0777, true);
    }
    
}