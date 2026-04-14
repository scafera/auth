<?php

declare(strict_types=1);

namespace Scafera\Auth\Listener;

use Scafera\Auth\GuardInterface;
use Scafera\Auth\Protect;
use Scafera\Kernel\Http\Internal\ResponseConverter;
use Scafera\Kernel\Http\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal Reads #[Protect] attributes from the matched controller and runs guards.
 */
final class ProtectListener implements EventSubscriberInterface
{
    /**
     * @param list<string> $globalGuards
     * @param list<string> $excludePaths
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $globalGuards = [],
        private readonly array $excludePaths = [],
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 8]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $symfonyRequest = $event->getRequest();
        $controller = $symfonyRequest->attributes->get('_controller');

        if (!\is_string($controller)) {
            return;
        }

        $scaferaRequest = new Request($symfonyRequest);
        $currentPath = $symfonyRequest->getPathInfo();

        // Run global guards (unless path is excluded)
        if (!$this->isExcluded($currentPath)) {
            foreach ($this->globalGuards as $guardClass) {
                $response = $this->runGuard($guardClass, $scaferaRequest, $symfonyRequest);
                if ($response !== null) {
                    $event->setResponse($response);

                    return;
                }
            }
        }

        // Resolve controller class from _controller attribute
        $controllerClass = $this->resolveControllerClass($controller);
        if ($controllerClass === null || !class_exists($controllerClass)) {
            return;
        }

        // Read #[Protect] attributes from the controller class
        $reflection = new \ReflectionClass($controllerClass);
        $attributes = $reflection->getAttributes(Protect::class);

        foreach ($attributes as $attribute) {
            $protect = $attribute->newInstance();

            $response = $this->runGuard($protect->guard, $scaferaRequest, $symfonyRequest, $protect->options);
            if ($response !== null) {
                $event->setResponse($response);

                return;
            }
        }
    }

    /** @param array<string, mixed> $options */
    private function runGuard(string $guardClass, Request $scaferaRequest, \Symfony\Component\HttpFoundation\Request $symfonyRequest, array $options = []): ?\Symfony\Component\HttpFoundation\Response
    {
        if (!$this->container->has($guardClass)) {
            throw new \LogicException(sprintf('Guard "%s" is not registered as a service.', $guardClass));
        }

        $guard = $this->container->get($guardClass);

        if (!$guard instanceof GuardInterface) {
            throw new \LogicException(sprintf('Guard "%s" must implement %s.', $guardClass, GuardInterface::class));
        }

        $result = $guard->check($scaferaRequest, $options);

        if ($result === null) {
            return null;
        }

        return ResponseConverter::toSymfony($result);
    }

    private function resolveControllerClass(string $controller): ?string
    {
        if (str_contains($controller, '::')) {
            return explode('::', $controller, 2)[0];
        }

        if (class_exists($controller)) {
            return $controller;
        }

        return null;
    }

    private function isExcluded(string $path): bool
    {
        foreach ($this->excludePaths as $excludePath) {
            if ($path === $excludePath || str_starts_with($path, rtrim($excludePath, '/') . '/')) {
                return true;
            }
        }

        return false;
    }
}
