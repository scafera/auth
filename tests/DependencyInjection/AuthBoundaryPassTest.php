<?php

declare(strict_types=1);

namespace Scafera\Auth\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Scafera\Auth\DependencyInjection\AuthBoundaryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AuthBoundaryPassTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/scafera_auth_boundary_' . uniqid();
        mkdir($this->tmpDir . '/src', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testBlocksSymfonySessionImport(): void
    {
        file_put_contents($this->tmpDir . '/src/Bad.php', <<<'PHP'
        <?php
        use Symfony\Component\HttpFoundation\Session\SessionInterface;
        class Bad {}
        PHP);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Symfony Session/');

        $this->runPass();
    }

    public function testBlocksSymfonyCookieImport(): void
    {
        file_put_contents($this->tmpDir . '/src/Bad.php', <<<'PHP'
        <?php
        use Symfony\Component\HttpFoundation\Cookie;
        class Bad {}
        PHP);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Symfony Cookie/');

        $this->runPass();
    }

    public function testBlocksSymfonySecurityImport(): void
    {
        file_put_contents($this->tmpDir . '/src/Bad.php', <<<'PHP'
        <?php
        use Symfony\Component\Security\Core\User\UserInterface;
        class Bad {}
        PHP);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Symfony Security/');

        $this->runPass();
    }

    public function testBlocksSymfonyPasswordHasherImport(): void
    {
        file_put_contents($this->tmpDir . '/src/Bad.php', <<<'PHP'
        <?php
        use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
        class Bad {}
        PHP);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Symfony PasswordHasher/');

        $this->runPass();
    }

    public function testBlocksNewFqcnInstantiation(): void
    {
        file_put_contents($this->tmpDir . '/src/Bad.php', <<<'PHP'
        <?php
        class Bad {
            public function run() {
                $hasher = new \Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher();
            }
        }
        PHP);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Symfony PasswordHasher/');

        $this->runPass();
    }

    public function testBlocksExtendsFqcn(): void
    {
        file_put_contents($this->tmpDir . '/src/Bad.php', <<<'PHP'
        <?php
        class Bad extends \Symfony\Component\Security\Core\User\InMemoryUser {}
        PHP);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Symfony Security/');

        $this->runPass();
    }

    public function testAllowsScaferaAuthImports(): void
    {
        file_put_contents($this->tmpDir . '/src/Good.php', <<<'PHP'
        <?php
        use Scafera\Auth\Session;
        use Scafera\Auth\Authenticator;
        class Good {}
        PHP);

        // Should not throw
        $this->runPass();
        $this->assertTrue(true);
    }

    private function runPass(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->tmpDir);

        $pass = new AuthBoundaryPass();
        $pass->process($container);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
