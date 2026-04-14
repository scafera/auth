<?php

declare(strict_types=1);

namespace Scafera\Auth;

interface UserProvider
{
    public function findByIdentifier(string $identifier): ?User;
}
