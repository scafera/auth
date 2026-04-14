<?php

declare(strict_types=1);

namespace Scafera\Auth\Tests;

use PHPUnit\Framework\TestCase;
use Scafera\Auth\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SessionTest extends TestCase
{
    private function createSessionWithRequest(): Session
    {
        $symfonySession = new SymfonySession(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($symfonySession);

        $stack = new RequestStack();
        $stack->push($request);

        return new Session($stack);
    }

    private function createSessionWithoutRequest(): Session
    {
        return new Session(new RequestStack());
    }

    public function testGetAndSet(): void
    {
        $session = $this->createSessionWithRequest();
        $session->set('key', 'value');

        $this->assertSame('value', $session->get('key'));
    }

    public function testHas(): void
    {
        $session = $this->createSessionWithRequest();

        $this->assertFalse($session->has('missing'));

        $session->set('exists', true);
        $this->assertTrue($session->has('exists'));
    }

    public function testRemove(): void
    {
        $session = $this->createSessionWithRequest();
        $session->set('key', 'value');
        $session->remove('key');

        $this->assertFalse($session->has('key'));
    }

    public function testFlash(): void
    {
        $session = $this->createSessionWithRequest();
        $session->flash('notice', 'Hello');

        $this->assertSame(['Hello'], $session->getFlash('notice'));
        // Flash messages are consumed on read
        $this->assertSame([], $session->getFlash('notice'));
    }

    // CLI safety — no request in stack
    public function testGetReturnsDefaultWithNoRequest(): void
    {
        $session = $this->createSessionWithoutRequest();

        $this->assertNull($session->get('anything'));
        $this->assertSame('fallback', $session->get('anything', 'fallback'));
    }

    public function testHasReturnsFalseWithNoRequest(): void
    {
        $session = $this->createSessionWithoutRequest();

        $this->assertFalse($session->has('anything'));
    }

    public function testSetDoesNotThrowWithNoRequest(): void
    {
        $session = $this->createSessionWithoutRequest();
        $session->set('key', 'value');

        // No exception — silent no-op
        $this->assertFalse($session->has('key'));
    }

    public function testGetFlashReturnsEmptyWithNoRequest(): void
    {
        $session = $this->createSessionWithoutRequest();

        $this->assertSame([], $session->getFlash('anything'));
    }

    public function testMigrateDoesNotThrowWithNoRequest(): void
    {
        $session = $this->createSessionWithoutRequest();
        $session->migrate();

        $this->assertTrue(true); // No exception
    }
}
