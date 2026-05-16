<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;

/**
 * M3-T5: Designer preview-background uploader.
 *
 * Covers the three contract surfaces of `/templates/upload-background`:
 * 1. Happy path: image goes through ImageOptimizer → uploads row owned by
 *    the current user → JSON response with the storage URL.
 * 2. Non-image rejection: getimagesize() guard returns 400 (so the GD
 *    decode in ImageOptimizer never runs on garbage input).
 * 3. Auth gate: anonymous POST redirects to /login like every other
 *    authenticated surface in the app.
 */
final class TemplatesUploadBackgroundTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Templates', 'app.Uploads'];

    private function loginAs(): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'X', 'email' => 'a@x.com', 'role' => 'user', 'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id]]);

        return $u->id;
    }

    public function testUploadProducesUploadRow(): void
    {
        $userId = $this->loginAs();

        $img = imagecreatetruecolor(800, 600);
        $tmp = tempnam(sys_get_temp_dir(), 'bg_');
        imagejpeg($img, $tmp);
        imagedestroy($img);
        $upload = new UploadedFile($tmp, filesize($tmp), UPLOAD_ERR_OK, 'bg.jpg', 'image/jpeg');

        $this->configRequest(['files' => ['background_upload' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/templates/upload-background', []);
        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('url', $body);
        $this->assertStringContainsString('files/uploads/', $body['url']);
        $this->assertGreaterThan(0, $body['upload_id']);

        $row = $this->getTableLocator()->get('Uploads')->get($body['upload_id']);
        $this->assertSame($userId, $row->user_id);
    }

    public function testRejectsNonImage(): void
    {
        $this->loginAs();
        $tmp = tempnam(sys_get_temp_dir(), 'nope_');
        file_put_contents($tmp, 'not an image');
        $upload = new UploadedFile($tmp, filesize($tmp), UPLOAD_ERR_OK, 'bg.txt', 'text/plain');

        $this->configRequest(['files' => ['background_upload' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/templates/upload-background', []);
        $this->assertResponseCode(400);
    }

    public function testRequiresAuth(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/templates/upload-background', []);
        $this->assertRedirectContains('/login');
    }
}
