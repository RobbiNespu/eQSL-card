<?php
declare(strict_types=1);

namespace App\Test\TestCase\Integration;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * M4-T3: Audit instrumentation integration coverage.
 *
 * One representative event per category — share/revoke/delete on the cards
 * surface plus admin template approve. Full event matrix is covered by per-
 * controller unit tests; this file's job is to prove the AuditLogger wiring
 * lands rows in `audit_logs` end-to-end through a real HTTP request.
 *
 * The fixtures are empty `app.*` tables (the schema lives in migrations),
 * so each test seeds exactly the rows it needs and asserts on the resulting
 * audit_logs row.
 */
final class AuditEventsTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'app.Users',
        'app.Templates',
        'app.CardBackgrounds',
        'app.Cards',
        'app.AuditLogs',
        'app.GuestVisits',
    ];

    /**
     * Per-test counter so `seedCard()` can mint unique sha256 hashes without
     * tripping the uploads.sha256_hash unique index across helpers.
     */
    private static int $shaCounter = 0;

    /**
     * Seed an authenticated identity and return its id. Email is a parameter
     * so a single test can spin up two distinct users (e.g. submitter + admin).
     */
    private function loginAs(string $role = 'user', string $email = 'a@x.com'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'X',
            'email' => $email,
            'role' => $role,
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id]]);

        return $u->id;
    }

    /**
     * Seed a system template + per-test upload + card row owned by `$userId`.
     * Returns the new card's id.
     */
    private function seedCard(int $userId): int
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

        $uploads = $this->getTableLocator()->get('CardBackgrounds');
        self::$shaCounter++;
        $upload = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $userId,
            'original_filename' => 'x.jpg',
            'storage_path' => 'p.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1,
            'height_px' => 1,
            'file_size_bytes' => 1,
            'sha256_hash' => str_pad((string)self::$shaCounter, 64, 'a', STR_PAD_LEFT),
        ]));

        $cards = $this->getTableLocator()->get('Cards');
        $card = $cards->saveOrFail($cards->newEntity([
            'user_id' => $userId,
            'template_id' => $tpl->id,
            'upload_id' => $upload->id,
            'qso_data_json' => '{}',
            'png_path' => 'p.png',
            'pdf_path' => 'p.pdf',
        ]));

        return $card->id;
    }

    public function testShareEventIsLogged(): void
    {
        $u = $this->loginAs();
        $cardId = $this->seedCard($u);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $cardId . '/share', ['password' => '']);

        // Scope by actor_user_id too: when the suite runs as a whole, other
        // share-touching tests (e.g. CardsControllerShareTest, M2JourneyTest)
        // also write to audit_logs and they don't include `app.AuditLogs` in
        // their fixtures, so the table is not truncated between classes. The
        // freshly-seeded Users fixture IS truncated, so `$u` is unique per
        // test and disambiguates this row from any prior leftovers.
        $logs = $this->getTableLocator()->get('AuditLogs');
        $logged = $logs->find()
            ->where(['event' => 'card.shared', 'target_id' => $cardId, 'actor_user_id' => $u])
            ->first();
        $this->assertNotNull($logged, 'Expected a card.shared row in audit_logs.');
        $this->assertSame('Cards', $logged->target_type);
    }

    public function testRevokeEventIsLogged(): void
    {
        $u = $this->loginAs();
        $cardId = $this->seedCard($u);

        // Share first so revoke has something to act on.
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $cardId . '/share', ['password' => '']);

        // Then revoke.
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $cardId . '/revoke');

        // Same disambiguation rationale as testShareEventIsLogged: scope by
        // the freshly-seeded user id.
        $logs = $this->getTableLocator()->get('AuditLogs');
        $row = $logs->find()
            ->where(['event' => 'card.revoked', 'target_id' => $cardId, 'actor_user_id' => $u])
            ->first();
        $this->assertNotNull($row, 'Expected a card.revoked row in audit_logs.');
    }

    public function testDeleteEventIsLogged(): void
    {
        $u = $this->loginAs();
        $cardId = $this->seedCard($u);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $cardId . '/delete');

        // Same disambiguation rationale as testShareEventIsLogged.
        $logs = $this->getTableLocator()->get('AuditLogs');
        $row = $logs->find()
            ->where(['event' => 'card.deleted', 'target_id' => $cardId, 'actor_user_id' => $u])
            ->first();
        $this->assertNotNull($row, 'Expected a card.deleted row in audit_logs.');
    }

    public function testTemplateApproveEventIsLogged(): void
    {
        // Submitter creates a pending public template.
        $owner = $this->loginAs('user');
        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'user_id' => $owner,
            'name' => 'pending',
            'canvas_width' => 1500,
            'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_public' => true,
            'is_approved' => false,
        ], ['accessibleFields' => ['is_public' => true, 'is_approved' => true]]));

        // Admin logs in (overwrites the user session) and approves.
        $adminId = $this->loginAs('admin', 'admin@x.com');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/templates/' . $tpl->id . '/approve');

        // Disambiguate by the admin's actor_user_id — moderation-related
        // tests in other classes may have left rows in audit_logs.
        $logs = $this->getTableLocator()->get('AuditLogs');
        $row = $logs->find()
            ->where(['event' => 'template.approved', 'target_id' => $tpl->id, 'actor_user_id' => $adminId])
            ->first();
        $this->assertNotNull($row, 'Expected a template.approved row in audit_logs.');
        $this->assertSame('Templates', $row->target_type);
    }
}
