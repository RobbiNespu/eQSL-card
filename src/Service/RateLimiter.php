<?php
declare(strict_types=1);

namespace App\Service;

final class RateLimiter
{
    /** @var \Closure():int */
    private \Closure $clock;

    public function __construct(
        private string $storageDir,
        ?callable $clock = null,
    ) {
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0o775, true);
        }
        $this->clock = $clock ? \Closure::fromCallable($clock) : static fn(): int => time();
    }

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
