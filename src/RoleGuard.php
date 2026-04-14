<?php

declare(strict_types=1);

namespace Scafera\Auth;

use Scafera\Kernel\Http\Request;
use Scafera\Kernel\Http\Response;
use Scafera\Kernel\Http\ResponseInterface;

final class RoleGuard implements GuardInterface
{
    public function __construct(private readonly Authenticator $authenticator) {}

    public function check(Request $request, array $options = []): ?ResponseInterface
    {
        if (!$this->authenticator->isAuthenticated()) {
            return new Response('Unauthorized', 401);
        }

        $user = $this->authenticator->getUser();
        if ($user === null) {
            return new Response('Unauthorized', 401);
        }

        $requiredRole = $options['role'] ?? null;

        if ($requiredRole === null) {
            return null;
        }

        if (\in_array($requiredRole, $user->getRoles(), true)) {
            return null;
        }

        return new Response('Forbidden', 403);
    }
}
