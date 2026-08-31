# Guide d'utilisation et de personnalisation

Ce document part du principe que le package est déjà installé (voir
"Installation" dans `README.md`). Il répond à trois questions : quelles
commandes sont disponibles, comment le package fonctionne une fois installé,
et jusqu'où on peut/doit l'adapter à son propre projet.

**Sommaire** : [Commandes disponibles](#commandes-disponibles) ·
[Comment ça marche une fois installé](#comment-ça-marche-une-fois-installé) ·
[Rigide ou permissif ?](#rigide-ou-permissif--réponse-directe) ·
[Recettes de personnalisation](#recettes-de-personnalisation) ·
[Compléments recommandés pour un projet API](#compléments-recommandés-pour-un-projet-api-laravel)

## Commandes disponibles

### `php artisan cqrs-modules:install`

À lancer **une seule fois**, juste après `composer require`. Publie
`config/cqrs-modules.php`, copie les 7 fichiers du Bus minimal dans
`app/Shared/Bus/` (chemin par défaut, configurable), et enregistre
`BusServiceProvider` dans `bootstrap/providers.php`. Sans option — tout est
piloté par `config/cqrs-modules.php` si vous l'avez déjà publié/ajusté avant.
Relancer cette commande ne réécrase rien : chaque fichier déjà présent est
signalé "Deja present, ignore".

### `php artisan make:module {Name}`

Scaffold un module complet.

```bash
php artisan make:module Widget
php artisan make:module Widget --dirs=DTOs,Models,Controllers --no-test
```

| Option | Effet |
|---|---|
| `--dirs=DTOs,Models,...` | Choisit les dossiers optionnels sans passer par le prompt interactif |
| `--test` | Force la génération d'un test Feature de départ |
| `--no-test` | N'en génère jamais |
| `--no-register` | Ne touche pas à `bootstrap/providers.php` (à vous de l'ajouter à la main) |
| `-n`, `--no-interaction` | Aucune question posée — utilise les valeurs par défaut de la config |

Sans `--dirs` en mode interactif, un prompt `multiselect` demande quels
dossiers optionnels créer. Relancer sur un module existant est **sans
danger** : chaque élément déjà présent est signalé, jamais écrasé.

Dossiers toujours créés (non configurables au cas par cas, seulement dans
`config('cqrs-modules.modules.directories.required')`) : `Commands/`,
`Queries/`, `Handlers/`, `Providers/`.

### `php artisan make:cqrs-command {Module} {Name}`

Ajoute **une seule** paire Command+Handler à un module déjà généré par
`make:module` (échoue explicitement si le Provider du module n'existe pas).

```bash
php artisan make:cqrs-command Widget CreateWidget
php artisan make:cqrs-command Widget CreateWidget --no-register
```

| Option | Effet |
|---|---|
| `--register` | Ajoute la paire à `registerHandlers()` (comportement par défaut) |
| `--no-register` | Génère les fichiers sans toucher au Provider — à enregistrer vous-même |

### `php artisan make:cqrs-query {Module} {Name}`

Identique à `make:cqrs-command`, pour une paire Query+Handler.

```bash
php artisan make:cqrs-query Widget ListWidgets
```

### `php artisan vendor:publish`

```bash
php artisan vendor:publish --tag=cqrs-modules-config   # config/cqrs-modules.php
php artisan vendor:publish --tag=cqrs-modules-stubs    # stubs/ surchargeables
```

## Comment ça marche une fois installé

**Rien de ce package ne tourne en production.** Chaque commande lit un stub
texte (`stubs/**/*.stub`), remplace quelques `{{ placeholders }}`, et écrit un
fichier PHP normal chez vous. Une fois écrit, ce fichier n'a plus AUCUN lien
avec le package — pas d'héritage, pas d'interface importée depuis
`Jorgo69\LaravelCqrsModules\*`, rien. Vous pourriez désinstaller le package
le lendemain, tout votre code généré continuerait de fonctionner à l'identique.

Concrètement, `cqrs-modules:install` copie **une fois** un Bus minimal
(`Command`, `Query`, `CommandHandler`, `QueryHandler`, `CommandBus`,
`QueryBus`, `BusServiceProvider` — 7 fichiers) dans `app/Shared/Bus/` (ou
l'emplacement que vous configurez). Le package ne revient jamais dessus
ensuite — vous en êtes pleinement propriétaire dès la copie, vous pouvez le
modifier comme n'importe quel code de votre projet.

Ensuite, `make:module`/`make:cqrs-command`/`make:cqrs-query` génèrent du code
qui **utilise** ce Bus (`implements Command`, `$commandBus->register(...)`),
mais toujours par référence à VOTRE copie (`App\Shared\Bus\...`), jamais au
package. C'est pour ça qu'il reste en `require-dev` : à l'exécution réelle de
votre application, `Jorgo69\LaravelCqrsModules\*` n'est jamais chargé.

## Rigide ou permissif ? (réponse directe)

**Très permissif.** Rien n'est imposé une fois le code généré — vous éditez
les fichiers produits exactement comme n'importe quel fichier de votre projet
(ce ne sont pas des fichiers "magiques" surveillés par le package). Ce qui
est réellement figé, c'est la **forme du pattern** que le générateur produit :

- Deux Bus séparés (Command vs Query), pas un Bus unique polyvalent.
- Enregistrement **explicite** des handlers (`register()` dans
  `registerHandlers()`), pas de résolution automatique par convention de nom
  ou d'attribut PHP.
- `assert($command instanceof X)` pour le narrowing statique dans chaque
  Handler, pas de generics/templates PHPStan sur le Bus.

Si ce pattern précis ne vous convient pas (vous préférez un seul Bus, ou une
résolution par attribut, ou des Handlers auto-découverts), ce générateur
n'est probablement pas fait pour vous tel quel — mais rien n'empêche de
publier les stubs (`--tag=cqrs-modules-stubs`) et de les réécrire entièrement
à votre convention : le générateur (dossiers, config, enregistrement
automatique dans `registerHandlers()`/`bootstrap/providers.php`) continue de
fonctionner, seul le CONTENU produit change.

Tout le reste — noms, chemins, suffixes, préfixes de route — est piloté par
`config/cqrs-modules.php`, sans toucher au code du package. Voir les recettes
ci-dessous.

## Recettes de personnalisation

Toutes les clés ci-dessous vivent dans `config/cqrs-modules.php` (publié via
`--tag=cqrs-modules-config`).

### Changer le namespace/chemin des modules

```php
'modules' => [
    'namespace' => 'App\\Domain',       // au lieu de App\Modules
    'path' => app_path('Domain'),
],
```

### Changer les dossiers créés par défaut

```php
'modules' => [
    'directories' => [
        'required' => ['Commands', 'Queries', 'Handlers', 'Providers'], // toujours créés
        'optional' => [
            'DTOs' => true,   // coché par défaut en mode non-interactif/prompt
            'Models' => false, // décoché par défaut
            // ...
        ],
    ],
],
```

### Changer le suffixe des Handlers

```php
'handlers' => ['suffix' => 'Action'], // CreateWidgetAction au lieu de CreateWidgetHandler
```

### Changer le préfixe/middleware des routes générées

```php
'routes' => [
    'prefix' => 'api/v1/{kebab}', // {kebab} = nom du module en kebab-case
    'middleware' => ['api', 'auth:sanctum'],
],
```

Pour un schéma d'URL qui ne suit PAS `{kebab}` (ex: garder `api/v1` sans le
nom du module, comme fait pour un module `Auth` dont les routes doivent
rester `api/v1/login` et pas `api/v1/auth/login`), le plus simple reste
d'éditer directement le `routes.php` généré pour CE module après coup — la
config pilote le cas général, pas les exceptions ponctuelles.

### Changer où/comment le Bus est copié

```php
'bus' => [
    'namespace' => 'App\\Infrastructure\\Bus',
    'path' => app_path('Infrastructure/Bus'),
    'command_bus_class' => 'Commands',   // au lieu de CommandBus
    'query_bus_class' => 'Queries',
],
```

N'a d'effet que sur un **nouveau** `cqrs-modules:install` (un Bus déjà copié
n'est jamais régénéré ni déplacé automatiquement).

### Désactiver l'auto-registration dans `bootstrap/providers.php`

```php
'provider' => ['auto_register_in_bootstrap' => false],
```

Ou au cas par cas : `--no-register` sur `make:module`.

### Surcharger un stub sans forker le package

```bash
php artisan vendor:publish --tag=cqrs-modules-stubs
# édite ensuite stubs/cqrs-modules/module/service-provider.stub, piece/*.stub, etc.
```

Le générateur cherche d'abord dans
`config('cqrs-modules.stubs.publish_path')` (par défaut
`stubs/cqrs-modules/` à la racine du projet) avant de retomber sur les stubs
embarqués dans le package — vos overrides survivent à une mise à jour du
package.

## Compléments recommandés pour un projet API Laravel

Ce package génère uniquement la couche CQRS. Pour un projet API complet
(retour d'expérience réel sur un projet construit avec ce générateur), les
compléments suivants sont indépendants de ce package (aucun lien entre eux) :

| Besoin | Package | Notes |
|---|---|---|
| Auth API par token | `laravel/sanctum` | Déjà présent dans la plupart des starter kits API Laravel |
| Documentation API auto-générée | `dedoc/scramble` | Zéro annotation, génère l'OpenAPI depuis les FormRequest/Resources existants |
| Interface Swagger UI classique en plus de Scramble | Aucun package — une simple vue Blade chargeant [swagger-ui-dist](https://www.jsdelivr.com/package/npm/swagger-ui-dist) par CDN, pointée sur le même `/docs/api.json` exporté par Scramble | Utile si vous ou votre équipe êtes plus habitués au bouton "Authorize" de Swagger qu'à celui de Stoplight Elements (l'UI de Scramble) |
| RBAC par rôle/permission | `spatie/laravel-permission` | Fonctionnalité **Teams** si vos permissions doivent être scopées par organisation/agence plutôt que globales |
| Audit trail CRUD automatique | `spatie/laravel-activitylog` | Complémentaire à un éventuel registre d'audit métier custom, pas un remplacement — les deux répondent à des questions différentes ("qu'est-ce qui a changé" vs. "quelle action métier a eu lieu") |
| Temps réel (WebSockets) | `laravel/reverb` | Pour notifier un front d'un événement déclenché par un Handler |
| Tests | `pestphp/pest` ou PHPUnit natif | Ce package lui-même est testé en PHPUnit pur, Pest fonctionne aussi bien pour vos modules générés |
| Qualité de code | `laravel/pint` + `larastan/larastan` (PHPStan) + `rector/rector` | Le trio utilisé par ce package lui-même — voir `CONTRIBUTING.md` |

**Important** : ce package n'installe, ne recommande ni ne dépend d'aucun de
ces outils — la liste ci-dessus est un point de départ tiré d'un usage réel,
pas une prescription. Un module généré par `make:module` fonctionne
identiquement avec ou sans eux.
