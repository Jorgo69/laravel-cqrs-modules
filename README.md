# jorgo69/laravel-cqrs-modules

[![Tests](https://github.com/Jorgo69/laravel-cqrs-modules/actions/workflows/tests.yml/badge.svg)](https://github.com/Jorgo69/laravel-cqrs-modules/actions/workflows/tests.yml)
[![Licence MIT](https://img.shields.io/badge/licence-MIT-green)](LICENSE.md)

Générateur de modules CQRS pour Laravel. Outil de dev uniquement — aucune
dépendance runtime dans les projets qui l'installent (voir "Principe"
ci-dessous).

**Auteur :** Ibra Le Jorgo — ibralejorgo@gmail.com — [github.com/Jorgo69](https://github.com/Jorgo69)
**Licence :** MIT (voir `LICENSE.md`) — projet ouvert, contributions bienvenues (voir `CONTRIBUTING.md`).

## Principe

Ce package ne fournit **pas** un Bus tout fait à importer dans votre code. Il
**copie** un Bus minimal (`Command`/`Query`/`CommandHandler`/`QueryHandler`/
`CommandBus`/`QueryBus`) dans votre propre application, sous le namespace de
votre choix (`App\Shared\Bus` par défaut). Le code généré ensuite ne référence
jamais ce package — seulement votre propre code. Vous en devenez pleinement
propriétaire, vous pouvez le faire évoluer librement projet par projet.

Conséquence : ce package peut rester en `require-dev` dans vos projets. Il ne
sert qu'à générer du code, jamais à l'exécuter en production.

## Installation

### Le plus simple (une fois publié sur Packagist)

```bash
composer require --dev jorgo69/laravel-cqrs-modules
php artisan cqrs-modules:install
```

*(Pas encore soumis à [packagist.org](https://packagist.org) — voir les deux méthodes ci-dessous en attendant, toutes les deux fonctionnelles dès aujourd'hui.)*

### Directement depuis GitHub (équivalent de `npm install git+...`)

Composer sait installer un package directement depuis un dépôt Git, sans
passer par Packagist — utile pour tester la dernière version sur `main`, ou
pour toute personne qui préfère ne pas attendre la publication officielle.
Dans le `composer.json` du projet consommateur :

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Jorgo69/laravel-cqrs-modules"
        }
    ]
}
```

```bash
composer require --dev jorgo69/laravel-cqrs-modules:dev-main
# ou une version taguée si des tags existent : jorgo69/laravel-cqrs-modules:^1.0
php artisan cqrs-modules:install
```

### En développement local (monorepo / plusieurs projets sur la même machine)

Pratique quand vous développez le package et un projet qui le consomme côte à
côte — chaque modification du package est immédiatement visible dans le
projet, sans réinstaller :

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-cqrs-modules",
            "options": { "symlink": true }
        }
    ]
}
```

```bash
composer require --dev jorgo69/laravel-cqrs-modules:@dev
php artisan cqrs-modules:install
```

`cqrs-modules:install` publie `config/cqrs-modules.php`, copie le Bus dans
`app/Shared/Bus/` et enregistre son Provider dans `bootstrap/providers.php`
automatiquement.

## Commandes

### `php artisan make:module {Name}`

Scaffold un module complet : `Commands/ Queries/ Handlers/ DTOs/ Enums/
Models/ Controllers/ Requests/ Providers/ Database/{Migrations,Factories,
Seeders}/` + un `{Name}ServiceProvider.php` + un `routes.php` propre au
module. Enregistre automatiquement le Provider dans
`bootstrap/providers.php`.

Options :
- `--dirs=DTOs,Models` : choisit les dossiers optionnels sans passer par le
  prompt interactif.
- `--test` / `--no-test` : force/évite la génération d'un test Feature de
  départ.
- `--no-register` : ne touche pas à `bootstrap/providers.php`.

Sans flags, en terminal interactif, les choix (dossiers optionnels, test) sont
demandés via des prompts. En CI/scripté (`--no-interaction`), les valeurs par
défaut de `config/cqrs-modules.php` sont utilisées.

Relancer la commande sur un module existant est sans danger : chaque élément
déjà présent est simplement signalé, jamais écrasé.

### `php artisan make:cqrs-command {Module} {Name}`

Ajoute une seule paire Command+Handler dans un module **déjà généré** par
`make:module` — pour le cas où vous n'avez besoin que d'une nouvelle action
dans un module existant, pas d'un module entier. Enregistre automatiquement la
paire dans `registerHandlers()` (`--no-register` pour désactiver).

### `php artisan make:cqrs-query {Module} {Name}`

Identique pour une paire Query+Handler.

## Convention générée

- Bus à enregistrement **explicite** (`$commandBus->register(X::class,
  Y::class)` dans `registerHandlers()`) — pas de résolution par convention de
  nommage, plus robuste aux réorganisations de dossiers et aux renommages
  (IDE-friendly).
- Handler nommé `XHandler`, jamais `XCommandHandler`/`XQueryHandler` — permis
  par l'enregistrement explicite.
- `declare(strict_types=1)` partout, `assert($command instanceof X)` dans
  chaque Handler pour le narrowing statique.
- Routes encapsulées dans le module (`routes.php` propre, chargé via
  `loadRoutesFrom`) plutôt que centralisées dans un fichier racine.

## Configuration (`config/cqrs-modules.php`)

Publiable via l'installeur. Permet d'ajuster : le namespace/chemin des
modules, les dossiers optionnels créés par défaut, le suffixe des Handlers, le
préfixe/middleware des routes, les noms de méthode du Provider
(`registerRoutes`/`registerHandlers`), le namespace/chemin du Bus, et si
l'enregistrement automatique dans `bootstrap/providers.php` doit avoir lieu.

Les stubs (`stubs/module/*.stub`, `stubs/piece/*.stub`, `stubs/bus/*.stub`)
sont publiables (`--tag=cqrs-modules-stubs`) et surchargeables sans forker le
package — le générateur cherche d'abord dans
`config('cqrs-modules.stubs.publish_path')` avant de retomber sur les stubs
embarqués.

## Limites connues (v1)

- Pas de génération de DTO automatique avec `make:cqrs-command`/`query`.
- Pas de stratégie de nommage Handler pluggable (un seul suffixe configurable).
- Un seul CommandBus + un seul QueryBus par projet.
- Pas de commande de migration rétroactive pour des modules déjà écrits avec
  une autre convention.
- Pas encore soumis à Packagist (voir "Installation" ci-dessus pour installer
  dès maintenant sans Packagist).

## Tests

```bash
composer install
composer test        # phpunit
composer test:lint    # pint --test (vérifie sans modifier)
```

Utilise `orchestra/testbench` + `orchestra/workbench` — les commandes
s'exécutent contre une véritable application Laravel de test (pas de mocks
sur le système de fichiers).

## Contribuer

Les issues et pull requests sont les bienvenues — voir `CONTRIBUTING.md` pour
le détail (mise en place de l'environnement, conventions de code, process de
PR).
