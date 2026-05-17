<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;

/**
 * M4-T15 + T16: profile page + avatar upload integration coverage.
 *
 * Test surfaces:
 * 1. GET renders the page for a logged-in user.
 * 2. POST patches the allow-listed identity fields (name, callsign, qth,
 *    grid_square, bio).
 * 3. Privilege-escalation guard: a POST that includes `role=admin` MUST NOT
 *    be persisted — this is the security gate the controller's allow-list
 *    filter exists for.
 * 4. Avatar upload happy path: POST writes `files/avatars/{id}.jpg` and the
 *    `users.avatar_path` column is updated.
 * 5. Auth gate: anonymous GET to /profile redirects to /login.
 */
final class ProfileControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users'];

    private function loginAs(): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'X',
            'email' => 'a@x.com',
            'role' => 'user',
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id]]);

        return $u->id;
    }

    public function testGetProfile(): void
    {
        $this->loginAs();
        $this->get('/profile');
        $this->assertResponseOk();
        $this->assertResponseContains('Profile');
    }

    public function testPostUpdatesAllowedFields(): void
    {
        $userId = $this->loginAs();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/profile', [
            'name' => 'Updated Name',
            'callsign' => 'BB2BB',
            'qth' => 'Newington, CT',
            'grid_square' => 'FN31pr',
            'bio' => 'Hello world',
        ]);
        $this->assertRedirect('/profile');
        $row = $this->getTableLocator()->get('Users')->get($userId);
        $this->assertSame('Updated Name', $row->name);
        $this->assertSame('BB2BB', $row->callsign);
        $this->assertSame('FN31pr', $row->grid_square);
    }

    /**
     * M5 T27 — block_dupes_in_activation preference round-trips through
     * the profile form. Default is false; user can opt in via the
     * checkbox; the hidden 0-input ensures POST always sends a value
     * even when the box is unchecked (otherwise unchecked → key
     * absent from POST → false stays).
     */
    public function testBlockDupesInActivationPrefSavesAndClears(): void
    {
        $userId = $this->loginAs();
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        // Opt in.
        $this->post('/profile', [
            'name' => 'OP', 'callsign' => 'AA1AA',
            'block_dupes_in_activation' => '1',
        ]);
        $this->assertRedirect('/profile');
        $row = $this->getTableLocator()->get('Users')->get($userId);
        $this->assertTrue((bool)$row->block_dupes_in_activation);

        // Opt back out — hidden 0-input in the form means even unchecked
        // posts send block_dupes_in_activation=0 so the column flips.
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/profile', [
            'name' => 'OP', 'callsign' => 'AA1AA',
            'block_dupes_in_activation' => '0',
        ]);
        $row = $this->getTableLocator()->get('Users')->get($userId);
        $this->assertFalse((bool)$row->block_dupes_in_activation);
    }

    /**
     * M5 T29 — voice_input_callsign preference round-trips through the
     * profile form using the same hidden-0 + checkbox pattern as T27.
     */
    public function testVoiceInputCallsignPrefSavesAndClears(): void
    {
        $userId = $this->loginAs();
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->post('/profile', [
            'name' => 'OP', 'callsign' => 'AA1AA',
            'voice_input_callsign' => '1',
        ]);
        $this->assertRedirect('/profile');
        $row = $this->getTableLocator()->get('Users')->get($userId);
        $this->assertTrue((bool)$row->voice_input_callsign);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/profile', [
            'name' => 'OP', 'callsign' => 'AA1AA',
            'voice_input_callsign' => '0',
        ]);
        $row = $this->getTableLocator()->get('Users')->get($userId);
        $this->assertFalse((bool)$row->voice_input_callsign);
    }

    public function testCannotEscalateRole(): void
    {
        $userId = $this->loginAs();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/profile', ['role' => 'admin', 'name' => 'Hacker']);
        $row = $this->getTableLocator()->get('Users')->get($userId);
        $this->assertSame('user', $row->role);
    }

    public function testUploadAvatar(): void
    {
        $userId = $this->loginAs();

        $img = imagecreatetruecolor(800, 800);
        $tmp = tempnam(sys_get_temp_dir(), 'av_');
        imagejpeg($img, $tmp);
        imagedestroy($img);
        $upload = new UploadedFile($tmp, filesize($tmp), UPLOAD_ERR_OK, 'av.jpg', 'image/jpeg');

        $this->configRequest(['files' => ['avatar' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/profile/avatar');
        $this->assertRedirect('/profile');
        $row = $this->getTableLocator()->get('Users')->get($userId);
        $this->assertNotEmpty($row->avatar_path);
        $this->assertStringContainsString((string)$userId . '.jpg', (string)$row->avatar_path);
    }

    public function testRequiresAuth(): void
    {
        $this->get('/profile');
        $this->assertRedirectContains('/login');
    }
}
