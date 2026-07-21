<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Test bout-en-bout : install + module + dispatch reel + route reelle.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Jorgo69\LaravelCqrsModules\Tests\TestCase;

final class EndToEndDispatchTest extends TestCase
{
    public function test_install_then_module_then_command_then_real_dispatch(): void
    {
        $this->artisan('cqrs-modules:install', ['--no-interaction' => true])->assertExitCode(0);
        $this->artisan('make:module', ['name' => 'Widget', '--no-test' => true, '--no-interaction' => true])
            ->assertExitCode(0);
        $this->artisan('make:cqrs-command', ['module' => 'Widget', 'name' => 'CreateWidget'])
            ->assertExitCode(0);
        $this->artisan('make:cqrs-query', ['module' => 'Widget', 'name' => 'ListWidgets'])
            ->assertExitCode(0);

        // Complete la logique metier laissee en placeholder par le stub, pour
        // avoir un round-trip reel (pas juste un fichier qui existe).
        $files = new Filesystem;
        $handlerPath = app_path('Modules/Widget/Handlers/CreateWidgetHandler.php');
        $files->put($handlerPath, str_replace(
            "// TODO: implementer la logique metier.\n\n        return null;",
            "return 'created';",
            $files->get($handlerPath),
        ));

        $queryHandlerPath = app_path('Modules/Widget/Handlers/ListWidgetsHandler.php');
        $files->put($queryHandlerPath, str_replace(
            "// TODO: implementer la lecture.\n\n        return null;",
            "return ['created'];",
            $files->get($queryHandlerPath),
        ));

        // Sous WithWorkbench, testbench lit workbench/bootstrap/providers.php
        // pour l'auto-registration des providers, pas bootstrap/providers.php
        // du squelette (que notre mutateur a bien modifie — verifie ailleurs
        // via son contenu). On enregistre donc ici directement les Providers
        // generes, pour prouver que LEUR registerHandlers() cable reellement
        // le Bus — le vrai test de l'auto-registration en conditions reelles
        // se fait via le smoke test manuel sur une appli Laravel sœur.
        $this->app->register('App\\Shared\\Bus\\BusServiceProvider');
        $this->app->register('App\\Modules\\Widget\\Providers\\WidgetServiceProvider');

        $commandBusClass = 'App\\Shared\\Bus\\CommandBus';
        $queryBusClass = 'App\\Shared\\Bus\\QueryBus';
        $commandClass = 'App\\Modules\\Widget\\Commands\\CreateWidgetCommand';
        $queryClass = 'App\\Modules\\Widget\\Queries\\ListWidgetsQuery';

        $commandBus = $this->app->make($commandBusClass);
        $result = $commandBus->dispatch(new $commandClass);
        $this->assertSame('created', $result);

        $queryBus = $this->app->make($queryBusClass);
        $queryResult = $queryBus->dispatch(new $queryClass);
        $this->assertSame(['created'], $queryResult);
    }

    public function test_generated_provider_registers_the_module_route_prefix(): void
    {
        $this->artisan('make:module', ['name' => 'Widget', '--no-test' => true, '--no-interaction' => true])
            ->assertExitCode(0);

        // Le squelette genere un groupe de routes vide (`//`) — on y ajoute une
        // route triviale pour verifier que le prefix/middleware du groupe
        // fonctionne reellement une fois charge, pas seulement que le fichier existe.
        $files = new Filesystem;
        $routesPath = app_path('Modules/Widget/routes.php');
        $files->put($routesPath, str_replace(
            '    //',
            "    Route::get('/ping', fn () => 'pong');",
            $files->get($routesPath),
        ));

        $this->app->register('App\\Modules\\Widget\\Providers\\WidgetServiceProvider');

        $uris = collect($this->app['router']->getRoutes())->map(fn ($route) => $route->uri());

        $this->assertTrue($uris->contains(fn (string $uri): bool => str_starts_with($uri, 'api/widget')));
    }
}
