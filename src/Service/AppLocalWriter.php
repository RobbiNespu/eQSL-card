<?php
declare(strict_types=1);

namespace App\Service;

final class AppLocalWriter
{
    public function __construct(private string $examplePath) {}

    /** @param array<string,string> $values */
    public function write(string $destinationPath, array $values): void
    {
        $template = file_get_contents($this->examplePath);
        if ($template === false) {
            throw new \RuntimeException("Cannot read template at {$this->examplePath}");
        }
        foreach ($values as $key => $value) {
            $template = str_replace('__' . $key . '__', addslashes($value), $template);
        }
        if (file_put_contents($destinationPath, $template, LOCK_EX) === false) {
            throw new \RuntimeException("Cannot write to {$destinationPath}");
        }
        chmod($destinationPath, 0o640);
    }
}
