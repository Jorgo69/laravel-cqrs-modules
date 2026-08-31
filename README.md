# jorgo69/laravel-cqrs-modules

[![Tests](https://github.com/Jorgo69/laravel-cqrs-modules/actions/workflows/tests.yml/badge.svg)](https://github.com/Jorgo69/laravel-cqrs-modules/actions/workflows/tests.yml)
[![Licence MIT](https://img.shields.io/badge/licence-MIT-green)](LICENSE.md)

Générateur de modules CQRS pour Laravel. Outil de dev uniquement — aucune
dépendance runtime dans les projets qui l'installent (voir "Principe"
ci-dessous).

**Auteur :** Ibra Le Jorgo — ibralejorgo@gmail.com — [github.com/Jorgo69](https://github.com/Jorgo69)
**Licence :** MIT (voir `LICENSE.md`) — projet ouvert, contributions bienvenues (voir `CONTRIBUTING.md`).

## Compatibilité

| PHP | Laravel | Testé par la CI |
|---|---|---|
| 8.2, 8.3, 8.4 | 12.x | Oui (`.github/workflows/tests.yml`) |
| 8.3, 8.4 (**pas 8.2**) | 13.x | Oui |
| 8.2, 8.3, 8.4 | 11.x | Non — voir ci-dessous |

Laravel 13 lui-même exige PHP ^8.3 — impossible de l'utiliser sur PHP 8.2,
quel que soit ce package. Ce n'est jamais un souci en pratique (un projet
Laravel 13 tourne forcément déjà sur PHP 8.3+), mais bon à savoir si vous
gérez plusieurs projets sur des PHP différents.

**La CI teste réellement 5 combinaisons PHP × Laravel** (Laravel 12 sur
8.2/8.3/8.4, Laravel 13 sur 8.3/8.4), pas seulement plusieurs PHP contre une
seule version de Laravel.

**Laravel 11 n'est plus dans la matrice CI, volontairement.** Son support
sécurité s'est terminé le 12 mars 2026 : au moment d'écrire ceci,
`composer require laravel/framework:^11.0` ne se résout plus du tout, car
Packagist signale désormais **toutes** les versions 11.x par des advisories
de sécurité (constaté directement, pas une supposition) — il n'existe plus de
version 11.x "propre" contre laquelle tester. Le package continue d'accepter
`illuminate/console`/`support`/`filesystem` en `^11.0` dans son `composer.json`
(ce sont des sous-packages, pas `laravel/framework` — un projet déjà en
Laravel 11 garde son propre lock file et n'est pas forcé de migrer), mais
cette compatibilité n'est plus couverte par la CI et repose sur le fait que
le code du package n'utilise que des API stables, inchangées depuis
longtemps. Si vous êtes encore sur Laravel 11, ça devrait fonctionner, mais
c'est un "best effort", pas une garantie testée. La compatibilité Laravel 13
a aussi été vérifiée manuellement sur un vrai projet consommateur
([wuri-anip-api](https://github.com/Jorgo69)), en plus de la CI.

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
composer require --dev jorgo69/laravel-cqrs-modules:^1.0
# ou la dernière version non taguée : jorgo69/laravel-cqrs-modules:dev-main
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

> Référence complète (toutes les options, comment le package fonctionne une
> fois installé, jusqu'où et comment le personnaliser, complémentaires
> recommandés pour un projet API) : voir **`USAGE.md`**. Ce qui suit est un
> résumé rapide.

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
- Laravel 11 non couvert par la CI, upstream EOL sécurité — voir
  "Compatibilité" ci-dessus.

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
le détail (structure du projet, signature des fichiers, stratégie de
branches, mise en place de l'environnement, conventions de code, process de
PR). `main` est protégée : toute contribution passe par une PR avec CI verte
(PHP 8.2/8.3/8.4), pas d'exception.

Historique des changements : voir `CHANGELOG.md`.
