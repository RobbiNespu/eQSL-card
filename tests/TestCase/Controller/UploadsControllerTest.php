<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class UploadsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.Uploads', 'app.AuditLogs'];

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

    private function seedUpload(int $userId, array $extras = []): int
    {
        self::$shaCounter++;
        $uploads = $this->getTableLocator()->get('Uploads');
        $row = $uploads->saveOrFail($uploads->newEntity(array_merge([
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

    public function testIndexShowsOnlyOwnUploads(): void
    {
        $u1 = $this->loginAs('a@x.com');
        $users = $this->getTableLocator()->get('Users');
        $u2 = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));

        $this->seedUpload($u1, ['author_name' => 'MineMine']);
        $this->seedUpload($u2->id, ['author_name' => 'TheirsTheirs']);

        $this->get('/uploads');
        $this->assertResponseOk();
        $this->assertResponseContains('MineMine');
        $this->assertResponseNotContains('TheirsTheirs');
    }

    public function testEditOwnSavesAttribution(): void
    {
        $userId = $this->loginAs('a@x.com');
        $id = $this->seedUpload($userId);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/uploads/{$id}/edit", [
            'author_name' => 'Updated Name',
            'license' => 'pixabay_license',
        ]);
        $this->assertRedirectContains('/uploads');
        $row = $this->getTableLocator()->get('Uploads')->get($id);
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
        $bsUpload = $this->seedUpload($b->id);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/uploads/{$bsUpload}/edit", ['author_name' => 'hijack', 'license' => 'unknown']);
        $this->assertResponseCode(404);
        $row = $this->getTableLocator()->get('Uploads')->get($bsUpload);
        $this->assertSame('Original Author', $row->author_name);
    }

    public function testAdminCanEditOtherUserUpload(): void
    {
        $admin = $this->loginAs('admin@x.com', 'admin');
        $users = $this->getTableLocator()->get('Users');
        $regular = $users->saveOrFail($users->newEntity([
            'name' => 'R', 'email' => 'r@x.com', 'role' => 'user', 'callsign' => 'RR1RR', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $rsUpload = $this->seedUpload($regular->id);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/uploads/{$rsUpload}/edit", ['author_name' => 'admin override', 'license' => 'pixabay_license']);
        $this->assertResponseCode(302);
        $row = $this->getTableLocator()->get('Uploads')->get($rsUpload);
        $this->assertSame('admin override', $row->author_name);
    }

    public function testDeleteSetsDeletedAt(): void
    {
        $userId = $this->loginAs('a@x.com');
        $id = $this->seedUpload($userId);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post("/uploads/{$id}/delete");
        $this->assertRedirectContains('/uploads');
        $row = $this->getTableLocator()->get('Uploads')->get($id);
        $this->assertNotNull($row->deleted_at);
    }

    public function testReturnQueryParamHonoured(): void
    {
        $admin = $this->loginAs('admin@x.com', 'admin');
        $id = $this->seedUpload($admin);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/uploads/{$id}/edit?return=/admin/uploads", [
            'author_name' => 'X', 'license' => 'unknown',
        ]);
        $this->assertRedirect('/admin/uploads');
    }

    public function testAnonymousRedirectedToLogin(): void
    {
        $this->get('/uploads');
        $this->assertRedirectContains('/login');
    }

    private function loginAsDefault(): int
    {
        return $this->loginAs('a@x.com');
    }

    public function testAdminAllUploadsListing(): void
    {
        $admin = $this->loginAs('admin@x.com', 'admin');
        $this->seedUpload($admin, ['author_name' => 'CalligraphicAuthor']);

        $this->get('/admin/uploads');
        $this->assertResponseOk();
        $this->assertResponseContains('CalligraphicAuthor');
    }

    public function testAdminAllUploadsForbiddenForRegular(): void
    {
        $this->loginAs('user@x.com', 'user');
        $this->get('/admin/uploads');
        $this->assertResponseCode(403);
    }
}
