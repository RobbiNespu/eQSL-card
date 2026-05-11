<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\CallsignLookup;

use App\Service\AppSettings;
use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignLookupService;
use App\Service\CallsignLookup\CallsignProviderInterface;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;

/**
 * Unit tests for the orchestrator. Providers are stubs that record calls and
 * return canned results — no real HTTP touches these tests.
 */
final class CallsignLookupServiceTest extends TestCase
{
    protected array $fixtures = ['app.AppSettings', 'app.CallsignLookups'];

    protected function setUp(): void
    {
        parent::setUp();
        // Static cache on AppSettings persists across tests; clear so each
        // test sees the fixture-loaded settings (which start empty).
        (new AppSettings())->clear();
    }

    private function stub(string $code, ?CallsignLookupResult $returns = null, ?\Throwable $throws = null): CallsignProviderInterface
    {
        return new class($code, $returns, $throws) implements CallsignProviderInterface {
            public int $calls = 0;
            public function __construct(
                private string $codeStr,
                private ?CallsignLookupResult $returns,
                private ?\Throwable $throws,
            ) {}
            public function code(): string { return $this->codeStr; }
            public function label(): string { return $this->codeStr; }
            public function supports(string $callsign): bool { return true; }
            public function lookup(string $callsign): ?CallsignLookupResult
            {
                $this->calls++;
                if ($this->throws) throw $this->throws;
                return $this->returns;
            }
        };
    }

    public function testReturnsNullWhenDisabled(): void
    {
        $service = new CallsignLookupService(
            providers: ['x' => $this->stub('x', new CallsignLookupResult('W1AW', 'x', name: 'Hiram'))],
            settings: new AppSettings(),
        );
        // No app_setting → defaults to false → disabled.
        $this->assertNull($service->resolve('W1AW'));
    }

    public function testFirstProviderWithUsefulFieldsWins(): void
    {
        $settings = new AppSettings();
        $settings->set('callsign_lookup_enabled', true);
        $settings->set('callsign_lookup_providers', 'a,b');

        $a = $this->stub('a', null);                                       // no hit
        $b = $this->stub('b', new CallsignLookupResult('W1AW', 'b', name: 'Hiram'));
        $service = new CallsignLookupService(['a' => $a, 'b' => $b], $settings);

        $result = $service->resolve('w1aw');  // lowercase intentionally
        $this->assertNotNull($result);
        $this->assertSame('W1AW', $result->callsign);   // normalised to upper
        $this->assertSame('b', $result->source);
        $this->assertSame('Hiram', $result->name);
        $this->assertSame(1, $a->calls);
        $this->assertSame(1, $b->calls);
    }

    public function testExceptionFallsThroughToNextProvider(): void
    {
        $settings = new AppSettings();
        $settings->set('callsign_lookup_enabled', true);
        $settings->set('callsign_lookup_providers', 'broken,good');

        $broken = $this->stub('broken', null, throws: new \RuntimeException('upstream down'));
        $good = $this->stub('good', new CallsignLookupResult('W1AW', 'good', qth: 'Newington, CT'));
        $service = new CallsignLookupService(['broken' => $broken, 'good' => $good], $settings);

        $result = $service->resolve('W1AW');
        $this->assertNotNull($result);
        $this->assertSame('good', $result->source);
        $this->assertSame('Newington, CT', $result->qth);
    }

    public function testCacheHitSkipsProviders(): void
    {
        $settings = new AppSettings();
        $settings->set('callsign_lookup_enabled', true);
        $settings->set('callsign_lookup_providers', 'a');

        $a = $this->stub('a', new CallsignLookupResult('W1AW', 'a', name: 'Hiram'));
        $service = new CallsignLookupService(['a' => $a], $settings);

        // First call hits the provider and caches.
        $service->resolve('W1AW');
        $this->assertSame(1, $a->calls);

        // Second call should serve from the cache — provider not touched.
        $cached = $service->resolve('W1AW');
        $this->assertNotNull($cached);
        $this->assertSame('Hiram', $cached->name);
        $this->assertSame(1, $a->calls);
    }

    public function testExpiredCacheRefetches(): void
    {
        $settings = new AppSettings();
        $settings->set('callsign_lookup_enabled', true);
        $settings->set('callsign_lookup_providers', 'a');
        $a = $this->stub('a', new CallsignLookupResult('W1AW', 'a', name: 'Hiram'));

        // Fixed clock at T0 with TTL 1 day.
        $t0 = new DateTime('2026-01-01 00:00:00');
        $service = new CallsignLookupService(['a' => $a], $settings, fn() => $t0, ttlDays: 1);
        $service->resolve('W1AW');
        $this->assertSame(1, $a->calls);

        // Move clock 2 days forward — cache row is expired.
        $t2 = new DateTime('2026-01-03 00:00:00');
        $service2 = new CallsignLookupService(['a' => $a], $settings, fn() => $t2, ttlDays: 1);
        $service2->resolve('W1AW');
        $this->assertSame(2, $a->calls, 'expired cache should re-fetch');
    }

    public function testHonoursProviderOrderFromSettings(): void
    {
        $settings = new AppSettings();
        $settings->set('callsign_lookup_enabled', true);
        // 'b' is listed first explicitly even though 'a' is registered first.
        $settings->set('callsign_lookup_providers', 'b,a');

        $a = $this->stub('a', new CallsignLookupResult('W1AW', 'a', name: 'A first'));
        $b = $this->stub('b', new CallsignLookupResult('W1AW', 'b', name: 'B first'));
        $service = new CallsignLookupService(['a' => $a, 'b' => $b], $settings);

        $result = $service->resolve('W1AW');
        $this->assertNotNull($result);
        $this->assertSame('b', $result->source, 'admin order overrides registration order');
        $this->assertSame(0, $a->calls, 'a should not have been consulted');
    }

    public function testEmptyResultsFromAllProvidersReturnsNull(): void
    {
        $settings = new AppSettings();
        $settings->set('callsign_lookup_enabled', true);
        $settings->set('callsign_lookup_providers', 'a,b');

        $a = $this->stub('a', null);
        $b = $this->stub('b', null);
        $service = new CallsignLookupService(['a' => $a, 'b' => $b], $settings);

        $this->assertNull($service->resolve('W1AW'));
    }
}
