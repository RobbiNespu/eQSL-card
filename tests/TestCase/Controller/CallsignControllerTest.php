<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Service\AppSettings;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Integration tests for the JSON callsign API.
 *
 * The endpoint wires up real providers, so we seed an entry directly into
 * `callsign_lookups` to test cache hits without going through the network.
 * A request that bypasses the cache will fall through to the live RadioID
 * provider — which would dial out — so the "not in cache, lookup disabled"
 * test toggles the global off-switch to keep things hermetic.
 */
final class CallsignControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.AppSettings', 'app.CallsignLookups'];

    protected function setUp(): void
    {
        parent::setUp();
        (new AppSettings())->clear();
    }

    private function loginAs(): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'OP', 'email' => 'op@x.com', 'role' => 'user', 'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id]]);

        return $u->id;
    }

    public function testReturns404WhenFeatureDisabled(): void
    {
        $this->loginAs();
        // Feature is disabled by default.
        $this->get('/api/callsign/W1AW');
        $this->assertResponseCode(404);
    }

    public function testCacheHitReturnsResult(): void
    {
        $this->loginAs();
        (new AppSettings())->setMany([
            'callsign_lookup_enabled' => true,
            'callsign_lookup_providers' => 'radioid',
        ]);

        // Seed a cache row directly so we don't depend on network.
        $cache = $this->getTableLocator()->get('CallsignLookups');
        $cache->saveOrFail($cache->newEntity([
            'callsign' => 'W1AW',
            'name' => 'Hiram Maxim',
            'qth' => 'Newington, CT',
            'country' => 'United States',
            'source' => 'radioid',
            'source_url' => 'https://radioid.net/?call=W1AW',
            'fetched_at' => DateTime::now(),
            'expires_at' => DateTime::now()->addDays(30),
        ], ['accessibleFields' => [
            'callsign' => true, 'name' => true, 'qth' => true, 'country' => true,
            'source' => true, 'source_url' => true, 'fetched_at' => true, 'expires_at' => true,
        ]]));

        $this->get('/api/callsign/W1AW');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $payload = json_decode($body, true);
        $this->assertIsArray($payload);
        $this->assertSame('Hiram Maxim', $payload['result']['name'] ?? null);
        $this->assertSame('Newington, CT', $payload['result']['qth'] ?? null);
        $this->assertSame('radioid', $payload['result']['source'] ?? null);
    }

    public function testCallsignNormalisedToUppercase(): void
    {
        $this->loginAs();
        (new AppSettings())->setMany([
            'callsign_lookup_enabled' => true,
            'callsign_lookup_providers' => 'radioid',
        ]);
        $cache = $this->getTableLocator()->get('CallsignLookups');
        $cache->saveOrFail($cache->newEntity([
            'callsign' => '9W2NSP', 'name' => 'Robbi', 'source' => 'radioid',
            'fetched_at' => DateTime::now(), 'expires_at' => DateTime::now()->addDays(30),
        ], ['accessibleFields' => ['*' => true]]));

        // Route pattern excludes lowercase, so request with the matched alphabet.
        // Our service normalises internally; the cache key is always uppercase.
        $this->get('/api/callsign/9W2NSP');
        $this->assertResponseOk();
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame('Robbi', $payload['result']['name'] ?? null);
    }

    public function testAnonymousRedirectedToLogin(): void
    {
        $this->get('/api/callsign/W1AW');
        $this->assertRedirectContains('/login');
    }
}
