<?php

declare(strict_types=1);

namespace Scafera\Auth;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Protect
{
    /**
     * @param class-string<GuardInterface> $guard
     * @param array<string, mixed>         $options
     */
    public function __construct(
        public readonly string $guard,
        public readonly array $options = [],
    ) {}
}
