<?php

/**
 * This is free and unencumbered software released into the public domain.
 *
 * Anyone is free to copy, modify, publish, use, compile, sell, or
 * distribute this software, either in source code form or as a compiled
 * binary, for any purpose, commercial or non-commercial, and by any
 * means.
 *
 * In jurisdictions that recognize copyright laws, the author or authors
 * of this software dedicate any and all copyright interest in the
 * software to the public domain. We make this dedication for the benefit
 * of the public at large and to the detriment of our heirs and
 * successors. We intend this dedication to be an overt act of
 * relinquishment in perpetuity of all present and future rights to this
 * software under copyright law.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR
 * OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 * For more information, please refer to <http://unlicense.org>
 *
 * ICS.php
 * =============================================================================
 * Use this class to create an .ics file.
 *
 *
 * Usage
 * -----------------------------------------------------------------------------
 * Basic usage - generate ics file contents (see below for available properties):
 *   $ics = new ICS($props);
 *   $ics_file_contents = $ics->to_string();
 *
 * Setting properties after instantiation
 *   $ics = new ICS();
 *   $ics->set('summary', 'My awesome event');
 *
 * You can also set multiple properties at the same time by using an array:
 *   $ics->set(array(
 *     'dtstart' => 'now + 30 minutes',
 *     'dtend' => 'now + 1 hour'
 *   ));
 *
 * Available properties
 * -----------------------------------------------------------------------------
 * description
 *   String description of the event.
 * dtend
 *   A date/time stamp designating the end of the event. You can use either a
 *   DateTime object or a PHP datetime format string (e.g. "now + 1 hour").
 * dtstart
 *   A date/time stamp designating the start of the event. You can use either a
 *   DateTime object or a PHP datetime format string (e.g. "now + 1 hour").
 * location
 *   String address or description of the location of the event.
 * summary
 *   String short summary of the event - usually used as the title.
 * url
 *   A url to attach to the the event. Make sure to add the protocol (http://
 *   or https://).
 */

class ICS {
    const DT_FORMAT = 'Ymd\THis';
    const D_ONLY_FORMAT = 'Ymd'; // only date, no time (for full day events)
    const ICS_PROPS = array(
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//REDCap v1.0//EN',
        'CALSCALE:GREGORIAN'
    );

    protected $properties = array();
    private $available_properties = array(
        'description',
        'dtend',
        'dtstart',
        'location',
        'summary',
        'url'
    );

    public function __construct($props) {
        $this->set($props);
    }

    public function set($key, $val = false) {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->set($k, $v);
            }
        } else {
            if (in_array($key, $this->available_properties, true)) {
                $this->properties[$key] = $this->sanitize_val($val, $key);
            }
        }
    }

    public function to_string() {
        $rows = $this->build_props();
        return implode("\r\n", $rows);
    }

    private function isAllDayEvent() {
        $dateStart = $this->properties['dtstart'];
        $dateEnd = $this->properties['dtend'];
        $startIsDayOnly = $this->validateDateTime($dateStart, self::D_ONLY_FORMAT);
        $endIsDayOnly = $this->validateDateTime($dateEnd, self::D_ONLY_FORMAT);
        $isOneDay = $this->isOneDayInterval($dateStart, $dateEnd);
        return $startIsDayOnly && $endIsDayOnly && $isOneDay;
    }

    private function isOneDayInterval($date1, $date2) {
        
        // Convert date strings to DateTime objects
        $datetime1 = new DateTime($date1);
        $datetime2 = new DateTime($date2);
    
        // Get the difference between the timestamps in seconds
        $interval = abs($datetime2->getTimestamp() - $datetime1->getTimestamp());
    
        // Calculate the number of seconds in one day (86400 seconds = 1 day)
        $oneDayInSeconds = 86400;
    
        // Check if the interval is equal to one day
        return $interval === $oneDayInSeconds;
    }

    private function build_props() {
        // Build ICS properties - add header
        $ics_props = array(
            'BEGIN:VEVENT'
        );

        // Build ICS properties - add header
        $props = array();
        foreach($this->properties as $k => $v) {
            $props[strtoupper($k . ($k === 'url' ? ';VALUE=URI' : ''))] = $v;
        }

        // Set some default values
        $props['DTSTAMP'] = $this->format_timestamp('now');
        $props['UID'] = uniqid();

        // Append properties
        foreach ($props as $k => $v) {
            // Add timezone for DTSTART/DTEND that have a time component
            if (($k == 'DTSTART' || $k == 'DTEND') && strlen($v) > 8) {
                $k .= ";TZID=".getTimeZone()."";
            }

            // Add to final array
            $ics_props[] = "$k:$v";
        }
        if($this->isAllDayEvent()) {
            // The event is marked as "transparent," indicating that it
            // does not block other events in the calendar.
            // This is optional, but commonly used for all-day events.
            $ics_props[] = "TRANSP:TRANSPARENT";
        }

        // Build ICS properties - add footer
        $ics_props[] = 'END:VEVENT';

        return $ics_props;
    }

    private function sanitize_val($val, $key = false) {
        switch($key) {
            case 'dtend':
            case 'dtstamp':
            case 'dtstart':
                $val = $this->format_timestamp($val);
                break;
            default:
                $val = $this->escape_string($val);
        }

        return $val;
    }

    private function format_timestamp($timestamp) {
        $dt = new DateTime($timestamp);
        if (!$this->validateDateTime($timestamp) && !$this->validateDateTime($timestamp, 'Y-m-d g.i A')) { // Date Only
            return $dt->format(self::D_ONLY_FORMAT);
        } else { // Date and Time
            return $dt->format(self::DT_FORMAT);
        }

    }

    private function escape_string($str) {
        return preg_replace('/([\,;])/','\\\$1', $str);
    }

    private function validateDateTime($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }
}