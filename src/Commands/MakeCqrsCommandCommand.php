<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Commande artisan make:cqrs-command : ajoute une Command+Handler a un module existant.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Jorgo69\LaravelCqrsModules\Commands\Concerns\GeneratesModulePiece;
use Jorgo69\LaravelCqrsModules\Support\GuardedFileMutator;
use Jorgo69\LaravelCqrsModules\Support\StubRenderer;

final class MakeCqrsCommandCommand extends Command
{
    use GeneratesModulePiece;

    protected $signature = 'make:cqrs-command
        {module : Module existant, ex: Widget}
        {name : Nom de base sans suffixe, ex: CreateWidget}
        {--register : Ajouter a registerHandlers() (defaut)}
        {--no-register : Ne pas modifier registerHandlers()}';

    protected $description = 'Ajoute une Command+Handler dans un module CQRS existant';

    /**
     * Délègue à `GeneratesModulePiece::generatePiece()` avec `kind: 'Command'`
     * — voir ce trait pour le détail (le module cible doit déjà exister, sinon
     * échec explicite invitant à lancer `make:module` d'abord).
     *
     * @return int self::SUCCESS ou self::FAILURE.
     */
    public function handle(Filesystem $files, StubRenderer $renderer, GuardedFileMutator $mutator): int
    {
        return $this->generatePiece(
            $files,
            $renderer,
            $mutator,
            moduleName: (string) $this->argument('module'),
            baseName: (string) $this->argument('name'),
            kind: 'Command',
            register: ! $this->option('no-register'),
        );
    }
}
