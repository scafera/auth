<?php

declare(strict_types=1);

namespace Scafera\Auth\Tests;

use PHPUnit\Framework\TestCase;
use Scafera\Auth\Authenticator;
use Scafera\Auth\RoleGuard;
use Scafera\Auth\Session;
use Scafera\Auth\User;
use Scafera\Auth\UserProvider;
use Scafera\Kernel\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class RoleGuardTest extends TestCase
{
    private function createAuth(array $roles = []): Authenticator
    {
        $symfonySession = new SymfonySession(new MockArraySessionStorage());
        $request = new SymfonyRequest();
        $request->setSession($symfonySession);
        $stack = new RequestStack();
        $stack->push($request);
        $session = new Session($stack);

        if ($roles !== []) {
            $user = $this->createStub(User::class);
            $user->method('getIdentifier')->willReturn('user-1');
            $user->method('getRoles')->willReturn($roles);
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

    public function testAllowsUserWithRequiredRole(): void
    {
        $guard = new RoleGuard($this->createAuth(['USER', 'ADMIN']));

        $result = $guard->check($this->createRequest(), ['role' => 'ADMIN']);

        $this->assertNull($result);
    }

    public function testDeniesUserWithoutRequiredRole(): void
    {
        $guard = new RoleGuard($this->createAuth(['USER']));

        $result = $guard->check($this->createRequest(), ['role' => 'ADMIN']);

        $this->assertNotNull($result);
        $this->assertSame(403, $result->getStatusCode());
    }

    public function testDeniesUnauthenticatedUser(): void
    {
        $guard = new RoleGuard($this->createAuth());

        $result = $guard->check($this->createRequest(), ['role' => 'ADMIN']);

        $this->assertNotNull($result);
        $this->assertSame(401, $result->getStatusCode());
    }

    public function testAllowsWhenNoRoleSpecified(): void
    {
        $guard = new RoleGuard($this->createAuth(['USER']));

        $result = $guard->check($this->createRequest(), []);

        $this->assertNull($result);
    }
}
