<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup;

use App\Service\OperationLog;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Imports a CSV of callsign records into `callsign_directory`.
 *
 * Accepts common header variations rather than forcing a strict schema —
 * operators downloading lists from MCMC vs. MARTS vs. RAPI vs. their own
 * club roster end up with different column names. Recognised aliases per
 * field:
 *
 *   callsign:      callsign, call_sign, call, indicatif, panggilan
 *   name:          name, operator, holder, pemegang, nama
 *   qth:           qth, location, city, address, alamat, kota
 *   country:       country, negara, negeri
 *   grid_square:   grid, grid_square, locator, maidenhead
 *   license_class: class, license, license_class, kelas, lisens
 *
 * Headers are case-insensitive and ignore surrounding whitespace + a leading
 * UTF-8 BOM. Columns we don't recognise are ignored — the importer never
 * fails on extra columns. A row with no callsign is skipped (counted as
 * "skipped" in the summary).
 *
 * Behaviour:
 *   - Existing rows (matched by uppercase callsign) are UPDATED with the
 *     new values; any non-empty field overwrites the previous one. Empty
 *     fields in the CSV do NOT clobber existing non-empty values.
 *   - Brand-new callsigns are INSERTED.
 *   - `imported_at` is set to "now" on every touched row; `source_label`
 *     comes from the importer arg so the admin can tag the upload
 *     ("MCMC 2026-Q1", "MARTS roster 2026").
 */
final class DirectoryCsvImporter
{
    /** @var array<string, string[]> */
    private const ALIASES = [
        'callsign'      => ['callsign', 'call_sign', 'call', 'indicatif', 'panggilan'],
        'name'          => ['name', 'operator', 'holder', 'pemegang', 'nama', 'full_name', 'fullname'],
        'qth'           => ['qth', 'location', 'city', 'address', 'alamat', 'kota'],
        'country'       => ['country', 'negara', 'negeri'],
        'grid_square'   => ['grid', 'grid_square', 'locator', 'maidenhead'],
        'license_class' => ['class', 'license', 'license_class', 'kelas', 'lisens'],
    ];

    /**
     * Import a callsign CSV file into the `callsign_directory` table.
     *
     * Existing rows (matched by uppercase callsign) are updated; new callsigns
     * are inserted. Empty CSV fields do NOT overwrite existing non-empty values.
     * Rows without a callsign are counted as skipped. A single summary event is
     * logged after the import completes.
     *
     * @param string      $csvPath     Absolute path to the CSV file (must be readable).
     * @param string|null $sourceLabel Human label for the data source (e.g. "MCMC 2026-Q1").
     * @return array{imported:int, updated:int, skipped:int, errors:string[]}
     * @throws \InvalidArgumentException If the CSV is not readable or lacks a callsign column.
     * @throws \RuntimeException         If the CSV file cannot be opened.
     */
    public function import(string $csvPath, ?string $sourceLabel = null): array
    {
        if (!is_readable($csvPath)) {
            throw new \InvalidArgumentException('CSV file is not readable: ' . $csvPath);
        }
        $fh = fopen($csvPath, 'r');
        if (!$fh) {
            throw new \RuntimeException('Could not open CSV: ' . $csvPath);
        }
        try {
            $headerRow = $this->readHeaderRow($fh);
            if ($headerRow === null) {
                return ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['CSV is empty.']];
            }
            $columnMap = $this->buildColumnMap($headerRow);
            if (!isset($columnMap['callsign'])) {
                throw new \InvalidArgumentException(
                    'CSV must contain a "callsign" column. Recognised aliases: '
                    . implode(', ', self::ALIASES['callsign'])
                );
            }

            $table = TableRegistry::getTableLocator()->get('CallsignDirectory');
            $imported = $updated = $skipped = 0;
            $errors = [];
            $now = DateTime::now();
            $rowNum = 1; // header row was #1

            while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
                $rowNum++;
                $rec = $this->extractRecord($row, $columnMap);
                if ($rec['callsign'] === null) {
                    $skipped++;
                    continue;
                }

                $existing = $table->find()->where(['callsign' => $rec['callsign']])->first();
                $isNew = $existing === null;
                $entity = $existing ?: $table->newEmptyEntity();
                $patch = $this->buildPatch($rec, $existing, $sourceLabel, $now);
                $entity->set($patch, ['guard' => false]);

                try {
                    $table->saveOrFail($entity);
                    if ($isNew) {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $skipped++;
                    $errors[] = "Row {$rowNum} ({$rec['callsign']}): " . $e->getMessage();
                }
            }

            OperationLog::event('callsign.directory.import', [
                'source_label' => $sourceLabel,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
            ]);

            return [
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        } finally {
            fclose($fh);
        }
    }

    /**
     * Read the first row, stripping a UTF-8 BOM from the first cell if
     * present. Common when operators export from Excel on Windows.
     *
     * @return string[]|null
     */
    private function readHeaderRow($fh): ?array
    {
        $row = fgetcsv($fh, 0, ',', '"', '\\');
        if ($row === false || $row === null) {
            return null;
        }
        if (count($row) > 0) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
        }
        return $row;
    }

    /**
     * @param string[] $headerRow
     * @return array<string, int> Maps canonical field name → CSV column index
     */
    private function buildColumnMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $idx => $cell) {
            $norm = strtolower(trim((string)$cell));
            $norm = str_replace([' ', '-'], '_', $norm);
            foreach (self::ALIASES as $canonical => $aliases) {
                if (in_array($norm, $aliases, true)) {
                    $map[$canonical] ??= $idx;
                    break;
                }
            }
        }
        return $map;
    }

    /**
     * @param string[] $row
     * @param array<string,int> $columnMap
     * @return array<string,?string>
     */
    private function extractRecord(array $row, array $columnMap): array
    {
        $out = [];
        foreach (array_keys(self::ALIASES) as $canonical) {
            if (!isset($columnMap[$canonical])) {
                $out[$canonical] = null;
                continue;
            }
            $idx = $columnMap[$canonical];
            $raw = $row[$idx] ?? null;
            $value = $raw === null ? null : trim((string)$raw);
            $out[$canonical] = ($value === '' ? null : $value);
        }
        if ($out['callsign'] !== null) {
            $out['callsign'] = strtoupper($out['callsign']);
        }
        return $out;
    }

    /**
     * Build a patch dict. New fields fill missing values; empty CSV fields
     * do NOT clobber existing data.
     */
    private function buildPatch(
        array $rec,
        ?object $existing,
        ?string $sourceLabel,
        DateTime $now,
    ): array {
        $patch = [
            'callsign' => $rec['callsign'],
            'imported_at' => $now,
        ];
        if ($sourceLabel !== null && $sourceLabel !== '') {
            $patch['source_label'] = $sourceLabel;
        }
        foreach (['name', 'qth', 'country', 'grid_square', 'license_class'] as $field) {
            if ($rec[$field] !== null) {
                $patch[$field] = $rec[$field];
            }
        }
        return $patch;
    }
}
