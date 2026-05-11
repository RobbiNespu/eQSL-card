<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Spillover surfaces for the net QSO feature: detail view shows the net
 * block, render picker pre-selects the Net check-in template, bulk render
 * guard reports the skipped count.
 */
final class QsosControllerNetSpilloverTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Qsos', 'app.Templates', 'app.Uploads', 'app.Cards'];

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

    private function seedNetQso(int $userId): int
    {
        $qsos = $this->getTableLocator()->get('Qsos');
        $entity = $qsos->newEntity([
            'call_worked' => 'W1PART',
            'qso_datetime_utc' => '2026-05-09 14:00:00',
            'band' => '40m', 'mode' => 'FM',
            'qso_type' => 'net',
            'ncs_callsign' => '9W2NSP', 'net_title' => 'PARTY Net', 'net_organisation' => 'MARTS',
        ]);
        $entity->user_id = $userId;
        $qsos->saveOrFail($entity);

        return $entity->id;
    }

    public function testDetailViewShowsNetBlock(): void
    {
        $u = $this->loginAs();
        $qsoId = $this->seedNetQso($u);

        $this->get('/qsos/' . $qsoId);
        $this->assertResponseOk();
        $this->assertResponseContains('Net check-in by');
        $this->assertResponseContains('NCS');
        $this->assertResponseContains('9W2NSP');
        $this->assertResponseContains('PARTY Net');
        $this->assertResponseContains('MARTS');
        $this->assertResponseContains('NET');
    }

    public function testRenderPickerPreselectsNetTemplateForNetQso(): void
    {
        $u = $this->loginAs();
        $qsoId = $this->seedNetQso($u);
        // Seed both Classic + Net templates so the picker has the real choice.
        $tpls = $this->getTableLocator()->get('Templates');
        $classic = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'Classic — bottom panel', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => '{"fields":[]}',
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));
        $netTpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'Net check-in', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => '{"fields":[]}',
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $this->get('/qsos/' . $qsoId . '/render');
        $this->assertResponseOk();
        // The net template's radio should be checked; the classic's should not.
        // We assert on the rendered HTML rather than the form helper because
        // we hand-wrote the radio in the render template.
        $body = (string)$this->_response->getBody();
        $this->assertMatchesRegularExpression(
            '/name="template_id"\s+value="' . $netTpl->id . '"[^>]*\bchecked\b/',
            $body,
            'Net template radio should be checked'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="template_id"\s+value="' . $classic->id . '"[^>]*\bchecked\b/',
            $body,
            'Classic template radio should NOT be checked'
        );
    }

    public function testBulkRenderSkipsQsosWithExistingCards(): void
    {
        $u = $this->loginAs();
        $qsos = $this->getTableLocator()->get('Qsos');

        // Seed 3 QSOs; pre-render a card on the second one so it should be
        // skipped by the bulk run.
        $ids = [];
        foreach (['W1ALPHA', 'W1BETA', 'W1GAMMA'] as $call) {
            $e = $qsos->newEntity([
                'call_worked' => $call, 'qso_datetime_utc' => '2026-05-09 10:00:00',
                'band' => '20m', 'mode' => 'SSB',
            ]);
            $e->user_id = $u;
            $qsos->saveOrFail($e);
            $ids[] = $e->id;
        }

        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => '{"fields":[]}',
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $uploads = $this->getTableLocator()->get('Uploads');
        $up = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $u, 'original_filename' => 'bg.webp',
            'storage_path' => 'files/uploads/bulk-guard-test.webp', 'mime_type' => 'image/webp',
            'width_px' => 600, 'height_px' => 400, 'file_size_bytes' => 100,
            'sha256_hash' => str_repeat('z', 64),
        ]));

        // Pre-existing card on $ids[1]
        $cards = $this->getTableLocator()->get('Cards');
        $cards->saveOrFail($cards->newEntity([
            'user_id' => $u, 'qso_id' => $ids[1], 'template_id' => $tpl->id, 'upload_id' => $up->id,
            'qso_data_json' => '{}', 'png_path' => 'files/cards/preexisting.webp', 'pdf_path' => null,
        ]));

        // Fire bulk render. Json view configured; we only care about the
        // skipped count + that ids[1] wasn't re-rendered.
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/bulk-render', [
            'qso_ids' => $ids,
            'template_id' => $tpl->id,
            'upload_id' => $up->id,
        ]);

        $body = (string)$this->_response->getBody();
        $payload = json_decode($body, true);
        $this->assertIsArray($payload, 'bulk-render should respond with JSON');
        $this->assertSame(1, $payload['skipped'] ?? null, 'one QSO already had a card');
        // ids[0] + ids[2] should be rendered (total=2), ids[1] excluded.
        $this->assertSame(2, $payload['total'] ?? null);
    }
}
