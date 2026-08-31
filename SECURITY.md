# Politique de sécurité

## Versions supportées

| Version | Supportée |
|---|---|
| 1.x     | Oui |
| < 1.0 (`dev-main` avant tag) | Non |

Ce package n'a aucune dépendance runtime dans les projets qui l'installent
(voir "Principe" dans `README.md`) — il ne s'exécute jamais en production,
seulement au moment où vous lancez ses commandes Artisan pour générer du
code. La surface d'attaque réelle est donc limitée à votre environnement de
développement/CI, pas à vos utilisateurs finaux.

## Signaler une vulnérabilité

**N'ouvrez pas d'issue publique pour une vulnérabilité** — utilisez plutôt
l'un de ces deux canaux privés :

1. [GitHub Security Advisories](https://github.com/Jorgo69/laravel-cqrs-modules/security/advisories/new)
   du repo (méthode recommandée — permet un suivi et une divulgation
   coordonnée directement sur GitHub).
2. Email à ibralejorgo@gmail.com avec `[SECURITY] laravel-cqrs-modules` en
   objet.

Merci d'inclure :
- Le comportement exact observé et comment le reproduire.
- Version du package et de Laravel/PHP concernées.
- Si possible, un scénario ou un code d'exploitation minimal.

## Ce qui compte comme vulnérabilité ici

Étant un générateur de code exécuté uniquement en dev, les cas les plus
pertinents sont : un stub généré produisant du code PHP dangereux par
défaut (ex. injection via un nom de module/commande mal échappé dans le
code généré), ou une dépendance (`require`/`require-dev`) affectée par une
CVE connue. `composer audit` est exécuté manuellement avant chaque
publication de version — voir `CONTRIBUTING.md`.

## Délai de réponse

Pas de SLA formel (mainteneur solo, projet perso) — accusé de réception
visé sous une semaine, correctif ou plan d'action communiqué dans la
foulée selon la gravité.
