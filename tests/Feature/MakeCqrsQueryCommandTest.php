<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Tests de la commande make:cqrs-query.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Jorgo69\LaravelCqrsModules\Tests\TestCase;

final class MakeCqrsQueryCommandTest extends TestCase
{
    private const CREATE_COMMAND_REGISTRATION = '\App\Modules\Widget\Commands\CreateWidgetCommand::class, \App\Modules\Widget\Handlers\CreateWidgetHandler::class';

    private const LIST_QUERY_REGISTRATION = '\App\Modules\Widget\Queries\ListWidgetsQuery::class, \App\Modules\Widget\Handlers\ListWidgetsHandler::class';

    private function scaffoldWidgetModule(): void
    {
        $this->artisan('make:module', ['name' => 'Widget', '--no-test' => true, '--no-interaction' => true])
            ->assertExitCode(0);
    }

    public function test_it_fails_when_the_module_does_not_exist(): void
    {
        $this->artisan('make:cqrs-query', ['module' => 'Ghost', 'name' => 'ListGhosts'])
            ->assertExitCode(1);
    }

    public function test_it_adds_a_query_and_handler_and_registers_it(): void
    {
        $this->scaffoldWidgetModule();

        $this->artisan('make:cqrs-query', ['module' => 'Widget', 'name' => 'ListWidgets'])
            ->assertExitCode(0);

        $files = new Filesystem;
        $modulePath = app_path('Modules/Widget');

        $this->assertFileExists("{$modulePath}/Queries/ListWidgetsQuery.php");
        $this->assertFileExists("{$modulePath}/Handlers/ListWidgetsHandler.php");

        $queryContent = $files->get("{$modulePath}/Queries/ListWidgetsQuery.php");
        $this->assertStringContainsString('final readonly class ListWidgetsQuery implements Query', $queryContent);

        $handlerContent = $files->get("{$modulePath}/Handlers/ListWidgetsHandler.php");
        $this->assertStringContainsString('assert($query instanceof ListWidgetsQuery);', $handlerContent);

        $providerContent = $files->get("{$modulePath}/Providers/WidgetServiceProvider.php");
        $this->assertStringContainsString('$queryBus->register('.self::LIST_QUERY_REGISTRATION.');', $providerContent);
    }

    public function test_it_is_idempotent_on_a_second_run(): void
    {
        $this->scaffoldWidgetModule();

        $options = ['module' => 'Widget', 'name' => 'ListWidgets'];
        $this->artisan('make:cqrs-query', $options)->assertExitCode(0);
        $this->artisan('make:cqrs-query', $options)->assertExitCode(0);

        $providerContent = (new Filesystem)->get(app_path('Modules/Widget/Providers/WidgetServiceProvider.php'));

        $this->assertSame(1, substr_count($providerContent, self::LIST_QUERY_REGISTRATION));
    }

    public function test_command_and_query_registrations_coexist_independently(): void
    {
        $this->scaffoldWidgetModule();

        $this->artisan('make:cqrs-command', ['module' => 'Widget', 'name' => 'CreateWidget'])->assertExitCode(0);
        $this->artisan('make:cqrs-query', ['module' => 'Widget', 'name' => 'ListWidgets'])->assertExitCode(0);

        $providerContent = (new Filesystem)->get(app_path('Modules/Widget/Providers/WidgetServiceProvider.php'));

        $this->assertStringContainsString(self::CREATE_COMMAND_REGISTRATION, $providerContent);
        $this->assertStringContainsString(self::LIST_QUERY_REGISTRATION, $providerContent);
    }

    public function test_no_register_flag_skips_provider_mutation(): void
    {
        $this->scaffoldWidgetModule();

        $this->artisan('make:cqrs-query', [
            'module' => 'Widget',
            'name' => 'ListWidgets',
            '--no-register' => true,
        ])->assertExitCode(0);

        $providerContent = (new Filesystem)->get(app_path('Modules/Widget/Providers/WidgetServiceProvider.php'));

        $this->assertStringNotContainsString(self::LIST_QUERY_REGISTRATION, $providerContent);
    }
}
