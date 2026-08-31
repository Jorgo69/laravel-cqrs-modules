<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: ServiceProvider du package : declare la config, les commandes, et l'installeur (Bus + enregistrement Provider).
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules;

use Jorgo69\LaravelCqrsModules\Commands\MakeCqrsCommandCommand;
use Jorgo69\LaravelCqrsModules\Commands\MakeCqrsQueryCommand;
use Jorgo69\LaravelCqrsModules\Commands\MakeModuleCommand;
use Jorgo69\LaravelCqrsModules\Support\GuardedFileMutator;
use Jorgo69\LaravelCqrsModules\Support\MutationResult;
use Jorgo69\LaravelCqrsModules\Support\StubRenderer;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LaravelCqrsModulesServiceProvider extends PackageServiceProvider
{
    /**
     * Déclaration spatie/laravel-package-tools : fichier de config publiable,
     * les 3 commandes Artisan du package, et l'installeur `cqrs-modules:install`
     * (publie la config PUIS copie le Bus dans l'app consommatrice).
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('cqrs-modules')
            ->hasConfigFile()
            ->hasCommands([
                MakeModuleCommand::class,
                MakeCqrsCommandCommand::class,
                MakeCqrsQueryCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->endWith(function (InstallCommand $command): void {
                        $this->installBusInfrastructure($command);
                    });
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(StubRenderer::class);
        $this->app->singleton(GuardedFileMutator::class);
    }

    public function packageBooted(): void
    {
        $this->publishes([
            __DIR__.'/../stubs' => config('cqrs-modules.stubs.publish_path'),
        ], 'cqrs-modules-stubs');
    }

    /**
     * Copie les 7 fichiers du Bus minimal (Command/Query/CommandHandler/
     * QueryHandler/CommandBus/QueryBus/BusServiceProvider) chez le
     * consommateur, sous le namespace configuré — jamais régénérés ensuite,
     * le consommateur en devient pleinement propriétaire dès cet instant.
     */
    private function installBusInfrastructure(InstallCommand $command): void
    {
        $renderer = $this->app->make(StubRenderer::class);

        $namespace = (string) config('cqrs-modules.bus.namespace');
        $path = rtrim((string) config('cqrs-modules.bus.path'), '/');
        $replacements = ['{{ namespace }}' => $namespace];

        $files = [
            'command.stub' => 'Command.php',
            'query.stub' => 'Query.php',
            'command-handler.stub' => 'CommandHandler.php',
            'query-handler.stub' => 'QueryHandler.php',
            'command-bus.stub' => config('cqrs-modules.bus.command_bus_class').'.php',
            'query-bus.stub' => config('cqrs-modules.bus.query_bus_class').'.php',
            'bus-service-provider.stub' => config('cqrs-modules.bus.service_provider').'.php',
        ];

        foreach ($files as $stub => $targetFilename) {
            $generated = $renderer->render("bus/{$stub}", "{$path}/{$targetFilename}", $replacements);
            $generated->wasCreated
                ? $command->info("Cree : {$generated->path}")
                : $command->warn("Deja present, ignore : {$generated->path}");
        }

        if (! config('cqrs-modules.provider.auto_register_in_bootstrap')) {
            return;
        }

        $this->registerBusProviderInBootstrap($command, $namespace);
    }

    private function registerBusProviderInBootstrap(InstallCommand $command, string $namespace): void
    {
        $mutator = $this->app->make(GuardedFileMutator::class);
        $providerFqcn = $namespace.'\\'.config('cqrs-modules.bus.service_provider');

        $result = $mutator->insertBeforeArrayClose(
            (string) config('cqrs-modules.provider.bootstrap_providers_path'),
            "{$providerFqcn}::class",
            "{$providerFqcn}::class,",
        );

        match ($result) {
            MutationResult::Inserted => $command->info("Enregistre dans bootstrap/providers.php : {$providerFqcn}"),
            MutationResult::AlreadyPresent => $command->warn("Deja enregistre dans bootstrap/providers.php : {$providerFqcn}"),
            MutationResult::AbortedShapeMismatch, MutationResult::AbortedFileMissing => $command->error(
                "Impossible d'enregistrer automatiquement. Ajoute cette ligne toi-meme dans bootstrap/providers.php : {$providerFqcn}::class,",
            ),
        };
    }
}
