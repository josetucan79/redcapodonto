<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Traits;

trait CanRollbackDatabaseQuery {

  /**
   * Run one (or more) query(ies) in the provided $callable
   * with AUTOCOMMIT disabled
   * Rollback if the $callable throws an exception
   *
   * @param callable $callable
   * @return mixed
   */
  protected function runQueryWithRollback($callable)
  {
    try {
      db_query("SET AUTOCOMMIT=0");
      db_query("BEGIN");
      $result = $callable();
      db_query("COMMIT");
      db_query("SET AUTOCOMMIT=1");
      return $result;
    } catch (\Exception $e) {
        // intercept detection to rollback changes then throw the same exception again
        db_query("ROLLBACK");
        db_query("SET AUTOCOMMIT=1");
        throw $e;
    }
  }
}