<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup;

use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;

/**
 * Sync the local RadioID lookup cache from the upstream user registry.
 *
 * RadioID publishes the user list as a CSV download with columns
 * RADIO_ID, CALLSIGN, FIRST_NAME, LAST_NAME, CITY, STATE, COUNTRY.
 * Designed to play nicely with their API use policy
 * (https://radioid.net/api_use_policy): a single admin-triggered fetch
 * populates the local cache so subsequent QSO form submits don't ping
 * the upstream at all. The data isn't redistributed by this install —
 * the cache is internal storage that supports our own lookups.
 *
 * Two streaming stages, neither of which loads the whole file into
 * PHP memory:
 *
 *   download(): stream_copy_to_stream from the upstream URL into a
 *     temp file on disk. Constant memory regardless of payload size,
 *     hard-capped to refuse anything implausibly large.
 *   import(path): fopen+fgetcsv line-by-line, buffer 1000 rows at a
 *     time, flush via a single multi-row INSERT. The local table is
 *     replaced wholesale so our cache stays consistent with the
 *     upstream snapshot — no merge logic needed.
 *
 * The one-shot refresh() composes both for the admin UI's Sync button.
 */
final class RadioIdRegistryImporter
{
    public const SOURCE_URL = 'https://radioid.net/static/user.csv';
    public const TABLE = 'radioid_registry';

    /** Max CSV size we'll accept (bytes). Cuts off any runaway. */
    private const MAX_BYTES = 200_000_000; // 200 MB headroom over the ~16 MB baseline.

    /** Rows per INSERT batch — keeps each query under the typical max_allowed_packet. */
    private const BATCH_SIZE = 1000;

    /**
     * Stream-download the CSV to a temp file. Returns the local path.
     *
     * @throws \RuntimeException When the source is unreachable or the
     *   downloaded payload exceeds MAX_BYTES.
     */
    public function download(string $url = self::SOURCE_URL): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: eQSL-card RadioId Importer\r\n",
                'timeout' => 60,
                'follow_location' => 1,
                'max_redirects' => 3,
            ],
        ]);

        $src = @fopen($url, 'r', false, $context);
        if ($src === false) {
            throw new \RuntimeException('Could not open ' . $url);
        }

        $dst = tempnam(sys_get_temp_dir(), 'radioid_csv_');
        $dstFh = fopen($dst, 'w');
        if ($dstFh === false) {
            fclose($src);
            throw new \RuntimeException('Could not open temp file for writing.');
        }

        // stream_copy_to_stream lets us cap the maximum bytes copied. If
        // the source is corrupt or someone replaces the URL with a huge
        // payload, we bail before exhausting disk.
        $copied = stream_copy_to_stream($src, $dstFh, self::MAX_BYTES);
        fclose($src);
        fclose($dstFh);

        if ($copied === false || $copied === 0) {
            @unlink($dst);
            throw new \RuntimeException('Empty / failed download from ' . $url);
        }
        if ($copied >= self::MAX_BYTES) {
            @unlink($dst);
            throw new \RuntimeException(sprintf(
                'Download exceeded %d-byte cap; refusing to import.',
                self::MAX_BYTES
            ));
        }

        return $dst;
    }

    /**
     * Stream-parse a local CSV into `radioid_registry`. TRUNCATE-then-
     * insert because the upstream is canonical; we don't try to merge
     * row-by-row. Returns the imported row count.
     *
     * @throws \RuntimeException If the CSV header doesn't match the
     *   expected RadioID schema (defends against accidentally pointing
     *   the importer at the wrong URL).
     */
    public function import(string $csvPath): int
    {
        if (!is_file($csvPath) || !is_readable($csvPath)) {
            throw new \RuntimeException('CSV not readable: ' . $csvPath);
        }
        $fh = fopen($csvPath, 'r');
        if ($fh === false) {
            throw new \RuntimeException('Could not open CSV: ' . $csvPath);
        }

        try {
            $header = fgetcsv($fh);
            if ($header === false || $header === null) {
                throw new \RuntimeException('CSV is empty.');
            }

            // Validate the expected columns up front. Tolerant of case but
            // strict on shape — refusing to import a mis-shaped file
            // beats silently writing garbage rows.
            $normalised = array_map(static fn ($h) => strtoupper(trim((string)$h)), $header);
            $expected = ['RADIO_ID', 'CALLSIGN', 'FIRST_NAME', 'LAST_NAME', 'CITY', 'STATE', 'COUNTRY'];
            if ($normalised !== $expected) {
                throw new \RuntimeException(
                    'Unexpected CSV header: ' . implode(',', $header)
                    . ' (expected ' . implode(',', $expected) . ')'
                );
            }

            // Replace the cache wholesale — the upstream is canonical, so
            // a full reload is simpler than row-by-row reconciliation and
            // guarantees we never serve stale entries that the upstream
            // has since removed.
            $conn = ConnectionManager::get('default');
            $conn->execute('DELETE FROM ' . self::TABLE);
            // SQLite (used by the test suite) has no TRUNCATE; reset the
            // auto-increment counter separately when present.
            try {
                $conn->execute('DELETE FROM sqlite_sequence WHERE name = ?', [self::TABLE]);
            } catch (\Throwable $e) {
                // MySQL/MariaDB — ignore.
            }

            $now = DateTime::now()->format('Y-m-d H:i:s');
            $rows = [];
            $total = 0;
            while (($row = fgetcsv($fh)) !== false) {
                if (count($row) < 7) {
                    continue; // malformed line — skip
                }
                $callsign = strtoupper(trim((string)$row[1]));
                if ($callsign === '') {
                    continue;
                }
                $rows[] = [
                    'radio_id'    => (int)$row[0] ?: null,
                    'callsign'    => $callsign,
                    'first_name'  => $this->str($row[2], 80),
                    'last_name'   => $this->str($row[3], 80),
                    'city'        => $this->str($row[4], 80),
                    'state'       => $this->str($row[5], 80),
                    'country'     => $this->str($row[6], 80),
                    'imported_at' => $now,
                ];
                if (count($rows) >= self::BATCH_SIZE) {
                    $total += $this->flush($conn, $rows);
                    $rows = [];
                }
            }
            if (!empty($rows)) {
                $total += $this->flush($conn, $rows);
            }
            return $total;
        } finally {
            fclose($fh);
        }
    }

    /**
     * One-shot: download + import in a single call. Cleans up the temp
     * file regardless of success. Returns the imported row count.
     */
    public function refresh(): int
    {
        $path = $this->download();
        try {
            return $this->import($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Batch-INSERT a chunk of rows. Returns the number of rows written.
     */
    private function flush(\Cake\Database\Connection $conn, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }
        $cols = ['radio_id', 'callsign', 'first_name', 'last_name', 'city', 'state', 'country', 'imported_at'];
        $placeholderRow = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $sql = 'INSERT INTO ' . self::TABLE . ' (' . implode(',', $cols) . ') VALUES '
             . implode(',', array_fill(0, count($rows), $placeholderRow));

        $params = [];
        foreach ($rows as $r) {
            foreach ($cols as $c) {
                $params[] = $r[$c] ?? null;
            }
        }
        $conn->execute($sql, $params);
        return count($rows);
    }

    /** Trim + null-coalesce + length-cap a string field. */
    private function str(mixed $v, int $max): ?string
    {
        $s = trim((string)($v ?? ''));
        if ($s === '') {
            return null;
        }
        return mb_substr($s, 0, $max);
    }
}
