<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class AuthControllerVerifyTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.PasswordResets'];

    private function seedUser(string $email): void
    {
        $users = $this->getTableLocator()->get('Users');
        $users->saveOrFail($users->newEntity([
            'name' => 'X',
            'email' => $email,
            'role' => 'user',
            'callsign' => 'AA1AA',
            'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
    }

    public function testVerifyEndpointMarksUserVerified(): void
    {
        $this->seedUser('v@x.com');
        $token = (new \App\Service\EmailVerificationService())->issue('v@x.com');

        $this->get('/email/verify/' . $token);
        $this->assertRedirect('/login');

        $users = $this->getTableLocator()->get('Users');
        $row = $users->find()->where(['email' => 'v@x.com'])->firstOrFail();
        $this->assertNotNull($row->email_verified_at);
    }

    public function testRegisterIssuesVerificationToken(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/register', [
            'name' => 'New',
            'callsign' => 'AA1AA',
            'email' => 'new@x.com',
            'password' => 'SecurePass123',
            'password_confirm' => 'SecurePass123',
        ]);
        $this->assertRedirect('/login');

        $resets = $this->getTableLocator()->get('PasswordResets');
        $count = $resets->find()
            ->where(['email' => 'new@x.com', 'kind' => 'email_verify'])
            ->count();
        $this->assertSame(1, $count);
    }

    public function testInvalidTokenRedirectsWithError(): void
    {
        $this->get('/email/verify/' . str_repeat('A', 43));
        $this->assertRedirect('/login');
    }

    public function testTokenPatternEnforced(): void
    {
        // Anything that is not 43 chars of [A-Za-z0-9_-] must not match the
        // route at all (no controller dispatch). Routes guard this so the
        // service layer never sees garbage.
        $this->get('/email/verify/short');
        $this->assertResponseError();
    }
}
