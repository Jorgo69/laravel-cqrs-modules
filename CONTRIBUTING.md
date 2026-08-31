# Contribuer à laravel-cqrs-modules

Merci de l'intérêt porté à ce package ! Issues, questions et pull requests
sont les bienvenues, en français ou en anglais.

**Sommaire** : [Se repérer dans le projet](#se-repérer-dans-le-projet) ·
[Signature des fichiers](#signature-des-fichiers) ·
[Stratégie de branches](#stratégie-de-branches) ·
[Mise en place de l'environnement](#mise-en-place-de-lenvironnement) ·
[Lancer la suite de tests](#lancer-la-suite-de-tests) ·
[Explorer en conditions réelles](#explorer-le-package-en-conditions-réelles) ·
[Conventions de code](#conventions-de-code) ·
[Signaler un bug](#signaler-un-bug--proposer-une-fonctionnalité) ·
[Process de pull request](#process-de-pull-request) ·
[Licence](#licence)

## Se repérer dans le projet

```
laravel-cqrs-modules/
├── config/cqrs-modules.php        Toute la config publiable : namespaces, dossiers
│                                   optionnels, suffixe des Handlers, chemin du Bus...
├── src/
│   ├── LaravelCqrsModulesServiceProvider.php   Point d'entrée : déclare la config,
│   │                                            les 3 commandes Artisan, et l'installeur.
│   ├── Commands/
│   │   ├── MakeModuleCommand.php               make:module — scaffold un module complet.
│   │   ├── MakeCqrsCommandCommand.php           make:cqrs-command — ajoute 1 paire
│   │   │                                        Command+Handler à un module existant.
│   │   ├── MakeCqrsQueryCommand.php             make:cqrs-query — idem pour Query+Handler.
│   │   └── Concerns/GeneratesModulePiece.php    Logique PARTAGÉE entre les deux commandes
│   │                                            ci-dessus (make:cqrs-command/query ne
│   │                                            diffèrent que par kind: 'Command'|'Query').
│   └── Support/                                Aucune classe ici ne dépend d'Artisan —
│       │                                        testable indépendamment des Commands.
│       ├── ModuleNameResolver.php               1 nom de module -> namespace/chemin/nom
│       │                                        de classe du Provider dérivés.
│       ├── StubRenderer.php                     Trouve le bon stub (override projet ou
│       │                                        stub embarqué) et le rend vers un fichier.
│       ├── GuardedFileMutator.php               Insère une ligne dans un fichier PHP
│       │                                        EXISTANT (bootstrap/providers.php,
│       │                                        registerHandlers()) sans jamais deviner —
│       │                                        abandonne si la forme attendue diffère.
│       ├── MutationResult.php                   Enum des issues d'une mutation
│       │                                        (Inserted/AlreadyPresent/Aborted...).
│       └── GeneratedFile.php                    DTO simple : chemin + a-été-créé-ou-non.
├── stubs/
│   ├── module/*.stub                            Gabarits utilisés par make:module
│   │                                             (ServiceProvider, routes.php, test).
│   ├── piece/*.stub                              Gabarits pour 1 Command/Query + Handler.
│   └── bus/*.stub                                Gabarits du Bus lui-même (copié UNE FOIS
│                                                  chez le consommateur par l'installeur,
│                                                  jamais régénéré ensuite).
├── tests/
│   ├── Feature/*CommandTest.php                  Un test par commande Artisan — exécute la
│   │                                              VRAIE commande dans le workbench et relit
│   │                                              les fichiers produits (pas de mock FS).
│   ├── Feature/EndToEndDispatchTest.php          Scénario complet : générer un module, une
│   │                                              paire Command+Handler, puis dispatcher
│   │                                              réellement via le Bus généré.
│   ├── Feature/InstallCommandTest.php             Teste `cqrs-modules:install` seul.
│   └── Unit/GuardedFileMutatorTest.php            Tests unitaires ciblés sur les cas
│                                                   limites de mutation de fichier.
└── workbench/                                    Squelette d'app Laravel MINIMAL utilisé
                                                    par testbench pour exécuter les tests
                                                    ci-dessus en conditions réelles.
```

**Règle d'or du projet** : ce package ne fait QUE générer/muter des fichiers
chez le consommateur — `src/` ne contient jamais de code qui s'exécute à
l'usage final (pas de Bus importé, pas de logique métier). Tout changement de
comportement passe par les `stubs/`, pas par du code caché dans `src/`.

## Signature des fichiers

Chaque fichier source (`src/`, `tests/`, hors `stubs/*.stub` qui restent de
simples gabarits texte) porte un en-tête :

```php
/**
 * @ Author: <Prénom Nom>
 * @ Email: <email>
 * @ Github: <profil Github>
 * @ Create Time: <AAAA-MM-JJ>
 * @ Description: <ce que fait ce fichier, une phrase>
 */
```

- **Vous créez un nouveau fichier** → en-tête complet avec VOS informations
  (pas celles du mainteneur). `@ Create Time` = la date de votre commit.
- **Vous modifiez un fichier existant** → ne touchez PAS à l'en-tête
  d'origine. Ajoutez juste en dessous :

  ```php
  * @ Modified by: <Prénom Nom>
  * @ Email: <email>
  * @ Github: <profil Github>
  * @ Modified time: <AAAA-MM-JJ>
  ```

  (répétez ce bloc, un par contributeur, dans l'ordre chronologique — ne
  réécrivez jamais le bloc d'un contributeur précédent).
- Champ `@ Gitlab` optionnel — présent sur les fichiers du mainteneur
  d'origine par habitude personnelle, pas obligatoire pour les contributions
  externes qui n'ont pas de profil GitLab.

Cette convention par fichier est volontairement redondante avec `git blame` —
c'est un choix assumé du mainteneur (lisible directement dans l'éditeur, sans
outil), pas une exigence Laravel/Composer. Une PR qui l'omet n'est pas
rejetée pour autant ; elle sera juste complétée avant merge.

## Stratégie de branches

`main` est la branche stable, toujours déployable. Un [GitHub Flow](https://docs.github.com/en/get-started/using-github/github-flow)
simple, pas de `develop`/`release` séparés (package trop petit pour justifier
une GitFlow complète) :

### Protection de `main` (réellement activée, pas juste une convention)

Ce n'est pas qu'une règle de bonne conduite — GitHub **refuse techniquement**
tout `git push` direct sur `main`, même pour le mainteneur. Concrètement,
depuis une branche protégée :
- **Pull request obligatoire** pour tout changement — aucune exception.
- **CI obligatoire et verte** avant de pouvoir merger : les 3 checks
  `PHP 8.2` / `PHP 8.3` / `PHP 8.4` (`.github/workflows/tests.yml`) doivent
  tous passer. Le bouton "Merge" reste grisé tant que ce n'est pas le cas —
  vous verrez l'état de chaque check directement dans la PR.
- **Conversations résolues** avant de pouvoir merger (toute discussion ouverte
  sur la PR doit être marquée "Resolved").
- **0 review humaine exigée** pour l'instant (mainteneur solo au moment de
  l'ouverture du projet) — la CI est la seule porte de qualité automatique.
  Une PR qui passe la CI peut être mergée dès qu'elle est ouverte.
- Force-push et suppression de `main` bloqués pour tout le monde.

Si votre `git push origin main` est rejeté avec `protected branch hook
declined` : c'est normal, ouvrez une PR à la place (voir ci-dessous).

| Préfixe de branche | Pour quoi |
|---|---|
| `feature/<sujet>` | Nouvelle commande, nouvelle option, nouveau générateur |
| `fix/<sujet>` | Correction de bug |
| `docs/<sujet>` | README, CONTRIBUTING, commentaires uniquement |
| `chore/<sujet>` | Dépendances, CI, config Pint/PHPUnit, sans changement fonctionnel |

Exemples : `feature/make-cqrs-projection`, `fix/mutator-nested-closures`,
`docs/readme-packagist`.

- Une branche = un sujet. Ne mélangez pas un `fix/` et un `feature/` dans la
  même branche/PR.
- Rebasez sur `main` avant d'ouvrir la PR si `main` a bougé entre-temps
  (`git fetch origin && git rebase origin/main`) plutôt qu'un merge commit.
- Une branche est supprimée dès sa PR mergée — pas de branches qui traînent.

## Mise en place de l'environnement

```bash
git clone https://github.com/Jorgo69/laravel-cqrs-modules.git
cd laravel-cqrs-modules
composer install
```

`composer install` déclenche automatiquement (`post-autoload-dump`) :
- `testbench package:purge-skeleton` — nettoie le squelette d'app Laravel de test.
- `testbench package:discover` — redécouvre le Service Provider du package dans ce squelette.

Le package est testé via [`orchestra/testbench`](https://packages.tools/testbench) +
`orchestra/workbench` : les commandes s'exécutent contre une vraie application
Laravel de test (`workbench/`), jamais des mocks du système de fichiers — un
`make:module` dans les tests crée réellement des fichiers PHP, que les
assertions relisent ensuite.

## Lancer la suite de tests

```bash
composer test        # ou : vendor/bin/phpunit
composer test:lint    # vérifie le style de code sans le modifier
composer lint         # applique les corrections de style automatiquement
```

Toute pull request doit passer les deux avant d'être proposée. Si vous ajoutez
une commande ou modifiez un générateur existant, ajoutez un test `Feature`
(voir `tests/Feature/*CommandTest.php` pour le patron à suivre — chaque test
exécute la vraie commande Artisan dans le workbench et vérifie le contenu des
fichiers générés).

## Explorer le package en conditions réelles

```bash
composer build   # construit le squelette workbench/
composer serve   # lance un vrai serveur Laravel avec le package installé dessus
```

Utile pour tester manuellement une commande (`php artisan make:module Demo`)
avant d'écrire le test automatisé correspondant.

## Conventions de code

- `declare(strict_types=1)` en tête de chaque fichier PHP.
- Style de code appliqué par Pint (`pint.json`) — ne pas reformater à la main,
  lancer `composer lint` avant de committer.
- Chaque méthode publique documentée (rôle, `@param`, `@return`, `@throws` si
  pertinent) — les méthodes privées triviales (un seul return, nom explicite)
  peuvent s'en passer si le nom suffit à comprendre.
- Les stubs (`stubs/**/*.stub`) restent de simples gabarits texte — toute
  logique de substitution vit dans `src/Support/StubRenderer.php`, pas dans
  les stubs eux-mêmes.
- Un nouveau générateur (`make:xxx`) doit :
  1. Réutiliser `GeneratesModulePiece` (`src/Commands/Concerns/`) si la
     commande génère un ou plusieurs fichiers dans un module existant.
  2. Ne jamais écraser un fichier déjà présent sans le signaler explicitement
     (voir `GuardedFileMutator` pour la logique de mutation sûre de fichiers
     existants, ex: enregistrement dans `registerHandlers()`).
  3. Avoir un test `Feature` qui exécute la commande dans le workbench et
     vérifie le contenu réel des fichiers produits.

## Signaler un bug / proposer une fonctionnalité

Ouvrez une [issue GitHub](https://github.com/Jorgo69/laravel-cqrs-modules/issues)
avec :
- La commande exacte lancée et son résultat.
- La version de Laravel/PHP utilisée.
- Pour un bug : le comportement attendu vs. observé, idéalement avec un test
  qui le reproduit (`tests/Feature/`).

## Process de pull request

1. Fork + branche depuis `main` en suivant la convention ci-dessus
   (`git checkout -b feature/ma-fonctionnalite`).
2. Un changement = une PR — évitez de mélanger plusieurs sujets non liés.
3. Tests + Pint verts avant d'ouvrir la PR (`composer test && composer test:lint`) —
   la CI GitHub Actions (`.github/workflows/tests.yml`) relance automatiquement les
   deux sur PHP 8.2/8.3/8.4 dès l'ouverture (plus un `php -l` sur tout le repo,
   `workbench/` inclus — attrape les erreurs fatales de parsing qu'un fichier
   jamais chargé par les tests eux-mêmes laisserait passer), et les 3 checks
   sont **requis** par la protection de `main` (voir "Protection de `main`"
   plus haut) : sans eux tous verts, impossible de merger, quel que soit qui
   vous êtes.
4. Message de commit au format [Conventional Commits](https://www.conventionalcommits.org/)
   (`feat:`, `fix:`, `docs:`, `test:`, `chore:`...) — le premier commit de ce
   repo (`feat: generateur CQRS modulaire pour Laravel`) donne le ton attendu.
5. Décrivez dans la PR *pourquoi* le changement est nécessaire, pas seulement
   *quoi* — particulièrement utile pour un fix qui corrige un bug rencontré
   sur un projet réel.
6. Changement visible pour les utilisateurs du package (nouvelle commande,
   option, comportement, fix de bug) → ajoutez une entrée dans `CHANGELOG.md`
   (section `[Non publié]`). Un changement purement interne (refacto sans
   impact, typo) n'a pas besoin d'entrée.

## Licence

En contribuant, vous acceptez que votre contribution soit distribuée sous la
licence MIT du projet (voir `LICENSE.md`).
