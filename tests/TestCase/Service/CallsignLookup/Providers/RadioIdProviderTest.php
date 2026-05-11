<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\Providers\RadioIdProvider;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\TestSuite\TestCase;

final class RadioIdProviderTest extends TestCase
{
    private function provider(string $body, int $status = 200): RadioIdProvider
    {
        $adapter = new class($body, $status) implements \Cake\Http\Client\AdapterInterface {
            public function __construct(private string $body, private int $status) {}
            public function send(\Psr\Http\Message\RequestInterface $request, array $options): array
            {
                return [new Response(["HTTP/1.1 {$this->status} OK"], $this->body)];
            }
        };
        $client = new Client(['adapter' => $adapter]);
        return new RadioIdProvider($client);
    }

    public function testParsesValidJson(): void
    {
        $body = json_encode([
            'count' => 1,
            'results' => [[
                'callsign' => 'W1AW',
                'fname' => 'Hiram',
                'surname' => 'Maxim',
                'city' => 'Newington',
                'state' => 'CT',
                'country' => 'United States',
                'id' => 1234567,
            ]],
        ]);
        $r = $this->provider($body)->lookup('W1AW');
        $this->assertNotNull($r);
        $this->assertSame('radioid', $r->source);
        $this->assertSame('Hiram Maxim', $r->name);
        $this->assertSame('Newington, CT', $r->qth);
        $this->assertSame('United States', $r->country);
    }

    public function testEmptyResultsReturnsNull(): void
    {
        $r = $this->provider(json_encode(['count' => 0, 'results' => []]))->lookup('XX0XX');
        $this->assertNull($r);
    }

    public function testHttpErrorThrows(): void
    {
        $this->expectException(\App\Service\CallsignLookup\CallsignLookupException::class);
        $this->provider('Internal Server Error', 500)->lookup('W1AW');
    }

    public function testSupportsOnlyValidCallsigns(): void
    {
        $p = new RadioIdProvider();
        $this->assertTrue($p->supports('W1AW'));
        $this->assertTrue($p->supports('9W2NSP'));
        $this->assertTrue($p->supports('VK2DEF/P'));
        $this->assertFalse($p->supports(''));
        $this->assertFalse($p->supports('AB'));
        $this->assertFalse($p->supports('NotACallsignSequence'));
    }
}
