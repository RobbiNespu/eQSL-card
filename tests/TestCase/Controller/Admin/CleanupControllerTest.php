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
        'app.CardBackgrounds',
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
        $uploads = $this->getTableLocator()->get('CardBackgrounds');
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

        $uploads = $this->getTableLocator()->get('CardBackgrounds');
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

    /**
     * Helper: seed a throwaway file inside a tmp directory so the cleanup
     * actions have something concrete to delete and so we can assert it's
     * gone afterwards. Returns [absolutePath, basename].
     *
     * @return array{0:string, 1:string}
     */
    private function seedFile(string $dir, string $suffix = '.tmp'): array
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'cleanup-test-' . uniqid('', true) . $suffix;
        $abs = $dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($abs, "test\n");

        return [$abs, $name];
    }

    public function testCacheActionDeletesFilesAndPreservesGitkeep(): void
    {
        $this->loginAs('admin');

        [$victim, $vBase] = $this->seedFile(TMP . 'cache' . DIRECTORY_SEPARATOR . 'persistent');
        $keep = TMP . 'cache' . DIRECTORY_SEPARATOR . 'persistent' . DIRECTORY_SEPARATOR . '.gitkeep';
        if (!file_exists($keep)) {
            file_put_contents($keep, '');
        }

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/cleanup/cache');
        $this->assertRedirect('/admin/cleanup');

        $this->assertFileDoesNotExist($victim);
        $this->assertFileExists($keep);

        $audit = $this->getTableLocator()->get('AuditLogs');
        $log = $audit->find()->where(['event' => 'cleanup.cache_cleared'])->first();
        $this->assertNotNull($log);
    }

    public function testCacheActionRecursesIntoNestedSubdirs(): void
    {
        // Regression: stale `tmp/cache/rate_limits/*` buckets owned by an old
        // uid produced surprise 429s on /login because the original
        // non-recursive sweep only looked at known engine subdirs. Recursion
        // closes that hole — any nested file (rate_limits/, custom engine
        // dirs, ...) gets cleared.
        $this->loginAs('admin');

        $nestedDir = TMP . 'cache' . DIRECTORY_SEPARATOR . 'rate_limits';
        [$nestedVictim] = $this->seedFile($nestedDir);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/cleanup/cache');
        $this->assertRedirect('/admin/cleanup');

        $this->assertFileDoesNotExist($nestedVictim);
    }

    public function testLogsActionDeletesOnlyLogFiles(): void
    {
        $this->loginAs('admin');

        [$logFile] = $this->seedFile(LOGS, '.log');
        // A non-`.log` file in LOGS shouldn't be touched (the action filters
        // by extension to keep collateral damage away from anything else
        // someone might have parked there).
        [$bystander] = $this->seedFile(LOGS, '.txt');

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/cleanup/logs');
        $this->assertRedirect('/admin/cleanup');

        $this->assertFileDoesNotExist($logFile);
        $this->assertFileExists($bystander);
        @unlink($bystander);

        $audit = $this->getTableLocator()->get('AuditLogs');
        $log = $audit->find()->where(['event' => 'cleanup.logs_cleared'])->first();
        $this->assertNotNull($log);
    }

    public function testSessionsActionDeletesFilesAndSignsOut(): void
    {
        $this->loginAs('admin');

        [$sess] = $this->seedFile(TMP . 'sessions');

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/cleanup/sessions');
        // Forced logout → redirected to /login with a marker query string.
        $this->assertRedirectContains('/login');

        $this->assertFileDoesNotExist($sess);

        $audit = $this->getTableLocator()->get('AuditLogs');
        $log = $audit->find()->where(['event' => 'cleanup.sessions_cleared'])->first();
        $this->assertNotNull($log);
    }

    public function testGetMethodsRejectedOnFilesystemActions(): void
    {
        $this->loginAs('admin');

        // Should be 405 since each route is restricted to POST.
        $this->get('/admin/cleanup/cache');
        $this->assertResponseError();

        $this->get('/admin/cleanup/logs');
        $this->assertResponseError();

        $this->get('/admin/cleanup/sessions');
        $this->assertResponseError();
    }

    public function testExpireCardsIsNoOpWhenRetentionDisabled(): void
    {
        $this->loginAs('admin');
        // app_settings empty → retention is 0 → no-op flash + redirect.
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/cleanup/expire-cards');
        $this->assertRedirect('/admin/cleanup');

        // Audit row should NOT exist — no work was done.
        $audit = $this->getTableLocator()->get('AuditLogs');
        $log = $audit->find()->where(['event' => 'cleanup.cards_expired'])->first();
        $this->assertNull($log, 'no-op should not emit an audit row');
    }

    public function testExpireCardsSoftDeletesOldUserCards(): void
    {
        $admin = $this->loginAs('admin');
        $tplId = $this->seedTemplate();
        $visitId = $this->seedGuestVisit(); // unused; just satisfies the fixture
        $upload = $this->seedUpload(userId: $admin);

        // Seed 3 user-owned cards: 2 old (60 days), 1 fresh (10 days).
        $cards = $this->getTableLocator()->get('Cards');
        $oldIds = [];
        foreach ([0, 1] as $i) {
            $row = $cards->saveOrFail($cards->newEntity([
                'user_id' => $admin, 'template_id' => $tplId, 'upload_id' => $upload->id,
                'qso_data_json' => '{}',
                'png_path' => "files/cards/old{$i}.webp", 'pdf_path' => null,
            ]));
            $cards->updateAll(['created_at' => DateTime::now()->subDays(60)], ['id' => $row->id]);
            $oldIds[] = $row->id;
        }
        $fresh = $cards->saveOrFail($cards->newEntity([
            'user_id' => $admin, 'template_id' => $tplId, 'upload_id' => $upload->id,
            'qso_data_json' => '{}', 'png_path' => 'files/cards/fresh.webp', 'pdf_path' => null,
        ]));
        $cards->updateAll(['created_at' => DateTime::now()->subDays(10)], ['id' => $fresh->id]);

        // Enable retention at 30 days.
        (new \App\Service\AppSettings())->set('card_retention_days', 30);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/cleanup/expire-cards');
        $this->assertRedirect('/admin/cleanup');

        foreach ($oldIds as $id) {
            $row = $cards->find()->where(['id' => $id])->first();
            $this->assertNotNull($row->deleted_at, "card #$id should be soft-deleted");
        }
        $freshRow = $cards->find()->where(['id' => $fresh->id])->first();
        $this->assertNull($freshRow->deleted_at, 'fresh card stays alive');

        $audit = $this->getTableLocator()->get('AuditLogs');
        $log = $audit->find()->where(['event' => 'cleanup.cards_expired'])->first();
        $this->assertNotNull($log);
    }
}
