<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Radioless / internet-mediated QSO surface.
 *
 * Covers:
 *  - Adding an internet QSO persists transport + transport_meta.
 *  - Validation rejects an unknown transport code.
 *  - Listing filter `?transport=internet` returns only non-RF rows.
 *  - Listing filter `?transport=echolink` returns only echolink rows.
 *  - List badge surfaces the transport label for non-RF QSOs.
 *  - Frequency / band are still optional (no validation error when empty
 *    on an internet transport).
 */
final class QsosControllerTransportTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Qsos'];

    private function loginAs(string $email = 'op@x.com'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'OP', 'email' => $email, 'role' => 'user', 'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id, 'email' => $email]]);

        return $u->id;
    }

    public function testAddInternetQsoPersistsTransport(): void
    {
        $u = $this->loginAs();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/new', [
            'call_worked' => 'W1ABC',
            'qso_datetime_utc' => '2026-05-09 14:00:00',
            'qso_type' => 'contact',
            'transport' => 'echolink',
            'transport_meta' => 'Node 12345',
            // freq/band intentionally omitted — should be allowed
        ]);
        $this->assertRedirectContains('/qsos/');

        $row = $this->getTableLocator()->get('Qsos')
            ->find()->where(['user_id' => $u])->first();
        $this->assertSame('echolink', $row->transport);
        $this->assertSame('Node 12345', $row->transport_meta);
    }

    public function testUnknownTransportRejected(): void
    {
        $this->loginAs();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/new', [
            'call_worked' => 'W1ABC',
            'qso_datetime_utc' => '2026-05-09 14:00:00',
            'qso_type' => 'contact',
            'transport' => 'morse_carrier_pigeon',
        ]);
        // Form rerenders rather than redirecting.
        $this->assertResponseOk();
        $this->assertSame(0, $this->getTableLocator()->get('Qsos')->find()->count());
    }

    public function testRfTransportRoundtripIgnoresMeta(): void
    {
        $u = $this->loginAs();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/new', [
            'call_worked' => 'W1DEF',
            'qso_datetime_utc' => '2026-05-09 12:00:00',
            'qso_type' => 'contact',
            'band' => '20m', 'mode' => 'SSB',
            'transport' => 'rf',
            // RF mode posts empty transport_meta — entity mutator should
            // normalise this to NULL.
            'transport_meta' => '',
        ]);
        $this->assertRedirectContains('/qsos/');
        $row = $this->getTableLocator()->get('Qsos')->find()->first();
        $this->assertSame('rf', $row->transport);
        $this->assertNull($row->transport_meta);
    }

    public function testListingFilterInternet(): void
    {
        $u = $this->loginAs();
        $qsos = $this->getTableLocator()->get('Qsos');
        // Two RF, one Echolink, one Zello.
        $seed = function (string $call, string $tr, ?string $meta = null) use ($u, $qsos): void {
            $e = $qsos->newEntity([
                'call_worked' => $call,
                'qso_datetime_utc' => '2026-05-09 14:00:00',
                'band' => '20m', 'mode' => 'SSB',
                'transport' => $tr,
                'transport_meta' => $meta,
            ]);
            $e->user_id = $u;
            $qsos->saveOrFail($e);
        };
        $seed('W1AAA', 'rf');
        $seed('W1BBB', 'rf');
        $seed('W1ECHO', 'echolink', 'Node 999');
        $seed('W1ZELLO', 'zello', 'Hamradio channel');

        // ?transport=internet → both non-RF rows
        $this->get('/qsos?transport=internet');
        $this->assertResponseOk();
        $this->assertResponseContains('W1ECHO');
        $this->assertResponseContains('W1ZELLO');
        $this->assertResponseNotContains('W1AAA');

        // ?transport=echolink → only echolink
        $this->get('/qsos?transport=echolink');
        $this->assertResponseOk();
        $this->assertResponseContains('W1ECHO');
        $this->assertResponseNotContains('W1ZELLO');
        $this->assertResponseNotContains('W1AAA');

        // ?transport=rf → only RF rows
        $this->get('/qsos?transport=rf');
        $this->assertResponseOk();
        $this->assertResponseContains('W1AAA');
        $this->assertResponseContains('W1BBB');
        $this->assertResponseNotContains('W1ECHO');
    }

    public function testListingShowsTransportBadge(): void
    {
        $u = $this->loginAs();
        $qsos = $this->getTableLocator()->get('Qsos');
        $e = $qsos->newEntity([
            'call_worked' => 'W1ECHO', 'qso_datetime_utc' => '2026-05-09 14:00:00',
            'band' => '20m', 'mode' => 'SSB',
            'transport' => 'echolink', 'transport_meta' => 'Node 999',
        ]);
        $e->user_id = $u;
        $qsos->saveOrFail($e);

        $this->get('/qsos');
        $this->assertResponseOk();
        // Badge shows the uppercase code; tooltip carries the label.
        $this->assertResponseContains('ECHOLINK');
        $this->assertResponseContains('Echolink');
    }

    public function testDetailViewShowsTransport(): void
    {
        $u = $this->loginAs();
        $qsos = $this->getTableLocator()->get('Qsos');
        $e = $qsos->newEntity([
            'call_worked' => 'W1MUMBLE', 'qso_datetime_utc' => '2026-05-09 14:00:00',
            'transport' => 'mumble', 'transport_meta' => 'hamradio.example.com',
        ]);
        $e->user_id = $u;
        $qsos->saveOrFail($e);

        $this->get('/qsos/' . $e->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Mumble');
        $this->assertResponseContains('hamradio.example.com');
    }
}
