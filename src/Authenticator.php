<?php

declare(strict_types=1);

namespace Scafera\Auth;

final class Authenticator
{
    private const SESSION_USER_KEY = '_scafera_auth_user_id';

    /** @var array{resolved: bool, user: ?User} Request-scoped cache to avoid repeated DB lookups. */
    private array $cachedUser = ['resolved' => false, 'user' => null];

    public function __construct(
        private readonly Session $session,
        private readonly ?UserProvider $userProvider = null,
    ) {}

    public function login(User $user): void
    {
        $this->session->migrate();
        $this->session->set(self::SESSION_USER_KEY, $user->getIdentifier());
        $this->cachedUser = ['resolved' => false, 'user' => null];
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_USER_KEY);
        $this->session->migrate();
        $this->cachedUser = ['resolved' => false, 'user' => null];
    }

    public function getUser(): ?User
    {
        return $this->resolveUser();
    }

    public function isAuthenticated(): bool
    {
        if (!$this->session->has(self::SESSION_USER_KEY)) {
            return false;
        }

        if ($this->userProvider === null) {
            return true;
        }

        return $this->resolveUser() !== null;
    }

    private function resolveUser(): ?User
    {
        if ($this->cachedUser['resolved']) {
            return $this->cachedUser['user'];
        }

        $identifier = $this->session->get(self::SESSION_USER_KEY);

        if ($identifier === null || $this->userProvider === null) {
            $this->cachedUser = ['resolved' => true, 'user' => null];

            return null;
        }

        $user = $this->userProvider->findByIdentifier($identifier);
        $this->cachedUser = ['resolved' => true, 'user' => $user];

        return $user;
    }
}
