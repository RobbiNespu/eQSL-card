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
     * Reads in 64 KB chunks (rather than one stream_copy_to_stream call)
     * so the optional $onProgress callback can fire mid-download. That
     * also lets the HTTP layer flush bytes downstream every chunk —
     * critical when the request is fronted by nginx, whose default
     * proxy_read_timeout is 60 s. With periodic progress emission, nginx
     * never sees a long idle gap and the request can run for minutes.
     *
     * @param string $url Source URL.
     * @param (callable(string):void)|null $onProgress Status emitter.
     * @throws \RuntimeException When the source is unreachable or the
     *   downloaded payload exceeds MAX_BYTES.
     */
    public function download(string $url = self::SOURCE_URL, ?callable $onProgress = null): string
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

        $onProgress?->__invoke('Connecting to upstream…');
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

        // Chunked copy with progress + size cap. 64 KB chunks balance
        // syscall overhead against status-emission cadence (256 chunks
        // per 16 MB CSV → roughly one progress line per 1 MB).
        $copied = 0;
        $emitEvery = 1024 * 1024; // 1 MB
        $nextEmit = $emitEvery;
        while (!feof($src)) {
            $chunk = fread($src, 65536);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $written = fwrite($dstFh, $chunk);
            if ($written === false) {
                fclose($src); fclose($dstFh); @unlink($dst);
                throw new \RuntimeException('Disk write failed during download.');
            }
            $copied += $written;
            if ($copied > self::MAX_BYTES) {
                fclose($src); fclose($dstFh); @unlink($dst);
                throw new \RuntimeException(sprintf(
                    'Download exceeded %d-byte cap; refusing to import.',
                    self::MAX_BYTES
                ));
            }
            if ($onProgress && $copied >= $nextEmit) {
                $onProgress(sprintf('Downloaded %.1f MB…', $copied / 1048576));
                $nextEmit += $emitEvery;
            }
        }
        fclose($src);
        fclose($dstFh);

        if ($copied === 0) {
            @unlink($dst);
            throw new \RuntimeException('Empty / failed download from ' . $url);
        }

        $onProgress?->__invoke(sprintf('Download complete — %.1f MB.', $copied / 1048576));
        return $dst;
    }

    /**
     * Stream-parse a local CSV into `radioid_registry`. Wholesale
     * replace because the upstream is canonical; we don't try to merge
     * row-by-row. Returns the imported row count.
     *
     * @param string $csvPath Local CSV file to read.
     * @param (callable(string):void)|null $onProgress Status emitter
     *   called every BATCH_SIZE rows so a downstream UI (terminal,
     *   SSE) can show live progress.
     * @throws \RuntimeException If the CSV header doesn't match the
     *   expected RadioID schema (defends against accidentally pointing
     *   the importer at the wrong URL).
     */
    public function import(string $csvPath, ?callable $onProgress = null): int
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
                    $onProgress?->__invoke(sprintf('Processed %s rows…', number_format($total)));
                }
            }
            if (!empty($rows)) {
                $total += $this->flush($conn, $rows);
            }
            // After-the-fact row count from the table tells us the actual
            // distinct-callsign cardinality; the upserts may have collapsed
            // some rows (one operator with multiple DMR registrations).
            $cached = (int)$conn->execute('SELECT COUNT(*) AS c FROM ' . self::TABLE)
                ->fetch('assoc')['c'];
            $onProgress?->__invoke(sprintf(
                'Import complete — processed %s rows, cache now holds %s unique callsigns.',
                number_format($total),
                number_format($cached)
            ));
            return $cached;
        } finally {
            fclose($fh);
        }
    }

    /**
     * Fully streaming refresh — opens the upstream HTTP stream and
     * parses CSV lines as bytes arrive, batch-UPSERTing every
     * BATCH_SIZE rows. No intermediate temp file, no two-phase
     * download-then-import wait. Progress lines combine bytes-streamed
     * AND rows-processed so the operator sees both metrics climb
     * together instead of "downloading…" silence followed by
     * "importing…" silence.
     *
     * @param (callable(string):void)|null $onProgress Status emitter,
     *   fired on every BATCH_SIZE rows committed.
     */
    public function refresh(?callable $onProgress = null): int
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

        $onProgress?->__invoke('Connecting to upstream…');
        $src = @fopen(self::SOURCE_URL, 'r', false, $context);
        if ($src === false) {
            throw new \RuntimeException('Could not open ' . self::SOURCE_URL);
        }

        try {
            return $this->importFromStream($src, $onProgress);
        } finally {
            fclose($src);
        }
    }

    /**
     * Parse a CSV stream chunk-by-chunk: pull 64 KB at a time, split
     * out complete lines from the trailing buffer, parse each line
     * with str_getcsv, batch the resulting rows, flush on
     * BATCH_SIZE boundaries.
     *
     * Side-effect: TRUNCATEs `radioid_registry` before the first
     * insert so a successful stream replaces the previous snapshot.
     * Returns the post-import distinct-callsign count.
     *
     * @param resource $stream Any readable stream (HTTP wrapper, file).
     * @param (callable(string):void)|null $onProgress
     */
    private function importFromStream($stream, ?callable $onProgress = null): int
    {
        $conn = ConnectionManager::get('default');
        $now = DateTime::now()->format('Y-m-d H:i:s');
        $headerSeen = false;
        $buffer = '';
        $bytesRead = 0;
        $rows = [];
        $totalProcessed = 0;

        while (!feof($stream)) {
            $chunk = fread($stream, 65536);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $bytesRead += strlen($chunk);
            if ($bytesRead > self::MAX_BYTES) {
                throw new \RuntimeException(sprintf(
                    'Stream exceeded %d-byte cap; aborting import.',
                    self::MAX_BYTES
                ));
            }
            $buffer .= $chunk;

            // Pull every complete line out of the buffer. The remaining
            // partial line stays in $buffer for the next iteration.
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $newlinePos), "\r");
                $buffer = substr($buffer, $newlinePos + 1);
                if ($line === '') {
                    continue;
                }

                $cells = str_getcsv($line);
                if (!$headerSeen) {
                    $this->assertHeader($cells);
                    $headerSeen = true;
                    // Replace the previous snapshot only once we've
                    // confirmed the upstream is shaped correctly —
                    // protects the cache from being wiped on a 200 OK
                    // response that happens to contain the wrong CSV.
                    $conn->execute('DELETE FROM ' . self::TABLE);
                    continue;
                }

                $rowData = $this->buildRow($cells, $now);
                if ($rowData === null) {
                    continue;
                }
                $rows[] = $rowData;
                if (count($rows) >= self::BATCH_SIZE) {
                    $totalProcessed += $this->flush($conn, $rows);
                    $rows = [];
                    $onProgress?->__invoke(sprintf(
                        'Streamed %.1f MB, processed %s rows…',
                        $bytesRead / 1048576,
                        number_format($totalProcessed)
                    ));
                }
            }
        }

        // Tail line that didn't end in \n (rare — the upstream emits
        // one trailing newline, but we still handle the truncated case).
        if ($headerSeen && trim($buffer) !== '') {
            $cells = str_getcsv(rtrim($buffer, "\r\n"));
            $rowData = $this->buildRow($cells, $now);
            if ($rowData !== null) {
                $rows[] = $rowData;
            }
        }
        if (!empty($rows)) {
            $totalProcessed += $this->flush($conn, $rows);
        }

        if (!$headerSeen) {
            throw new \RuntimeException('Empty stream — never saw a CSV header row.');
        }

        $cached = (int)$conn->execute('SELECT COUNT(*) AS c FROM ' . self::TABLE)
            ->fetch('assoc')['c'];
        $onProgress?->__invoke(sprintf(
            'Stream complete — %.1f MB streamed, processed %s rows, cache now holds %s unique callsigns.',
            $bytesRead / 1048576,
            number_format($totalProcessed),
            number_format($cached)
        ));
        return $cached;
    }

    /**
     * Validate the CSV header row shape; throws on a mismatch so we
     * refuse to import a file that's been swapped under us.
     */
    private function assertHeader(array $cells): void
    {
        $normalised = array_map(static fn ($h) => strtoupper(trim((string)$h)), $cells);
        $expected = ['RADIO_ID', 'CALLSIGN', 'FIRST_NAME', 'LAST_NAME', 'CITY', 'STATE', 'COUNTRY'];
        if ($normalised !== $expected) {
            throw new \RuntimeException(
                'Unexpected CSV header: ' . implode(',', $cells)
                . ' (expected ' . implode(',', $expected) . ')'
            );
        }
    }

    /**
     * Project a parsed CSV row into the row shape `flush()` expects.
     * Returns null when the row is malformed (too few cells, empty
     * callsign) so the caller can skip it cleanly.
     */
    private function buildRow(array $cells, string $now): ?array
    {
        if (count($cells) < 7) {
            return null;
        }
        $callsign = strtoupper(trim((string)$cells[1]));
        if ($callsign === '') {
            return null;
        }
        return [
            'radio_id'    => (int)$cells[0] ?: null,
            'callsign'    => $callsign,
            'first_name'  => $this->str($cells[2], 80),
            'last_name'   => $this->str($cells[3], 80),
            'city'        => $this->str($cells[4], 80),
            'state'       => $this->str($cells[5], 80),
            'country'     => $this->str($cells[6], 80),
            'imported_at' => $now,
        ];
    }

    /**
     * Batch-UPSERT a chunk of rows. Returns the number of rows the
     * server processed (insert + update count combined — not the unique
     * row count, which the caller can fetch from a SELECT COUNT(*)).
     *
     * The upstream CSV occasionally repeats a callsign across rows when
     * an operator has multiple DMR registrations (VE3ZXN, KE6LWH, …).
     * Plain INSERT would fail the whole batch on the UNIQUE(callsign)
     * constraint; we use ON DUPLICATE KEY UPDATE on MySQL and
     * ON CONFLICT(callsign) DO UPDATE on SQLite so the last occurrence
     * wins for any in-batch duplicate. The wholesale DELETE in import()
     * still runs first, so we don't drift from the upstream snapshot —
     * the UPSERT only matters for collisions inside a single import.
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

        // Dialect-specific UPSERT suffix. We update every column except
        // `callsign` (the conflict key — by definition unchanged).
        $updateCols = array_values(array_diff($cols, ['callsign']));
        $driverClass = strtolower(get_class($conn->getDriver()));
        if (str_contains($driverClass, 'mysql')) {
            $set = array_map(
                static fn (string $c): string => "{$c} = VALUES({$c})",
                $updateCols
            );
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $set);
        } else {
            // SQLite 3.24+ and Postgres syntax — same shape, excluded.<col>
            // refers to the row that would have been inserted.
            $set = array_map(
                static fn (string $c): string => "{$c} = excluded.{$c}",
                $updateCols
            );
            $sql .= ' ON CONFLICT(callsign) DO UPDATE SET ' . implode(', ', $set);
        }

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
