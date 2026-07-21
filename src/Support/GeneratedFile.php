<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: DTO representant un fichier genere (chemin + a ete cree ou non).
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Support;

final readonly class GeneratedFile
{
    public function __construct(
        public string $path,
        public bool $wasCreated,
    ) {}
}
