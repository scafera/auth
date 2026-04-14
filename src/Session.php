<?php

declare(strict_types=1);

namespace Scafera\Auth;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class Session
{
    public function __construct(private readonly RequestStack $requestStack) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getSessionOrNull()?->get($key, $default) ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->getSessionOrNull()?->set($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->getSessionOrNull()?->has($key) ?? false;
    }

    public function remove(string $key): void
    {
        $this->getSessionOrNull()?->remove($key);
    }

    public function clear(): void
    {
        $this->getSessionOrNull()?->clear();
    }

    /**
     * Regenerate the session ID while preserving session data.
     * Must be called after login to prevent session fixation attacks.
     */
    public function migrate(): void
    {
        $this->getSessionOrNull()?->migrate(true);
    }

    public function flash(string $key, mixed $value): void
    {
        $this->getSessionOrNull()?->getFlashBag()->add($key, $value);
    }

    /** @return list<mixed> */
    public function getFlash(string $key): array
    {
        return $this->getSessionOrNull()?->getFlashBag()->get($key) ?? [];
    }

    private function getSessionOrNull(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        return $request->hasSession() ? $request->getSession() : null;
    }
}
