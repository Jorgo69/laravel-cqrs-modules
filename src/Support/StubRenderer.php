<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Resout et rend un stub (override projet ou stub embarque) vers un fichier cible.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Support;

use Illuminate\Filesystem\Filesystem;

final class StubRenderer
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * @param  array<string, string>  $replacements
     */
    public function render(string $relativeStubPath, string $targetPath, array $replacements): GeneratedFile
    {
        if ($this->files->exists($targetPath)) {
            return new GeneratedFile($targetPath, wasCreated: false);
        }

        $content = strtr($this->files->get($this->resolveStubPath($relativeStubPath)), $replacements);

        $this->files->ensureDirectoryExists(dirname($targetPath));
        $this->files->put($targetPath, $content);

        return new GeneratedFile($targetPath, wasCreated: true);
    }

    private function resolveStubPath(string $relativeStubPath): string
    {
        $projectOverride = rtrim((string) config('cqrs-modules.stubs.publish_path'), '/')."/{$relativeStubPath}";

        if ($this->files->exists($projectOverride)) {
            return $projectOverride;
        }

        return $this->packageStubsPath()."/{$relativeStubPath}";
    }

    private function packageStubsPath(): string
    {
        return dirname(__DIR__, 2).'/stubs';
    }
}
