<?php

declare(strict_types=1);

namespace Mom\Installer\Console\Tests;

use Mom\Installer\Console\NewCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NewCommandTest extends TestCase
{
    public function test_it_can_scaffold_a_new_laravel_app(): void
    {
        $scaffoldDirectoryName = 'tests-output/my-app';
        $scaffoldDirectory = __DIR__ . '/../' . $scaffoldDirectoryName;

        if (file_exists($scaffoldDirectory)) {
            if (PHP_OS_FAMILY === 'Windows') {
                exec("rd /s /q \"{$scaffoldDirectory}\"");
            } else {
                exec("rm -rf \"{$scaffoldDirectory}\"");
            }
        }

        $app = new Application('Mom Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        // The installer resolves the app name against the working directory.
        $originalDirectory = getcwd();
        chdir(__DIR__ . '/..');

        try {
            // mom/mom has no stable release yet, so install from the main branch.
            $statusCode = $tester->execute(['name' => $scaffoldDirectoryName, '--dev' => true], ['interactive' => false]);
        } finally {
            chdir($originalDirectory);
        }

        $this->assertSame(0, $statusCode, $tester->getDisplay());
        $this->assertDirectoryExists($scaffoldDirectory . '/vendor');
        $this->assertFileExists($scaffoldDirectory . '/.env');
    }

    public function test_on_at_least_laravel_11(): void
    {
        $command = new NewCommand;

        $onLaravel10 = $command->usingMomVersionOrNewer(11, __DIR__ . '/fixtures/laravel10');
        $onLaravel11 = $command->usingMomVersionOrNewer(11, __DIR__ . '/fixtures/laravel11');
        $onLaravel12 = $command->usingMomVersionOrNewer(11, __DIR__ . '/fixtures/laravel12');

        $this->assertFalse($onLaravel10);
        $this->assertTrue($onLaravel11);
        $this->assertTrue($onLaravel12);
    }
}
