<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Renamed from UploadsControllerTest along with the underlying table
 * rename (uploads → card_backgrounds) in migration 20260516000007.
 * URLs use the new /card-backgrounds prefix; the legacy /uploads URLs
 * still 301-redirect via the back-compat routes in config/routes.php.
 */
final class CardBackgroundsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.CardBackgrounds', 'app.AuditLogs'];

    private static int $shaCounter = 0;

    private function loginAs(string $email, string $role = 'user'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'X', 'email' => $email, 'role' => $role, 'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id]]);
        return $u->id;
    }

    private function seedBackground(int $userId, array $extras = []): int
    {
        self::$shaCounter++;
        $bgs = $this->getTableLocator()->get('CardBackgrounds');
        $row = $bgs->saveOrFail($bgs->newEntity(array_merge([
            'user_id' => $userId,
            'original_filename' => 'bg.jpg',
            'storage_path' => 'files/uploads/x.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => 1500, 'height_px' => 1000, 'file_size_bytes' => 1234,
            'sha256_hash' => str_pad((string)self::$shaCounter, 64, 'a', STR_PAD_LEFT),
            'author_name' => 'Original Author',
            'license' => 'cc_by_4_0',
        ], $extras)));
        return $row->id;
    }

    public function testIndexShowsOnlyOwnBackgrounds(): void
    {
        $u1 = $this->loginAs('a@x.com');
        $users = $this->getTableLocator()->get('Users');
        $u2 = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));

        $this->seedBackground($u1, ['author_name' => 'MineMine']);
        $this->seedBackground($u2->id, ['author_name' => 'TheirsTheirs']);

        $this->get('/card-backgrounds');
        $this->assertResponseOk();
        $this->assertResponseContains('MineMine');
        $this->assertResponseNotContains('TheirsTheirs');
    }

    public function testEditOwnSavesAttribution(): void
    {
        $userId = $this->loginAs('a@x.com');
        $id = $this->seedBackground($userId);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/card-backgrounds/{$id}/edit", [
            'author_name' => 'Updated Name',
            'license' => 'pixabay_license',
        ]);
        $this->assertRedirectContains('/card-backgrounds');
        $row = $this->getTableLocator()->get('CardBackgrounds')->get($id);
        $this->assertSame('Updated Name', $row->author_name);
        $this->assertSame('pixabay_license', $row->license);
    }

    public function testCannotEditOtherUserAsRegularUser(): void
    {
        $a = $this->loginAs('a@x.com');
        $users = $this->getTableLocator()->get('Users');
        $b = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $bsBg = $this->seedBackground($b->id);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/card-backgrounds/{$bsBg}/edit", ['author_name' => 'hijack', 'license' => 'unknown']);
        $this->assertResponseCode(404);
        $row = $this->getTableLocator()->get('CardBackgrounds')->get($bsBg);
        $this->assertSame('Original Author', $row->author_name);
    }

    public function testAdminCanEditOtherUserBackground(): void
    {
        $admin = $this->loginAs('admin@x.com', 'admin');
        $users = $this->getTableLocator()->get('Users');
        $regular = $users->saveOrFail($users->newEntity([
            'name' => 'R', 'email' => 'r@x.com', 'role' => 'user', 'callsign' => 'RR1RR', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $rsBg = $this->seedBackground($regular->id);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/card-backgrounds/{$rsBg}/edit", ['author_name' => 'admin override', 'license' => 'pixabay_license']);
        $this->assertResponseCode(302);
        $row = $this->getTableLocator()->get('CardBackgrounds')->get($rsBg);
        $this->assertSame('admin override', $row->author_name);
    }

    public function testDeleteSetsDeletedAt(): void
    {
        $userId = $this->loginAs('a@x.com');
        $id = $this->seedBackground($userId);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post("/card-backgrounds/{$id}/delete");
        $this->assertRedirectContains('/card-backgrounds');
        $row = $this->getTableLocator()->get('CardBackgrounds')->get($id);
        $this->assertNotNull($row->deleted_at);
    }

    public function testReturnQueryParamHonoured(): void
    {
        $admin = $this->loginAs('admin@x.com', 'admin');
        $id = $this->seedBackground($admin);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/card-backgrounds/{$id}/edit?return=/admin/card-backgrounds", [
            'author_name' => 'X', 'license' => 'unknown',
        ]);
        $this->assertRedirect('/admin/card-backgrounds');
    }

    public function testAnonymousRedirectedToLogin(): void
    {
        $this->get('/card-backgrounds');
        $this->assertRedirectContains('/login');
    }

    public function testAdminAllBackgroundsListing(): void
    {
        $admin = $this->loginAs('admin@x.com', 'admin');
        $this->seedBackground($admin, ['author_name' => 'CalligraphicAuthor']);

        $this->get('/admin/card-backgrounds');
        $this->assertResponseOk();
        $this->assertResponseContains('CalligraphicAuthor');
    }

    public function testAdminAllBackgroundsForbiddenForRegular(): void
    {
        $this->loginAs('user@x.com', 'user');
        $this->get('/admin/card-backgrounds');
        $this->assertResponseCode(403);
    }

    public function testLegacyUploadsUrlRedirectsToCardBackgrounds(): void
    {
        $this->loginAs('a@x.com');
        $this->get('/uploads');
        $this->assertResponseCode(301);
        $this->assertRedirectContains('/card-backgrounds');
    }
}
