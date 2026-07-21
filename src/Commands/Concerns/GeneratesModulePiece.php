<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Logique partagee entre make:cqrs-command et make:cqrs-query.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Commands\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Jorgo69\LaravelCqrsModules\Support\GeneratedFile;
use Jorgo69\LaravelCqrsModules\Support\GuardedFileMutator;
use Jorgo69\LaravelCqrsModules\Support\ModuleNameResolver;
use Jorgo69\LaravelCqrsModules\Support\MutationResult;
use Jorgo69\LaravelCqrsModules\Support\StubRenderer;

/**
 * Logique partagee entre `make:cqrs-command` et `make:cqrs-query` : ajouter
 * une seule paire piece+Handler dans un module EXISTANT, avec enregistrement
 * optionnel dans `registerHandlers()`. `$kind` vaut toujours 'Command' ou
 * 'Query' — tout le reste (dossier, namespace, classe du Bus) en decoule.
 */
trait GeneratesModulePiece
{
    private function generatePiece(
        Filesystem $files,
        StubRenderer $renderer,
        GuardedFileMutator $mutator,
        string $moduleName,
        string $baseName,
        string $kind,
        bool $register,
    ): int {
        $module = new ModuleNameResolver($moduleName);

        if (! $files->exists($module->providerPath())) {
            $this->components->error(
                "Provider introuvable : {$module->providerPath()}. Lance d'abord : php artisan make:module {$module->studly}",
            );

            return self::FAILURE;
        }

        $base = Str::studly($baseName);
        $handlerSuffix = (string) config('cqrs-modules.handlers.suffix');
        $pieceClass = "{$base}{$kind}";
        $handlerClass = "{$base}{$handlerSuffix}";
        $directory = $kind === 'Command' ? 'Commands' : 'Queries';
        $pieceNamespace = "{$module->namespace}\\{$directory}";
        $handlerNamespace = "{$module->namespace}\\Handlers";
        $busNamespace = (string) config('cqrs-modules.bus.namespace');
        $stubPrefix = strtolower($kind);

        $this->reportGenerated($renderer->render(
            "piece/{$stubPrefix}.stub",
            "{$module->path}/{$directory}/{$pieceClass}.php",
            [
                '{{ namespace }}' => $pieceNamespace,
                '{{ class }}' => $pieceClass,
                '{{ busNamespace }}' => $busNamespace,
            ],
        ));

        $this->reportGenerated($renderer->render(
            "piece/{$stubPrefix}-handler.stub",
            "{$module->path}/Handlers/{$handlerClass}.php",
            [
                '{{ namespace }}' => $handlerNamespace,
                '{{ class }}' => $handlerClass,
                "{{ {$stubPrefix}Namespace }}" => $pieceNamespace,
                "{{ {$stubPrefix}Class }}" => $pieceClass,
                '{{ busNamespace }}' => $busNamespace,
            ],
        ));

        if (! $register) {
            return self::SUCCESS;
        }

        // FQCN complet (namespace explicite) : le Provider n'importe pas ces
        // classes via `use`, un nom court s'y resoudrait dans le mauvais
        // namespace (celui du Provider, pas celui de la piece).
        $pieceFqcn = "{$pieceNamespace}\\{$pieceClass}";
        $handlerFqcn = "{$handlerNamespace}\\{$handlerClass}";

        return $this->registerInHandlers($mutator, $module, $kind, $pieceFqcn, $handlerFqcn) ? self::SUCCESS : self::FAILURE;
    }

    private function registerInHandlers(
        GuardedFileMutator $mutator,
        ModuleNameResolver $module,
        string $kind,
        string $pieceFqcn,
        string $handlerFqcn,
    ): bool {
        $busConfigKey = $kind === 'Command' ? 'command_bus' : 'query_bus';
        $busClass = (string) config("cqrs-modules.bus.{$busConfigKey}_class");
        $busVariable = (string) config("cqrs-modules.bus.{$busConfigKey}_variable");
        $registerMethod = (string) config('cqrs-modules.provider.register_handlers_method');

        $uniqueMarker = "\\{$pieceFqcn}::class, \\{$handlerFqcn}::class";
        $registerLine = "        \${$busVariable}->register(\\{$pieceFqcn}::class, \\{$handlerFqcn}::class);\n";

        $quotedVariable = preg_quote($busVariable, '/');
        $quotedClass = preg_quote($busClass, '/');

        $result = $mutator->insertIntoMethodBody(
            $module->providerPath(),
            $registerMethod,
            $uniqueMarker,
            $registerLine,
            [
                "/\\\${$quotedVariable}->register\\([^;]*\\);\\r?\\n/",
                "/\\\${$quotedVariable} = \\\$this->app->make\\({$quotedClass}::class\\);\\r?\\n/",
            ],
        );

        match ($result) {
            MutationResult::Inserted => $this->components->info(
                "Enregistre dans {$module->providerClass()} : {$pieceFqcn} -> {$handlerFqcn}",
            ),
            MutationResult::AlreadyPresent => $this->components->warn(
                "Deja enregistre : {$pieceFqcn} -> {$handlerFqcn}",
            ),
            MutationResult::AbortedShapeMismatch, MutationResult::AbortedFileMissing => $this->components->error(
                "Impossible d'enregistrer automatiquement dans {$module->providerClass()}. Ajoute cette ligne toi-meme dans {$registerMethod}() : \${$busVariable}->register(\\{$pieceFqcn}::class, \\{$handlerFqcn}::class);",
            ),
        };

        return $result->isSuccessful();
    }

    private function reportGenerated(GeneratedFile $generated): void
    {
        $generated->wasCreated
            ? $this->components->info("Cree : {$generated->path}")
            : $this->components->warn("Deja present, ignore : {$generated->path}");
    }
}
