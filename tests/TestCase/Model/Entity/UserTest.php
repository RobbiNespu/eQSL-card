<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Entity;

use App\Model\Entity\User;
use Cake\TestSuite\TestCase;

final class UserTest extends TestCase
{
    public function testPasswordIsHashedWithArgon2id(): void
    {
        $user = new User(['password' => 'CorrectHorseBatteryStaple1']);
        $hash = $user->password_hash;

        $this->assertNotEmpty($hash);
        $this->assertStringStartsWith('$argon2id$', $hash, 'Password must be hashed with Argon2id');
        $this->assertTrue(password_verify('CorrectHorseBatteryStaple1', $hash));
    }

    public function testEmptyPasswordDoesNotSetHash(): void
    {
        $user = new User(['password' => '']);
        $this->assertNull($user->password_hash);
    }
}
