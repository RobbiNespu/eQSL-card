<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup;

use App\Service\AppSettings;
use App\Service\OperationLog;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Orchestrator for the callsign auto-complete chain.
 *
 * Flow per resolve():
 *   1. If `callsign_lookup_enabled` admin setting is false → return null
 *      (feature disabled globally).
 *   2. Normalise the callsign to uppercase.
 *   3. Check the `callsign_lookups` cache. Cache hit + not expired → return
 *      the row's result. Cache miss or expired → continue.
 *   4. Read the ordered list of enabled provider codes from
 *      `callsign_lookup_providers` (comma-separated). Iterate the in-memory
 *      provider map in that order.
 *   5. Skip providers whose `supports()` says no for this callsign (fast
 *      in-memory check, no network).
 *   6. Call `lookup()` on each remaining provider. First non-null result
 *      wins. Exceptions are caught, logged, and the chain continues.
 *   7. Write the winning result to the cache with an `expires_at` 90 days
 *      out (configurable per call).
 *
 * Injection:
 *   - Providers are passed in as an associative array keyed by code, so the
 *     unit tests can drop in stubs without DI gymnastics. Production wiring
 *     happens at controller boot.
 *   - `$now` and `$ttlDays` are constructor params so the cache TTL behavior
 *     can be tested without sleeping.
 */
final class CallsignLookupService
{
    /**
     * @param array<string,CallsignProviderInterface> $providers Keyed by code()
     * @param \Closure():DateTime|null $clock Defaults to DateTime::now()
     */
    public function __construct(
        private array $providers,
        private AppSettings $settings,
        private ?\Closure $clock = null,
        private int $ttlDays = 90,
    ) {
    }

    /**
     * Resolve a callsign through the configured provider chain.
     * Returns null when the feature is disabled, no provider supports the
     * callsign, or every supporting provider returned null/threw.
     */
    public function resolve(string $callsign): ?CallsignLookupResult
    {
        if (!$this->isEnabled()) {
            return null;
        }
        $callsign = $this->normalise($callsign);
        if ($callsign === '') {
            return null;
        }

        $cached = $this->loadFromCache($callsign);
        if ($cached !== null) {
            return $cached;
        }

        foreach ($this->orderedProviders() as $provider) {
            if (!$provider->supports($callsign)) {
                continue;
            }
            try {
                $result = $provider->lookup($callsign);
            } catch (\Throwable $e) {
                // Provider blew up. Log + try next. The user-facing request
                // must complete regardless of any single source's health.
                OperationLog::failure(
                    'callsign.lookup.' . $provider->code(),
                    $e,
                    ['callsign' => $callsign]
                );
                continue;
            }
            if ($result === null || !$result->hasUsefulFields()) {
                OperationLog::warning(
                    'callsign.lookup.' . $provider->code(),
                    ['callsign' => $callsign, 'hit' => false]
                );
                continue;
            }
            OperationLog::event(
                'callsign.lookup.' . $provider->code(),
                ['callsign' => $callsign, 'hit' => true]
            );
            $this->writeCache($result);
            return $result;
        }

        return null;
    }

    /**
     * Whether the callsign lookup feature is enabled in admin settings.
     *
     * @return bool True when the `callsign_lookup_enabled` setting is truthy.
     */
    public function isEnabled(): bool
    {
        return (bool)$this->settings->get('callsign_lookup_enabled', false);
    }

    /**
     * Purge all rows from the `callsign_lookups` cache table.
     *
     * @return int Number of rows deleted.
     */
    public function clearCache(): int
    {
        $table = TableRegistry::getTableLocator()->get('CallsignLookups');
        return $table->deleteAll([]);
    }

    /**
     * Uppercase and trim a raw callsign string.
     *
     * @param string $callsign Raw user input.
     * @return string Normalised callsign, or empty string.
     */
    private function normalise(string $callsign): string
    {
        return strtoupper(trim($callsign));
    }

    /**
     * Return providers in the admin-configured order, falling back to registration order.
     *
     * @return iterable<CallsignProviderInterface>
     */
    private function orderedProviders(): iterable
    {
        $raw = (string)$this->settings->get('callsign_lookup_providers', '');
        $codes = array_values(array_filter(array_map('trim', explode(',', $raw))));
        // Default order when admin hasn't customised: providers in the
        // order they were registered. Newer providers (likely better data)
        // first; admins can reorder via the settings UI.
        if (empty($codes)) {
            return array_values($this->providers);
        }
        $out = [];
        foreach ($codes as $code) {
            if (isset($this->providers[$code])) {
                $out[] = $this->providers[$code];
            }
        }
        return $out;
    }

    /**
     * Load a non-expired cache row from `callsign_lookups` and return it as a result object.
     *
     * @param string $callsign Normalised (uppercase) callsign.
     * @return CallsignLookupResult|null Cached result, or null on miss/expiry.
     */
    private function loadFromCache(string $callsign): ?CallsignLookupResult
    {
        $table = TableRegistry::getTableLocator()->get('CallsignLookups');
        $row = $table->find()->where(['callsign' => $callsign])->first();
        if (!$row) {
            return null;
        }
        $now = $this->now();
        if ($row->expires_at !== null && $row->expires_at < $now) {
            return null;
        }
        return new CallsignLookupResult(
            callsign: $row->callsign,
            source: $row->source,
            name: $row->name,
            qth: $row->qth,
            country: $row->country,
            gridSquare: $row->grid_square,
            licenseClass: $row->license_class,
            sourceUrl: $row->source_url,
            rawPayload: $row->raw_payload ? json_decode($row->raw_payload, true) : null,
        );
    }

    /**
     * Upsert a lookup result into the `callsign_lookups` cache with a TTL of `ttlDays` days.
     *
     * @param CallsignLookupResult $r The result to persist.
     * @return void
     */
    private function writeCache(CallsignLookupResult $r): void
    {
        $table = TableRegistry::getTableLocator()->get('CallsignLookups');
        $now = $this->now();
        $expires = $now->addDays($this->ttlDays);
        $existing = $table->find()->where(['callsign' => $r->callsign])->first();
        $entity = $existing ?: $table->newEmptyEntity();
        $entity->set([
            'callsign' => $r->callsign,
            'name' => $r->name,
            'qth' => $r->qth,
            'country' => $r->country,
            'grid_square' => $r->gridSquare,
            'license_class' => $r->licenseClass,
            'source' => $r->source,
            'source_url' => $r->sourceUrl,
            'raw_payload' => $r->rawPayload ? json_encode($r->rawPayload, JSON_UNESCAPED_SLASHES) : null,
            'fetched_at' => $now,
            'expires_at' => $expires,
        ], ['guard' => false]);
        $table->saveOrFail($entity);
    }

    /**
     * Return the current DateTime, honoring the injected clock if set.
     *
     * @return \Cake\I18n\DateTime
     */
    private function now(): DateTime
    {
        return $this->clock ? ($this->clock)() : DateTime::now();
    }
}
