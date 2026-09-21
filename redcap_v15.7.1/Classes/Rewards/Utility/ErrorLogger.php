<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Utility;

use Exception;

class ErrorLogger {

    /**
     * Logs an error into the error_logs table
     *
     * @param string $errorType The type of error (e.g., Exception, Warning, Critical)
     * @param string $errorMessage Detailed message about the error
     * @param string $file The file where the error occurred
     * @param int $line The line number where the error occurred
     * @param string|null $stackTrace The full stack trace of the error (optional)
     * @param int|null $userId (Optional) ID of the user that triggered the error (if applicable)
     * @param string|null $context (Optional) Additional context information about the error
     *
     * @return bool True if the log was successfully inserted, False on failure
     */
    public function logError($errorType, $errorMessage, $file, $line, $stackTrace = null, $userId = null, $context = null) {
        try {
            // Prepare the SQL query
            $sql = "INSERT INTO error_logs (error_type, error_message, file, line, stack_trace, user_id, context)
                    VALUES (:error_type, :error_message, :file, :line, :stack_trace, :user_id, :context)";
            
            // Prepare the parameters for db_query
            $params = [
                ':error_type' => $errorType,
                ':error_message' => $errorMessage,
                ':file' => $file,
                ':line' => $line,
                ':stack_trace' => $stackTrace,
                ':user_id' => $userId,
                ':context' => $context
            ];
            
            // Execute the query using the db_query function
            return db_query($sql, $params); // Return true if the query executed successfully
        } catch (Exception $e) {
            // Optionally log the exception somewhere else or return false
            return false;
        }
    }

    /**
     * Formats and logs an exception with optional user and context information
     *
     * @param \Throwable $exception The exception to log
     * @param int|null $userId (Optional) ID of the user that triggered the error
     * @param string|null $context (Optional) Additional context information about the error
     *
     * @return bool True if the log was successfully inserted, False on failure
     */
    public function logException(\Throwable $exception, $userId = null, $context = null) {
        return $this->logError(
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
            $userId,
            $context
        );
    }
}
