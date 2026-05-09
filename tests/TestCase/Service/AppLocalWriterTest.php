<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AppLocalWriter;
use Cake\TestSuite\TestCase;

final class AppLocalWriterTest extends TestCase
{
    public function testWritesAndReplacesPlaceholders(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'app_local_');
        $exampleSrc = tempnam(sys_get_temp_dir(), 'app_local_example_');
        file_put_contents(
            $exampleSrc,
            "<?php return ['Datasources' => ['default' => ['host' => '__DB_HOST__', 'database' => '__DB_NAME__', 'username' => '__DB_USER__', 'password' => '__DB_PASS__', 'port' => '__DB_PORT__']]];"
        );

        (new AppLocalWriter($exampleSrc))->write($tmp, [
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'DB_USER' => 'eqsl',
            'DB_PASS' => 'secret',
            'DB_NAME' => 'eqsl_db',
            'SECURITY_SALT' => str_repeat('a', 64),
            'SMTP_HOST' => 'mail.example.com',
            'SMTP_USER' => 'me@example.com',
            'SMTP_PASS' => 'p',
            'SMTP_FROM' => 'noreply@example.com',
        ]);

        $written = file_get_contents($tmp);
        $this->assertStringContainsString("'host' => 'localhost'", $written);
        $this->assertStringNotContainsString('__DB_HOST__', $written);

        unlink($tmp);
        unlink($exampleSrc);
    }
}
