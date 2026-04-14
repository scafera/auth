<?php

declare(strict_types=1);

namespace Scafera\Auth\Tests;

use PHPUnit\Framework\TestCase;
use Scafera\Auth\CookieJar;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieJarTest extends TestCase
{
    private function createCookieJar(array $existingCookies = []): CookieJar
    {
        $request = new Request(cookies: $existingCookies);
        $stack = new RequestStack();
        $stack->push($request);

        return new CookieJar($stack);
    }

    public function testGetReturnsExistingCookie(): void
    {
        $jar = $this->createCookieJar(['theme' => 'dark']);

        $this->assertSame('dark', $jar->get('theme'));
    }

    public function testGetReturnsNullForMissingCookie(): void
    {
        $jar = $this->createCookieJar();

        $this->assertNull($jar->get('missing'));
    }

    public function testHas(): void
    {
        $jar = $this->createCookieJar(['lang' => 'en']);

        $this->assertTrue($jar->has('lang'));
        $this->assertFalse($jar->has('missing'));
    }

    public function testSetQueuesCookie(): void
    {
        $jar = $this->createCookieJar();
        $jar->set('token', 'abc123');

        $pending = $jar->consumePending();

        $this->assertCount(1, $pending);
        $this->assertSame('token', $pending[0]->getName());
        $this->assertSame('abc123', $pending[0]->getValue());
    }

    public function testRemoveQueuesDeletionCookie(): void
    {
        $jar = $this->createCookieJar(['old' => 'value']);
        $jar->remove('old');

        $pending = $jar->consumePending();

        $this->assertCount(1, $pending);
        $this->assertSame('old', $pending[0]->getName());
        $this->assertTrue($pending[0]->isCleared());
    }

    public function testConsumePendingClearsQueue(): void
    {
        $jar = $this->createCookieJar();
        $jar->set('a', '1');
        $jar->set('b', '2');

        $first = $jar->consumePending();
        $second = $jar->consumePending();

        $this->assertCount(2, $first);
        $this->assertCount(0, $second);
    }
}
