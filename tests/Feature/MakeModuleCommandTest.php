<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Tests de la commande make:module.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Jorgo69\LaravelCqrsModules\Tests\TestCase;

final class MakeModuleCommandTest extends TestCase
{
    public function test_it_scaffolds_a_module_and_registers_the_provider(): void
    {
        $this->artisan('make:module', ['name' => 'Widget', '--no-test' => true, '--no-interaction' => true])
            ->assertExitCode(0);

        $files = new Filesystem;
        $modulePath = app_path('Modules/Widget');

        foreach (['Commands', 'Queries', 'Handlers', 'Providers', 'DTOs', 'Enums', 'Models', 'Controllers', 'Requests', 'Database/Migrations'] as $directory) {
            $this->assertDirectoryExists("{$modulePath}/{$directory}");
        }

        $this->assertFileExists("{$modulePath}/Providers/WidgetServiceProvider.php");
        $this->assertFileExists("{$modulePath}/routes.php");

        $providerContent = $files->get("{$modulePath}/Providers/WidgetServiceProvider.php");
        $this->assertStringContainsString('namespace App\Modules\Widget\Providers;', $providerContent);
        $this->assertStringContainsString('class WidgetServiceProvider extends ServiceProvider', $providerContent);
        $this->assertStringContainsString('registerHandlers', $providerContent);

        $routesContent = $files->get("{$modulePath}/routes.php");
        $this->assertStringContainsString("Route::prefix('api/widget')", $routesContent);
        $this->assertStringContainsString("->middleware(['api'])", $routesContent);

        $bootstrap = $files->get(base_path('bootstrap/providers.php'));
        $this->assertStringContainsString('App\Modules\Widget\Providers\WidgetServiceProvider::class', $bootstrap);
    }

    public function test_it_is_idempotent_on_a_second_run(): void
    {
        $options = ['name' => 'Widget', '--no-test' => true, '--no-interaction' => true];

        $this->artisan('make:module', $options)->assertExitCode(0);
        $this->artisan('make:module', $options)->assertExitCode(0);

        $files = new Filesystem;
        $bootstrap = $files->get(base_path('bootstrap/providers.php'));

        $this->assertSame(
            1,
            substr_count($bootstrap, 'App\Modules\Widget\Providers\WidgetServiceProvider::class'),
            'Le Provider ne doit apparaitre qu une seule fois apres deux generations.',
        );
    }

    public function test_it_aborts_without_corrupting_bootstrap_providers_when_shape_is_unexpected(): void
    {
        $files = new Filesystem;
        $unexpected = "<?php\n\n// forme inattendue, pas un simple return [ ... ];\n\$providers = [];\n\$providers[] = 'x';\n";
        $files->put(base_path('bootstrap/providers.php'), $unexpected);

        $this->artisan('make:module', ['name' => 'Widget', '--no-test' => true, '--no-interaction' => true])
            ->assertExitCode(1);

        $this->assertSame($unexpected, $files->get(base_path('bootstrap/providers.php')));
    }

    public function test_no_register_flag_skips_bootstrap_mutation(): void
    {
        $this->artisan('make:module', [
            'name' => 'Widget',
            '--no-test' => true,
            '--no-register' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $files = new Filesystem;
        $bootstrap = $files->get(base_path('bootstrap/providers.php'));

        $this->assertStringNotContainsString('WidgetServiceProvider', $bootstrap);
    }

    public function test_dirs_option_restricts_optional_directories(): void
    {
        $this->artisan('make:module', [
            'name' => 'Widget',
            '--dirs' => 'Models',
            '--no-test' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $modulePath = app_path('Modules/Widget');

        $this->assertDirectoryExists("{$modulePath}/Models");
        $this->assertDirectoryDoesNotExist("{$modulePath}/DTOs");
        $this->assertDirectoryDoesNotExist("{$modulePath}/Controllers");
    }

    public function test_test_flag_generates_a_feature_test_stub(): void
    {
        $this->artisan('make:module', ['name' => 'Widget', '--test' => true, '--no-interaction' => true])
            ->assertExitCode(0);

        $this->assertFileExists(base_path('tests/Feature/Widget/WidgetModuleTest.php'));
    }

    public function test_default_prompt_config_does_not_generate_a_test_when_non_interactive_and_no_flag_given(): void
    {
        $this->artisan('make:module', ['name' => 'Widget', '--no-interaction' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist(base_path('tests/Feature/Widget/WidgetModuleTest.php'));
    }
}
