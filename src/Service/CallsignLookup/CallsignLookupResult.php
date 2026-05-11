<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup;

/**
 * Value object returned by a CallsignProviderInterface when it successfully
 * resolves a callsign. Every field is nullable because different providers
 * surface different subsets — QRZ has grid square + license class, MCMC only
 * has name + class, RadioID has DMR ID + country. The orchestrator keeps the
 * first provider's hit verbatim; downstream consumers handle missing fields
 * gracefully.
 */
final class CallsignLookupResult
{
    public function __construct(
        public readonly string $callsign,
        public readonly string $source,
        public readonly ?string $name = null,
        public readonly ?string $qth = null,
        public readonly ?string $country = null,
        public readonly ?string $gridSquare = null,
        public readonly ?string $licenseClass = null,
        public readonly ?string $sourceUrl = null,
        public readonly ?array $rawPayload = null,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'callsign' => $this->callsign,
            'source' => $this->source,
            'name' => $this->name,
            'qth' => $this->qth,
            'country' => $this->country,
            'grid_square' => $this->gridSquare,
            'license_class' => $this->licenseClass,
            'source_url' => $this->sourceUrl,
        ];
    }

    public function hasUsefulFields(): bool
    {
        return $this->name !== null
            || $this->qth !== null
            || $this->country !== null
            || $this->gridSquare !== null
            || $this->licenseClass !== null;
    }
}
