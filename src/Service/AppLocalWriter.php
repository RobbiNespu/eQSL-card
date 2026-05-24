<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Writes `app_local.php` from a `.example` template during the installation wizard.
 *
 * Replaces `__KEY__` tokens in the example file with the caller-supplied values
 * (DB credentials, security salt, etc.) and writes the result to `$destinationPath`
 * with best-effort 0640 permissions.
 */
final class AppLocalWriter
{
    /**
     * @param string $examplePath Absolute path to the `app_local.example.php` template.
     */
    public function __construct(private string $examplePath) {}

    /**
     * Render the example template with the given values and write the result to disk.
     *
     * Each `__KEY__` token in the template is replaced with `addslashes($value)` so
     * the written PHP file is safe to include. The file is written with `LOCK_EX` and
     * chmod'd to 0640 (best-effort — may silently fail on mismatched ownership in dev).
     *
     * @param string              $destinationPath Absolute path for the output file.
     * @param array<string,string> $values         Token name (without `__` delimiters) → replacement value.
     * @return void
     * @throws \RuntimeException If the template file cannot be read or the destination cannot be written.
     */
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
        // Best-effort lock-down of the secrets file. Fails when PHP runs as a
        // different uid than the file owner (e.g. www-data writing into a
        // bind-mounted dev tree owned by the host user). Production hosting
        // typically has matching ownership; dev does not.
        @chmod($destinationPath, 0o640);
    }
}
