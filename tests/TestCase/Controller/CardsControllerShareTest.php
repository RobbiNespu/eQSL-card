<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * CardsController::share integration tests (M2-T13).
 *
 * Covers:
 *  - POST /cards/{id}/share without a password mints a 43-char slug and leaves
 *    `share_password_hash` null.
 *  - POST with a password persists an Argon2id hash (NOT plaintext) that
 *    `password_verify` accepts.
 *  - Cross-user attempts 404 (matches `view`/`delete` policy of not leaking row
 *    existence).
 *  - Re-sharing after a previous revoke mints a NEW slug and clears
 *    `share_revoked_at` — the only path back to a working public link
 *    post-revoke.
 */
final class CardsControllerShareTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Templates', 'app.CardBackgrounds', 'app.Cards'];

    /**
     * `uploads.sha256_hash` carries a UNIQUE index, so each seeded upload row
     * needs a distinct hash. A static counter keeps that simple even when a
     * single test seeds multiple cards.
     */
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

    private function seedCard(int $userId, array $extras = []): int
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
            'storage_path' => 'files/uploads/x' . self::$shaCounter . '.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1500, 'height_px' => 1000,
            'file_size_bytes' => 1234,
            'sha256_hash' => str_pad((string)self::$shaCounter, 64, 'a', STR_PAD_LEFT),
        ]));

        $c = $this->getTableLocator()->get('Cards');
        $card = $c->saveOrFail($c->newEntity(array_merge([
            'user_id' => $userId,
            'template_id' => $tpl->id,
            'upload_id' => $upload->id,
            'qso_data_json' => json_encode(['callsign' => 'W1AW']),
            'png_path' => 'p.png', 'pdf_path' => 'p.pdf',
        ], $extras), ['accessibleFields' => [
            'user_id' => true, 'template_id' => true, 'upload_id' => true,
            'qso_data_json' => true, 'png_path' => true, 'pdf_path' => true,
            'share_slug' => true, 'share_revoked_at' => true,
        ]]));

        return $card->id;
    }

    public function testShareMintsSlugWithoutPassword(): void
    {
        $u = $this->seedUserAndLogin();
        $cardId = $this->seedCard($u);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $cardId . '/share', ['password' => '']);
        $this->assertRedirect('/cards/' . $cardId);

        $row = $this->getTableLocator()->get('Cards')->get($cardId);
        // 43 chars = base64(32 bytes) with the single '=' pad rtrim'd.
        $this->assertSame(43, strlen((string)$row->share_slug));
        $this->assertNull($row->share_password_hash);
    }

    public function testShareWithPasswordHashesIt(): void
    {
        $u = $this->seedUserAndLogin();
        $cardId = $this->seedCard($u);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $cardId . '/share', ['password' => 'secret123']);

        $row = $this->getTableLocator()->get('Cards')->get($cardId);
        $this->assertNotNull($row->share_password_hash);
        // Pin the hash algorithm — relying on PHP's PASSWORD_DEFAULT would
        // let a future PHP upgrade silently swap algorithms on us.
        $this->assertStringStartsWith('$argon2id$', $row->share_password_hash);
        $this->assertTrue(password_verify('secret123', $row->share_password_hash));
    }

    public function testCannotShareOtherUsersCard(): void
    {
        $a = $this->seedUserAndLogin('a@x.com');

        $users = $this->getTableLocator()->get('Users');
        $b = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $bsCard = $this->seedCard($b->id);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $bsCard . '/share', ['password' => '']);
        $this->assertResponseCode(404);
    }

    public function testReSharingAfterRevokeReinstates(): void
    {
        $u = $this->seedUserAndLogin();
        $cardId = $this->seedCard($u, [
            'share_slug' => str_repeat('r', 43),
            'share_revoked_at' => '2026-05-09 12:00:00',
        ]);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $cardId . '/share', ['password' => '']);

        $row = $this->getTableLocator()->get('Cards')->get($cardId);
        // A new slug must replace the old one. Reusing the revoked slug would
        // "un-revoke" any URL the operator had previously distributed, which
        // is exactly the safety property revocation is meant to provide.
        $this->assertNotSame(str_repeat('r', 43), $row->share_slug, 'new slug should be minted');
        $this->assertNull($row->share_revoked_at);
    }
}
