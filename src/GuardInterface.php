<?php

declare(strict_types=1);

namespace Scafera\Auth;

use Scafera\Kernel\Http\Request;
use Scafera\Kernel\Http\ResponseInterface;

interface GuardInterface
{
    /**
     * Return null to allow access, or a Response to deny (redirect, 403, etc.)
     *
     * @param array<string, mixed> $options Options from the #[Protect] attribute
     */
    public function check(Request $request, array $options = []): ?ResponseInterface;
}
