<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Share-PDF download endpoint tests.
 *
 * Mirrors the share view's auth model: 404 unknown slug, 410 revoked, redirect
 * to unlock for password-gated cards, 200 application/pdf when allowed.
 */
final class PublicControllerDownloadSharePdfTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Templates', 'app.Uploads', 'app.Cards'];

    private function seedShare(string $slug, array $cardExtras = []): array
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'OP', 'email' => 'op-' . uniqid() . '@x.com', 'role' => 'user',
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));

        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'sys', 'canvas_width' => 600, 'canvas_height' => 400,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $uploads = $this->getTableLocator()->get('Uploads');
        $up = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $u->id, 'original_filename' => 'bg.webp',
            'storage_path' => 'files/uploads/share-test.webp', 'mime_type' => 'image/webp',
            'width_px' => 600, 'height_px' => 400, 'file_size_bytes' => 100,
            'sha256_hash' => str_pad((string)uniqid(), 64, 'e', STR_PAD_LEFT),
        ]));

        $cardsDir = WWW_ROOT . 'files/cards/';
        if (!is_dir($cardsDir)) {
            mkdir($cardsDir, 0o775, true);
        }
        $filename = 'share-' . uniqid() . '.webp';
        $imageAbs = $cardsDir . $filename;
        $img = imagecreatetruecolor(600, 400);
        imagewebp($img, $imageAbs, 80);
        imagedestroy($img);

        $cards = $this->getTableLocator()->get('Cards');
        $card = $cards->saveOrFail($cards->newEntity(array_merge([
            'user_id' => $u->id, 'template_id' => $tpl->id, 'upload_id' => $up->id,
            'qso_data_json' => '{"callsign":"W1AW","operator_callsign":"AA1AA"}',
            'png_path' => 'files/cards/' . $filename, 'pdf_path' => null,
            'share_slug' => $slug,
        ], $cardExtras), ['accessibleFields' => [
            'share_slug' => true, 'share_revoked_at' => true, 'share_password_hash' => true,
        ]]));

        return [$card->id, $imageAbs];
    }

    public function testOpenShareReturnsPdf(): void
    {
        $slug = str_repeat('a', 43);
        [$cardId, $imageAbs] = $this->seedShare($slug);
        try {
            $this->get('/qsl/' . $slug . '/download.pdf');
            $this->assertResponseOk();
            $this->assertContentType('application/pdf');
            $this->assertStringStartsWith('%PDF-', (string)$this->_response->getBody());
        } finally {
            @unlink($imageAbs);
        }
    }

    public function testUnknownSlugReturns404(): void
    {
        $this->get('/qsl/' . str_repeat('z', 43) . '/download.pdf');
        $this->assertResponseCode(404);
    }

    public function testRevokedShareReturns410(): void
    {
        $slug = str_repeat('b', 43);
        [$cardId, $imageAbs] = $this->seedShare($slug, [
            'share_revoked_at' => \Cake\I18n\DateTime::now(),
        ]);
        try {
            $this->get('/qsl/' . $slug . '/download.pdf');
            $this->assertResponseCode(410);
        } finally {
            @unlink($imageAbs);
        }
    }

    public function testPasswordProtectedSharedRedirectsToUnlock(): void
    {
        $slug = str_repeat('c', 43);
        $hash = (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('letmein');
        [$cardId, $imageAbs] = $this->seedShare($slug, ['share_password_hash' => $hash]);
        try {
            $this->get('/qsl/' . $slug . '/download.pdf');
            $this->assertRedirectContains('/qsl/' . $slug . '/unlock');
        } finally {
            @unlink($imageAbs);
        }
    }
}
