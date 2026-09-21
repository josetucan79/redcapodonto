<?php namespace ExternalModules;

/**
 * Suppressed because it is only used in classes ignored by psalm
 * @psalm-suppress UnusedClass
 */
class ScanConstants
{
    const DB_TAINT_SOURCE_METHODS = [
        'REDCap::getData',
        'mysqli_fetch_all',
        'mysqli_fetch_array',
        'mysqli_fetch_assoc',
        'mysqli_fetch_column',
        'mysqli_fetch_object',
        'mysqli_fetch_row',
        'db_fetch_array',
        'db_fetch_assoc',
        'db_fetch_object',
        'db_fetch_row',
        'db_result',
    ];
}