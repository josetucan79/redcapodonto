<?php namespace ExternalModules\Sniffs\Misc;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class SharedDisallowedFunctionSniff implements Sniff{
    private $filterTagsCount = 0;

    function register()
    {
        return [T_STRING];
    }

    function process(File $file, $position)
    {
        $content = $file->getTokens()[$position]['content'];
        if(
            $content === 'filter_tags'
            &&
            !$this->shouldSkip($file)
        ){
            $file->addWarning('The filter_tags() function was used instead of $module->escape().  This warning can be ignored if user input itself is HTML and cannot be split into discrete escapable fields.', $position, 'Found');
        }
    }

    function shouldSkip($file){
        $this->filterTagsCount++;

        if(
            (
                file_exists(dirname(dirname($file->path)) . '/FlightTrackerExternalModule.php')
                &&
                str_ends_with($file->path, 'classes/Sanitizer.php')
                &&
                $this->filterTagsCount === 1 // Only one filter_tags() call is currently expected
            )
            ||
            (
                file_exists(dirname(dirname($file->path)) . '/DashboardAnalysisPlatformExternalModule.php')
                &&
                str_ends_with($file->path, 'classes/StatsCharts.php')
                &&
                $this->filterTagsCount === 1 // Only one filter_tags() call is currently expected
            )
            ||
            (
                file_exists(dirname($file->path) . '/EmailTriggerExternalModule.php')
                &&
                str_ends_with($file->path, 'previewRecordForm.php')
                &&
                $this->filterTagsCount === 1 // Only one filter_tags() call is currently expected
            )
            ||
            (
                file_exists(dirname($file->path) . '/FaqMenuExternalModule.php')
                &&
                $this->filterTagsCount <= 8
            )
        ){
            return true;
        }

        return false;
    }
}
