<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Parses ADIF (Amateur Data Interchange Format) text into QSO records.
 *
 * Output shape (one entry per QSO):
 *   [
 *     'call_worked' => 'W1AW',
 *     'qso_datetime_utc' => '2026-05-09 14:32:00',
 *     'frequency_mhz' => '14.205' or null,
 *     'band' => '20m' or null,
 *     'mode' => 'SSB' or null,
 *     'rst_sent' => '59' or null,
 *     'rst_received' => '59' or null,
 *     'operator_name' => 'Hiram Maxim' or null,
 *     'operator_qth' => 'Newington' or null,
 *     'grid_square' => 'FN31pr' or null,
 *     'notes' => 'First QSO with W1AW' or null,
 *   ]
 *
 * Tag whitelist (others ignored): CALL, QSO_DATE, TIME_ON, FREQ, BAND, MODE,
 * RST_SENT, RST_RCVD, NAME, QTH, GRIDSQUARE, NOTES, COMMENT.
 *
 * Returns: ['records' => array, 'invalid' => int, 'errors' => string[]]
 */
final class AdifParser
{
    private const WHITELIST = [
        'CALL', 'QSO_DATE', 'TIME_ON', 'FREQ', 'BAND', 'MODE',
        'RST_SENT', 'RST_RCVD', 'NAME', 'QTH', 'GRIDSQUARE',
        'NOTES', 'COMMENT',
    ];

    /**
     * @return array{records: array<int, array<string, mixed>>, invalid: int, errors: string[]}
     */
    public function parse(string $content): array
    {
        // Strip header (everything up to <EOH>), case-insensitive
        if (preg_match('/<eoh>/i', $content, $m, PREG_OFFSET_CAPTURE)) {
            $content = substr($content, $m[0][1] + strlen($m[0][0]));
        }

        $records = [];
        $invalid = 0;
        $errors = [];

        $rawRecords = preg_split('/<eor>/i', $content) ?: [];
        foreach ($rawRecords as $i => $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            $tags = $this->parseTags($raw);
            if (empty($tags['CALL']) || empty($tags['QSO_DATE'])) {
                $invalid++;
                $errors[] = "record #{$i}: missing required CALL or QSO_DATE";
                continue;
            }

            $records[] = $this->normalizeRecord($tags);
        }

        return ['records' => $records, 'invalid' => $invalid, 'errors' => $errors];
    }

    /** @return array<string, string> */
    private function parseTags(string $raw): array
    {
        $tags = [];
        // Match <TAGNAME:LEN>VALUE   (LEN bytes long), with optional :TYPE indicator
        $offset = 0;
        $length = strlen($raw);
        while ($offset < $length) {
            if (!preg_match('/<([A-Za-z_][A-Za-z0-9_]*):(\d+)(?::[A-Za-z])?>/', $raw, $m, PREG_OFFSET_CAPTURE, $offset)) {
                break;
            }
            $name = strtoupper($m[1][0]);
            $len = (int)$m[2][0];
            $valueStart = $m[0][1] + strlen($m[0][0]);
            $value = substr($raw, $valueStart, $len);
            $offset = $valueStart + $len;
            if (in_array($name, self::WHITELIST, true)) {
                $tags[$name] = $value;
            }
        }
        return $tags;
    }

    /**
     * @param array<string, string> $tags
     * @return array<string, mixed>
     */
    private function normalizeRecord(array $tags): array
    {
        $date = $tags['QSO_DATE'];
        $time = $tags['TIME_ON'] ?? '000000';
        if (strlen($time) === 4) {
            $time .= '00';
        }
        $datetime = sprintf(
            '%s-%s-%s %s:%s:%s',
            substr($date, 0, 4), substr($date, 4, 2), substr($date, 6, 2),
            substr($time, 0, 2), substr($time, 2, 2), substr($time, 4, 2)
        );

        return [
            'call_worked' => trim($tags['CALL']),
            'qso_datetime_utc' => $datetime,
            'frequency_mhz' => isset($tags['FREQ']) ? trim($tags['FREQ']) : null,
            'band' => isset($tags['BAND']) ? trim($tags['BAND']) : null,
            'mode' => isset($tags['MODE']) ? trim($tags['MODE']) : null,
            'rst_sent' => isset($tags['RST_SENT']) ? trim($tags['RST_SENT']) : null,
            'rst_received' => isset($tags['RST_RCVD']) ? trim($tags['RST_RCVD']) : null,
            'operator_name' => isset($tags['NAME']) ? trim($tags['NAME']) : null,
            'operator_qth' => isset($tags['QTH']) ? trim($tags['QTH']) : null,
            'grid_square' => isset($tags['GRIDSQUARE']) ? trim($tags['GRIDSQUARE']) : null,
            'notes' => $this->preferNotes($tags),
        ];
    }

    /** @param array<string, string> $tags */
    private function preferNotes(array $tags): ?string
    {
        $notes = $tags['NOTES'] ?? $tags['COMMENT'] ?? null;
        return $notes !== null ? trim($notes) : null;
    }
}
