<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Copyright 2017, Egbert van der Wal

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer. Redistributions in binary form
must reproduce the above copyright notice, this list of conditions and the
following disclaimer in the documentation and/or other materials provided with
the distribution. Neither the name of Zend or Rogue Wave Software, nor the
names of its contributors may be used to endorse or promote products derived
from this software without specific prior written permission. THIS SOFTWARE IS
PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO
EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE. 
*/

namespace Wedeto\I18n;

use DateTime;
use DateTimeZone;
use IntlDateFormatter;

use Wedeto\Util\Functions as WF;
use Wedeto\Util\Hook;
use Wedeto\I18n\Locale;

/**
 * Formats dates and times using the locale aware IntlDateFormatter
 */
class Date
{
    /** Represent a date */
    const DATE = 1;
    
    /** Represent a time */
    const TIME = 2;

    /** Represent a date and time */
    const DATETIME = 3;

    /** The locale used for formatting */
    protected $locale;

    /** The formatter (IntlDateFormatter) used for formatting and parsing */
    protected $date_formatter;

    /** The date format used by default - ISO8601 */
    protected $date_format = 'yyyy-MM-dd';

    /** The datetime format used by default - ISO8601 */
    protected $datetime_format = 'yyyy-MM-dd HH:mm:ss';

    /** The time format used by default - ISO8601 */
    protected $time_format = 'HH:mm:ss';

    /** The timezone used by the parsed and formatter */
    protected $timezone = null;

    /**
     * Construct the formatter.
     * @param Locale $locale The locale to use for formatting and parsing
     */
    public function __construct(Locale $locale)
    {
        $this->locale = $locale;
        $this->date_formatter = new IntlDateFormatter($locale->getLocale());

        $tz = date_default_timezone_get();
        if (empty($tz))
            $tz = IntlTimeZone::createDefault();

        $this->setTimeZone($tz);

        // Call the hook to allow adjusting default values
        Hook::execute("Wedeto.I18n.Formatting.Date.Create", ['date_formatter' => $this]);
    }

    /**
     * Format a date, time or datetime according to the configured format
     * @param mixed $date The date to format. Can be a DateTimeInterface object,
     *                    a IntlCalendar object, an int representing the seconds since the epoch,
     *                    or a string representing any parseable date by PHP's DateTime object.
     * @param int $type The formatting type. One of Date::DATE, Date::TIME or Date::DATETIME.
     * @return string The formatted string
     */
    public function format($date, $type = Date::DATE)
    {
        if (WF::is_int_val($date))
            $date = new DateTime("@" . $date);
        elseif (is_string($date))
            $date = new DateTime($date);

        if (!$date instanceof DateTimeInterface)
            throw new I18nException("Invalid date: " . WF::str($date));

        $date = IntlCalendar::fromDateTime($date);

        switch ($type)
        {
            case Date::DATE:
                $this->date_formatter->setPattern($this->date_format);
                break;
            case Date::TIME:
                $this->date_formatter->setPattern($this->time_format);
                break;
            case Date::DATETIME:
            default:
                $this->date_formatter->setPattern($this->datetime_format);
                break;
        }
        return $this->date_formatter->format($date);
    }

    /**
     * Format the argument as a date
     * @param mixed $date The date to format
     * @return string The string representation
     * @see Date::format
     */
    public function formatDate($date)
    {
        return $this->format($date, Date::DATE);
    }

    /**
     * Format the argument as a time
     * @param mixed $date The date to format
     * @return string The string representation
     * @see Date::format
     */
    public function formatTime($date)
    {
        return $this->format($date, Date::TIME);
    }

    /**
     * Format the argument as a DateTime
     * @param mixed $date The date to format
     * @return string The string representation
     * @see Date::format
     */
    public function formatDateTime($date)
    {
        return $this->format($date, Date::DATETIME);
    }
    
    /**
     * Parse a date, time or datetime according to the configured format.
     * @param string $datestr The string to parse
     * @param int $type One of Date::DATE, Date::TIME or Date::DATETIME
     * @return DateTime The parsed DateTime object
     */
    public function parse($datestr, $type = Date::DATETIME)
    {
        $dt = null;
        switch ($type)
        {
            case Date::DATE:
                $dt = DateTime::createFromFormat($this->date_format);
                break;
            case Date::DATETIME:
                $dt = DateTime::createFromFormat($this->datetime_format);
                break;
            case Date::TIME:
                $dt = DateTime::createFromFormat($this->time_format);
                break;
            default:
                throw new \DomainException("Invalid date type: " . WF::str($type));
        }
        $date = new DateTime("@" . $dt);
        $date->setTimeZone($this->timezone->toDateTimeZone());
        return $date;
    }

    /**
     * Change the time zone for this formatter
     * @param mixed $tz A DateTimeZone object or a string representing a time zone
     * @return Date Provides fluent interface
     */
    public function setTimezone($tz)
    {
        if ($tz instanceof DateTimeZone)
            $tz = IntlTimeZone::fromDateTimeZone($tz);
        elseif (is_string($tz))
            $tz = IntlTimeZone::createTimeZone($tz);
        else
            throw new I18nException("Invalid time zone: " . $tz);

        $this->timezone = $tz;
        $this->date_formatter->setTimeZone($tz);
        return $this;
    }

    /**
     * @return IntlDateTimeZone Return the currently configured time zone
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * Set the date format used in parsing and formatting dates and times.
     * 
     * Since the Intl extension is used, this follows the ICU notation, not
     * PHP's native Date format. See documentation at:
     *
     * http://userguide.icu-project.org/formatparse/datetime#TOC-Date-Time-Format-Syntax
     * 
     * @param string $fmt The format to sue
     * @param int $type One of Date::DATE, Date::TIME or Date::DATETIME,
     *                  specifying what format to set
     * @return Date Provides fluent interface
     */
    public function setDateFormat($fmt, $type)
    {
        // Validate the pattern
        $cur = $this->date_formatter->getPattern();
        if (!$this->date_formatter->setPattern($fmt))
        {
            $this->date_formatter->setPattern($cur);
            throw new I18nException("Invalid date/time pattern: " . WF::str($fmt));
        }

        switch ($type)
        {
            case Date::DATE:
                $this->date_format = $fmt;
                break;
            case Date::TIME:
                $this->time_format = $fmt;
                break;
            case Date::DATETIME:
                $this->datetime_format = $fmt;
                break;
            default:
                throw new \DomainException("Invalid date type: $type");
        }
        return $this;
    }
}

WF::check_extension('intl', 'IntlDateFormatter');
