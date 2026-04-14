<?php

declare(strict_types=1);

namespace Scafera\Auth;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieJar
{
    /** @var list<Cookie> */
    private array $pendingCookies = [];

    public function __construct(private readonly RequestStack $requestStack) {}

    public function set(string $name, string $value, int $maxAge = 0, string $path = '/'): void
    {
        $this->pendingCookies[] = Cookie::create($name, $value, $maxAge > 0 ? time() + $maxAge : 0, $path, null, true, true, false, Cookie::SAMESITE_LAX);
    }

    public function get(string $name): ?string
    {
        return $this->requestStack->getCurrentRequest()?->cookies->get($name);
    }

    public function has(string $name): bool
    {
        return $this->requestStack->getCurrentRequest()?->cookies->has($name) ?? false;
    }

    public function remove(string $name): void
    {
        $this->pendingCookies[] = Cookie::create($name, null, 1, '/');
    }

    /** @internal Used by CookieListener to auto-apply pending cookies to the response. */
    public function consumePending(): array
    {
        $cookies = $this->pendingCookies;
        $this->pendingCookies = [];

        return $cookies;
    }
}
