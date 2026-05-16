<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Lazy-PDF download endpoint tests.
 *
 * Cards no longer persist a pre-rendered PDF; the download endpoint streams
 * a freshly-built one from the on-disk card image. These tests cover the
 * three access paths the controller branches on:
 *
 *   - authenticated owner          → 200 application/pdf
 *   - guest matching the visit     → 200 application/pdf
 *   - anonymous / wrong identity   → 404 (existence not leaked)
 */
final class CardsControllerDownloadPdfTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'app.Users', 'app.Templates', 'app.CardBackgrounds', 'app.Cards', 'app.GuestVisits',
    ];

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

    /**
     * Seed a real card on disk + DB. Returns [cardId, imageAbsPath]. We write
     * an actual WebP file so CardRenderer::wrapPdf has something to transcode
     * and FPDF has bytes to embed; using a fake placeholder would explode in
     * `getimagesize` and pollute the test with renderer internals.
     */
    private function seedCardWithImage(?int $userId, ?int $guestVisitId = null): array
    {
        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'sys', 'canvas_width' => 600, 'canvas_height' => 400,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $uploads = $this->getTableLocator()->get('CardBackgrounds');
        $up = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $userId,
            'guest_visit_id' => $guestVisitId,
            'original_filename' => 'bg.webp', 'storage_path' => 'files/uploads/dl-test.webp',
            'mime_type' => 'image/webp', 'width_px' => 600, 'height_px' => 400,
            'file_size_bytes' => 100, 'sha256_hash' => str_pad((string)uniqid(), 64, 'd', STR_PAD_LEFT),
        ]));

        $cardsDir = WWW_ROOT . 'files/cards/';
        if (!is_dir($cardsDir)) {
            mkdir($cardsDir, 0o775, true);
        }
        $filename = 'dl-test-' . uniqid() . '.webp';
        $imageAbs = $cardsDir . $filename;
        $img = imagecreatetruecolor(600, 400);
        imagewebp($img, $imageAbs, 80);
        imagedestroy($img);

        $cards = $this->getTableLocator()->get('Cards');
        $card = $cards->saveOrFail($cards->newEntity([
            'user_id' => $userId,
            'guest_visit_id' => $guestVisitId,
            'template_id' => $tpl->id,
            'upload_id' => $up->id,
            'qso_data_json' => '{"callsign":"W1AW"}',
            'png_path' => 'files/cards/' . $filename,
            'pdf_path' => null,
        ]));

        return [$card->id, $imageAbs];
    }

    public function testOwnerCanDownloadPdf(): void
    {
        $u = $this->loginAs();
        [$cardId, $imageAbs] = $this->seedCardWithImage($u);
        try {
            $this->get('/cards/' . $cardId . '/download.pdf');
            $this->assertResponseOk();
            $this->assertContentType('application/pdf');
            $this->assertHeaderContains('Content-Disposition', 'card-' . $cardId . '.pdf');
            // PDF magic bytes
            $body = (string)$this->_response->getBody();
            $this->assertStringStartsWith('%PDF-', $body);
        } finally {
            @unlink($imageAbs);
        }
    }

    public function testNonOwnerGets404(): void
    {
        $this->loginAs('a@x.com');
        // Card owned by a different user — must look like 'not found' rather
        // than '403 forbidden' so existence isn't leaked.
        $users = $this->getTableLocator()->get('Users');
        $b = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB',
            'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        [$cardId, $imageAbs] = $this->seedCardWithImage($b->id);
        try {
            $this->get('/cards/' . $cardId . '/download.pdf');
            $this->assertResponseCode(404);
        } finally {
            @unlink($imageAbs);
        }
    }

    public function testGuestWithMatchingCookieCanDownload(): void
    {
        $visits = $this->getTableLocator()->get('GuestVisits');
        $visit = $visits->saveOrFail($visits->newEntity([
            'session_token' => str_repeat('z', 43),
            'ip_hash' => str_repeat('a', 64), 'user_agent_hash' => str_repeat('b', 64),
        ]));
        [$cardId, $imageAbs] = $this->seedCardWithImage(null, $visit->id);
        try {
            $this->cookie(\App\Service\GuestSession::COOKIE, $visit->session_token);
            $this->get('/cards/' . $cardId . '/download.pdf');
            $this->assertResponseOk();
            $this->assertContentType('application/pdf');
        } finally {
            @unlink($imageAbs);
        }
    }

    public function testGuestWithoutCookieGets404(): void
    {
        $visits = $this->getTableLocator()->get('GuestVisits');
        $visit = $visits->saveOrFail($visits->newEntity([
            'session_token' => str_repeat('w', 43),
            'ip_hash' => str_repeat('a', 64), 'user_agent_hash' => str_repeat('b', 64),
        ]));
        [$cardId, $imageAbs] = $this->seedCardWithImage(null, $visit->id);
        try {
            // No cookie set → must 404, not 200.
            $this->get('/cards/' . $cardId . '/download.pdf');
            $this->assertResponseCode(404);
        } finally {
            @unlink($imageAbs);
        }
    }
}
