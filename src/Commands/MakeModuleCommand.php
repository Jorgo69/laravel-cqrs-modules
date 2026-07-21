<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Commande artisan make:module : scaffold un module CQRS complet.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Jorgo69\LaravelCqrsModules\Support\GuardedFileMutator;
use Jorgo69\LaravelCqrsModules\Support\ModuleNameResolver;
use Jorgo69\LaravelCqrsModules\Support\MutationResult;
use Jorgo69\LaravelCqrsModules\Support\StubRenderer;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

final class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : Nom du module en StudlyCase, ex: Widget}
        {--dirs= : CSV des dossiers optionnels a creer (ecrase le prompt/la config)}
        {--no-register : Ne pas enregistrer le Provider dans bootstrap/providers.php}
        {--test : Generer un test Feature de depart}
        {--no-test : Ne pas generer de test Feature}';

    protected $description = 'Scaffold un module CQRS (Commands, Queries, Handlers, DTOs, Enums, Models, Controllers, Requests, Providers, Database)';

    public function handle(Filesystem $files, StubRenderer $renderer, GuardedFileMutator $mutator): int
    {
        $resolver = new ModuleNameResolver((string) $this->argument('name'));

        $this->createDirectories($files, $resolver);
        $this->components->info("Dossiers du module [{$resolver->studly}] prets.");

        $this->renderProvider($renderer, $resolver);
        $this->renderRoutes($renderer, $resolver);

        if ($this->shouldGenerateTest()) {
            $this->renderFeatureTest($renderer, $resolver);
        }

        if ($this->option('no-register') || ! config('cqrs-modules.provider.auto_register_in_bootstrap')) {
            return self::SUCCESS;
        }

        return $this->registerProviderInBootstrap($mutator, $resolver) ? self::SUCCESS : self::FAILURE;
    }

    private function createDirectories(Filesystem $files, ModuleNameResolver $resolver): void
    {
        foreach ((array) config('cqrs-modules.modules.directories.required') as $directory) {
            $files->ensureDirectoryExists("{$resolver->path}/{$directory}");
        }

        foreach ($this->selectedOptionalDirectories() as $directory) {
            $files->ensureDirectoryExists("{$resolver->path}/{$directory}");
        }
    }

    /**
     * @return list<string>
     */
    private function selectedOptionalDirectories(): array
    {
        /** @var array<string, bool> $optional */
        $optional = (array) config('cqrs-modules.modules.directories.optional');

        if ($this->option('dirs') !== null) {
            $requested = array_map('trim', explode(',', (string) $this->option('dirs')));

            return array_values(array_intersect($requested, array_keys($optional)));
        }

        if ($this->input->isInteractive()) {
            return multiselect(
                label: 'Quels dossiers optionnels inclure ?',
                options: array_keys($optional),
                default: array_keys(array_filter($optional)),
            );
        }

        return array_keys(array_filter($optional));
    }

    private function shouldGenerateTest(): bool
    {
        if ($this->option('test')) {
            return true;
        }

        if ($this->option('no-test')) {
            return false;
        }

        $config = config('cqrs-modules.tests.feature_stub');

        if ($config === 'prompt') {
            return $this->input->isInteractive()
                ? confirm('Generer un test Feature de depart pour ce module ?', default: true)
                : false;
        }

        return (bool) $config;
    }

    private function renderProvider(StubRenderer $renderer, ModuleNameResolver $resolver): void
    {
        $generated = $renderer->render(
            'module/service-provider.stub',
            $resolver->providerPath(),
            [
                '{{ namespace }}' => "{$resolver->namespace}\\Providers",
                '{{ class }}' => $resolver->providerClass(),
                '{{ busNamespace }}' => (string) config('cqrs-modules.bus.namespace'),
                '{{ busCommandClass }}' => (string) config('cqrs-modules.bus.command_bus_class'),
                '{{ busQueryClass }}' => (string) config('cqrs-modules.bus.query_bus_class'),
                '{{ busCommandVariable }}' => (string) config('cqrs-modules.bus.command_bus_variable'),
                '{{ busQueryVariable }}' => (string) config('cqrs-modules.bus.query_bus_variable'),
                '{{ registerRoutesMethod }}' => (string) config('cqrs-modules.provider.register_routes_method'),
                '{{ registerHandlersMethod }}' => (string) config('cqrs-modules.provider.register_handlers_method'),
            ],
        );

        $this->reportGenerated($generated->path, $generated->wasCreated);
    }

    private function renderRoutes(StubRenderer $renderer, ModuleNameResolver $resolver): void
    {
        $middleware = (array) config('cqrs-modules.routes.middleware');
        $middlewareExpression = '['.implode(', ', array_map(fn (string $m): string => "'{$m}'", $middleware)).']';
        $routePrefix = str_replace('{kebab}', $resolver->kebab, (string) config('cqrs-modules.routes.prefix'));

        $generated = $renderer->render(
            'module/routes.stub',
            "{$resolver->path}/routes.php",
            [
                '{{ routePrefix }}' => $routePrefix,
                '{{ middlewareExpression }}' => $middlewareExpression,
            ],
        );

        $this->reportGenerated($generated->path, $generated->wasCreated);
    }

    private function renderFeatureTest(StubRenderer $renderer, ModuleNameResolver $resolver): void
    {
        $testClass = "{$resolver->studly}ModuleTest";
        $testPath = rtrim((string) config('cqrs-modules.tests.path'), '/')."/{$resolver->studly}/{$testClass}.php";

        $generated = $renderer->render(
            'module/feature-test.stub',
            $testPath,
            [
                '{{ namespace }}' => "Tests\\Feature\\{$resolver->studly}",
                '{{ class }}' => $testClass,
            ],
        );

        $this->reportGenerated($generated->path, $generated->wasCreated);
    }

    private function registerProviderInBootstrap(GuardedFileMutator $mutator, ModuleNameResolver $resolver): bool
    {
        $providerFqcn = $resolver->providerFqcn();

        $result = $mutator->insertBeforeArrayClose(
            (string) config('cqrs-modules.provider.bootstrap_providers_path'),
            "{$providerFqcn}::class",
            "{$providerFqcn}::class,",
        );

        match ($result) {
            MutationResult::Inserted => $this->components->info("Enregistre dans bootstrap/providers.php : {$providerFqcn}"),
            MutationResult::AlreadyPresent => $this->components->warn("Deja enregistre dans bootstrap/providers.php : {$providerFqcn}"),
            MutationResult::AbortedShapeMismatch, MutationResult::AbortedFileMissing => $this->components->error(
                "bootstrap/providers.php n'a pas la forme attendue. Ajoute cette ligne toi-meme : {$providerFqcn}::class,",
            ),
        };

        return $result->isSuccessful();
    }

    private function reportGenerated(string $path, bool $wasCreated): void
    {
        $wasCreated
            ? $this->components->info("Cree : {$path}")
            : $this->components->warn("Deja present, ignore : {$path}");
    }
}
