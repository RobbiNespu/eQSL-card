<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * QsosController::index integration tests (M2-T2).
 *
 * Covers:
 *  - Anonymous request redirects to /login.
 *  - Index lists ONLY the logged-in user's QSOs.
 *  - Callsign substring search.
 *  - Band and mode filters.
 *  - Date-range filter (from/to).
 */
final class QsosControllerIndexTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Qsos', 'app.Templates', 'app.CardBackgrounds', 'app.Cards'];

    private function seedUserAndLogin(string $email = 'op@x.com'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $user = $users->saveOrFail($users->newEntity([
            'name' => 'OP',
            'email' => $email,
            'role' => 'user',
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pass1234'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $user->id, 'email' => $email]]);

        return $user->id;
    }

    private function seedQso(int $userId, array $overrides = []): int
    {
        $qsos = $this->getTableLocator()->get('Qsos');
        $entity = $qsos->newEntity(array_merge([
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
            'band' => '20m',
            'mode' => 'SSB',
            'rst_sent' => '59',
            'rst_received' => '59',
        ], $overrides));
        $entity->user_id = $userId;
        $qsos->saveOrFail($entity);

        return $entity->id;
    }

    public function testRedirectsToLoginWhenAnonymous(): void
    {
        $this->get('/qsos');
        $this->assertRedirectContains('/login');
    }

    public function testListsOnlyOwnQsos(): void
    {
        $u1 = $this->seedUserAndLogin('a@x.com');
        $u2id = (function () {
            $users = $this->getTableLocator()->get('Users');
            $u2 = $users->saveOrFail($users->newEntity([
                'name' => 'B',
                'email' => 'b@x.com',
                'role' => 'user',
                'callsign' => 'BB1BB',
                'password_hash' => 'h',
            ], ['accessibleFields' => ['*' => true]]));

            return $u2->id;
        })();

        $this->seedQso($u1, ['call_worked' => 'W1MINE']);
        $this->seedQso($u2id, ['call_worked' => 'W1OTHER', 'band' => '40m']);

        $this->get('/qsos');
        $this->assertResponseOk();
        $this->assertResponseContains('W1MINE');
        $this->assertResponseNotContains('W1OTHER');
    }

    public function testCallsignSearchFiltersResults(): void
    {
        $userId = $this->seedUserAndLogin();
        $this->seedQso($userId, ['call_worked' => 'W1AW']);
        $this->seedQso($userId, [
            'call_worked' => 'K2DST',
            'qso_datetime_utc' => '2026-05-08 14:32:00',
            'band' => '40m',
        ]);

        $this->get('/qsos?q=w1');
        $this->assertResponseOk();
        $this->assertResponseContains('W1AW');
        $this->assertResponseNotContains('K2DST');
    }

    public function testBandAndModeFilters(): void
    {
        $userId = $this->seedUserAndLogin();
        $this->seedQso($userId, ['call_worked' => 'W1AW', 'band' => '20m', 'mode' => 'SSB']);
        $this->seedQso($userId, [
            'call_worked' => 'K2DST',
            'qso_datetime_utc' => '2026-05-08 14:32:00',
            'band' => '40m',
            'mode' => 'CW',
        ]);

        $this->get('/qsos?band=40m');
        $this->assertResponseContains('K2DST');
        $this->assertResponseNotContains('W1AW');

        $this->get('/qsos?mode=SSB');
        $this->assertResponseContains('W1AW');
        $this->assertResponseNotContains('K2DST');
    }

    public function testDateRangeFilter(): void
    {
        $userId = $this->seedUserAndLogin();
        $this->seedQso($userId, [
            'call_worked' => 'EARLY',
            'qso_datetime_utc' => '2026-05-01 14:32:00',
            'band' => '10m',
        ]);
        $this->seedQso($userId, [
            'call_worked' => 'LATE',
            'qso_datetime_utc' => '2026-05-15 14:32:00',
        ]);

        $this->get('/qsos?from=2026-05-10&to=2026-05-20');
        $this->assertResponseContains('LATE');
        $this->assertResponseNotContains('EARLY');
    }

    public function testIndexShowsViewCardLinkWhenCardExists(): void
    {
        $userId = $this->seedUserAndLogin();
        $renderableQso = $this->seedQso($userId, ['call_worked' => 'NEEDREN']);
        $renderedQso = $this->seedQso($userId, ['call_worked' => 'HASCARD']);

        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));
        $uploads = $this->getTableLocator()->get('CardBackgrounds');
        $up = $uploads->saveOrFail($uploads->newEntity([
            'user_id' => $userId, 'original_filename' => 'b.jpg',
            'storage_path' => 'files/uploads/b.jpg', 'mime_type' => 'image/jpeg',
            'width_px' => 1, 'height_px' => 1, 'file_size_bytes' => 1,
            'sha256_hash' => str_repeat('z', 64),
        ]));
        $cards = $this->getTableLocator()->get('Cards');
        $card = $cards->saveOrFail($cards->newEntity([
            'user_id' => $userId, 'qso_id' => $renderedQso, 'template_id' => $tpl->id,
            'upload_id' => $up->id, 'qso_data_json' => '{}',
            'png_path' => 'files/cards/x.png', 'pdf_path' => 'files/cards/x.pdf',
        ]));

        $this->get('/qsos');
        $this->assertResponseOk();
        // Rendered QSO: link points at the card.
        $this->assertResponseContains('/cards/' . $card->id);
        $this->assertResponseContains('View card');
        // Un-rendered QSO: still gets the Render button.
        $this->assertResponseContains('/qsos/' . $renderableQso . '/render');
    }
}
