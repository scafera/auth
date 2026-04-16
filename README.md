# scafera/auth

Authentication and access control for the Scafera framework. Provides session management, guards, login/logout, and password hashing — all behind Scafera-owned types.

Internally adopts `symfony/http-foundation` Session and `symfony/password-hasher`. Userland code never imports Symfony types — boundary enforcement blocks it at compile time.

> **Provides:** Authentication and access control for Scafera — `Authenticator` (login/logout), `Session` (state + flash), `Password` (hash / verify / `needsRehash`), `#[Protect]` attribute with `GuardInterface` guards, plus `CookieJar` for secure cookies. User-existence-based authentication per ADR-058.
>
> **Depends on:** A Scafera host project that implements `User` and `UserProvider` (how auth finds an identity). When exactly one `UserProvider` implementation exists, it is auto-aliased for injection.
>
> **Extension points:**
> - Contracts — `User`, `UserProvider`, `GuardInterface`
> - Attribute — `#[Protect(guard: ..., options: [...])]` on controllers
> - Built-in guards — `SessionGuard` and `RoleGuard`; implement `GuardInterface` for custom guards
> - Config — `scafera_auth.global` (guards applied to every request), `scafera_auth.exclude` (paths bypassing global guards)
>
> **Not responsible for:** User storage (app implements `UserProvider`) · password complexity / policy rules · two-factor auth or passkey flows · session storage backend (Symfony's responsibility) · direct use of `Symfony\Component\Security`, `HttpFoundation\Session`, `PasswordHasher`, `HttpFoundation\Cookie` in userland (blocked by `AuthBoundaryPass` and `AuthBoundaryValidator`).

This is a **capability package**. It adds optional authentication and access control to a Scafera project. It does not define folder structure or architectural rules — those belong to architecture packages.

## What it provides

- `Session` — session state management with flash messages
- `CookieJar` — secure cookie handling (auto-applied via response listener)
- `Authenticator` — login, logout, user resolution
- `Password` — hash, verify, needsRehash
- `GuardInterface` + `#[Protect]` — route protection
- `SessionGuard` and `RoleGuard` — built-in guards
- `User` + `UserProvider` — user identity contracts

## Design decisions

- **User existence is the source of truth** — `isAuthenticated()` verifies the user still exists in the provider, not just that a session key is present. One cached DB lookup per request (ADR-058).
- **Session fixation prevention** — session ID is regenerated on both login and logout.
- **Explicit guard execution** — guards are declared via `#[Protect]` attributes on controllers, not via implicit firewall rules. Options are passed directly to `check()` — no magic attributes.

## Installation

```bash
composer require scafera/auth
```

## Requirements

- PHP >= 8.4
- scafera/kernel

## Session

```php
use Scafera\Auth\Session;

$session->set('key', 'value');
$session->get('key');              // 'value'
$session->has('key');              // true
$session->remove('key');
$session->flash('notice', 'Saved!');
$session->getFlash('notice');      // ['Saved!']
```

Safe in CLI context — returns defaults when no request exists.

## Authentication

```php
use Scafera\Auth\Authenticator;
use Scafera\Auth\Password;

// Login
$user = $userProvider->findByIdentifier($email);
if ($user && $password->verify($user->getPassword(), $plainPassword)) {
    $authenticator->login($user);
}

// Check
$authenticator->isAuthenticated();  // true
$authenticator->getUser();          // User instance

// Logout
$authenticator->logout();

// Rehash check (on login)
if ($password->needsRehash($user->getPassword())) {
    // update stored hash
}
```

## User contracts

Implement these in your application:

```php
use Scafera\Auth\User;
use Scafera\Auth\UserProvider;

final class AppUser implements User
{
    public function getIdentifier(): string;
    public function getRoles(): array;
    public function getPassword(): string;
}

final class AppUserProvider implements UserProvider
{
    public function findByIdentifier(string $identifier): ?User;
}
```

When exactly one `UserProvider` implementation exists, it is auto-aliased for injection.

## Route protection

```php
use Scafera\Auth\Protect;
use Scafera\Auth\SessionGuard;
use Scafera\Auth\RoleGuard;

#[Protect(guard: SessionGuard::class)]
final class EditProfile
{
    // Only authenticated users reach this controller
}

#[Protect(guard: RoleGuard::class, options: ['role' => 'ADMIN'])]
final class AdminDashboard
{
    // Only users with ADMIN role
}
```

Guards run in declaration order. Return `null` to allow, or a `ResponseInterface` to deny. Options from `#[Protect]` are passed directly to `check()` — no magic attributes.

## Global guards

```yaml
# config/config.yaml
scafera_auth:
    global:
        - App\Guard\MaintenanceGuard
    exclude:
        - /health
        - /login
```

Global guards run before route-specific guards. Excluded paths are matched exactly or as prefixes with `/`.

## Boundary enforcement

| Blocked | Use instead |
|---------|-------------|
| `Symfony\Component\HttpFoundation\Session\*` | `Scafera\Auth\Session` |
| `Symfony\Component\HttpFoundation\Cookie` | `Scafera\Auth\CookieJar` |
| `Symfony\Component\Security\*` | `Scafera\Auth\Authenticator`, `GuardInterface` |
| `Symfony\Component\PasswordHasher\*` | `Scafera\Auth\Password` |

Enforced via compiler pass (build time) and validator (`scafera validate`). Detects `use`, `new`, and `extends` patterns.

## License

MIT
