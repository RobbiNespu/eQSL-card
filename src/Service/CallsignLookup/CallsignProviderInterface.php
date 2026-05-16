<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup;

/**
 * Contract for a single callsign data source.
 *
 * Providers are scrapers or thin API wrappers around external services. Each
 * lives in its own file so an upstream HTML change or a new provider can be
 * shipped independently without touching the orchestrator.
 *
 * Design rules every provider must follow:
 *
 *  1. `code()` returns a short ASCII id (qrz, mcmc, radioid, marts, rapi).
 *     This is the value persisted to `callsign_lookups.source` and the key
 *     admins enable/disable from `/admin/settings`.
 *
 *  2. `supports($callsign)` is a fast in-memory prefix check — e.g. MCMC
 *     only knows 9M / 9W prefixes; QRZ knows worldwide. The orchestrator
 *     skips providers that don't claim support to avoid wasted network
 *     calls.
 *
 *  3. `lookup($callsign)` MUST complete within a few seconds or throw a
 *     CallsignLookupException. It MUST NOT block the request indefinitely —
 *     the orchestrator wraps every call in a timeout, but providers should
 *     also configure their HTTP client.
 *
 *  4. A `null` return from `lookup()` means "I checked, no record". An
 *     exception means "I'm broken right now". The orchestrator treats them
 *     identically for fallthrough but logs exceptions to the audit trail.
 */
interface CallsignProviderInterface
{
    /**
     * Stable short id for this provider. Examples: 'qrz', 'mcmc', 'radioid'.
     */
    public function code(): string;

    /**
     * Human label for admin UI / source attribution on rendered cards.
     */
    public function label(): string;

    /**
     * Quick test: would this provider have any chance of resolving the
     * given callsign? Used by the orchestrator to skip irrelevant providers.
     * Default broad implementations return true; country-specific scrapers
     * (MCMC, RAPI) return false for callsigns outside their prefix space.
     */
    public function supports(string $callsign): bool;

    /**
     * Attempt to resolve. Return null when the provider could reach the
     * upstream but no record was found. Throw CallsignLookupException for
     * transport failures, parse errors, etc. — the orchestrator catches
     * and falls through to the next provider.
     */
    public function lookup(string $callsign): ?CallsignLookupResult;
}
