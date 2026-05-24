<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Log\Log;
use Throwable;

/**
 * Application operational logger.
 *
 * A thin, safe wrapper over CakePHP's Log facade for recording a
 * human-readable trail of what the application did at meaningful
 * boundaries — auth events, session lifecycle, create/update/delete
 * mutations, external API calls, imports/exports, and handled errors.
 *
 * Every message is written to the dedicated `operations` scope (see the
 * 'operations' channel in config/app.php → logs/operations.log).
 *
 * Crucially, all context passed here is run through {@see self::redact()}
 * before it touches disk: any key whose name looks secret (password,
 * token, hash, secret, csrf, salt, api key, authorization) is replaced
 * with `[redacted]`, and email addresses are masked. Callers can pass
 * raw arrays without worrying about leaking credentials or PII.
 *
 * Usage:
 *   OperationLog::event('net.session.started', ['id' => $id, 'user_id' => $uid]);
 *   OperationLog::warning('callsign.lookup.miss', ['callsign' => $call]);
 *   OperationLog::failure('email.send', $exception, ['to_user_id' => $uid]);
 */
final class OperationLog
{
    /**
     * Substrings that mark a context key as sensitive. Matching is
     * case-insensitive and by substring, so `password_hash`,
     * `logger_token`, `reset_token`, `csrfToken`, `api_key`, etc. are all
     * caught. Over-matching is intentional — redacting a benign field is
     * harmless; leaking a secret is not.
     *
     * @var list<string>
     */
    private const SECRET_SUBSTRINGS = [
        'password', 'token', 'secret', 'hash',
        'csrf', 'salt', 'api_key', 'apikey', 'authorization',
    ];

    /**
     * Record a normal operational event at info level.
     *
     * @param string $event Dotted event name, e.g. 'qso.created'.
     * @param array<string, mixed> $context Structured detail (auto-redacted).
     * @return void
     */
    public static function event(string $event, array $context = []): void
    {
        Log::write('info', self::format($event, $context), ['scope' => ['operations']]);
    }

    /**
     * Record a notable-but-non-fatal condition at warning level
     * (e.g. a rejected upload, a duplicate, an external miss).
     *
     * @param string $event Dotted event name.
     * @param array<string, mixed> $context Structured detail (auto-redacted).
     * @return void
     */
    public static function warning(string $event, array $context = []): void
    {
        Log::write('warning', self::format($event, $context), ['scope' => ['operations']]);
    }

    /**
     * Record a handled failure at error level, capturing the exception
     * type and message alongside the caller's context.
     *
     * @param string $event Dotted event name, e.g. 'email.send'.
     * @param \Throwable $e The caught exception.
     * @param array<string, mixed> $context Structured detail (auto-redacted).
     * @return void
     */
    public static function failure(string $event, Throwable $e, array $context = []): void
    {
        $context['exception'] = $e::class;
        $context['error'] = $e->getMessage();
        Log::write('error', self::format($event, $context), ['scope' => ['operations']]);
    }

    /**
     * Build the log line: the event name followed by its redacted,
     * JSON-encoded context (omitted entirely when there is no context).
     *
     * @param string $event Dotted event name.
     * @param array<string, mixed> $context Raw context.
     * @return string
     */
    private static function format(string $event, array $context): string
    {
        if ($context === []) {
            return $event;
        }
        $safe = self::redact($context);

        return $event . ' ' . (string)json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Recursively redact secrets and mask emails in a context array.
     *
     * @param array<string, mixed> $context Raw context.
     * @return array<string, mixed> Redacted copy safe to write to disk.
     */
    private static function redact(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $lower = strtolower((string)$key);
            if (self::isSecretKey($lower)) {
                $out[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $out[$key] = self::redact($value);
            } elseif (is_string($value) && str_contains($lower, 'email')) {
                $out[$key] = self::maskEmail($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Whether a (lower-cased) key name looks sensitive.
     *
     * @param string $lowerKey Lower-cased context key.
     * @return bool
     */
    private static function isSecretKey(string $lowerKey): bool
    {
        foreach (self::SECRET_SUBSTRINGS as $needle) {
            if (str_contains($lowerKey, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask the local part of an email so the domain remains useful for
     * debugging without logging the full address. `robbi@example.com`
     * becomes `r***@example.com`; non-email strings pass through.
     *
     * @param string $value Possibly an email address.
     * @return string
     */
    private static function maskEmail(string $value): string
    {
        $at = strpos($value, '@');
        if ($at === false || $at === 0) {
            return $value;
        }

        return $value[0] . '***' . substr($value, $at);
    }
}
