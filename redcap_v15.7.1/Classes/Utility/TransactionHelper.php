<?php
namespace Vanderbilt\REDCap\Classes\Utility;

class TransactionHelper {
    public static function executeDryRun(callable $function, array $params = []) {
        try {
            self::beginTransaction();
            $result = call_user_func_array($function, $params);
        } catch (\Throwable $th) {
            $result = ['error' => $th->getMessage()];
        } finally {
            // Rollback changes for dry run
            self::rollbackTransaction();
        }

        return $result;
    }

        /**
     * Begins a database transaction and disables autocommit.
     *
     * @return void
     */
    public static function beginTransaction()
    {
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");
    }

    /**
     * Commits the current database transaction and re-enables autocommit.
     *
     * @return void
     */
    public static function commitTransaction()
    {
        db_query("COMMIT");
        db_query("SET AUTOCOMMIT=1");
    }

    /**
     * Rolls back the current database transaction and re-enables autocommit.
     *
     * @return void
     */
    public static function rollbackTransaction()
    {
        db_query("ROLLBACK");
        db_query("SET AUTOCOMMIT=1");
    }
}
