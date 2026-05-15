<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Converts a Gregorian date to a formatted Hijri (Islamic) date string
 * using Malay month names, e.g. "12 MUHARRAM 1447H".
 *
 * Requires the PHP intl extension (IntlCalendar). Returns an empty string
 * when intl is not loaded so card rendering degrades gracefully.
 */
final class HijriDate
{
    /** Hijri month names in Malay (1-indexed). */
    private const MONTHS = [
        1  => 'MUHARRAM',
        2  => 'SAFAR',
        3  => "RABI'UL AWWAL",
        4  => "RABI'UL AKHIR",
        5  => 'JAMADIL AWWAL',
        6  => 'JAMADIL AKHIR',
        7  => 'REJAB',
        8  => "SYA'ABAN",
        9  => 'RAMADHAN',
        10 => 'SYAWAL',
        11 => 'ZUL QAEDAH',
        12 => 'ZUL HIJJAH',
    ];

    /**
     * Convert a Gregorian datetime to a Hijri date string.
     *
     * Uses the Islamic Civil calendar (tabular algorithm) which is the
     * standard for printed and civil use in Malaysia/Indonesia.
     *
     * @param \DateTimeInterface $dt Gregorian datetime (UTC assumed).
     * @return string e.g. "12 MUHARRAM 1447H", or '' if intl is unavailable.
     */
    public static function fromGregorian(\DateTimeInterface $dt): string
    {
        if (!extension_loaded('intl')) {
            return '';
        }
        // IntlCalendar::fromDateTime accepts DateTime|string, not DateTimeImmutable,
        // until PHP 8.2. Pass a formatted string so both mutable and immutable
        // instances work across PHP 8.1+.
        $cal = \IntlCalendar::fromDateTime($dt->format('Y-m-d H:i:s'), '@calendar=islamic-civil');
        if ($cal === null) {
            return '';
        }
        $day   = $cal->get(\IntlCalendar::FIELD_DAY_OF_MONTH);
        $month = $cal->get(\IntlCalendar::FIELD_MONTH) + 1; // IntlCalendar months are 0-indexed
        $year  = $cal->get(\IntlCalendar::FIELD_YEAR);
        $name  = self::MONTHS[$month] ?? '';

        return sprintf('%d %s %dH', $day, $name, $year);
    }
}
