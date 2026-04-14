<?php

declare(strict_types=1);

namespace Scafera\Auth;

interface User
{
    public function getIdentifier(): string;

    /** @return list<string> */
    public function getRoles(): array;

    public function getPassword(): string;
}
