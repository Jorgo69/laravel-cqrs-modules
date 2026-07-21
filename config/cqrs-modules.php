<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Configuration publiable du generateur de modules CQRS.
 */

declare(strict_types=1);

return [

    'modules' => [
        'namespace' => 'App\\Modules',
        'path' => app_path('Modules'),

        'directories' => [
            // toujours crees, jamais demandes
            'required' => ['Commands', 'Queries', 'Handlers', 'Providers'],

            // proposes via prompt multiselect en mode interactif ; valeur par
            // defaut utilisee telle quelle en mode non-interactif/--dirs
            'optional' => [
                'DTOs' => true,
                'Enums' => true,
                'Models' => true,
                'Controllers' => true,
                'Requests' => true,
                'Database/Migrations' => true,
                'Database/Factories' => false,
                'Database/Seeders' => false,
            ],
        ],
    ],

    'handlers' => [
        // ex: "CreatePingHandler", pas "CreatePingCommandHandler" — permis par
        // la resolution explicite du Bus (pas de convention de nommage).
        'suffix' => 'Handler',
    ],

    'routes' => [
        'enabled' => true,
        // {kebab} est remplace par le nom du module en kebab-case
        'prefix' => 'api/{kebab}',
        'middleware' => ['api'],
    ],

    'provider' => [
        'register_routes_method' => 'registerRoutes',
        'register_handlers_method' => 'registerHandlers',
        'auto_register_in_bootstrap' => true,
        'bootstrap_providers_path' => base_path('bootstrap/providers.php'),
    ],

    'bus' => [
        'namespace' => 'App\\Shared\\Bus',
        'path' => app_path('Shared/Bus'),
        'command_bus_class' => 'CommandBus',
        'query_bus_class' => 'QueryBus',
        'command_bus_variable' => 'commandBus',
        'query_bus_variable' => 'queryBus',
        'service_provider' => 'BusServiceProvider',
    ],

    'stubs' => [
        // cherche ici en premier (override projet) avant de retomber sur les
        // stubs embarques dans le package — meme logique que stub:publish.
        'publish_path' => base_path('stubs/cqrs-modules'),
    ],

    'tests' => [
        // true|false|'prompt'
        'feature_stub' => 'prompt',
        'path' => base_path('tests/Feature'),
    ],

];
