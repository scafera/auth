<?php

declare(strict_types=1);

namespace Scafera\Auth\Validator;

use Scafera\Auth\DependencyInjection\AuthBoundaryPass;
use Scafera\Kernel\Contract\ValidatorInterface;
use Scafera\Kernel\Tool\FileFinder;

final class AuthBoundaryValidator implements ValidatorInterface
{
    public function getId(): string
    {
        return 'auth.boundary';
    }

    public function getName(): string
    {
        return 'Auth Boundary';
    }

    public function validate(string $projectDir): array
    {
        $srcDir = $projectDir . '/src';

        if (!is_dir($srcDir)) {
            return [];
        }

        $violations = [];

        foreach (FileFinder::findPhpFiles($srcDir) as $file) {
            $relative = str_replace($projectDir . '/', '', $file);
            $contents = file_get_contents($file);

            foreach (AuthBoundaryPass::FORBIDDEN_PATTERNS as $pattern => $message) {
                if (AuthBoundaryPass::matches($contents, $pattern)) {
                    $violations[] = "{$relative}: uses {$message}";
                }
            }
        }

        return $violations;
    }
}
