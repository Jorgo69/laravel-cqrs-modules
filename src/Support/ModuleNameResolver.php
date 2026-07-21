<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Resout casing/namespace/chemin d'un module a partir de son nom brut.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Support;

use Illuminate\Support\Str;

final readonly class ModuleNameResolver
{
    public string $studly;

    public string $kebab;

    public string $namespace;

    public string $path;

    public function __construct(string $rawName)
    {
        $this->studly = Str::studly($rawName);
        $this->kebab = Str::kebab($this->studly);
        $this->namespace = rtrim((string) config('cqrs-modules.modules.namespace'), '\\').'\\'.$this->studly;
        $this->path = rtrim((string) config('cqrs-modules.modules.path'), '/').'/'.$this->studly;
    }

    public function providerClass(): string
    {
        return "{$this->studly}ServiceProvider";
    }

    public function providerFqcn(): string
    {
        return "{$this->namespace}\\Providers\\{$this->providerClass()}";
    }

    public function providerPath(): string
    {
        return "{$this->path}/Providers/{$this->providerClass()}.php";
    }
}
