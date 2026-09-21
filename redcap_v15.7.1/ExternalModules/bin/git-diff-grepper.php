<?php

/**
 * This script was written to verify a specific set of changes before committing.
 * It will likely need to be modified heavily before being used again.
 * I wanted to save it, just in case.
 */

$lines = explode("\n", shell_exec("git diff -U0 --word-diff-regex=. --cached --no-color| sort"));

$actualCounts = [];
$expectedCounts = [
    "{+ *+}" => 28,
    "{+ */+}" => 7,
    "{+ /**+}" => 7,
    '{+ * @return false|string+}' => 1,
    '{+ * @return int|string+}' => 1,
    '{+ * @return string+}' => 4,
    '{+ * @return (mixed|null)[]+}' => 1,
    '{+ * @return null|string+}' => 1,
    '{+ * @return true+}' => 1,
    '' => 1,
];

$prefixes = [
    '* @param {+mixed +}$results' => 1,
    '* @param {+string +}$hookName' => 1,
    '+++' => 6,
    '---' => 6,
    '@@' => 53,
    'diff --git ' => 6,
    'index ' => 6,
    '{+ * @param ' => 61,
];

foreach($lines as $line){
    $line = trim(str_replace("\t", " ", $line));
    $line = str_replace("\r", "", $line);
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);

    foreach($prefixes as $prefix => $count){
        $expectedCounts[$prefix] = $count;
        
        if(strpos($line, $prefix) === 0){
            $line = substr($line, 0, strlen($prefix));
            break;
        }
    }

    @$actualCounts[$line]++;
}

foreach($actualCounts as $line => $actualCount){
    $expectedCount = $expectedCounts[$line] ?? null;
    if($actualCount !== $expectedCount){
        var_dump([
            'error' => 'count mismatch',
            'line' => $line,
            'expected' => $expectedCount,
            'actual' => $actualCount
        ]);
        return;
    }
}