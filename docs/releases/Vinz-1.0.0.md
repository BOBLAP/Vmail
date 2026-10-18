# Vinz 1.0.0 — 18 octobre 2026

Première version personnalisée du fork BOBLAP/Vmail, publiée depuis `develop`.

## Nouveautés

- Thème Vinz basé sur Nord : tons sombres teal, accents orange, jaune et violet.
- Recherche avancée accessible à droite de la recherche simple : expéditeur,
  destinataire, copie, objet, libellé, pièces jointes et état lu/non lu.
  La validation remplit la barre avec les tags et lance la recherche.
- Hauteur du contenu des messages indépendante de la densité du volet de lecture :
  adaptative, compacte (80 px), cosy (200 px), confortable (1080 px).
  Le choix est enregistré et disponible dans les réglages et via JMAP.
- Traductions française, anglaise, allemande et anglaise pirate.
- Documentation de personnalisation, inventaire des composants partagés et
  cartographie du code pour les prochaines évolutions.

## Corrections

Le script de mesure du contenu HTML normalise les fins de ligne avant son calcul
d'empreinte CSP. Cela corrige le contenu bloqué à 80 px sur un checkout Windows.
Les scripts shell sont conservés avec des fins de ligne Unix.

## Installation depuis les sources

Cette version fournit le code source ; aucune nouvelle image Docker publiée
n'est associée à ce tag. Pour construire les personnalisations du fork :

```sh
git checkout Vinz-1.0.0
docker compose -f compose.yaml -f compose.vinz.yaml up -d --build
```

Conserver les paramètres et volumes de l'installation existante et effectuer une
sauvegarde avant migration. La migration `Version20261018140000` ajoute la
préférence de hauteur avec la valeur adaptative par défaut ; les migrations
s'exécutent au démarrage. Les paramètres locaux et secrets ne sont pas publiés.

## Vérifications

- Tests Appearance : 560 tests, 4 816 assertions.
- Tests du parseur de recherche : 23 tests, 39 assertions.
- Tests du script des messages et des fins de ligne : 3 tests, 8 assertions.
- Tests JavaScript de recherche avancée : 3 tests.
- Scénarios navigateur ciblés : recherche avancée sur ordinateur et mobile,
  hauteurs fixes, hauteur adaptative après rechargement et changement à chaud.
- Vérification des modèles Twig, traductions et instance Docker locale.

La suite complète n'a pas été exécutée. Le contrôle TypeScript global rencontre
cinq erreurs préexistantes dans `tests/e2e/dnd-lifecycle.spec.ts`.
