<?php

namespace Vanderbilt\REDCap\Tests\DateTimeRC;

use PHPUnit\Framework\TestCase;

class DateTimeRCTest extends TestCase
{
    public function testValidateDateFormatYMD()
    {
        $currentDateTime = new \DateTime();

        // "YYYY-MM-DD" formats
        $dateYMD = $currentDateTime->format('Y-m-d');
        $dateYMDTime = $currentDateTime->format('Y-m-d H:i:s');
        $dateYMDTimeWithoutSeconds = $currentDateTime->format('Y-m-d H:i');

        // MM-DD-YYYY formats
        $dateMDY = $currentDateTime->format('m-d-Y');
        $dateMDYTime = $currentDateTime->format('m-d-Y H:i:s');
        $dateMDYTimeWithoutSeconds = $currentDateTime->format('m-d-Y H:i');

        // DD-MM-YYYY formats
        $dateDMY = $currentDateTime->format('d-m-Y');
        $dateDMYTime = $currentDateTime->format('d-m-Y H:i:s');
        $dateDMYTimeWithoutSeconds = $currentDateTime->format('d-m-Y H:i');

        $beginWords = ['dateYMD', 'dateMDY', 'dateDMY'];
        $endWords = ['', 'Time', 'TimeWithoutSeconds'];

        foreach ($beginWords as $top) {
            foreach ($endWords as $tail) {
                $var = ${$top . $tail};
                $validationResult = \DateTimeRC::validateDateFormatYMD($var);
                if (!str_contains($top, 'YMD')) {
                    $this->assertFalse($validationResult);
                } else {
                    $this->assertTrue($validationResult);
                }
            }
        }
    }
}
