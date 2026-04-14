<?php

declare(strict_types=1);

namespace Scafera\Auth\Tests\Listener;

use PHPUnit\Framework\TestCase;
use Scafera\Auth\GuardInterface;
use Scafera\Auth\Listener\ProtectListener;
use Scafera\Auth\Protect;
use Scafera\Kernel\Http\Request;
use Scafera\Kernel\Http\Response;
use Scafera\Kernel\Http\ResponseInterface;
use Scafera\Kernel\Http\Route;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ProtectListenerTest extends TestCase
{
    private function createEvent(string $controllerClass, string $path = '/'): RequestEvent
    {
        $request = new SymfonyRequest(server: ['REQUEST_URI' => $path]);
        $request->attributes->set('_controller', $controllerClass . '::__invoke');

        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function createGuard(?ResponseInterface $response): GuardInterface
    {
        return new class($response) implements GuardInterface {
            public function __construct(private readonly ?ResponseInterface $response) {}
            public function check(Request $request, array $options = []): ?ResponseInterface
            {
                return $this->response;
            }
        };
    }

    public function testRunsGuardFromProtectAttribute(): void
    {
        $guard = $this->createGuard(new Response('Denied', 403));
        $container = new Container();
        $container->set(DenyGuard::class, $guard);

        $listener = new ProtectListener($container);
        $event = $this->createEvent(ProtectedController::class);

        $listener->onKernelRequest($event);

        $this->assertNotNull($event->getResponse());
        $this->assertSame(403, $event->getResponse()->getStatusCode());
    }

    public function testAllowsWhenGuardReturnsNull(): void
    {
        $guard = $this->createGuard(null);
        $container = new Container();
        $container->set(DenyGuard::class, $guard);

        $listener = new ProtectListener($container);
        $event = $this->createEvent(ProtectedController::class);

        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsControllerWithoutProtectAttribute(): void
    {
        $container = new Container();
        $listener = new ProtectListener($container);
        $event = $this->createEvent(UnprotectedController::class);

        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testPassesOptionsToGuard(): void
    {
        $receivedOptions = [];
        $guard = new class($receivedOptions) implements GuardInterface {
            public function __construct(private array &$received) {}
            public function check(Request $request, array $options = []): ?ResponseInterface
            {
                $this->received = $options;
                return null;
            }
        };

        $container = new Container();
        $container->set(DenyGuard::class, $guard);

        $listener = new ProtectListener($container);
        $event = $this->createEvent(ProtectedWithOptionsController::class);

        $listener->onKernelRequest($event);

        $this->assertSame(['role' => 'ADMIN'], $receivedOptions);
    }

    public function testGlobalGuardRunsBeforeRouteGuard(): void
    {
        $globalGuard = $this->createGuard(new Response('Global denied', 403));
        $container = new Container();
        $container->set('App\\Guard\\GlobalGuard', $globalGuard);

        $listener = new ProtectListener($container, ['App\\Guard\\GlobalGuard']);
        $event = $this->createEvent(UnprotectedController::class);

        $listener->onKernelRequest($event);

        $this->assertNotNull($event->getResponse());
        $this->assertSame(403, $event->getResponse()->getStatusCode());
    }

    public function testExcludedPathSkipsGlobalGuards(): void
    {
        $globalGuard = $this->createGuard(new Response('Denied', 403));
        $container = new Container();
        $container->set('App\\Guard\\GlobalGuard', $globalGuard);

        $listener = new ProtectListener($container, ['App\\Guard\\GlobalGuard'], ['/login']);
        $event = $this->createEvent(UnprotectedController::class, '/login');

        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testExcludedPathDoesNotMatchPrefix(): void
    {
        $globalGuard = $this->createGuard(new Response('Denied', 403));
        $container = new Container();
        $container->set('App\\Guard\\GlobalGuard', $globalGuard);

        // /login excluded, but /login-help should NOT be excluded
        $listener = new ProtectListener($container, ['App\\Guard\\GlobalGuard'], ['/login']);
        $event = $this->createEvent(UnprotectedController::class, '/login-help');

        $listener->onKernelRequest($event);

        $this->assertNotNull($event->getResponse());
    }

    public function testExcludedPathMatchesSubpaths(): void
    {
        $globalGuard = $this->createGuard(new Response('Denied', 403));
        $container = new Container();
        $container->set('App\\Guard\\GlobalGuard', $globalGuard);

        // /login excluded, /login/callback should also be excluded
        $listener = new ProtectListener($container, ['App\\Guard\\GlobalGuard'], ['/login']);
        $event = $this->createEvent(UnprotectedController::class, '/login/callback');

        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }
}

// Test controller fixtures
#[Protect(guard: DenyGuard::class)]
final class ProtectedController
{
    public function __invoke(): void {}
}

#[Protect(guard: DenyGuard::class, options: ['role' => 'ADMIN'])]
final class ProtectedWithOptionsController
{
    public function __invoke(): void {}
}

final class UnprotectedController
{
    public function __invoke(): void {}
}

// Marker class for container registration
interface DenyGuard extends GuardInterface {}
