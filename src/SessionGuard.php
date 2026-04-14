<?php

declare(strict_types=1);

namespace Scafera\Auth;

use Scafera\Kernel\Http\RedirectResponse;
use Scafera\Kernel\Http\Request;
use Scafera\Kernel\Http\ResponseInterface;

final class SessionGuard implements GuardInterface
{
    public function __construct(
        private readonly Authenticator $authenticator,
        private readonly string $loginPath = '/login',
    ) {}

    public function check(Request $request, array $options = []): ?ResponseInterface
    {
        if ($this->authenticator->isAuthenticated()) {
            return null;
        }

        return new RedirectResponse($this->loginPath);
    }
}
