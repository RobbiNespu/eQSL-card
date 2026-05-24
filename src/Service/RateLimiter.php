<?php
declare(strict_types=1);

namespace App\Service;

/**
 * File-backed sliding-window rate limiter.
 *
 * Each action+identifier pair is stored as a comma-separated list of Unix
 * timestamps in a single file under `$storageDir`. Hits older than the window
 * are pruned on every check. Writes are atomic (temp-file + rename) so
 * concurrent processes and cross-uid test/production runs don't corrupt the
 * bucket file.
 */
final class RateLimiter
{
    /** @var \Closure():int */
    private \Closure $clock;

    /**
     * @param string        $storageDir Absolute directory path for bucket files (created if missing).
     * @param callable|null $clock      Returns the current Unix timestamp. Defaults to `time()`.
     *                                  Inject a fixed value in tests to control the window.
     */
    public function __construct(
        private string $storageDir,
        ?callable $clock = null,
    ) {
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0o775, true);
        }
        $this->clock = $clock ? \Closure::fromCallable($clock) : static fn(): int => time();
    }

    /**
     * Record a hit and return whether it is within the allowed limit.
     *
     * Prunes timestamps outside the sliding window before checking. When
     * the hit is allowed the current timestamp is appended and the bucket
     * is persisted atomically. When the limit is already reached the bucket
     * is persisted with stale entries removed but without adding a new stamp.
     *
     * @param string $action          Dotted action name (e.g. `auth.login`).
     * @param string $identifier      Per-actor key (IP address, user id, etc.).
     * @param int    $limit           Maximum allowed hits within the window.
     * @param int    $windowSeconds   Sliding window width in seconds.
     * @return bool True when this hit is within the limit; false when throttled.
     */
    public function hit(string $action, string $identifier, int $limit, int $windowSeconds): bool
    {
        $file = $this->storageDir . '/' . hash('sha256', $action . ':' . $identifier);
        $now = ($this->clock)();
        $cutoff = $now - $windowSeconds;
        $stamps = is_file($file) ? array_map('intval', explode(',', (string)file_get_contents($file))) : [];
        $stamps = array_values(array_filter($stamps, static fn($t) => $t > $cutoff));
        $allowed = count($stamps) < $limit;
        if ($allowed) {
            $stamps[] = $now;
        }
        $this->atomicWrite($file, implode(',', $stamps));
        return $allowed;
    }

    /**
     * Atomically replace `$file` with `$contents` using `tmp + rename`. Rename
     * replaces the destination regardless of whether the existing file is
     * owned by the current process — important here because the test suite,
     * run as root via `docker compose exec`, leaves root-owned bucket files
     * behind, and the www-data php-fpm worker would otherwise be unable to
     * overwrite them. The previous `file_put_contents($file, ...)` would
     * silently fail on a permission error, leaving stale test-run stamps to
     * read and accumulate-toward-limit forever — producing a phantom 429 on
     * real logins until someone manually wiped the file. The temp file is
     * chmod'd to 0o666 BEFORE the rename so the next caller (regardless of
     * uid) can replace it the same way.
     */
    private function atomicWrite(string $file, string $contents): void
    {
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $contents, LOCK_EX) === false) {
            return;
        }
        @chmod($tmp, 0o666);
        @rename($tmp, $file);
    }
}
