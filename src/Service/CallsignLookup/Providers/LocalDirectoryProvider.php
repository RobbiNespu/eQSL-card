<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;
use Cake\ORM\TableRegistry;

/**
 * Looks up callsigns in the admin-uploaded `callsign_directory` table.
 *
 * This is the right-shaped provider for the Malaysian / Indonesian / etc.
 * registries that publish data as PDF or Excel rather than via an API.
 * Operator workflow:
 *   1. Download official member list from MCMC / MARTS / RAPI / ...
 *   2. Convert to CSV (one row per callsign).
 *   3. Upload via /admin/callsign-directory.
 *   4. This provider serves the row on every subsequent lookup.
 *
 * Always positioned EARLIER in the orchestrator chain than external
 * providers — once the operator has imported a row, we don't need to hit
 * an external source for it. supports() is permissive because the directory
 * can contain callsigns from any prefix the admin chooses to import.
 */
final class LocalDirectoryProvider implements CallsignProviderInterface
{
    public function code(): string
    {
        return 'local';
    }

    public function label(): string
    {
        return 'Local directory (admin-imported CSV)';
    }

    public function supports(string $callsign): bool
    {
        return (bool)preg_match('/^[A-Z0-9\/]{3,15}$/', $callsign);
    }

    public function lookup(string $callsign): ?CallsignLookupResult
    {
        $table = TableRegistry::getTableLocator()->get('CallsignDirectory');
        $row = $table->find()->where(['callsign' => $callsign])->first();
        if (!$row) {
            return null;
        }
        $result = new CallsignLookupResult(
            callsign: $row->callsign,
            source: $this->code(),
            name: $row->name,
            qth: $row->qth,
            country: $row->country,
            gridSquare: $row->grid_square,
            licenseClass: $row->license_class,
            sourceUrl: null,
            rawPayload: ['source_label' => $row->source_label],
        );
        return $result->hasUsefulFields() ? $result : null;
    }
}
