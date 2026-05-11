<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * PublicController::share integration tests (M2-T14).
 *
 * Covers:
 *  - Anonymous GET /qsl/{slug} renders the embedded card with operator
 *    callsign, QSO callsign, download buttons, and OG meta tags.
 *  - A revoked share returns 410 Gone with a "Share revoked" message.
 *  - A password-protected share redirects to /qsl/{slug}/unlock when the
 *    visitor hasn't already unlocked it in this session.
 *  - An unknown / non-existent slug returns 404.
 */
final class PublicControllerShareTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Templates', 'app.Uploads', 'app.Cards'];

    /**
     * `uploads.sha256_hash` carries a UNIQUE index, so each seeded upload
     * row needs a distinct hash. A static counter keeps that simple even
     * across multiple seed calls within the same test method.
     */
    private static int $shaCounter = 0;

    private function seedSharedCard(array $cardExtras = []): array
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'OP', 'email' => 'op-' . uniqid() . '@x.com', 'role' => 'user',
            'callsign' => 'AA1AA', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));

        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $uploads = $this->getTableLocator()->get('Uploads');
        self::$shaCounter++;
        $upload = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $u->id,
            'original_filename' => 'bg.jpg',
            'storage_path' => 'files/uploads/x.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1500, 'height_px' => 1000, 'file_size_bytes' => 1234,
            'sha256_hash' => str_pad((string)self::$shaCounter, 64, 'a', STR_PAD_LEFT),
        ]));

        $slug = str_repeat('s', 43);
        $cards = $this->getTableLocator()->get('Cards');
        $card = $cards->saveOrFail($cards->newEntity(array_merge([
            'user_id' => $u->id,
            'template_id' => $tpl->id,
            'upload_id' => $upload->id,
            'qso_data_json' => json_encode([
                'callsign' => 'W1AW', 'qso_datetime_utc' => '2026-05-09 14:32:00',
                'band' => '20m', 'mode' => 'SSB', 'rst_sent' => '59', 'rst_received' => '59',
            ]),
            'png_path' => 'p.png', 'pdf_path' => 'p.pdf',
            'share_slug' => $slug,
        ], $cardExtras), ['accessibleFields' => ['share_slug' => true, 'share_revoked_at' => true, 'share_password_hash' => true]]));

        return ['card' => $card, 'slug' => $slug];
    }

    public function testPublicSharePageRendersAnonymously(): void
    {
        ['card' => $card, 'slug' => $slug] = $this->seedSharedCard();
        $this->get('/qsl/' . $slug);
        $this->assertResponseOk();
        $this->assertResponseContains('AA1AA');
        $this->assertResponseContains('W1AW');
        // Label renamed to "Download image" after the renderer switched from
        // PNG to WebP (the file is .webp on disk now).
        $this->assertResponseContains('Download image');
        $this->assertResponseContains('og:image');
    }

    public function testRevokedShareReturns410(): void
    {
        ['slug' => $slug] = $this->seedSharedCard(['share_revoked_at' => '2026-05-09 12:00:00']);
        $this->get('/qsl/' . $slug);
        $this->assertResponseCode(410);
        $this->assertResponseContains('Share revoked');
    }

    public function testPasswordProtectedShareRedirectsToUnlock(): void
    {
        ['slug' => $slug] = $this->seedSharedCard([
            'share_password_hash' => password_hash('secret', PASSWORD_ARGON2ID),
        ]);
        $this->get('/qsl/' . $slug);
        $this->assertRedirectContains('/qsl/' . $slug . '/unlock');
    }

    public function testUnknownSlugIs404(): void
    {
        $this->get('/qsl/' . str_repeat('z', 43));
        $this->assertResponseCode(404);
    }
}
