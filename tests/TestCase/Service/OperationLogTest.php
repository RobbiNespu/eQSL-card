<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\OperationLog;
use Cake\TestSuite\TestCase;
use ReflectionMethod;

/**
 * Guards the OperationLog redaction/masking logic — the safety net that
 * keeps secrets and PII out of logs/operations.log.
 */
final class OperationLogTest extends TestCase
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $m = new ReflectionMethod(OperationLog::class, 'redact');
        $m->setAccessible(true);

        return $m->invoke(null, $context);
    }

    private function format(string $event, array $context): string
    {
        $m = new ReflectionMethod(OperationLog::class, 'format');
        $m->setAccessible(true);

        return $m->invoke(null, $event, $context);
    }

    public function testSecretKeysAreRedacted(): void
    {
        $out = $this->redact([
            'password' => 'hunter2',
            'password_hash' => '$argon2id$...',
            'logger_token' => 'abc',
            'csrfToken' => 'xyz',
            'api_key' => 'k',
            'reset_secret' => 's',
        ]);
        foreach ($out as $v) {
            $this->assertSame('[redacted]', $v);
        }
    }

    public function testNonSecretValuesPassThrough(): void
    {
        $out = $this->redact(['callsign' => '9W2NSP', 'user_id' => 7, 'band' => '40m']);
        $this->assertSame('9W2NSP', $out['callsign']);
        $this->assertSame(7, $out['user_id']);
        $this->assertSame('40m', $out['band']);
    }

    public function testEmailIsMasked(): void
    {
        $out = $this->redact(['email' => 'robbi@example.com']);
        $this->assertSame('r***@example.com', $out['email']);
    }

    public function testNestedSecretsAreRedacted(): void
    {
        $out = $this->redact(['meta' => ['share_password' => 'p', 'note' => 'ok']]);
        $this->assertSame('[redacted]', $out['meta']['share_password']);
        $this->assertSame('ok', $out['meta']['note']);
    }

    public function testFormatWithoutContextIsEventOnly(): void
    {
        $this->assertSame('qso.created', $this->format('qso.created', []));
    }

    public function testFormatWithContextAppendsRedactedJson(): void
    {
        $line = $this->format('auth.login', ['email' => 'a@b.com', 'password' => 'x']);
        $this->assertStringStartsWith('auth.login ', $line);
        $this->assertStringContainsString('a***@b.com', $line);
        $this->assertStringContainsString('[redacted]', $line);
        $this->assertStringNotContainsString('"x"', $line);
    }
}
