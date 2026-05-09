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
        if (count($stamps) >= $limit) {
            file_put_contents($file, implode(',', $stamps));
            return false;
        }
        $stamps[] = $now;
        file_put_contents($file, implode(',', $stamps), LOCK_EX);
        return true;
    }
}
