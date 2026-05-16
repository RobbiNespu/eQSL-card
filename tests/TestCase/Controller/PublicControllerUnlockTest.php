<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * PublicController::unlock integration tests (M2-T15).
 *
 * Covers:
 *  - GET /qsl/{slug}/unlock renders the password form for a protected card.
 *  - POST with the correct password redirects to /qsl/{slug} (and writes
 *    the per-slug session unlock flag, which `share()` honours).
 *  - POST with the wrong password re-renders the form with the flash error.
 *  - Unknown slug 404s.
 *  - A card with no `share_password_hash` redirects straight to
 *    /qsl/{slug} (`share()` handles the open / 410 state).
 */
final class PublicControllerUnlockTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Templates', 'app.CardBackgrounds', 'app.Cards'];

    /**
     * `uploads.sha256_hash` carries a UNIQUE index, so each seeded upload
     * row needs a distinct hash. A static counter keeps that simple even
     * across multiple seed calls within the same test method.
     */
    private static int $shaCounter = 0;

    private function seedProtectedShare(string $password): array
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
        $uploads = $this->getTableLocator()->get('CardBackgrounds');
        self::$shaCounter++;
        $upload = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $u->id,
            'original_filename' => 'bg.jpg',
            'storage_path' => 'files/uploads/x.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1500, 'height_px' => 1000, 'file_size_bytes' => 1234,
            'sha256_hash' => str_pad((string)self::$shaCounter, 64, 'a', STR_PAD_LEFT),
        ]));
        $slug = str_repeat('p', 43);
        $cards = $this->getTableLocator()->get('Cards');
        $card = $cards->saveOrFail($cards->newEntity([
            'user_id' => $u->id, 'template_id' => $tpl->id, 'upload_id' => $upload->id,
            'qso_data_json' => json_encode(['callsign' => 'W1AW']),
            'png_path' => 'p.png', 'pdf_path' => 'p.pdf',
            'share_slug' => $slug,
            'share_password_hash' => password_hash($password, PASSWORD_ARGON2ID),
        ], ['accessibleFields' => ['share_slug' => true, 'share_password_hash' => true]]));
        return ['slug' => $slug, 'cardId' => $card->id];
    }

    public function testGetUnlockShowsForm(): void
    {
        ['slug' => $slug] = $this->seedProtectedShare('secret123');
        $this->get('/qsl/' . $slug . '/unlock');
        $this->assertResponseOk();
        $this->assertResponseContains('Password required');
    }

    public function testCorrectPasswordRedirectsToShare(): void
    {
        ['slug' => $slug] = $this->seedProtectedShare('secret123');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsl/' . $slug . '/unlock', ['password' => 'secret123']);
        $this->assertRedirect('/qsl/' . $slug);
    }

    public function testWrongPasswordRedisplaysForm(): void
    {
        ['slug' => $slug] = $this->seedProtectedShare('secret123');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsl/' . $slug . '/unlock', ['password' => 'wrong']);
        $this->assertResponseOk();
        $this->assertResponseContains('Incorrect password');
    }

    public function testUnknownSlug404(): void
    {
        $this->get('/qsl/' . str_repeat('z', 43) . '/unlock');
        $this->assertResponseCode(404);
    }

    public function testNoPasswordOnCardRedirectsToShare(): void
    {
        // Reuse seed but clear password_hash
        ['slug' => $slug, 'cardId' => $cid] = $this->seedProtectedShare('xyz');
        $cards = $this->getTableLocator()->get('Cards');
        $card = $cards->get($cid);
        $card->set('share_password_hash', null, ['guard' => false]);
        $cards->saveOrFail($card);
        $this->get('/qsl/' . $slug . '/unlock');
        $this->assertRedirect('/qsl/' . $slug);
    }
}
