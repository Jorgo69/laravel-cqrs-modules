<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Tests de la commande make:cqrs-command.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Jorgo69\LaravelCqrsModules\Tests\TestCase;

final class MakeCqrsCommandCommandTest extends TestCase
{
    private const CREATE_REGISTRATION = '\App\Modules\Widget\Commands\CreateWidgetCommand::class, \App\Modules\Widget\Handlers\CreateWidgetHandler::class';

    private const DELETE_REGISTRATION = '\App\Modules\Widget\Commands\DeleteWidgetCommand::class, \App\Modules\Widget\Handlers\DeleteWidgetHandler::class';

    private function scaffoldWidgetModule(): void
    {
        $this->artisan('make:module', ['name' => 'Widget', '--no-test' => true, '--no-interaction' => true])
            ->assertExitCode(0);
    }

    public function test_it_fails_when_the_module_does_not_exist(): void
    {
        $this->artisan('make:cqrs-command', ['module' => 'Ghost', 'name' => 'CreateGhost'])
            ->assertExitCode(1);
    }

    public function test_it_adds_a_command_and_handler_and_registers_it(): void
    {
        $this->scaffoldWidgetModule();

        $this->artisan('make:cqrs-command', ['module' => 'Widget', 'name' => 'CreateWidget'])
            ->assertExitCode(0);

        $files = new Filesystem;
        $modulePath = app_path('Modules/Widget');

        $this->assertFileExists("{$modulePath}/Commands/CreateWidgetCommand.php");
        $this->assertFileExists("{$modulePath}/Handlers/CreateWidgetHandler.php");

        $commandContent = $files->get("{$modulePath}/Commands/CreateWidgetCommand.php");
        $this->assertStringContainsString('final readonly class CreateWidgetCommand implements Command', $commandContent);

        $handlerContent = $files->get("{$modulePath}/Handlers/CreateWidgetHandler.php");
        $this->assertStringContainsString('assert($command instanceof CreateWidgetCommand);', $handlerContent);

        $providerContent = $files->get("{$modulePath}/Providers/WidgetServiceProvider.php");
        $this->assertStringContainsString('$commandBus->register('.self::CREATE_REGISTRATION.');', $providerContent);
    }

    public function test_it_is_idempotent_on_a_second_run(): void
    {
        $this->scaffoldWidgetModule();

        $options = ['module' => 'Widget', 'name' => 'CreateWidget'];
        $this->artisan('make:cqrs-command', $options)->assertExitCode(0);
        $this->artisan('make:cqrs-command', $options)->assertExitCode(0);

        $files = new Filesystem;
        $providerContent = $files->get(app_path('Modules/Widget/Providers/WidgetServiceProvider.php'));

        $this->assertSame(1, substr_count($providerContent, self::CREATE_REGISTRATION));
    }

    public function test_it_stacks_multiple_registrations_in_order(): void
    {
        $this->scaffoldWidgetModule();

        $this->artisan('make:cqrs-command', ['module' => 'Widget', 'name' => 'CreateWidget'])->assertExitCode(0);
        $this->artisan('make:cqrs-command', ['module' => 'Widget', 'name' => 'DeleteWidget'])->assertExitCode(0);

        $providerContent = (new Filesystem)->get(app_path('Modules/Widget/Providers/WidgetServiceProvider.php'));

        $this->assertStringContainsString(self::CREATE_REGISTRATION, $providerContent);
        $this->assertStringContainsString(self::DELETE_REGISTRATION, $providerContent);
        $this->assertGreaterThan(
            strpos($providerContent, self::CREATE_REGISTRATION),
            strpos($providerContent, self::DELETE_REGISTRATION),
        );
    }

    public function test_no_register_flag_skips_provider_mutation(): void
    {
        $this->scaffoldWidgetModule();

        $this->artisan('make:cqrs-command', [
            'module' => 'Widget',
            'name' => 'CreateWidget',
            '--no-register' => true,
        ])->assertExitCode(0);

        $providerContent = (new Filesystem)->get(app_path('Modules/Widget/Providers/WidgetServiceProvider.php'));

        $this->assertStringNotContainsString(self::CREATE_REGISTRATION, $providerContent);
    }

    public function test_it_aborts_without_corrupting_the_provider_when_register_handlers_shape_is_unexpected(): void
    {
        $this->scaffoldWidgetModule();

        $providerPath = app_path('Modules/Widget/Providers/WidgetServiceProvider.php');
        $files = new Filesystem;
        $original = $files->get($providerPath);

        // remplace registerHandlers() par une forme mecconnaissable (aucun
        // ancrage attendu) sans casser le PHP.
        $mutated = preg_replace(
            '/private function registerHandlers\(\): void\s*\{.*?\n    \}/s',
            "private function registerHandlers(): void\n    {\n        // reecrit a la main, plus d'ancrage reconnu\n    }",
            $original,
        );
        $files->put($providerPath, $mutated);

        $this->artisan('make:cqrs-command', ['module' => 'Widget', 'name' => 'CreateWidget'])
            ->assertExitCode(1);

        $this->assertSame($mutated, $files->get($providerPath));
    }
}
