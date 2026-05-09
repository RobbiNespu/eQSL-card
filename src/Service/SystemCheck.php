<?php
declare(strict_types=1);

namespace App\Service;

final class SystemCheck
{
    /** @return array<string, array{ok:bool, detail:string}> */
    public function run(): array
    {
        $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        return [
            'php_version' => [
                'ok' => $phpOk,
                'detail' => 'Detected PHP ' . PHP_VERSION . '; require >= 8.1',
            ],
            'gd' => [
                'ok' => extension_loaded('gd'),
                'detail' => extension_loaded('gd') ? 'GD enabled' : 'GD extension missing',
            ],
            'pdo_mysql' => [
                'ok' => extension_loaded('pdo_mysql'),
                'detail' => extension_loaded('pdo_mysql') ? 'pdo_mysql enabled' : 'pdo_mysql missing',
            ],
            'writable_config' => [
                'ok' => is_writable(CONFIG),
                'detail' => CONFIG . ' must be writable',
            ],
            'writable_files' => [
                'ok' => is_writable(WWW_ROOT . 'files'),
                'detail' => WWW_ROOT . 'files must be writable',
            ],
        ];
    }
}
