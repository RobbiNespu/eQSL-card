<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class AuthControllerRegisterTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users'];

    public function testGetRegisterPage(): void
    {
        $this->get('/register');
        $this->assertResponseOk();
        $this->assertResponseContains('Create account');
    }

    public function testRegisterCreatesUserAndRedirects(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/register', [
            'name' => 'Robbi',
            'callsign' => 'AA1AA',
            'email' => 'r@x.com',
            'password' => 'CorrectHorseBatteryStaple1',
            'password_confirm' => 'CorrectHorseBatteryStaple1',
        ]);
        $this->assertRedirect('/login');
        $users = $this->getTableLocator()->get('Users');
        $this->assertSame(1, $users->find()->where(['email' => 'r@x.com'])->count());
    }

    public function testRegisterRejectsMismatchedPasswords(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/register', [
            'name' => 'A',
            'callsign' => 'A',
            'email' => 'a@x.com',
            'password' => 'one',
            'password_confirm' => 'two',
        ]);
        $this->assertResponseOk(); // re-renders form
        $this->assertResponseContains('do not match');
    }
}
