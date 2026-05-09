<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\PasswordResetService;
use Cake\TestSuite\TestCase;

/**
 * @covers \App\Service\PasswordResetService
 */
final class PasswordResetServiceTest extends TestCase
{
    protected array $fixtures = ['app.Users', 'app.PasswordResets'];

    public function testIssueAndConsumeToken(): void
    {
        $svc = new PasswordResetService();
        $token = $svc->issue('a@x.com');
        $this->assertSame(43, strlen($token));
        $email = $svc->consume($token);
        $this->assertSame('a@x.com', $email);
        $this->expectException(\RuntimeException::class);
        $svc->consume($token); // already used
    }

    public function testExpiredTokenRejected(): void
    {
        $svc = new PasswordResetService(ttlSeconds: -1);
        $token = $svc->issue('a@x.com');
        $this->expectException(\RuntimeException::class);
        $svc->consume($token);
    }
}
