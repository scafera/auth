<?php

declare(strict_types=1);

namespace Scafera\Auth\Tests;

use PHPUnit\Framework\TestCase;
use Scafera\Auth\Authenticator;
use Scafera\Auth\Session;
use Scafera\Auth\User;
use Scafera\Auth\UserProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class AuthenticatorTest extends TestCase
{
    private function createSession(): Session
    {
        $symfonySession = new SymfonySession(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($symfonySession);

        $stack = new RequestStack();
        $stack->push($request);

        return new Session($stack);
    }

    private function createUser(string $id = 'user-1', array $roles = ['USER']): User
    {
        return new class($id, $roles) implements User {
            public function __construct(private readonly string $id, private readonly array $roles) {}
            public function getIdentifier(): string { return $this->id; }
            public function getRoles(): array { return $this->roles; }
            public function getPassword(): string { return 'hashed'; }
        };
    }

    public function testLoginAndIsAuthenticated(): void
    {
        $session = $this->createSession();
        $auth = new Authenticator($session);

        $this->assertFalse($auth->isAuthenticated());

        $auth->login($this->createUser());

        $this->assertTrue($auth->isAuthenticated());
    }

    public function testLogout(): void
    {
        $session = $this->createSession();
        $auth = new Authenticator($session);

        $auth->login($this->createUser());
        $this->assertTrue($auth->isAuthenticated());

        $auth->logout();
        $this->assertFalse($auth->isAuthenticated());
    }

    public function testGetUserReturnsNullWithoutProvider(): void
    {
        $session = $this->createSession();
        $auth = new Authenticator($session);

        $auth->login($this->createUser());

        $this->assertNull($auth->getUser());
    }

    public function testGetUserReturnsUserViaProvider(): void
    {
        $session = $this->createSession();
        $user = $this->createUser('test-id');
        $provider = $this->createStub(UserProvider::class);
        $provider->method('findByIdentifier')->willReturn($user);

        $auth = new Authenticator($session, $provider);
        $auth->login($user);

        $this->assertSame($user, $auth->getUser());
    }

    public function testDeletedUserIsNotAuthenticated(): void
    {
        $session = $this->createSession();
        $user = $this->createUser('deleted-user');
        $provider = $this->createStub(UserProvider::class);
        $provider->method('findByIdentifier')->willReturn(null);

        $auth = new Authenticator($session, $provider);
        $auth->login($user);

        // Session key exists, but user no longer in provider
        $this->assertFalse($auth->isAuthenticated());
    }
}
