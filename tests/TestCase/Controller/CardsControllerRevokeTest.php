<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * CardsController::revoke integration tests (M2-T16).
 *
 * Covers:
 *  - POST /cards/{id}/revoke on an actively-shared card stamps `share_revoked_at`
 *    (the slug itself is intentionally preserved so the public route can keep
 *    returning 410 Gone instead of 404, per M2-T14).
 *  - POST against a card that was never shared is a flash-and-redirect no-op,
 *    not a 5xx — operators may double-click the button or be confused by stale
 *    UI; the controller should be polite about it.
 *  - Cross-user attempts 404 (matches the existing `view`/`delete`/`share`
 *    policy of not leaking row existence).
 */
final class CardsControllerRevokeTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Templates', 'app.CardBackgrounds', 'app.Cards'];

    /**
     * `uploads.sha256_hash` is UNIQUE-indexed, so each seeded upload row needs
     * a distinct hash. A static counter keeps that simple even when a single
     * test seeds multiple cards (e.g. cross-user 404 case).
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

    private function seedSharedCard(int $userId, array $extras = []): int
    {
        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $uploads = $this->getTableLocator()->get('CardBackgrounds');
        self::$shaCounter++;
        $upload = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $userId,
            'original_filename' => 'bg.jpg',
            'storage_path' => 'files/uploads/x' . self::$shaCounter . '.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1500, 'height_px' => 1000, 'file_size_bytes' => 1234,
            'sha256_hash' => str_pad((string)self::$shaCounter, 64, 'a', STR_PAD_LEFT),
        ]));

        $cards = $this->getTableLocator()->get('Cards');
        $card = $cards->saveOrFail($cards->newEntity(array_merge([
            'user_id' => $userId, 'template_id' => $tpl->id, 'upload_id' => $upload->id,
            'qso_data_json' => json_encode(['callsign' => 'W1AW']),
            'png_path' => 'p.png', 'pdf_path' => 'p.pdf',
            'share_slug' => str_repeat('s', 43),
        ], $extras), ['accessibleFields' => [
            'user_id' => true, 'template_id' => true, 'upload_id' => true,
            'qso_data_json' => true, 'png_path' => true, 'pdf_path' => true,
            'share_slug' => true, 'share_revoked_at' => true,
        ]]));

        return $card->id;
    }

    public function testRevokeSetsRevokedAt(): void
    {
        $u = $this->seedUserAndLogin();
        $cardId = $this->seedSharedCard($u);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $cardId . '/revoke');
        $this->assertRedirect('/cards/' . $cardId);

        $row = $this->getTableLocator()->get('Cards')->get($cardId);
        $this->assertNotNull($row->share_revoked_at);
        // Slug is intentionally preserved post-revoke so /qsl/{slug} can return
        // 410 Gone rather than 404. Asserting the slug stays put is the test
        // that pins that behavioural contract here.
        $this->assertNotEmpty($row->share_slug);
    }

    public function testRevokingUnsharedDoesNotErrorButFlashes(): void
    {
        $u = $this->seedUserAndLogin();

        // Card without a share_slug — never shared, can't be revoked.
        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $uploads = $this->getTableLocator()->get('CardBackgrounds');
        self::$shaCounter++;
        $upload = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $u, 'original_filename' => 'b.jpg', 'storage_path' => 'p' . self::$shaCounter . '.jpg',
            'mime_type' => 'image/jpeg', 'width_px' => 1, 'height_px' => 1, 'file_size_bytes' => 1,
            'sha256_hash' => str_pad((string)self::$shaCounter, 64, 'b', STR_PAD_LEFT),
        ]));

        $cards = $this->getTableLocator()->get('Cards');
        $card = $cards->saveOrFail($cards->newEntity([
            'user_id' => $u, 'template_id' => $tpl->id, 'upload_id' => $upload->id,
            'qso_data_json' => '{}', 'png_path' => 'p.png', 'pdf_path' => 'p.pdf',
        ]));

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $card->id . '/revoke');
        $this->assertRedirect('/cards/' . $card->id);

        $row = $cards->get($card->id);
        $this->assertNull($row->share_revoked_at);
    }

    public function testCannotRevokeOtherUsersCard(): void
    {
        $a = $this->seedUserAndLogin('a@x.com');

        $users = $this->getTableLocator()->get('Users');
        $b = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $bsCard = $this->seedSharedCard($b->id);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $bsCard . '/revoke');
        $this->assertResponseCode(404);
    }
}
