<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Enum des issues possibles d'une mutation de fichier guidee.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Support;

enum MutationResult
{
    case Inserted;
    case AlreadyPresent;
    case AbortedFileMissing;
    case AbortedShapeMismatch;

    public function isSuccessful(): bool
    {
        return match ($this) {
            self::Inserted, self::AlreadyPresent => true,
            self::AbortedFileMissing, self::AbortedShapeMismatch => false,
        };
    }
}
