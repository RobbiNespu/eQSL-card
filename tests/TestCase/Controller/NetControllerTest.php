<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class NetControllerTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.NetSessions', 'app.NetSessionLoggers', 'app.Qsos'];

    public function testPublicViewRendersWhenPublic(): void
    {
        $this->get('/net/live-net-slug');
        $this->assertResponseOk();
        $this->assertResponseContains('MARTS Daily Net');
    }

    public function testPublicFeedHidesLoggedBy(): void
    {
        // Seed a real check-in so the feed returns a non-empty checkins array.
        // Without a row the assertion passes vacuously (nothing to strip).
        // Users fixture is empty, so create the owner row first (needed by
        // the existsIn FK rule on Qsos.user_id).
        $u = $this->getTableLocator()->get('Users');
        $owner = $u->newEmptyEntity();
        $owner->set('id', 1, ['guard' => false]);
        $owner->set('name', 'Net Stn', ['guard' => false]);
        $owner->set('callsign', '9W2NSP', ['guard' => false]);
        $owner->set('email', 'nsp@example.com', ['guard' => false]);
        $owner->set('password', 'irrelevant', ['guard' => false]);
        $u->saveOrFail($owner, ['checkRules' => false]);

        $q = $this->getTableLocator()->get('Qsos');
        $q->saveOrFail($q->newEntity([
            'user_id'          => 1,
            'call_worked'      => '9M2PUB',
            'qso_type'         => 'net',
            'net_session_id'   => 1,
            'ncs_callsign'     => '9W2NSP',
            'net_title'        => 'MARTS Daily Net',
            'rst_received'     => '59',
            'qso_datetime_utc' => '2026-05-22 12:30:00',
        ], ['accessibleFields' => ['*' => true]]));
        // logged_by_user_id is locked from mass assignment — set it explicitly.
        $row = $q->find()->where(['call_worked' => '9M2PUB'])->firstOrFail();
        $row->set('logged_by_user_id', 2, ['guard' => false]);
        $q->saveOrFail($row);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/net/live-net-slug/live');
        $this->assertResponseOk();
        // Row is present in the payload — confirms seeding worked.
        $this->assertResponseContains('9M2PUB');
        // logged_by_user_id must be absent — confirms the whitelist strips it.
        $this->assertResponseNotContains('logged_by_user_id');
    }

    public function testScheduledSessionNotPublic(): void
    {
        $t = $this->getTableLocator()->get('NetSessions');
        $s = $t->newEntity(['net_title' => 'Future'], ['accessibleFields' => ['*' => true]]);
        $s->set('owner_id', 1, ['guard' => false]);
        $s->set('status', 'scheduled', ['guard' => false]);
        $s->set('public_slug', 'future-slug', ['guard' => false]);
        $s->set('is_public', true, ['guard' => false]);
        $t->saveOrFail($s);
        $this->get('/net/future-slug');
        $this->assertResponseCode(404);
    }

    public function testPrivateSessionNotPublic(): void
    {
        $t = $this->getTableLocator()->get('NetSessions');
        $s = $t->newEntity(['net_title' => 'Private'], ['accessibleFields' => ['*' => true]]);
        $s->set('owner_id', 1, ['guard' => false]);
        $s->set('status', 'live', ['guard' => false]);
        $s->set('public_slug', 'private-slug', ['guard' => false]);
        $s->set('is_public', false, ['guard' => false]);
        $t->saveOrFail($s);
        $this->get('/net/private-slug');
        $this->assertResponseCode(404);
    }
}
