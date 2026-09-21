<?php
namespace Vanderbilt\REDCap\Classes\Utility;



class CSVHelper
{
    /**
    * Generate and force the download of a CSV file with predefined headers.
    *
    * @param string $filename The filename for the CSV file.
    * @param array $headers The headers for the CSV file.
    * @param array $data The data for the CSV file (optional).
    */
    static function downloadCSV($filename, $headers, $data = []) {
        // Set the content type header to force a download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Open a file handle for the output
        $handle = fopen('php://output', 'w');
        
        // Write the headers to the file handle
        fputcsv($handle, $headers, ',', '"', '');
        
        // Write the data to the file handle (if provided)
        if (!empty($data)) {
            foreach ($data as $row) {
                fputcsv($handle, $row, ',', '"', '');
            }
        }
        
        // Close the file handle
        fclose($handle);
        
        // Exit to prevent any further output
        exit;
    }
    
    /**
    * Converts a multiline CSV string into a multidimensional array
    *
    * @param string $csvData The CSV data as a string.
    * @param bool $associative (optional) Whether to return an associative array or not. Defaults to true.
    * @param string $delimiter (optional) The delimiter used to separate fields in the CSV data. Defaults to ",".
    * @param string $newline (optional) The character used to separate lines in the CSV data. Defaults to "\n".
    * @return array The resulting multidimensional array. If $associative is true, each element of the array
    * will be an associative array whose keys are the headers from the CSV data. If $associative is false,
    * the first row of the resulting array will contain the headers from the CSV data.
    */
    static function csvToArray($csvData, $associative=true, $delimiter = ',', $newline = "\n") {
        // Split the CSV data into an array of lines
        $lines = explode($newline, $csvData);
        
        // Extract the headers from the first line
        $headers = str_getcsv(array_shift($lines), $delimiter);
        
        // Loop through the remaining lines and convert them to arrays
        $rows = [];
        if(!$associative) $rows[] = $headers;
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $line = str_getcsv($line, $delimiter);
            if($associative) $rows[] = array_combine($headers, $line);
            else $rows[] = $line;
        }
        
        // Return the resulting array
        return $rows;
    }
    
    /**
    * Check if the headers of a CSV string match the expected headers.
    *
    * @param string $csv_string The CSV string to check.
    * @param array $expected_headers An array of expected headers.
    * @return bool True if the headers match, false otherwise.
    */
    static function checkHeadersFromString($csv_string, $expected_headers) {
        // Convert the CSV string to a file handle
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv_string);
        rewind($handle);
        
        $headers = fgetcsv($handle, 0, ',', '"', ''); // Get the headers from the CSV string
        
        // Check if the CSV string has the expected headers
        if ($headers !== $expected_headers) {
            // The CSV string does not have the expected headers
            return false;
        }
        
        return true;
    }
    
    /**
    * Check if a string of CSV data contains a specific number of lines.
    *
    * @param string $csv The CSV data to check.
    * @param int $lines The number of lines to check for.
    * @return bool True if the CSV data contains at least the specified number of lines, false otherwise.
    */
    static function hasData($csv, $lines=1) {
        // Split the CSV data into an array of lines
        $csv_lines = explode(PHP_EOL, $csv);
        
        // Remove any empty lines
        $csv_lines = array_filter($csv_lines);
        
        // Check if the number of lines in the CSV data is at least the specified number of lines
        return (count($csv_lines) >= $lines);
    }
    
}