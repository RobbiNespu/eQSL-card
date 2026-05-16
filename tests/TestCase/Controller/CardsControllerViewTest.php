<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class CardsControllerViewTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.Templates', 'app.CardBackgrounds', 'app.Cards'];

    private static int $shaCounter = 0;

    private function seedUserAndLogin(string $email = 'op@x.com'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'OP', 'email' => $email, 'role' => 'user', 'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id, 'email' => $email]]);
        return $u->id;
    }

    private function seedCardForUser(int $userId, array $extras = []): int
    {
        $t = $this->getTableLocator()->get('Templates');
        $tpl = $t->saveOrFail($t->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));
        $u = $this->getTableLocator()->get('CardBackgrounds');
        self::$shaCounter++;
        $upload = $u->saveOrFail($u->newEntity([
            'user_id' => $userId,
            'original_filename' => 'bg.jpg',
            'storage_path' => 'files/uploads/x.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1500, 'height_px' => 1000,
            'file_size_bytes' => 12345,
            'sha256_hash' => str_pad((string)self::$shaCounter, 64, 'a', STR_PAD_LEFT),
        ]));
        $c = $this->getTableLocator()->get('Cards');
        $card = $c->saveOrFail($c->newEntity(array_merge([
            'user_id' => $userId,
            'template_id' => $tpl->id,
            'upload_id' => $upload->id,
            'qso_data_json' => json_encode(['callsign' => 'W1AW', 'qso_datetime_utc' => '2026-05-09 14:32:00', 'band' => '20m', 'mode' => 'SSB', 'rst_sent' => '59', 'rst_received' => '59']),
            'png_path' => 'files/cards/abc.png',
            'pdf_path' => 'files/cards/abc.pdf',
        ], $extras), ['accessibleFields' => ['share_slug' => true, 'share_revoked_at' => true]]));
        return $card->id;
    }

    public function testViewShowsCardForOwner(): void
    {
        $u = $this->seedUserAndLogin();
        $cardId = $this->seedCardForUser($u);
        $this->get('/cards/' . $cardId);
        $this->assertResponseOk();
        $this->assertResponseContains('W1AW');
        // Button labels were renamed from "Download PNG" → "Download image"
        // when the renderer flipped from PNG to WebP. PDF stays.
        $this->assertResponseContains('Download image');
        $this->assertResponseContains('Download PDF');
    }

    public function testForeignCardReturns404(): void
    {
        $a = $this->seedUserAndLogin('a@x.com');
        $users = $this->getTableLocator()->get('Users');
        $b = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $bsCardId = $this->seedCardForUser($b->id);

        $this->get('/cards/' . $bsCardId);
        $this->assertResponseCode(404);
    }

    public function testSharedCardShowsLinkAndRevokeButton(): void
    {
        $u = $this->seedUserAndLogin();
        $cardId = $this->seedCardForUser($u, ['share_slug' => str_repeat('s', 43)]);
        $this->get('/cards/' . $cardId);
        $this->assertResponseOk();
        $this->assertResponseContains('Public link:');
        $this->assertResponseContains('/qsl/' . str_repeat('s', 43));
        $this->assertResponseContains('Revoke share');
    }

    public function testRevokedCardShowsHistoricNote(): void
    {
        $u = $this->seedUserAndLogin();
        $cardId = $this->seedCardForUser($u, [
            'share_slug' => str_repeat('r', 43),
            'share_revoked_at' => '2026-05-09 12:00:00',
        ]);
        $this->get('/cards/' . $cardId);
        $this->assertResponseContains('Share was revoked');
    }
}
