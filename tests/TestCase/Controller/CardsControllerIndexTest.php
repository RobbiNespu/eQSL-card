<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * CardsController::index integration tests (M2-T7).
 *
 * Covers:
 *  - Anonymous request redirects to /login.
 *  - Index lists ONLY the logged-in user's cards.
 *  - Empty-state copy renders when the user has zero cards.
 *  - Share status badges (Private / Shared / Share revoked) render correctly.
 */
final class CardsControllerIndexTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Templates', 'app.Uploads', 'app.Cards'];

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

    private function seedSystemTemplate(): int
    {
        $t = $this->getTableLocator()->get('Templates');
        $row = $t->saveOrFail($t->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        return $row->id;
    }

    private function seedUpload(int $userId): int
    {
        // sha256_hash has a unique index in `uploads`, so each seeded row
        // needs a distinct value. We derive one from `userId` + a counter so
        // tests that seed multiple uploads (e.g. owner-vs-other-user) don't
        // collide.
        static $counter = 0;
        $counter++;
        $hash = str_pad(dechex($userId * 1000 + $counter), 64, '0', STR_PAD_LEFT);

        $u = $this->getTableLocator()->get('Uploads');
        $row = $u->saveOrFail($u->newEntity([
            'user_id' => $userId,
            'original_filename' => 'bg.jpg',
            'storage_path' => 'files/uploads/abc' . $counter . '.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1500, 'height_px' => 1000,
            'file_size_bytes' => 12345,
            'sha256_hash' => $hash,
        ]));

        return $row->id;
    }

    private function seedCard(int $userId, int $templateId, int $uploadId, string $callsign = 'W1AW'): int
    {
        $c = $this->getTableLocator()->get('Cards');
        $row = $c->saveOrFail($c->newEntity([
            'user_id' => $userId,
            'template_id' => $templateId,
            'upload_id' => $uploadId,
            'qso_data_json' => json_encode([
                'callsign' => $callsign,
                'qso_datetime_utc' => '2026-05-09 14:32:00',
                'band' => '20m',
                'mode' => 'SSB',
            ]),
            'png_path' => 'files/cards/abc.png',
            'pdf_path' => 'files/cards/abc.pdf',
        ]));

        return $row->id;
    }

    public function testRedirectsAnonymousToLogin(): void
    {
        $this->get('/cards');
        $this->assertRedirectContains('/login');
    }

    public function testListsOnlyOwnCards(): void
    {
        $u1 = $this->seedUserAndLogin('a@x.com');
        $tplId = $this->seedSystemTemplate();
        $upId = $this->seedUpload($u1);

        // Other user's data
        $users = $this->getTableLocator()->get('Users');
        $u2 = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $up2 = $this->seedUpload($u2->id);

        $this->seedCard($u1, $tplId, $upId, 'W1MINE');
        $this->seedCard($u2->id, $tplId, $up2, 'W1OTHER');

        $this->get('/cards');
        $this->assertResponseOk();
        $this->assertResponseContains('W1MINE');
        $this->assertResponseNotContains('W1OTHER');
    }

    public function testEmptyStateShown(): void
    {
        $this->seedUserAndLogin();
        $this->get('/cards');
        $this->assertResponseOk();
        $this->assertResponseContains("haven't generated any cards");
    }

    public function testShareBadges(): void
    {
        $u = $this->seedUserAndLogin();
        $tpl = $this->seedSystemTemplate();
        $up = $this->seedUpload($u);

        // Private (no slug)
        $this->seedCard($u, $tpl, $up, 'NOSHARE');

        // Shared
        $cards = $this->getTableLocator()->get('Cards');
        $cards->saveOrFail($cards->newEntity([
            'user_id' => $u, 'template_id' => $tpl, 'upload_id' => $up,
            'qso_data_json' => json_encode(['callsign' => 'SHARED']),
            'png_path' => 'p.png', 'pdf_path' => 'p.pdf',
            'share_slug' => str_repeat('s', 43),
        ], ['accessibleFields' => ['share_slug' => true]]));

        // Revoked
        $cards->saveOrFail($cards->newEntity([
            'user_id' => $u, 'template_id' => $tpl, 'upload_id' => $up,
            'qso_data_json' => json_encode(['callsign' => 'REVOKED']),
            'png_path' => 'r.png', 'pdf_path' => 'r.pdf',
            'share_slug' => str_repeat('r', 43),
            'share_revoked_at' => '2026-05-09 12:00:00',
        ], ['accessibleFields' => ['share_slug' => true, 'share_revoked_at' => true]]));

        $this->get('/cards');
        $this->assertResponseContains('Private');
        $this->assertResponseContains('Shared');
        $this->assertResponseContains('Share revoked');
    }
}
