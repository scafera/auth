<?php

declare(strict_types=1);

namespace Scafera\Auth\Tests;

use PHPUnit\Framework\TestCase;
use Scafera\Auth\Authenticator;
use Scafera\Auth\Session;
use Scafera\Auth\SessionGuard;
use Scafera\Auth\User;
use Scafera\Auth\UserProvider;
use Scafera\Kernel\Http\Request;
use Scafera\Kernel\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SessionGuardTest extends TestCase
{
    private function createAuth(bool $withUser = false): Authenticator
    {
        $symfonySession = new SymfonySession(new MockArraySessionStorage());
        $request = new SymfonyRequest();
        $request->setSession($symfonySession);
        $stack = new RequestStack();
        $stack->push($request);
        $session = new Session($stack);

        $provider = null;
        if ($withUser) {
            $user = $this->createStub(User::class);
            $user->method('getIdentifier')->willReturn('user-1');
            $user->method('getRoles')->willReturn(['USER']);
            $user->method('getPassword')->willReturn('hashed');
            $provider = $this->createStub(UserProvider::class);
            $provider->method('findByIdentifier')->willReturn($user);

            $auth = new Authenticator($session, $provider);
            $auth->login($user);

            return $auth;
        }

        return new Authenticator($session);
    }

    private function createRequest(): Request
    {
        return new Request(new SymfonyRequest());
    }

    public function testAllowsAuthenticatedUser(): void
    {
        $guard = new SessionGuard($this->createAuth(withUser: true));

        $result = $guard->check($this->createRequest());

        $this->assertNull($result);
    }

    public function testRedirectsUnauthenticatedUser(): void
    {
        $guard = new SessionGuard($this->createAuth(withUser: false));

        $result = $guard->check($this->createRequest());

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame(302, $result->getStatusCode());
    }

    public function testRedirectsToCustomLoginPath(): void
    {
        $guard = new SessionGuard($this->createAuth(withUser: false), '/auth/signin');

        $result = $guard->check($this->createRequest());

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/auth/signin', $result->getContent());
    }
}
