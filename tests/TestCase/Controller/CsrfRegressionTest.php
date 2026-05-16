<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class CsrfRegressionTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users'];

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $this->post('/register', ['email' => 'x@y.com']);
        $this->assertResponseError(); // 403 expected from CSRF middleware
    }
}
