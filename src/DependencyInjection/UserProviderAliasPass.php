<?php

declare(strict_types=1);

namespace Scafera\Auth\DependencyInjection;

use Scafera\Auth\UserProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal Auto-aliases UserProvider interface to the app's implementation.
 *
 * If exactly one service tagged with 'scafera.auth.user_provider' exists,
 * it is aliased as the UserProvider interface for autowiring.
 */
final class UserProviderAliasPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $tagged = $container->findTaggedServiceIds('scafera.auth.user_provider');

        if (\count($tagged) === 1) {
            $serviceId = array_key_first($tagged);
            $container->setAlias(UserProvider::class, $serviceId)->setPublic(true);
        }
    }
}
