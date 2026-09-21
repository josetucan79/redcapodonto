<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Exceptions;

use Throwable;

class SaveException extends \Exception
{
    /**
     * @var array
     */
    public $issues;

    public function __construct($message = "", $issues = [], $code = 0, Throwable $previous = null)
    {
        parent::__construct(
            $message,
            $code,
            $previous
        );

        // Array of strings as returned by /redcap_vX.X.X/Classes/Records->saveData()
        $this->issues = $issues;
    }

    /**
     * See parseIssues static method
     * @return array
     */
    public function parsedIssues()
    {
        return self::parseIssues($this->issues);
    }

    /**
     * REDCap saveData() errors come in a variety of formats. parsedIssues() attempts to turn CSV strings into
     * associative arrays so we can attempt to recover from an error
     *
     * See /redcap_vX.X.X/Classes/Records->saveData(). Search for errors_array. Various formats we might receive:
     *
     * 1) "1","myfield","2","The value is not a valid category for myfield"
     *    -> "1" = Record ID
     *    -> "myfield" = Field name
     *    -> "2" = Value of "myfield"
     *    -> "The value is not a valid category for myfield" = Description of problem
     *
     * 2) "1","myfield",[data_import_tool_229]|[data_import_tool_230] (NOT YET TESTED)
     *    -> "1" = Record ID
     *    -> "myfield" = Field name
     *    -> "This field name does not exist in the project." = Description of problem, example of data_import_tool_230
     *
     * 3) "1","myfield","something",[data_import_tool_229]|[data_import_tool_230] (NOT YET TESTED)
     *    -> "1" = Record ID
     *    -> "myfield" = Field name
     *    -> "something" = ??? what is this
     *    -> "CHECKBOX RENAME ERROR..." = Description of problem, example of data_import_tool_229
     *
     * In most cases it seems that we should expect a comma-separated value list where index 0 is the record ID and
     * index 1 contains the offending field
     *
     * @return array
     */
    public static function parseIssues($issues)
    {
        $parsed = [];
        foreach ($issues as $issue) {
            $p = [
                'raw' => $issue,
                'parseSuccessful' => false,
                'record' => null,
                'key' => null,
                'val' => null,
                'description' => null
            ];

            $exploded = explode('","', $issue);
            if (count($exploded) === 3) {
                $p['parseSuccessful'] = true;
                $p['record'] = str_replace('"', '', $exploded[0]);
                $p['key'] = str_replace('"', '', $exploded[1]);
                $p['description'] = str_replace('"', '', $exploded[2]);
            } elseif (count($exploded) === 4) {
                $p['parseSuccessful'] = true;
                $p['record'] = str_replace('"', '', $exploded[0]);
                $p['key'] = str_replace('"', '', $exploded[1]);
                $p['val'] = str_replace('"', '', $exploded[2]);
                $p['description'] = str_replace('"', '', $exploded[3]);
            }

            $parsed[] = $p;
        }
        return $parsed;
    }
}
