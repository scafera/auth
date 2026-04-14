<?php

declare(strict_types=1);

namespace Scafera\Auth\DependencyInjection;

use Scafera\Kernel\Tool\FileFinder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal Enforces that Symfony session, cookie, security, and password-hasher types
 *           do not leak into userland code.
 */
final class AuthBoundaryPass implements CompilerPassInterface
{
    /** @var array<string, string> Regex pattern => violation message */
    public const FORBIDDEN_PATTERNS = [
        'Symfony\\\\Component\\\\HttpFoundation\\\\Session\\\\' => 'Symfony Session — use Scafera\\Auth\\Session instead',
        'Symfony\\\\Component\\\\HttpFoundation\\\\Cookie' => 'Symfony Cookie — use Scafera\\Auth\\CookieJar instead',
        'Symfony\\\\Component\\\\Security\\\\' => 'Symfony Security — use Scafera\\Auth types instead',
        'Symfony\\\\Component\\\\PasswordHasher\\\\' => 'Symfony PasswordHasher — use Scafera\\Auth\\Password instead',
    ];

    public function process(ContainerBuilder $container): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $srcDir = $projectDir . '/src';

        if (!is_dir($srcDir)) {
            return;
        }

        $violations = [];

        foreach (FileFinder::findPhpFiles($srcDir) as $file) {
            $relative = str_replace($projectDir . '/', '', $file);
            $contents = file_get_contents($file);

            foreach (self::FORBIDDEN_PATTERNS as $pattern => $message) {
                if (self::matches($contents, $pattern)) {
                    $violations[] = "  - {$relative}: uses {$message}";
                }
            }
        }

        if (!empty($violations)) {
            throw new \LogicException(
                "Scafera\\Auth boundary violation:\n\n"
                . implode("\n", $violations)
                . "\n\nUse Scafera\\Auth types instead (Session, Authenticator, Password, CookieJar, GuardInterface).",
            );
        }
    }

    public static function matches(string $contents, string $pattern): bool
    {
        return (bool) preg_match('/^use\s+' . $pattern . '/m', $contents)
            || (bool) preg_match('/new\s+\\\\?' . $pattern . '/m', $contents)
            || (bool) preg_match('/extends\s+\\\\?' . $pattern . '/m', $contents);
    }
}
