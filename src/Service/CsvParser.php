<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Parses CSV exports of QSO logs into normalized records (same shape as AdifParser).
 *
 * Header row is REQUIRED. Recognized columns (case-insensitive, after BOM/whitespace strip):
 *   call, callsign, call_worked, worked_call → call_worked
 *   date, qso_date, datetime, qso_datetime → date part
 *   time, time_on → time part
 *   datetime, qso_datetime → full datetime
 *   freq, frequency, frequency_mhz → frequency_mhz
 *   band → band
 *   mode → mode
 *   rst_sent, sent → rst_sent
 *   rst_rcvd, rst_received, rcvd, received → rst_received
 *   name, op_name → operator_name
 *   qth → operator_qth
 *   grid, gridsquare, grid_square → grid_square
 *   notes, comment → notes
 *
 * Auto-detects delimiter (comma / semicolon / tab) by header row.
 * Strips UTF-8 BOM. Trims whitespace from values.
 *
 * Returns: ['records' => array, 'invalid' => int, 'errors' => string[]]
 */
final class CsvParser
{
    private const COLUMN_MAP = [
        'call' => 'call_worked',
        'callsign' => 'call_worked',
        'call_worked' => 'call_worked',
        'worked_call' => 'call_worked',
        'date' => '_date_only',
        'qso_date' => '_date_only',
        'time' => '_time_only',
        'time_on' => '_time_only',
        'datetime' => 'qso_datetime_utc',
        'qso_datetime' => 'qso_datetime_utc',
        'qso_datetime_utc' => 'qso_datetime_utc',
        'freq' => 'frequency_mhz',
        'frequency' => 'frequency_mhz',
        'frequency_mhz' => 'frequency_mhz',
        'band' => 'band',
        'mode' => 'mode',
        'rst_sent' => 'rst_sent',
        'sent' => 'rst_sent',
        'rst_rcvd' => 'rst_received',
        'rst_received' => 'rst_received',
        'rcvd' => 'rst_received',
        'received' => 'rst_received',
        'name' => 'operator_name',
        'op_name' => 'operator_name',
        'qth' => 'operator_qth',
        'grid' => 'grid_square',
        'gridsquare' => 'grid_square',
        'grid_square' => 'grid_square',
        'notes' => 'notes',
        'comment' => 'notes',
    ];

    /**
     * @return array{records: array<int, array<string, mixed>>, invalid: int, errors: string[]}
     */
    public function parse(string $content): array
    {
        // Strip UTF-8 BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = (string)$content;

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        // Drop trailing empty lines
        while (!empty($lines) && trim(end($lines)) === '') {
            array_pop($lines);
        }
        if (count($lines) < 2) {
            return ['records' => [], 'invalid' => 0, 'errors' => ['CSV must have at least a header and one data row.']];
        }

        // Auto-detect delimiter from header
        $delim = $this->detectDelimiter($lines[0]);

        $headerRow = str_getcsv($lines[0], $delim);
        $columns = [];
        foreach ($headerRow as $i => $h) {
            $key = strtolower(trim($h));
            $columns[$i] = self::COLUMN_MAP[$key] ?? null;
        }

        $records = [];
        $invalid = 0;
        $errors = [];

        for ($idx = 1; $idx < count($lines); $idx++) {
            $line = $lines[$idx];
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line, $delim);
            $rec = $this->buildRecord($values, $columns);

            if (empty($rec['call_worked']) || empty($rec['qso_datetime_utc'])) {
                $invalid++;
                $errors[] = "row {$idx}: missing required call_worked or qso_datetime_utc";
                continue;
            }
            $records[] = $rec;
        }

        return ['records' => $records, 'invalid' => $invalid, 'errors' => $errors];
    }

    private function detectDelimiter(string $headerLine): string
    {
        $counts = [
            ',' => substr_count($headerLine, ','),
            ';' => substr_count($headerLine, ';'),
            "\t" => substr_count($headerLine, "\t"),
        ];
        arsort($counts);
        $top = array_key_first($counts);
        return $counts[$top] > 0 ? $top : ',';
    }

    /**
     * @param array<int, string> $values
     * @param array<int, string|null> $columns
     * @return array<string, mixed>
     */
    private function buildRecord(array $values, array $columns): array
    {
        $rec = array_fill_keys([
            'call_worked', 'qso_datetime_utc', 'frequency_mhz', 'band', 'mode',
            'rst_sent', 'rst_received', 'operator_name', 'operator_qth',
            'grid_square', 'notes',
        ], null);

        $datePart = null;
        $timePart = null;

        foreach ($values as $i => $v) {
            $field = $columns[$i] ?? null;
            if ($field === null) {
                continue;
            }
            $value = trim($v);
            if ($value === '') {
                continue;
            }
            if ($field === '_date_only') {
                $datePart = $value;
            } elseif ($field === '_time_only') {
                $timePart = $value;
            } else {
                $rec[$field] = $value;
            }
        }

        if ($rec['qso_datetime_utc'] === null && $datePart !== null) {
            $time = $timePart !== null ? $this->normalizeTime($timePart) : '00:00:00';
            $rec['qso_datetime_utc'] = $this->normalizeDate($datePart) . ' ' . $time;
        }

        return $rec;
    }

    private function normalizeDate(string $d): string
    {
        // Accepts: YYYY-MM-DD, YYYYMMDD, YYYY/MM/DD, MM/DD/YYYY (US, last resort)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $d, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $d, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        return $d; // pass through; downstream validation will catch
    }

    private function normalizeTime(string $t): string
    {
        // Accepts: HH:MM, HH:MM:SS, HHMM, HHMMSS
        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $t, $m)) {
            return "{$m[1]}:{$m[2]}:{$m[3]}";
        }
        if (preg_match('/^(\d{2}):(\d{2})$/', $t, $m)) {
            return "{$m[1]}:{$m[2]}:00";
        }
        if (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $t, $m)) {
            return "{$m[1]}:{$m[2]}:{$m[3]}";
        }
        if (preg_match('/^(\d{2})(\d{2})$/', $t, $m)) {
            return "{$m[1]}:{$m[2]}:00";
        }
        return $t;
    }
}
