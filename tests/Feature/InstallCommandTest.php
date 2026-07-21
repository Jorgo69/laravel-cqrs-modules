<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Tests de la commande cqrs-modules:install.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Jorgo69\LaravelCqrsModules\Tests\TestCase;

final class InstallCommandTest extends TestCase
{
    public function test_it_installs_the_bus_infrastructure_and_registers_the_provider(): void
    {
        $this->artisan('cqrs-modules:install', ['--no-interaction' => true])
            ->assertExitCode(0);

        $files = new Filesystem;
        $busPath = app_path('Shared/Bus');

        foreach (['Command.php', 'Query.php', 'CommandHandler.php', 'QueryHandler.php', 'CommandBus.php', 'QueryBus.php', 'BusServiceProvider.php'] as $file) {
            $this->assertFileExists("{$busPath}/{$file}");
        }

        $commandBusContent = $files->get("{$busPath}/CommandBus.php");
        $this->assertStringContainsString('namespace App\Shared\Bus;', $commandBusContent);
        $this->assertStringContainsString('final class CommandBus', $commandBusContent);

        $bootstrap = $files->get(base_path('bootstrap/providers.php'));
        $this->assertStringContainsString('App\Shared\Bus\BusServiceProvider::class', $bootstrap);
    }

    public function test_it_is_idempotent_on_a_second_run(): void
    {
        $this->artisan('cqrs-modules:install', ['--no-interaction' => true])->assertExitCode(0);
        $this->artisan('cqrs-modules:install', ['--no-interaction' => true])->assertExitCode(0);

        $files = new Filesystem;
        $bootstrap = $files->get(base_path('bootstrap/providers.php'));

        $this->assertSame(1, substr_count($bootstrap, 'App\Shared\Bus\BusServiceProvider::class'));
    }
}
