<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * CardsController::delete integration tests (M2-T9).
 *
 * Covers:
 *  - POST /cards/{id}/delete sets `deleted_at` and redirects back to the index.
 *  - Soft-deleted rows are hidden from the user's library listing.
 *  - Cross-user attempts 404 and leave the foreign row untouched.
 */
final class CardsControllerDeleteTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Templates', 'app.CardBackgrounds', 'app.Cards'];

    /**
     * `uploads.sha256_hash` carries a UNIQUE index, so each seeded upload row
     * needs a distinct hash. A static counter keeps that simple even when a
     * single test seeds multiple cards (each card = one upload).
     */
    private static int $shaCounter = 0;

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

    private function seedCardForUser(int $userId): int
    {
        $t = $this->getTableLocator()->get('Templates');
        $tpl = $t->saveOrFail($t->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $u = $this->getTableLocator()->get('CardBackgrounds');
        self::$shaCounter++;
        $upload = $u->saveOrFail($u->newEntity([
            'user_id' => $userId,
            'original_filename' => 'bg.jpg',
            'storage_path' => 'files/uploads/x' . self::$shaCounter . '.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1500, 'height_px' => 1000,
            'file_size_bytes' => 12345,
            'sha256_hash' => str_pad((string)self::$shaCounter, 64, 'a', STR_PAD_LEFT),
        ]));

        $c = $this->getTableLocator()->get('Cards');
        $card = $c->saveOrFail($c->newEntity([
            'user_id' => $userId,
            'template_id' => $tpl->id,
            'upload_id' => $upload->id,
            'qso_data_json' => json_encode(['callsign' => 'W1AW']),
            'png_path' => 'p.png',
            'pdf_path' => 'p.pdf',
        ]));

        return $card->id;
    }

    public function testSoftDeleteSetsDeletedAtAndRedirects(): void
    {
        $u = $this->seedUserAndLogin();
        $cardId = $this->seedCardForUser($u);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $cardId . '/delete');
        $this->assertRedirect('/cards');

        $row = $this->getTableLocator()->get('Cards')->get($cardId);
        $this->assertNotNull($row->deleted_at);
    }

    public function testDeletedCardHiddenFromIndex(): void
    {
        $u = $this->seedUserAndLogin();
        $aliveId = $this->seedCardForUser($u);
        $deletedId = $this->seedCardForUser($u);

        $cards = $this->getTableLocator()->get('Cards');
        $deleted = $cards->get($deletedId);
        $deleted->deleted_at = \Cake\I18n\DateTime::now();
        $cards->saveOrFail($deleted);

        $this->get('/cards');
        $this->assertResponseOk();
        // The alive card's id should appear in the rendered page; the deleted
        // one shouldn't. The index template links to `/cards/{id}` per row.
        $this->assertResponseContains('/cards/' . $aliveId);
        $this->assertResponseNotContains('/cards/' . $deletedId);
    }

    public function testCannotDeleteOtherUsersCard(): void
    {
        $a = $this->seedUserAndLogin('a@x.com');

        $users = $this->getTableLocator()->get('Users');
        $b = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $bsCard = $this->seedCardForUser($b->id);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $bsCard . '/delete');
        $this->assertResponseCode(404);

        $row = $this->getTableLocator()->get('Cards')->get($bsCard);
        $this->assertNull($row->deleted_at, 'foreign card must not have been soft-deleted');
    }
}
