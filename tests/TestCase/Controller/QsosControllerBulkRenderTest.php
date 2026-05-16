<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Bulk render endpoint integration tests (M2-T11).
 *
 * Covers:
 *  - POST /qsos/bulk-render renders the first chunk synchronously and returns
 *    a JSON payload exposing `{job_token, done, total, finished, card_ids}`.
 *  - With <=5 QSOs in the request, `finished` flips immediately.
 *  - Missing arguments yield a 400 JSON error.
 */
final class QsosControllerBulkRenderTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Qsos', 'app.Templates', 'app.Uploads', 'app.Cards'];

    private function seedUserAndLogin(): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'OP', 'email' => 'op@x.com', 'role' => 'user', 'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id, 'email' => 'op@x.com']]);

        return $u->id;
    }

    public function testBulkRenderProducesAllCards(): void
    {
        $u = $this->seedUserAndLogin();

        // Seed 3 QSOs — fits in one chunk of 5, so the first request finishes
        // the whole job synchronously and `finished` is true.
        $qsos = $this->getTableLocator()->get('Qsos');
        $qsoIds = [];
        foreach (['W1AW', 'K2DST', 'JA1ABC'] as $i => $call) {
            $e = $qsos->newEntity([
                'call_worked' => $call,
                'qso_datetime_utc' => sprintf('2026-05-%02d 14:32:00', $i + 1),
                'band' => '20m', 'mode' => 'SSB',
            ]);
            $e->user_id = $u;
            $qsos->saveOrFail($e);
            $qsoIds[] = $e->id;
        }

        // Template + a saved upload (skip the file optimization round-trip
        // by writing a real JPEG straight to disk and pointing an Uploads row
        // at it). Keeps the test focused on bulk dispatch, not image plumbing.
        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $bgPath = WWW_ROOT . 'files/uploads/bulk-test.jpg';
        if (!is_dir(dirname($bgPath))) {
            mkdir(dirname($bgPath), 0o775, true);
        }
        $img = imagecreatetruecolor(1500, 1000);
        imagejpeg($img, $bgPath);
        imagedestroy($img);

        $uploads = $this->getTableLocator()->get('Uploads');
        $upload = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $u,
            'original_filename' => 'bulk-test.jpg',
            'storage_path' => 'files/uploads/bulk-test.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1500, 'height_px' => 1000,
            'file_size_bytes' => filesize($bgPath),
            'sha256_hash' => str_pad('bulk', 64, 'a'),
        ]));

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/bulk-render', [
            'qso_ids' => $qsoIds,
            'template_id' => $tpl->id,
            'upload_id' => $upload->id,
        ]);
        $this->assertResponseOk();
        $this->assertContentType('application/json');

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(3, $body['total']);
        $this->assertTrue($body['finished'], 'Three QSOs fit in one chunk of 5');
        $this->assertCount(3, array_filter($body['card_ids']));

        $cards = $this->getTableLocator()->get('Cards');
        $this->assertSame(3, $cards->find()->where(['user_id' => $u])->count());
    }

    public function testRequiresArguments(): void
    {
        $this->seedUserAndLogin();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/bulk-render', []);
        $this->assertResponseCode(400);
    }

    /**
     * Bring the session written by the previous request forward into the
     * `_session` bag the trait uses to seed the next request. Mirrors the
     * helper in QsosControllerImportTest (M2-T6).
     */
    private function bridgeSession(): void
    {
        if (isset($_SESSION) && is_array($_SESSION) && $_SESSION !== []) {
            $this->_session = $_SESSION + $this->_session;
        }
    }

    public function testBulkRenderChunkPagination(): void
    {
        // Six QSOs to force two chunks
        $u = $this->seedUserAndLogin();
        $qsos = $this->getTableLocator()->get('Qsos');
        $qsoIds = [];
        for ($i = 0; $i < 6; $i++) {
            $e = $qsos->newEntity([
                'call_worked' => 'TEST' . $i,
                'qso_datetime_utc' => sprintf('2026-05-%02d 14:32:00', $i + 1),
                'band' => '20m', 'mode' => 'SSB',
            ]);
            $e->user_id = $u;
            $qsos->saveOrFail($e);
            $qsoIds[] = $e->id;
        }

        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $bgPath = WWW_ROOT . 'files/uploads/bulk-pag-test.jpg';
        if (!is_dir(dirname($bgPath))) {
            mkdir(dirname($bgPath), 0o775, true);
        }
        $img = imagecreatetruecolor(1500, 1000);
        imagejpeg($img, $bgPath);
        imagedestroy($img);

        $uploads = $this->getTableLocator()->get('Uploads');
        $upload = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $u,
            'original_filename' => 'p.jpg',
            'storage_path' => 'files/uploads/bulk-pag-test.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1500, 'height_px' => 1000,
            'file_size_bytes' => filesize($bgPath),
            'sha256_hash' => str_pad('pag', 64, 'b'),
        ]));

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/bulk-render', [
            'qso_ids' => $qsoIds,
            'template_id' => $tpl->id,
            'upload_id' => $upload->id,
        ]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(5, $body['done']);
        $this->assertSame(6, $body['total']);
        $this->assertFalse($body['finished']);
        $token = $body['job_token'];

        // The session-stored job needs to survive into the next request.
        $this->bridgeSession();

        // Second chunk
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/bulk-render/' . $token . '/next');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(6, $body['done']);
        $this->assertTrue($body['finished']);

        $cardCount = $this->getTableLocator()->get('Cards')->find()->where(['user_id' => $u])->count();
        $this->assertSame(6, $cardCount);
    }
}
