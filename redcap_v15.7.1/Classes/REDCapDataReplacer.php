<?php

namespace Vanderbilt\REDCapDataReplacer;

# NOTE: assumes one project_id per query to TARGET

class REDCapDataReplacer {
    const TARGET = "redcap_data";
    const TEST_TABLE = "REPLACED_TABLE";
    const PROJECT_ID = "project_id";
    const EVENT_ID = "event_id";
    const QUOTE_ARRAY = ["'", "\""];
    const STRING_REPLACEMENT_PREFIX = "~~~STRING";
    const STRING_REPLACEMENT_SUFFIX = "~~~CLOSE";
    const N_RETURN = "NRETURN";
    const R_RETURN = "RRETURN";

    # https://dev.mysql.com/doc/refman/8.0/en/charset-charsets.html
    const CHARSETS = [
        "armscii8",
        "ascii",
        "big5",
        "binary",
        "cp1250",
        "cp1251",
        "cp1256",
        "cp1257",
        "cp850",
        "cp852",
        "cp866",
        "cp932",
        "dec8",
        "eucjpms",
        "euckr",
        "gb18030",
        "gb2312",
        "gbk",
        "geostd8",
        "greek",
        "hebrew",
        "hp8",
        "keybcs2",
        "koi8r",
        "koi8u",
        "latin1",
        "latin2",
        "latin5",
        "latin7",
        "macce",
        "macroman",
        "sjis",
        "swe7",
        "tis620",
        "ucs2",
        "ujis",
        "utf16",
        "utf16le",
        "utf32",
        "utf8mb3",
        "utf8mb4",
    ];

    private static function replaceReturnsWithCode($sql) {
        return $sql;
        $sql = str_replace("\n", self::N_RETURN, $sql);
        return str_replace("\r", self::R_RETURN, $sql);
    }

    public static function replaceCodeWithReturns($sql) {
        return $sql;
        $sql = str_replace(self::N_RETURN, "\n", $sql);
        return str_replace(self::R_RETURN, "\r", $sql);
    }

    public function __construct($sql, $params) {
        $this->sql = self::replaceReturnsWithCode($sql);
        $this->params = $params;
        $this->test = FALSE;
        $this->verbose = FALSE;
    }

    public function setTest($b) {
        $this->test = $b;
    }

    public function setVerbose($b) {
        $this->verbose = $b;
    }

    # MAIN FUNCTION: Turns the query into an adjusted string
    # https://dev.mysql.com/doc/refman/8.0/en/sql-data-manipulation-statements.html
    public function adjustREDCapData() {
        if (
            preg_match("/\b".self::TARGET."\b/", $this->sql)
            && (
                $this->getID(self::PROJECT_ID)
                || $this->getID(self::EVENT_ID)
            )
        ) {
            $sql = $this->sql;
            $project_id = $this->getProjectId();
            if ($this->verbose) {
                echo "Begin: $sql\n";
                echo "Project_id: $project_id\n";
            }
            $strings = self::getStrings($sql);
            if ($this->verbose) {
                echo "Strings: $sql ".json_encode($strings)."\n";
            }
            $sql = self::replaceStringsWithCode($sql, $strings);
            if ($this->verbose) {
                echo "Encoded: $sql\n";
            }
            $sql = $this->replaceAllREDCapData($sql, $project_id);
            if ($this->verbose) {
                echo "Targets changed: $sql\n";
            }
            $sql = self::replaceCodeWithStrings($sql, $strings);
            if ($this->verbose) {
                echo "Decoded: $sql\n";
                echo "\n";
            }
            return self::replaceCodeWithReturns($sql);
        } else {
            if ($this->verbose) {
                echo "Returning native {$this->sql}\n";
            }
            return self::replaceCodeWithReturns($this->sql);
        }
    }

    # gets $variable = ... from the WHERE clause of a query
    public function getFromWhereClause($variable) {
        # use first $variable ---> TODO is this a weakness?
        if (preg_match("/`?$variable`?\s*=\s*\?/", $this->sql)) {
            $start = preg_split("/$variable`?\s*=/", $this->sql)[0];
            $index = substr_count($start, "?");
            return $this->params[$index] ?? FALSE;
        } else if (preg_match("/`?$variable`?\s*=\s*(['\"])([\s\S]+?)\\1/", $this->sql, $matches)) {
            return $matches[2];
        } else if (preg_match("/`?$variable`?\s*=\s*(\d+)/", $this->sql, $matches)) {
            return $matches[1];
        } else if (preg_match("/(['\"])([\s\S]+?)\\1\s*=\s*`?$variable`?/", $this->sql, $matches)) {
            return $matches[2];
        } else if (preg_match("/\b(\d+)\s*=\s*`?$variable`?/", $this->sql, $matches)) {
            return $matches[1];
        } else if (preg_match("/`?$variable`?\s*=\s*NULL/i", $this->sql, $matches)) {
            return NULL;
        } else {
            return FALSE;
        }
    }

    # gets the first instance of $variable's value from a query
    public function getID($variable) {
        $insertRegexStr = "^\s*INSERT\s+(LOW_PRIORITY\s+|DELAYED\s+|HIGH_PRIORITY\s+)?(IGNORE\s+)?(INTO\s+)?`?".self::TARGET."`?\s*(PARTITION\s+\([\w\s,]+\)\s+)?";
        $replaceRegex = "/^\s*REPLACE\s+(LOW_PRIORITY\s+|DELAYED\s+)?(INTO\s+)?`?".self::TARGET."`?\s+(PARTITION\s+\([\w\s,]+\)\s+)?/i";
        $updateRegex = "/^\s*UPDATE\s+(LOW_PRIORITY\s+)?(IGNORE\s+)?`?".self::TARGET."`?\s+/i";
        if (preg_match("/$insertRegexStr\(.*\b$variable\b.*\)/i", $this->sql)) {
            $end = preg_replace("/$insertRegexStr/i", "", $this->sql);
            return $this->getIDFromColValues($variable, $end);
        } else if (preg_match("/$insertRegexStr\sVALUES/i", $this->sql)) {
            # redcap_data but no column specifiers
            $redcapDataIndex = 0;
            $end = preg_replace("/$insertRegexStr/i", "", $this->sql);
            return $this->getIDFromColValues($redcapDataIndex, $end);
        } else if (preg_match($replaceRegex, $this->sql)) {
            $end = preg_replace($replaceRegex, "", $this->sql);
            return $this->getIDFromColValues($variable, $end);
        } else if (preg_match($updateRegex, $this->sql)) {
            if (
                preg_match("/WHERE[\S\s]+\b`?$variable`?\b\s*=\s*/i", $this->sql)
                || preg_match("/WHERE[\S\s]+\s*=\s*\b`?$variable`?\b/i", $this->sql)
            ) {
                return $this->getFromWhereClause($variable);
            } else if (preg_match("/SET[\s\S]+`?$variable`?\s*=\s*/i", $this->sql)) {
                $end = preg_replace($updateRegex, "", $this->sql);
                $setBlock = preg_replace("/(WHERE[\s\S]+)?(ORDER\s+BY\s[\s\S]+)?(LIMIT\s[\s\S]+)?\s*$/i", "", $end);
                return $this->getIDFromSetBlock($variable, $setBlock);
            } else {
                # no $variable in WHERE or SET clause
                return FALSE;
            }
        } else if (
            preg_match("/WHERE[\S\s]+\b`?$variable`?\b\s*=\s*/i", $this->sql)
            || preg_match("/WHERE[\S\s]+\s*=\s*\b`?$variable`?\b/i", $this->sql)
        ) {
            return $this->getFromWhereClause($variable);
        } else {
            return FALSE;
        }
    }

    # gets the first value of $variable from the $tail which stores the column values
    # $variable can be an index in the values list
    private function getIDFromColValues($variable, $tail) {
        # omitting VALUE() syntax because it's not applicable to redcap_data table
        if (is_numeric($variable) && preg_match("/^VALUES\s*([\s\S]+)/i", $tail, $matches)) {
            $index = $variable;
            $allValuesString = $matches[1];
            return $this->getIndexFromFirstValues($index, $allValuesString);
        } else if (preg_match("/^\((.*$variable.*)\)\s+VALUES\s*([\s\S]+)/i", $tail, $matches)) {
            $colList = $matches[1];
            $fields = preg_split("/\s*,\s*/", preg_replace("/['\"]/", "", $colList));
            $idIndex = array_search($variable, $fields, TRUE);
            if ($idIndex !== FALSE) {
                # TODO -- does this create a weakness by choosing the first $variable?
                $allValuesString = $matches[2];
                return $this->getIndexFromFirstValues($idIndex, $allValuesString);
            } else {
                return FALSE;
            }
        } else {
            $value = $this->getIDFromSetBlock($variable, $tail);
            return $value;
        }
    }

    private function getIndexFromFirstValues($index, $valuesString) {
        # use first line --> don't need to parse multiple lines
        $rowConstructorRemoved = preg_replace("/^ROW/", "", $valuesString);
        $dataFirst = preg_replace("/^\s*\(/", "", $rowConstructorRemoved);
        $startIndex = 0;
        $endIndex = $startIndex + 1;
        $valuesIndexCount = 0;
        while (($startIndex < strlen($dataFirst)) && ($endIndex < strlen($dataFirst))) {
            $found = FALSE;
            $substr = substr($dataFirst, $startIndex, ($endIndex - $startIndex));
            $value = "";
            if (preg_match("/^(['\"])([\s\S]+)\\1$/", $substr, $valueMatchAry)) {
                $value = $valueMatchAry[2];
                $found = TRUE;
            } else if (preg_match("/^(\d+)$/", $substr, $valueMatchAry)) {
                $value = $valueMatchAry[1];
                $found = TRUE;
            } else if ($substr == "?") {
                $questionMarkIndex = substr_count(substr($dataFirst, 0, $startIndex), "?");
                $value = $this->params[$questionMarkIndex] ?? FALSE;
                $found = TRUE;
            }
            if ($found && ($valuesIndexCount == $index)) {
                return $value;
            } else if ($found) {
                # match but wrong index --> move forward
                $valuesIndexCount++;
                $startIndex = $endIndex;
                $endIndex = $startIndex + 1;
            } else if (in_array($substr, [" ", "\r", "\n", "\t", "(", ")", ","])) {
                # outside a value because no quotes --> move everything forward
                $startIndex = $endIndex + 1;
                $endIndex = $startIndex + 1;
            } else {
                $endIndex++;
            }
        }
        return FALSE;
    }

    # gets the value of $variable from a SET block represented in $tail
    private function getIDFromSetBlock($variable, $tail) {
        if (preg_match("/SET\s+.*`?$variable`?\s*=\s*/i", $tail)) {
            if (preg_match("/`?$variable`?\s*=\s*['\"]?(\d+)['\"]?/", $tail, $matches)) {
                return $matches[1];
            } else if (preg_match("/`?$variable`?\s*=\s*\?/", $tail)) {
                $start = preg_split("/`?$variable`?\s*=/", $tail)[0];
                $index = substr_count($start, "?");
                return $this->params[$index] ?? FALSE;
            } else {
                return FALSE;
            }
        } else if (preg_match("/SET\s+[\s\S]*\s*=\s*`?$variable`?/i", $tail)) {
            if (preg_match("/\b['\"]?(\d+)['\"]?\s*=\s*`?$variable`?/", $tail, $matches)) {
                return $matches[1];
            } else if (preg_match("/\?\s*=\s*`?$variable`?/", $tail)) {
                $start = preg_split("/=\s*`?$variable`?/", $tail)[0];
                $index = substr_count($start, "?");
                return $this->params[$index] ?? FALSE;
            }
        } else {
            return FALSE;
        }
    }

    # gets the requested data table for $project_id. If not supported, then returns redcap_data
    private function getDataTable($project_id) {
        if (method_exists("\Records", "getDataTable")) {
            return \Records::getDataTable($project_id);
        } else {
            return self::TARGET;
        }
    }

    # gets the project_id of the requested query
    public function getProjectId() {
        $projectId = $this->getID(self::PROJECT_ID);
        if (!$projectId) {
            $eventId = $this->getID(self::EVENT_ID);
            if ($eventId) {
                return $this->getProjectIdFromEventId($eventId);
            }
        }
        return $projectId;
    }

    # transforms an event_id into a project_id via a query to MySQL
    public function getProjectIdFromEventId($eventId) {
        if ($this->test) {
            return 1;
        }
        $sql = "SELECT a.project_id AS project_id FROM redcap_events_metadata AS m INNER JOIN redcap_events_arms AS a ON m.arm_id = a.arm_id WHERE m.event_id = ?";
        $params = [$eventId];
        if (!$this->test) {
            $result = db_query($sql, $params);
            if ($row = $result->fetch_assoc()) {
                return $row["project_id"] ?? "";
            }
        }
        return "";
    }

    # replace all instances of redcap_data in $sql for pid $project_id
    public function replaceAllREDCapData($sql, $project_id) {
        if ($project_id) {
            if ($this->test) {
                $newSQL = preg_replace("/\b".self::TARGET."\b/", self::TEST_TABLE, $sql);
                return $newSQL;
            } else {
                return preg_replace("/\b".self::TARGET."\b/", $this->getDataTable($project_id), $sql);
            }
        } else {
            return $sql;
        }
    }

    # replace all instances of elements in $strings in $sql with an encoded value
    public static function replaceCodeWithStrings($sql, $strings) {
        foreach ($strings as $stringIndex => $string) {
            $sql = str_replace(self::STRING_REPLACEMENT_PREFIX.$stringIndex.self::STRING_REPLACEMENT_SUFFIX, $string, $sql);
        }
        return $sql;
    }

    # replace all encoded values in $sql with their corresponding items in $strings
    public static function replaceStringsWithCode($sql, $strings) {
        $i = 0;
        $isInString = FALSE;
        $stringStart = "";
        while ($i < strlen($sql)) {
            $restart = FALSE;
            if (!$isInString && in_array($sql[$i], self::QUOTE_ARRAY)) {
                $stringStart = $sql[$i];
                $isInString = TRUE;
            } else if ($isInString && ($stringStart == $sql[$i])) {
                $isInString = FALSE;
                $stringStart = "";
            }
            if ($isInString) {
                $substr = substr($sql, $i);
                foreach ($strings as $stringIndex => $string) {
                    $stringIndexMatch = strpos($substr, $string);
                    if ($stringIndexMatch === 0) {
                        $sql = substr_replace($sql, self::STRING_REPLACEMENT_PREFIX . $stringIndex . self::STRING_REPLACEMENT_SUFFIX, $i, strlen($string));
                        $restart = TRUE;
                        break;
                    }
                }
            }
            if ($restart) {
                $isInString = FALSE;
                $stringStart = "";
                $i = 0;
            } else {
                $i++;
            }
        }
        return $sql;
    }

    # https://dev.mysql.com/doc/refman/8.0/en/string-literals.html
    # get all the strings from a $sql query
    # handles all the supported ways of quotations
    public static function getStrings($sql) {
        $isInString = FALSE;
        $stringStart = "";
        $startI = 0;
        $strings = [];
        $isPriorEscaped = FALSE;
        $firstPartOfDoubleQuote = FALSE;

        $quoteBegin = "QUOTE\\(";
        $quoteEnd = "\\)";
        $i = 0;
        while ($i < strlen($sql)) {
            $nextChar = $sql[$i+1] ?? "";
            $prevChar = $sql[$i-1] ?? "";
            $lastPartOfDoubleQuote = $firstPartOfDoubleQuote && ($sql[$i] == $prevChar) && in_array($sql[$i], self::QUOTE_ARRAY);
            $firstPartOfDoubleQuote = ($sql[$i] == $nextChar) && in_array($sql[$i], self::QUOTE_ARRAY);
            $remainingSubstr = substr($sql, $i);

            if (!$isInString && in_array($sql[$i], self::QUOTE_ARRAY)) {
                $stringStart = $sql[$i];
                $isInString = TRUE;
                $startI = $i;
            } else if (
                !$isInString
                && preg_match("/^$quoteBegin"."[\s\S]+?$quoteEnd/", $remainingSubstr, $matches)
            ) {
                $strings[] = $matches[0];
                $startI = 0;
                $i += strlen($matches[0]) - 1;
            } else if (
                !$isInString
                && (
                    (
                        preg_match("/^_/", $remainingSubstr)
                        && preg_match("/\s/", $prevChar)
                    )
                    || preg_match("/^[nN]['\"]/", $remainingSubstr)
                )
            ) {
                self::tryToMatchALiteral($remainingSubstr, $strings, $i, $startI);
            } else if (
                $isInString
                && !$isPriorEscaped
                && ($stringStart == $sql[$i])
                && !$firstPartOfDoubleQuote
                && !$lastPartOfDoubleQuote
            ) {
                if (preg_match("/^['\"]\s*['\"]/", $remainingSubstr, $matches)) {
                    $concatRegion = $matches[0];
                    $i += strlen($concatRegion) - 1;
                } else {
                    # "Quoted strings placed next to each other are concatenated to a single string")
                    $isInString = FALSE;
                    $stringStart = "";
                    $string = substr($sql, $startI, ($i - $startI) + 1);
                    $strings[] = $string;
                    $startI = 0;
                }
            } else if (
                $isInString
                && !$isPriorEscaped
                && ($stringStart == $sql[$i])
            ) {
                $string = substr($sql, $startI, ($i - $startI) + 1);
                # handle """, """"", etc. at end if an odd number
                for ($j = strlen($string) - 1; $j >= 0; $j--) {
                    $char = $string[$j];
                    if (!in_array($char, self::QUOTE_ARRAY)) {
                        break;
                    }
                }
                $numQuotesAtEnd = strlen($string) - 1 - $j;
                if (
                    ($numQuotesAtEnd >= 3)
                    && ($numQuotesAtEnd % 2 == 1)
                    && !$firstPartOfDoubleQuote
                    && ($numQuotesAtEnd < strlen($string))
                ) {
                    # if odd
                    $isInString = FALSE;
                    $stringStart = "";
                    $string = substr($sql, $startI, ($i - $startI) + 1);
                    $strings[] = $string;
                    $startI = 0;
                } else if (
                    ($numQuotesAtEnd == 2)
                    && !$firstPartOfDoubleQuote
                    && (strlen($string) == 2)
                ) {
                    # case of simple "" or '' without being """ or '''
                    $strings[] = $string;
                    $isInString = FALSE;
                    $stringStart = "";
                    $startI = 0;
                }
            }
            if ($sql[$i] == "\\") {
                $isPriorEscaped = TRUE;
            } else {
                $isPriorEscaped = FALSE;
            }
            $i++;
        }
        return $strings;
    }

    # handle MySQL literals and adds them to $strings
    # if found, changes $i and $startI in conjunction with getStrings()
    # https://dev.mysql.com/doc/refman/8.0/en/literals.html
    private static function tryToMatchALiteral($remainingSubstr, &$strings, &$i, &$startI) {
        $literals = array_merge(self::CHARSETS, ["n", "N", "_utf8"]);
        $foundLiteral = FALSE;
        foreach ($literals as $prefix) {
            while (!$foundLiteral && preg_match("/^$prefix(['\"]).*?\\1/", $remainingSubstr, $matches)) {
                $proposedStringOuter = $matches[0];
                $quote = $matches[1];
                $prevCharForMatch = substr($proposedStringOuter, strlen($proposedStringOuter) - 1, 1);
                $nextCharForMatch = substr(substr_replace($remainingSubstr, "", 0, strlen($proposedStringOuter)), 0, 1);
                if (($nextCharForMatch != $matches[1]) && ($prevCharForMatch != "\\")) {
                    $strings[] = $proposedStringOuter;
                    $startI = 0;
                    $i += strlen($proposedStringOuter) - 1;
                    $foundLiteral = TRUE;
                } else {
                    $previousAddOn = $proposedStringOuter;
                    $stop = FALSE;
                    do {
                        $remainingSubstrMinusPreviousAddOn = substr_replace($remainingSubstr, "", 0, strlen($previousAddOn));
                        if (
                            preg_match("/^$quote.*?$quote/", $remainingSubstrMinusPreviousAddOn, $addOnMatches)
                            || preg_match("/^\\$quote.*?$quote/", $remainingSubstrMinusPreviousAddOn, $addOnMatches)
                        ) {
                            $proposedStringInner = $previousAddOn.$addOnMatches[0];
                            $prevCharForMatch = substr($proposedStringInner, strlen($proposedStringInner) - 1, 1);
                            $nextCharForMatch = substr(substr_replace($remainingSubstr, "", 0, strlen($proposedStringInner)), 0, 1);
                            if (($nextCharForMatch != $matches[1]) && ($prevCharForMatch != "\\")) {
                                $strings[] = $proposedStringInner;
                                $startI = 0;
                                $i += strlen($proposedStringInner) - 1;
                                $foundLiteral = TRUE;
                            } else if ($prevCharForMatch == "\\") {
                                $previousAddOn = substr($proposedStringInner, 0, strlen($proposedStringInner));
                            } else {
                                $previousAddOn = $proposedStringInner;
                            }
                        } else {
                            $stop = TRUE;    // illegal literal
                        }
                    } while(!$foundLiteral && !$stop);
                }
            }
            if ($foundLiteral) {
                return;
            }
        }
    }

    protected $verbose;
    protected $test;
    protected $sql;
    protected $params;
}