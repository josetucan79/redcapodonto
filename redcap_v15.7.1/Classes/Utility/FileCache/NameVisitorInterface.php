<?php
namespace Vanderbilt\REDCap\Classes\Utility\FileCache;


/**
 * interface for the file name modifier
 */
interface NameVisitorInterface
{

    /**
     *
     * @param string $key
     * @param string $hashedFilename
     * @param string $extension
     * @return array [$hashedFilename, $extension]
     */
    function visit($key, $hashedFilename, $extension);

 }