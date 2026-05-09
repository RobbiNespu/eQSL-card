<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Admin;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Integration tests for /admin/cleanup (M4-T9 + T10 + T11).
 *
 * Covers:
 *  - 403 for authenticated non-admins (mirrors the rest of Admin/*).
 *  - Index renders OK and includes both panels.
 *  - Purging old guest cards: deletes the row AND writes a
 *    `cleanup.guest_cards_purged` audit log entry.
 *  - Pruning orphaned uploads: deletes the row AND writes a
 *    `cleanup.orphan_uploads_pruned` audit log entry.
 */
final class CleanupControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'app.Users',
        'app.Templates',
        'app.Uploads',
        'app.Cards',
        'app.GuestVisits',
        'app.AuditLogs',
    ];

    private static int $shaCounter = 0;

    private function loginAs(string $role): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'X', 'email' => uniqid('u') . '@x.com', 'role' => $role,
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id]]);

        return $u->id;
    }

    private function seedGuestVisit(): int
    {
        $g = $this->getTableLocator()->get('GuestVisits');
        self::$shaCounter++;
        $row = $g->saveOrFail($g->newEntity([
            'session_token' => str_pad((string)self::$shaCounter, 43, 't'),
            'ip_hash' => str_repeat('a', 64),
            'user_agent_hash' => str_repeat('b', 64),
        ]));

        return $row->id;
    }

    private function seedTemplate(): int
    {
        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'sys',
            'canvas_width' => 1500,
            'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true,
            'is_public' => true,
            'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        return $tpl->id;
    }

    private function seedUpload(?int $guestVisitId = null, ?int $userId = null): \Cake\Datasource\EntityInterface
    {
        $uploads = $this->getTableLocator()->get('Uploads');
        self::$shaCounter++;
        $data = [
            'original_filename' => 'g.jpg',
            'storage_path' => 'p' . self::$shaCounter . '.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1,
            'height_px' => 1,
            'file_size_bytes' => 1024,
            'sha256_hash' => str_pad((string)self::$shaCounter, 64, 'c', STR_PAD_LEFT),
        ];
        if ($guestVisitId !== null) {
            $data['guest_visit_id'] = $guestVisitId;
        }
        if ($userId !== null) {
            $data['user_id'] = $userId;
        }

        return $uploads->saveOrFail($uploads->newEntity($data));
    }

    public function testNonAdminGets403(): void
    {
        $this->loginAs('user');
        $this->get('/admin/cleanup');
        $this->assertResponseCode(403);
    }

    public function testIndexShowsCounts(): void
    {
        $this->loginAs('admin');
        $this->get('/admin/cleanup?days=30');
        $this->assertResponseOk();
        $this->assertResponseContains('Guest cards to purge');
        $this->assertResponseContains('Orphaned uploads to prune');
    }

    public function testPurgeOldGuestCards(): void
    {
        $admin = $this->loginAs('admin');

        $tplId = $this->seedTemplate();
        $visitId = $this->seedGuestVisit();
        $upload = $this->seedUpload(guestVisitId: $visitId);

        $cards = $this->getTableLocator()->get('Cards');
        $oldCard = $cards->saveOrFail($cards->newEntity([
            'guest_visit_id' => $visitId,
            'template_id' => $tplId,
            'upload_id' => $upload->id,
            'qso_data_json' => '{}',
            'png_path' => 'files/cards/old.png',
            'pdf_path' => 'files/cards/old.pdf',
        ]));
        // Backdate so it falls past the 30-day cutoff.
        $cards->updateAll(['created_at' => DateTime::now()->subDays(60)], ['id' => $oldCard->id]);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/cleanup/purge-guests', ['days' => 30]);
        $this->assertRedirect('/admin/cleanup');

        $exists = $cards->find()->where(['id' => $oldCard->id])->count();
        $this->assertSame(0, $exists);

        $audit = $this->getTableLocator()->get('AuditLogs');
        $log = $audit->find()->where(['event' => 'cleanup.guest_cards_purged'])->first();
        $this->assertNotNull($log);
    }

    public function testPruneOrphanUploads(): void
    {
        $this->loginAs('admin');

        $visitId = $this->seedGuestVisit();
        $upload = $this->seedUpload(guestVisitId: $visitId);

        $uploads = $this->getTableLocator()->get('Uploads');
        // Backdate so it falls past the 30-day cutoff.
        $uploads->updateAll(['created_at' => DateTime::now()->subDays(60)], ['id' => $upload->id]);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/cleanup/prune-uploads', ['days' => 30]);
        $this->assertRedirect('/admin/cleanup');

        $exists = $uploads->find()->where(['id' => $upload->id])->count();
        $this->assertSame(0, $exists);

        $audit = $this->getTableLocator()->get('AuditLogs');
        $log = $audit->find()->where(['event' => 'cleanup.orphan_uploads_pruned'])->first();
        $this->assertNotNull($log);
    }
}
