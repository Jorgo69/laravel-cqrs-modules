<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Commande artisan make:cqrs-query : ajoute une Query+Handler a un module existant.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Jorgo69\LaravelCqrsModules\Commands\Concerns\GeneratesModulePiece;
use Jorgo69\LaravelCqrsModules\Support\GuardedFileMutator;
use Jorgo69\LaravelCqrsModules\Support\StubRenderer;

final class MakeCqrsQueryCommand extends Command
{
    use GeneratesModulePiece;

    protected $signature = 'make:cqrs-query
        {module : Module existant, ex: Widget}
        {name : Nom de base sans suffixe, ex: ListWidgets}
        {--register : Ajouter a registerHandlers() (defaut)}
        {--no-register : Ne pas modifier registerHandlers()}';

    protected $description = 'Ajoute une Query+Handler dans un module CQRS existant';

    public function handle(Filesystem $files, StubRenderer $renderer, GuardedFileMutator $mutator): int
    {
        return $this->generatePiece(
            $files,
            $renderer,
            $mutator,
            moduleName: (string) $this->argument('module'),
            baseName: (string) $this->argument('name'),
            kind: 'Query',
            register: ! $this->option('no-register'),
        );
    }
}
