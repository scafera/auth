<?php

declare(strict_types=1);

namespace Scafera\Auth\Tests;

use PHPUnit\Framework\TestCase;
use Scafera\Auth\Password;

final class PasswordTest extends TestCase
{
    public function testHashReturnsNonEmptyString(): void
    {
        $password = new Password();
        $hash = $password->hash('secret');

        $this->assertNotEmpty($hash);
        $this->assertNotEquals('secret', $hash);
    }

    public function testVerifyReturnsTrueForCorrectPassword(): void
    {
        $password = new Password();
        $hash = $password->hash('secret');

        $this->assertTrue($password->verify($hash, 'secret'));
    }

    public function testVerifyReturnsFalseForWrongPassword(): void
    {
        $password = new Password();
        $hash = $password->hash('secret');

        $this->assertFalse($password->verify($hash, 'wrong'));
    }

    public function testNeedsRehashReturnsFalseForFreshHash(): void
    {
        $password = new Password();
        $hash = $password->hash('secret');

        $this->assertFalse($password->needsRehash($hash));
    }
}
