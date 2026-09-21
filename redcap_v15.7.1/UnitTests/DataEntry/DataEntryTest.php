<?php
use PHPUnit\Framework\TestCase;

class DataEntryTestTest extends TestCase
{
    public function testFormatVimeoUrl()
    {
        $vimeoUrl = 'https://vimeo.com/12345';
        $expected = ['0', 'https://player.vimeo.com/video/12345', ''];

        $this->assertEquals($expected, \DataEntry::formatVideoUrl($vimeoUrl));
    }

    public function testFormatYouTubeUrl()
    {
        $youtubeUrl = 'https://youtube.com/watch?v=abcdefg';
        $expected = ['0', 'https://www.youtube.com/embed/abcdefg?wmode=transparent&rel=0', ''];

        $this->assertEquals($expected, \DataEntry::formatVideoUrl($youtubeUrl));
    }

    public function testFormatVidYardUrl()
    {
        $vidyardUrl = 'https://share.vidyard.com/watch/ENQKoSdoqSBQewJi4WstUe';
        $expected = ['0', '', '<div><img data-v="4" style="width: 100%; margin: auto; display: block;" class="vidyard-player-embed" src="https://play.vidyard.com/ENQKoSdoqSBQewJi4WstUe.jpg" data-uuid="ENQKoSdoqSBQewJi4WstUe" data-type="inline"></div>'];

        $this->assertEquals($expected, \DataEntry::formatVideoUrl($vidyardUrl));
    }

    public function testFormatInvalidUrl()
    {
        $invalidUrl = 'invalid url';
        $expected = ['1', '', ''];

        $this->assertEquals($expected, \DataEntry::formatVideoUrl($invalidUrl));
    }

    public function testCalcDateAndDateDiff()
    {
        $tests = [
            // y
            ["2024-11-01", 1, "y", "date", "2025-11-01"],
            ["2025-11-15", -1, "y", "date", "2024-11-14"],
            ["2024-11-01", 1, "y", "datetime", "2025-11-01 05:49"],
            ["2025-11-15", -1, "y", "datetime", "2024-11-14 18:10"],
            ["2013-11-05", 10, "y", "date", "2023-11-05"],
            ["2013-11-05", 10, "y", "datetime", "2023-11-05 10:12"],
            ["2013-11-05 01:59", 10, "y", "datetime", "2023-11-05 12:11"],
            // M
            ["2023-11-01", 1, "M", "date", "2023-12-01"],
            ["2023-11-15", -1, "M", "date", "2023-10-15"],
            ["2023-11-01", 1, "M", "datetime", "2023-12-01 10:33"],
            ["2023-11-15", -1, "M", "datetime", "2023-10-15 13:26"],
            ["2023-10-01", 3, "M", "date", "2023-12-31"],
            ["2024-01-02", -3, "M", "date", "2023-10-02"],
            ["2023-11-01", 3, "M", "datetime", "2024-01-31 07:40"],
            ["2024-02-03", -3, "M", "datetime", "2023-11-03 16:19"],
            ["2024-02-04", 3, "M", "date", "2024-05-05"],
            ["2024-05-02", -3, "M", "date", "2024-01-31"],
            ["2024-05-01", 3, "M", "date", "2024-07-31"],
            ["2024-08-01", -3, "M", "date", "2024-05-01"],
            ["2023-10-25 00:00", 3, "M", "datetime", "2024-01-24 07:40"],
            ["2023-10-25 12:00", 3, "M", "datetime", "2024-01-24 19:40"],
            ["2024-02-03", 3, "M", "datetime", "2024-05-04 07:40"],
            ["2024-05-03", -3, "M", "datetime", "2024-02-01 16:19"],
            ["2024-05-01", 3, "M", "datetime", "2024-07-31 07:40"],
            ["2024-08-01", -3, "M", "datetime", "2024-05-01 16:19"],
            // d
            ["2023-11-01", 14, "d", "date", "2023-11-15"],
            ["2023-11-15", -14, "d", "date", "2023-11-01"],
            ["2024-03-01", 14, "d", "date", "2024-03-15"],
            ["2024-03-15", -14, "d", "date", "2024-03-01"],
            ["2023-11-01 12:00", 14, "d", "datetime", "2023-11-15 12:00"],
            ["2023-11-15 12:00", -14, "d", "datetime", "2023-11-01 12:00"],
            ["2024-03-01 12:00", 14, "d", "datetime", "2024-03-15 12:00"],
            ["2024-03-15 12:00", -14, "d", "datetime", "2024-03-01 12:00"],
            ["2023-11-01", 14, "d", "datetime", "2023-11-15 00:00"],
            ["2023-11-15", -14, "d", "datetime", "2023-11-01 00:00"],
            ["2024-03-01", 14, "d", "datetime", "2024-03-15 00:00"],
            ["2024-03-15", -14, "d", "datetime", "2024-03-01 00:00"],
            ["2023-10-25 00:00", 14, "d", "datetime", "2023-11-08 00:00"],
            ["2023-11-08 00:00", -14, "d", "datetime", "2023-10-25 00:00"],
            ["2023-10-25", 14, "d", "datetime", "2023-11-08 00:00"],
            ["2023-11-08", -14, "d", "datetime", "2023-10-25 00:00"],
            ["2023-08-01", 126, "d", "date", "2023-12-05"],
            ["2023-12-05", -126, "d", "date", "2023-08-01"],
            // Decimal offset (for calcdate only)
            //    ["2023-11-01", 14.1, "d", "date", "2023-11-15"],
            //    ["2023-11-15", -14.1, "d", "date", "2023-10-31"],
            //    ["2024-03-01", 14.1, "d", "date", "2024-03-15"],
            //    ["2024-03-15", -14.1, "d", "date", "2024-02-29"],
            //    ["2023-11-01 12:00", 14.1, "d", "datetime", "2023-11-15 12:00"],
            //    ["2023-11-15 12:00", -14.1, "d", "datetime", "2023-10-31 12:00"],
            //    ["2024-03-01 12:00", 14.1, "d", "datetime", "2024-03-15 12:00"],
            //    ["2024-03-15 12:00", -14.1, "d", "datetime", "2024-02-29 12:00"],
            //    ["2023-11-01", 14.1, "d", "datetime", "2023-11-15 00:00"],
            //    ["2023-11-15", -14.1, "d", "datetime", "2023-10-31 00:00"],
            //    ["2024-03-01", 14.1, "d", "datetime", "2024-03-15 00:00"],
            //    ["2024-03-15", -14.1, "d", "datetime", "2024-02-29 00:00"],
            // h
            ["2023-11-01", 14*24, "h", "date", "2023-11-15"],
            ["2023-11-15", -14*24, "h", "date", "2023-11-01"],
            ["2024-03-01", 14*24, "h", "date", "2024-03-15"],
            ["2024-03-15", -14*24, "h", "date", "2024-03-01"],
            ["2023-11-01 12:00", 14*24, "h", "datetime", "2023-11-15 12:00"],
            ["2023-11-15 12:00", -14*24, "h", "datetime", "2023-11-01 12:00"],
            ["2024-03-01 12:00", 14*24, "h", "datetime", "2024-03-15 12:00"],
            ["2024-03-15 12:00", -14*24, "h", "datetime", "2024-03-01 12:00"],
            ["2023-11-01", 14*24, "h", "datetime", "2023-11-15 00:00"],
            ["2023-11-15", -14*24, "h", "datetime", "2023-11-01 00:00"],
            ["2024-03-01", 14*24, "h", "datetime", "2024-03-15 00:00"],
            ["2024-03-15", -14*24, "h", "datetime", "2024-03-01 00:00"],
            // m
            ["2023-11-01", 14*24*60, "m", "date", "2023-11-15"],
            ["2023-11-15", -14*24*60, "m", "date", "2023-11-01"],
            ["2024-03-01", 14*24*60, "m", "date", "2024-03-15"],
            ["2024-03-15", -14*24*60, "m", "date", "2024-03-01"],
            ["2023-11-01 12:00", 14*24*60, "m", "datetime", "2023-11-15 12:00"],
            ["2023-11-15 12:00", -14*24*60, "m", "datetime", "2023-11-01 12:00"],
            ["2024-03-01 12:00", 14*24*60, "m", "datetime", "2024-03-15 12:00"],
            ["2024-03-15 12:00", -14*24*60, "m", "datetime", "2024-03-01 12:00"],
            ["2023-11-01", 14*24*60, "m", "datetime", "2023-11-15 00:00"],
            ["2023-11-15", -14*24*60, "m", "datetime", "2023-11-01 00:00"],
            ["2024-03-01", 14*24*60, "m", "datetime", "2024-03-15 00:00"],
            ["2024-03-15", -14*24*60, "m", "datetime", "2024-03-01 00:00"],
        ];

        foreach ($tests as $test) {
            list ($d1, $offset, $units, $type, $expected) = $test;
            $result = calcdate($d1, $offset, $units, $type);
            $this->assertEquals($expected, $result);
        }

        foreach ($tests as $test) {
            list ($d1, $offset, $units, $type, $expected) = $test;
            $result = round(datediff($d1, $expected, $units, true));
            $this->assertEquals($offset, $result);
        }
    }
}