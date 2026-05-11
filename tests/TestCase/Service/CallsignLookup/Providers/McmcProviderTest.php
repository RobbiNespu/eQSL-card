<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\Providers\McmcProvider;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\TestSuite\TestCase;

/**
 * MCMC scraper tests against captured HTML fixtures.
 *
 * The fixtures under tests/Fixture/Html/Mcmc/ are real responses pulled from
 * the live MCMC search endpoint at the time this provider was written. If
 * MCMC redesigns the result page, these tests will fail loudly — that's
 * the signal to refresh the fixtures and update the scraper's regex.
 *
 * Tests never dial out: a mock adapter returns the fixture body verbatim.
 */
final class McmcProviderTest extends TestCase
{
    private function provider(string $fixtureFile, int $status = 200): McmcProvider
    {
        $body = file_get_contents(__DIR__ . '/../../../../Fixture/Html/Mcmc/' . $fixtureFile);
        $adapter = new class($body, $status) implements \Cake\Http\Client\AdapterInterface {
            public function __construct(private string $body, private int $status) {}
            public function send(\Psr\Http\Message\RequestInterface $request, array $options): array
            {
                return [new Response(["HTTP/1.1 {$this->status} OK"], $this->body)];
            }
        };
        return new McmcProvider(new Client(['adapter' => $adapter]));
    }

    public function testParsesSingleMatch(): void
    {
        $r = $this->provider('9w2nsp.html')->lookup('9W2NSP');
        $this->assertNotNull($r);
        $this->assertSame('mcmc', $r->source);
        $this->assertSame('9W2NSP', $r->callsign);
        // Real result from the registry — names are stored UPPERCASE in
        // MCMC; the scraper title-cases for display.
        $this->assertStringContainsStringIgnoringCase('Robbi Nespu', $r->name);
        $this->assertSame('Malaysia', $r->country);
        $this->assertSame('Class B', $r->licenseClass, '9W prefix → Class B');
        $this->assertNotNull($r->sourceUrl);
        $this->assertStringContainsString('keyword=9W2NSP', $r->sourceUrl);
    }

    public function testNoResultsReturnsNull(): void
    {
        $r = $this->provider('no-results.html')->lookup('ZZZ9XXX');
        $this->assertNull($r);
    }

    public function testExactMatchSelectedFromMultiRowPage(): void
    {
        // The 9M2 prefix returns 15 rows; we must ONLY return the row
        // whose Call Sign cell matches exactly. 9M2VOT is one of those
        // rows — verified at fixture capture time.
        $r = $this->provider('9m2-prefix.html')->lookup('9M2VOT');
        $this->assertNotNull($r);
        $this->assertSame('9M2VOT', $r->callsign);
        $this->assertSame('Class A', $r->licenseClass, '9M prefix → Class A');
    }

    public function testPartialPrefixDoesNotMatch(): void
    {
        // Looking up the bare prefix "9M2" against the multi-result page
        // should NOT return any of the 15 rows — none of them have
        // callsign exactly equal to "9M2".
        $r = $this->provider('9m2-prefix.html')->lookup('9M2');
        $this->assertNull($r);
    }

    public function testSupportsOnlyMalayPrefixes(): void
    {
        $p = new McmcProvider();
        $this->assertTrue($p->supports('9W2NSP'));
        $this->assertTrue($p->supports('9M2RC'));
        $this->assertTrue($p->supports('9W2BLA/P'));
        $this->assertFalse($p->supports('W1AW'), 'US callsign');
        $this->assertFalse($p->supports('VK2DEF'), 'Australian callsign');
        $this->assertFalse($p->supports('YB1ZZZ'), 'Indonesian — RAPI territory');
    }

    public function testHttpErrorThrows(): void
    {
        $this->expectException(\App\Service\CallsignLookup\CallsignLookupException::class);
        $this->provider('no-results.html', 503)->lookup('9W2NSP');
    }

    public function testTitleCasesScreamingCapsNames(): void
    {
        // MCMC stores names in ALL CAPS; the scraper should produce a
        // readable form. We assert the result doesn't contain runs of
        // three+ uppercase letters in a row inside the name.
        $r = $this->provider('9w2nsp.html')->lookup('9W2NSP');
        $this->assertNotNull($r);
        $this->assertDoesNotMatchRegularExpression(
            '/[A-Z]{3,}/',
            (string)$r->name,
            "Name should be title-cased, got: {$r->name}"
        );
    }
}
