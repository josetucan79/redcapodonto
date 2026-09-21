<?php
namespace Vanderbilt\REDCap\Classes\Utility;

class GeneratorUtils {
    /**
     * Skips records from a generator and optionally limits the number of items.
     *
     * @param \Generator $generator The generator to apply the skip and limit logic.
     * @param int $start The number of records to skip.
     * @param int|null $limit The maximum number of records to yield after skipping. Null for no limit.
     * @return \Generator A generator that yields records after skipping the specified number.
     */
    public static function skip(\Generator $generator, int $start = 0, ?int $limit = null): \Generator {
        $counter = 0;

        foreach ($generator as $key => $value) {
            // Skip records until the desired starting point is reached
            if ($counter++ < $start) {
                continue;
            }

            // If a limit is set, stop yielding after the limit is reached
            if ($limit !== null && $counter > $start + $limit) {
                break;
            }

            // Yield the current item
            yield $key => $value;
        }
    }
}
