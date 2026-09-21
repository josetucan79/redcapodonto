<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Utility;

class ErrorHandlerManager {

    /** @var bool $isHandlerAttached Tracks if the handler has been attached */
    private static $isHandlerAttached = false;

    /**
     * Attaches the custom error handler if it hasn't been attached yet
     *
     * @param ErrorLogger $logger An instance of the ErrorLogger class for logging errors
     */
    public static function attachErrorHandler(ErrorLogger $logger) {
        // Check if the handler is already attached
        if (self::$isHandlerAttached) {
            return;
        }

        // Store the existing error handler
        $previousErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$previousErrorHandler, $logger) {
            // Get the stack trace using debug_backtrace()
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $stackTrace = static::formatStackTrace($backtrace);

            // Log the error with the stack trace
            $logger->logError($errno, $errstr, $errfile, $errline, $stackTrace);

            // Call the previous error handler, if it exists
            if ($previousErrorHandler) {
                return call_user_func($previousErrorHandler, $errno, $errstr, $errfile, $errline);
            }

            // Return false to indicate that the standard PHP error handler should handle it as well
            return false;
        });

        // Mark the handler as attached
        self::$isHandlerAttached = true;
    }


    /**
     * Formats a stack trace array from debug_backtrace() into a string
     *
     * @param array $backtrace The backtrace array from debug_backtrace()
     *
     * @return string The formatted stack trace
     */
    protected static function formatStackTrace(array $backtrace) {
        $formattedTrace = '';
        foreach ($backtrace as $key => $trace) {
            $file = isset($trace['file']) ? $trace['file'] : '[internal function]';
            $line = isset($trace['line']) ? $trace['line'] : '';
            $function = isset($trace['function']) ? $trace['function'] : '';
            $formattedTrace .= "#{$key} {$file}({$line}): {$function}()\n";
        }
        return $formattedTrace;
    }
}
