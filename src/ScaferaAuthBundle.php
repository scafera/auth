<?php

declare(strict_types=1);

namespace Scafera\Auth;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class ScaferaAuthBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('framework', [
            'session' => [
                'handler_id' => null,
                'cookie_secure' => 'auto',
                'cookie_httponly' => true,
                'cookie_samesite' => 'lax',
            ],
        ]);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $globalGuards = $config['global'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        $container->services()
            // Core services
            ->set(Session::class)
                ->args([service('request_stack')])
                ->public()
            ->set(CookieJar::class)
                ->args([service('request_stack')])
                ->public()
            ->set(Password::class)
                ->public()
            ->set(Authenticator::class)
                ->args([
                    service(Session::class),
                    service(UserProvider::class)->nullOnInvalid(),
                ])
                ->public()

            // Built-in guards
            ->set(SessionGuard::class)
                ->args([service(Authenticator::class)])
                ->public()
            ->set(RoleGuard::class)
                ->args([service(Authenticator::class)])
                ->public()

            // Event listeners
            ->set(Listener\ProtectListener::class)
                ->args([
                    service('service_container'),
                    $globalGuards,
                    $excludePaths,
                ])
                ->tag('kernel.event_subscriber')
            ->set(Listener\CookieListener::class)
                ->args([service(CookieJar::class)])
                ->tag('kernel.event_subscriber')

            // Validator
            ->set(Validator\AuthBoundaryValidator::class)
                ->tag('scafera.validator');

        // Auto-tag any GuardInterface implementations
        $builder->registerForAutoconfiguration(GuardInterface::class)
            ->setPublic(true);

        // Auto-alias UserProvider interface to the app's implementation
        $builder->registerForAutoconfiguration(UserProvider::class)
            ->addTag('scafera.auth.user_provider');
    }

    public function configure(\Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('global')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('exclude')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end();
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DependencyInjection\UserProviderAliasPass());
        $container->addCompilerPass(new DependencyInjection\AuthBoundaryPass());
    }
}
