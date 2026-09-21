<?php
namespace Vanderbilt\REDCap\Classes\Utility;

class TransactionManager
{
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

