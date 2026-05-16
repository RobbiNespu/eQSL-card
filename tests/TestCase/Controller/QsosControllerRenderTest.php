<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;

/**
 * Render-from-QSO flow integration tests (M2-T10).
 *
 * Covers:
 *  - GET /qsos/{id}/render renders the template+background picker.
 *  - POST /qsos/{id}/render with a fresh upload renders, persists a card with
 *    `qso_id` set + `qso_data_json` populated, and redirects to /cards/{id}.
 *  - Foreign QSO 404s before any rendering happens.
 */
final class QsosControllerRenderTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Qsos', 'app.Templates', 'app.CardBackgrounds', 'app.Cards'];

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

    private function seedQso(int $userId): int
    {
        $qsos = $this->getTableLocator()->get('Qsos');
        $entity = $qsos->newEntity([
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
            'band' => '20m',
            'mode' => 'SSB',
        ]);
        $entity->user_id = $userId;
        $qsos->saveOrFail($entity);

        return $entity->id;
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

    public function testGetRenderShowsTemplatePicker(): void
    {
        $u = $this->seedUserAndLogin();
        $qsoId = $this->seedQso($u);
        $this->seedSystemTemplate();

        $this->get('/qsos/' . $qsoId . '/render');
        $this->assertResponseOk();
        $this->assertResponseContains('Template');
        $this->assertResponseContains('Generate eQSL');
    }

    public function testRenderProducesCardForOwner(): void
    {
        $u = $this->seedUserAndLogin();
        $qsoId = $this->seedQso($u);
        $tplId = $this->seedSystemTemplate();

        // Tiny JPEG fixture so GD has something real to decode.
        $img = imagecreatetruecolor(800, 600);
        $tmp = tempnam(sys_get_temp_dir(), 'fix_');
        imagejpeg($img, $tmp);
        imagedestroy($img);
        $upload = new UploadedFile($tmp, filesize($tmp), UPLOAD_ERR_OK, 'bg.jpg', 'image/jpeg');

        $this->configRequest(['files' => ['background_upload' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/' . $qsoId . '/render', ['template_id' => $tplId, 'upload_id' => 0]);
        $this->assertRedirectContains('/cards/');

        $cards = $this->getTableLocator()->get('Cards');
        $row = $cards->find()->where(['user_id' => $u, 'qso_id' => $qsoId])->first();
        $this->assertNotNull($row, 'Card should be persisted with qso_id set');
        $this->assertNotEmpty($row->png_path);
        // PDFs are no longer pre-rendered — built on demand by the lazy
        // download endpoint. New rows persist with pdf_path = null.
        $this->assertNull($row->pdf_path);
        // Card image is WebP now (column name kept for backwards compat).
        $this->assertStringEndsWith('.webp', $row->png_path);
        $this->assertNotEmpty($row->qso_data_json, 'qso snapshot must be persisted');
        $snapshot = json_decode($row->qso_data_json, true);
        $this->assertSame('W1AW', $snapshot['callsign'] ?? null);
    }

    public function testForeignQsoReturns404(): void
    {
        $this->seedUserAndLogin('a@x.com');
        $users = $this->getTableLocator()->get('Users');
        $b = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB',
            'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $bsQso = $this->seedQso($b->id);

        $this->get('/qsos/' . $bsQso . '/render');
        $this->assertResponseCode(404);
    }

    /**
     * Helper: stand up a non-deleted card row pointing at a QSO so the
     * "already rendered" guard can fire.
     */
    private function seedCard(int $userId, int $qsoId, int $templateId): int
    {
        // Need an upload row to satisfy cards.upload_id NOT NULL.
        $uploads = $this->getTableLocator()->get('CardBackgrounds');
        $u = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $userId,
            'original_filename' => 'guard.jpg',
            'storage_path' => 'files/uploads/guard.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 800, 'height_px' => 600, 'file_size_bytes' => 100,
            'sha256_hash' => str_repeat('g', 64),
        ]));
        $cards = $this->getTableLocator()->get('Cards');
        $row = $cards->saveOrFail($cards->newEntity([
            'user_id' => $userId,
            'qso_id' => $qsoId,
            'template_id' => $templateId,
            'upload_id' => $u->id,
            'qso_data_json' => '{}',
            'png_path' => 'files/cards/guard.png',
            'pdf_path' => 'files/cards/guard.pdf',
        ]));

        return $row->id;
    }

    public function testGetRenderRedirectsToExistingCard(): void
    {
        $u = $this->seedUserAndLogin();
        $qsoId = $this->seedQso($u);
        $tplId = $this->seedSystemTemplate();
        $cardId = $this->seedCard($u, $qsoId, $tplId);

        $this->get('/qsos/' . $qsoId . '/render');
        $this->assertRedirect('/cards/' . $cardId);
    }

    public function testPostRenderRefusedWhenCardExists(): void
    {
        $u = $this->seedUserAndLogin();
        $qsoId = $this->seedQso($u);
        $tplId = $this->seedSystemTemplate();
        $cardId = $this->seedCard($u, $qsoId, $tplId);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/' . $qsoId . '/render', ['template_id' => $tplId, 'upload_id' => 0]);
        $this->assertRedirect('/cards/' . $cardId);

        // No second card should have been created.
        $cards = $this->getTableLocator()->get('Cards');
        $this->assertSame(1, $cards->find()->where(['qso_id' => $qsoId])->count());
    }

    public function testRenderAllowedAfterCardSoftDeleted(): void
    {
        $u = $this->seedUserAndLogin();
        $qsoId = $this->seedQso($u);
        $tplId = $this->seedSystemTemplate();
        $cardId = $this->seedCard($u, $qsoId, $tplId);

        // Soft-delete the existing card.
        $cards = $this->getTableLocator()->get('Cards');
        $cards->updateAll(['deleted_at' => \Cake\I18n\DateTime::now()], ['id' => $cardId]);

        $this->get('/qsos/' . $qsoId . '/render');
        $this->assertResponseOk();
        $this->assertResponseContains('Generate eQSL');
    }
}
