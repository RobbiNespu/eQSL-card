<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\EmailVerificationService;
use Cake\TestSuite\TestCase;

/**
 * @covers \App\Service\EmailVerificationService
 */
final class EmailVerificationServiceTest extends TestCase
{
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

    public function testIssueAndConsumeMarksUserVerified(): void
    {
        $this->seedUser('a@x.com');
        $svc = new EmailVerificationService();
        $token = $svc->issue('a@x.com');
        $this->assertSame(43, strlen($token));

        $email = $svc->consume($token);
        $this->assertSame('a@x.com', $email);

        $row = $this->getTableLocator()->get('Users')->find()
            ->where(['email' => 'a@x.com'])->firstOrFail();
        $this->assertNotNull($row->email_verified_at);
    }

    public function testTokenSingleUse(): void
    {
        $this->seedUser('b@x.com');
        $svc = new EmailVerificationService();
        $token = $svc->issue('b@x.com');
        $svc->consume($token);
        $this->expectException(\RuntimeException::class);
        $svc->consume($token);
    }

    public function testExpiredTokenRejected(): void
    {
        $this->seedUser('c@x.com');
        $svc = new EmailVerificationService(ttlSeconds: -1);
        $token = $svc->issue('c@x.com');
        $this->expectException(\RuntimeException::class);
        $svc->consume($token);
    }

    public function testKindDiscriminatorIsolatesPasswordResets(): void
    {
        // A password-reset token must NOT be consumable by the verifier,
        // and vice versa. This proves the `kind` filter actually scopes
        // the lookup against the shared table.
        $this->seedUser('d@x.com');
        $resetToken = (new \App\Service\PasswordResetService())->issue('d@x.com');
        $svc = new EmailVerificationService();
        $this->expectException(\RuntimeException::class);
        $svc->consume($resetToken);
    }
}
