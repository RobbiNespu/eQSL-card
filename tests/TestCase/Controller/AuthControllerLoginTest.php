<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class AuthControllerLoginTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users'];

    protected function seedUser(string $email, string $password): void
    {
        $users = $this->getTableLocator()->get('Users');
        $users->saveOrFail($users->newEntity([
            'name' => 'X', 'callsign' => 'AA', 'email' => $email, 'role' => 'user',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash($password),
        ], ['accessibleFields' => ['*' => true]]));
    }

    public function testLoginValidCredentials(): void
    {
        $this->seedUser('a@x.com', 'pass1234');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/login', ['email' => 'a@x.com', 'password' => 'pass1234']);
        $this->assertRedirect('/');
    }

    public function testLoginInvalidCredentialsStays(): void
    {
        $this->seedUser('a@x.com', 'pass1234');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/login', ['email' => 'a@x.com', 'password' => 'wrong']);
        $this->assertResponseOk();
        $this->assertResponseContains('Invalid');
    }

    public function testLogoutClearsSession(): void
    {
        $this->seedUser('a@x.com', 'pass1234');
        $this->session(['Auth' => ['email' => 'a@x.com']]);
        $this->enableCsrfToken();
        $this->post('/logout');
        $this->assertRedirect('/login');
    }
}
