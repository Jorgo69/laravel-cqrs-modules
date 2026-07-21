<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: TestCase de base (testbench + workbench) pour les tests du package.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Tests;

use Illuminate\Filesystem\Filesystem;
use Jorgo69\LaravelCqrsModules\LaravelCqrsModulesServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    private static bool $skeletonAutoloaderRegistered = false;

    protected function setUp(): void
    {
        // Le squelette testbench (vendor/orchestra/testbench-core/laravel) est
        // partage entre tests : on repart d'un bootstrap/providers.php et d'un
        // app/Modules propres AVANT que parent::setUp() ne boote l'app (qui lit
        // bootstrap/providers.php) — trop tard pour le faire apres.
        $basePath = static::applicationBasePath();
        $files = new Filesystem;
        $files->put("{$basePath}/bootstrap/providers.php", "<?php\n\nreturn [\n    //\n];\n");
        $files->deleteDirectory("{$basePath}/app/Modules");
        $files->deleteDirectory("{$basePath}/app/Shared");
        $files->deleteDirectory("{$basePath}/tests/Feature/Widget");

        $this->registerSkeletonAutoloader($basePath);

        parent::setUp();
    }

    /**
     * Le composer.json du squelette testbench declare `App\` -> `app/`, mais
     * ce composer.json n'est jamais fusionne dans le vendor/autoload.php du
     * package (namespace `App\` deja pris ailleurs, ex: laravel/pint). Sans
     * ca, les classes generees par nos commandes (App\Modules\...) ne
     * seraient jamais trouvees par les tests qui les instancient reellement.
     */
    private function registerSkeletonAutoloader(string $basePath): void
    {
        if (self::$skeletonAutoloaderRegistered) {
            return;
        }

        spl_autoload_register(static function (string $class) use ($basePath): void {
            if (! str_starts_with($class, 'App\\')) {
                return;
            }

            $path = $basePath.'/app/'.str_replace('\\', '/', substr($class, strlen('App\\'))).'.php';

            if (is_file($path)) {
                require $path;
            }
        });

        self::$skeletonAutoloaderRegistered = true;
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelCqrsModulesServiceProvider::class,
        ];
    }
}
