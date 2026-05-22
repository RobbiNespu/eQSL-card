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
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/net/live-net-slug/live');
        $this->assertResponseOk();
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
