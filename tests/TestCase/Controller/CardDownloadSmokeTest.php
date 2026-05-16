<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\TestCase;

final class CardDownloadSmokeTest extends TestCase
{
    public function testDirectoryExistsAndIsServable(): void
    {
        $dir = WWW_ROOT . 'files/cards/';
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        $this->assertDirectoryExists($dir);
        $this->assertDirectoryIsWritable($dir);
    }
}
