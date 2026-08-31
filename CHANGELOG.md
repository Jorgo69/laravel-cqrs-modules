# Changelog

Toutes les modifications notables de ce package sont documentées ici.

Format basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
versionnage suivant [SemVer](https://semver.org/lang/fr/).

## [Non publié]

Pas encore de version taguée — package installable dès aujourd'hui via
Packagist (à venir) ou directement depuis GitHub (`dev-main`), voir
"Installation" dans `README.md`.

### Ajouté

- Générateur de modules CQRS : `make:module`, `make:cqrs-command`,
  `make:cqrs-query`, installeur `cqrs-modules:install` (copie du Bus minimal
  dans l'application consommatrice).
- Support Laravel 11.x (best-effort, non testé par CI), 12.x et 13.x (PHP
  8.2/8.3/8.4 selon la version de Laravel — voir "Compatibilité" dans
  `README.md`).
- Licence MIT, projet ouvert aux contributions (`CONTRIBUTING.md`).
- Guide d'utilisation et de personnalisation complet (`USAGE.md`).
- `.gitattributes` (`export-ignore`) pour un dist Composer propre.
- CI GitHub Actions : vraie matrice multi-Laravel (Laravel 12.x sur PHP
  8.2/8.3/8.4, Laravel 13.x sur PHP 8.3/8.4 — 5 combinaisons), tests + lint +
  syntax check sur chacune.

### Changé

- Laravel 11 retiré de la matrice CI (support sécurité upstream terminé le
  12 mars 2026, plus aucune version 11.x resolvable via Composer sans
  advisory de sécurité) — reste accepté dans `composer.json` en best-effort.

### Corrigé

- `workbench/database/factories/UserFactory.php` : double import de `User`
  provoquant une erreur fatale de parsing lors de `composer build`/`serve`,
  jamais détectée par la suite PHPUnit elle-même.

[Non publié]: https://github.com/Jorgo69/laravel-cqrs-modules/commits/main
