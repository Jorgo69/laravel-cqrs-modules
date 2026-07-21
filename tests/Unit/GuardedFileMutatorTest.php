<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Tests unitaires adversariaux du mutateur de fichier guide.
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Jorgo69\LaravelCqrsModules\Support\GuardedFileMutator;
use Jorgo69\LaravelCqrsModules\Support\MutationResult;
use PHPUnit\Framework\TestCase;

final class GuardedFileMutatorTest extends TestCase
{
    private string $fixturePath;

    private GuardedFileMutator $mutator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturePath = sys_get_temp_dir().'/cqrs-modules-mutator-'.uniqid().'.php';
        $this->mutator = new GuardedFileMutator(new Filesystem);
    }

    protected function tearDown(): void
    {
        @unlink($this->fixturePath);

        parent::tearDown();
    }

    private function write(string $contents): void
    {
        file_put_contents($this->fixturePath, $contents);
    }

    private function read(): string
    {
        return file_get_contents($this->fixturePath);
    }

    public function test_it_inserts_before_array_close_in_a_simple_return_array_file(): void
    {
        $this->write("<?php\n\nreturn [\n    Foo::class,\n];\n");

        $result = $this->mutator->insertBeforeArrayClose($this->fixturePath, 'Bar::class', 'Bar::class,');

        $this->assertSame(MutationResult::Inserted, $result);
        $this->assertSame("<?php\n\nreturn [\n    Foo::class,\n    Bar::class,\n];\n", $this->read());
    }

    public function test_it_is_idempotent_via_unique_marker_on_array_close(): void
    {
        $original = "<?php\n\nreturn [\n    Foo::class,\n    Bar::class,\n];\n";
        $this->write($original);

        $result = $this->mutator->insertBeforeArrayClose($this->fixturePath, 'Bar::class', 'Bar::class,');

        $this->assertSame(MutationResult::AlreadyPresent, $result);
        $this->assertSame($original, $this->read());
    }

    public function test_it_aborts_on_array_close_when_file_is_missing(): void
    {
        $result = $this->mutator->insertBeforeArrayClose('/nonexistent/path.php', 'Bar::class', 'Bar::class,');

        $this->assertSame(MutationResult::AbortedFileMissing, $result);
    }

    public function test_it_aborts_on_array_close_when_shape_is_unexpected(): void
    {
        $unexpected = "<?php\n\n\$x = [];\n\$x[] = 'a';\n";
        $this->write($unexpected);

        $result = $this->mutator->insertBeforeArrayClose($this->fixturePath, 'Bar::class', 'Bar::class,');

        $this->assertSame(MutationResult::AbortedShapeMismatch, $result);
        $this->assertSame($unexpected, $this->read());
    }

    public function test_it_inserts_after_the_last_register_call_ignoring_braces_in_strings_and_comments(): void
    {
        $this->write(<<<'PHP'
            <?php

            class WidgetServiceProvider
            {
                private function registerHandlers(): void
                {
                    // un commentaire avec une accolade } piegeuse ici
                    $note = "un texte avec une accolade { dedans";
                    $commandBus = $this->app->make(CommandBus::class);
                    $commandBus->register(FirstCommand::class, FirstHandler::class);
                }
            }

            PHP);

        $result = $this->mutator->insertIntoMethodBody(
            $this->fixturePath,
            'registerHandlers',
            'SecondCommand::class, SecondHandler::class',
            "        \$commandBus->register(SecondCommand::class, SecondHandler::class);\n",
            ['/\$commandBus->register\([^;]*\);\r?\n/'],
        );

        $this->assertSame(MutationResult::Inserted, $result);
        $contents = $this->read();
        $this->assertStringContainsString(
            "\$commandBus->register(FirstCommand::class, FirstHandler::class);\n        \$commandBus->register(SecondCommand::class, SecondHandler::class);",
            $contents,
        );
        // le commentaire et la string piegeuse n'ont pas ete alterees.
        $this->assertStringContainsString('une accolade } piegeuse', $contents);
        $this->assertStringContainsString('une accolade { dedans', $contents);
    }

    public function test_it_inserts_after_the_bus_resolution_line_when_no_register_call_exists_yet(): void
    {
        $this->write(<<<'PHP'
            <?php

            class WidgetServiceProvider
            {
                private function registerHandlers(): void
                {
                    $commandBus = $this->app->make(CommandBus::class);
                }
            }

            PHP);

        $result = $this->mutator->insertIntoMethodBody(
            $this->fixturePath,
            'registerHandlers',
            'FirstCommand::class, FirstHandler::class',
            "        \$commandBus->register(FirstCommand::class, FirstHandler::class);\n",
            [
                '/\$commandBus->register\([^;]*\);\r?\n/',
                '/\$commandBus = \$this->app->make\(CommandBus::class\);\r?\n/',
            ],
        );

        $this->assertSame(MutationResult::Inserted, $result);
        $this->assertStringContainsString(
            "\$commandBus = \$this->app->make(CommandBus::class);\n        \$commandBus->register(FirstCommand::class, FirstHandler::class);",
            $this->read(),
        );
    }

    public function test_it_survives_a_nested_closure_with_its_own_braces_inside_the_method(): void
    {
        $this->write(<<<'PHP'
            <?php

            class WidgetServiceProvider
            {
                private function registerHandlers(): void
                {
                    $callback = function () {
                        return ['nested' => true];
                    };
                    $commandBus = $this->app->make(CommandBus::class);
                    $callback();
                }
            }

            PHP);

        $result = $this->mutator->insertIntoMethodBody(
            $this->fixturePath,
            'registerHandlers',
            'FirstCommand::class, FirstHandler::class',
            "        \$commandBus->register(FirstCommand::class, FirstHandler::class);\n",
            [
                '/\$commandBus->register\([^;]*\);\r?\n/',
                '/\$commandBus = \$this->app->make\(CommandBus::class\);\r?\n/',
            ],
        );

        $this->assertSame(MutationResult::Inserted, $result);
        $contents = $this->read();
        $this->assertStringContainsString('$commandBus->register(FirstCommand::class, FirstHandler::class);', $contents);
        // la classe entiere reste syntaxiquement equilibree (le tokenizer ne
        // doit pas se tromper sur les accolades de la closure imbriquee).
        $this->assertSame(substr_count($contents, '{'), substr_count($contents, '}'));
    }

    public function test_it_handles_a_single_line_method_body(): void
    {
        $this->write(
            "<?php\n\nclass WidgetServiceProvider\n{\n"
            ."    private function registerHandlers(): void { \$commandBus = \$this->app->make(CommandBus::class); }\n"
            ."}\n",
        );

        $result = $this->mutator->insertIntoMethodBody(
            $this->fixturePath,
            'registerHandlers',
            'FirstCommand::class, FirstHandler::class',
            ' $commandBus->register(FirstCommand::class, FirstHandler::class);',
            ['/\$commandBus = \$this->app->make\(CommandBus::class\);/'],
        );

        $this->assertSame(MutationResult::Inserted, $result);
        $this->assertStringContainsString(
            '$commandBus = $this->app->make(CommandBus::class); $commandBus->register(FirstCommand::class, FirstHandler::class);',
            $this->read(),
        );
    }

    public function test_it_aborts_when_the_method_is_not_found(): void
    {
        $this->write("<?php\n\nclass WidgetServiceProvider\n{\n    private function boot(): void\n    {\n    }\n}\n");

        $result = $this->mutator->insertIntoMethodBody(
            $this->fixturePath,
            'registerHandlers',
            'Marker',
            "line\n",
            ['/nope/'],
        );

        $this->assertSame(MutationResult::AbortedShapeMismatch, $result);
    }

    public function test_it_aborts_when_the_method_name_is_ambiguous(): void
    {
        $this->write(<<<'PHP'
            <?php

            class One
            {
                private function registerHandlers(): void
                {
                }
            }

            class Two
            {
                private function registerHandlers(): void
                {
                }
            }

            PHP);

        $result = $this->mutator->insertIntoMethodBody(
            $this->fixturePath,
            'registerHandlers',
            'Marker',
            "line\n",
            ['/nope/'],
        );

        $this->assertSame(MutationResult::AbortedShapeMismatch, $result);
    }

    public function test_it_aborts_when_no_anchor_matches_and_leaves_the_file_untouched(): void
    {
        $original = "<?php\n\nclass WidgetServiceProvider\n{\n    private function registerHandlers(): void\n    {\n        // reecrit a la main\n    }\n}\n";
        $this->write($original);

        $result = $this->mutator->insertIntoMethodBody(
            $this->fixturePath,
            'registerHandlers',
            'FirstCommand::class, FirstHandler::class',
            "        \$commandBus->register(FirstCommand::class, FirstHandler::class);\n",
            [
                '/\$commandBus->register\([^;]*\);\r?\n/',
                '/\$commandBus = \$this->app->make\(CommandBus::class\);\r?\n/',
            ],
        );

        $this->assertSame(MutationResult::AbortedShapeMismatch, $result);
        $this->assertSame($original, $this->read());
    }
}
