# Affichage et personnalisation de pl_mail

Guide de maintenance établi à partir du code le **18 octobre 2026**, après l'ajout
du thème Vinz. Consulter ce fichier avant une modification d'apparence et mettre à
jour les sections concernées avec le changement. Les chemins sont relatifs à la
racine du dépôt ; le code reste la référence si cette cartographie vieillit.

Voir aussi [Composants visuels](COMPOSANTS_UI.md) pour les éléments partagés et
[Carte du code](CARTE_DU_CODE.md) pour les points d'entrée par type de demande.

## 1. Où intervenir selon la demande

| Demande | Points d'entrée |
|---|---|
| Ajouter ou modifier un thème | `src/Domain/Enum/Theme/Theme.php`, `assets/styles/app.css`, `translations/messages.*.yaml` |
| Changer boutons, survols, badges ou couleurs de texte | Variables sémantiques et composants dans `assets/styles/app.css` ; vérifier aussi `AppearanceRenderer.php` pour les valeurs personnelles |
| Modifier les réglages proposés | `templates/settings/_appearance.html.twig`, `assets/controllers/settings/appearance_controller.js` |
| Modifier l'aperçu des réglages | `templates/settings/_appearance_preview.html.twig` et le même contrôleur JavaScript |
| Changer disposition ou valeurs des curseurs à la sélection | `src/Domain/Enum/Theme/Layout.php`, règles `.layout-flat` dans `app.css` |
| Modifier valeurs initiales, bornes ou stockage | `src/Entity/Embeddable/Appearance.php` |
| Changer police, densité, animations ou non-lus | Enums dans `src/Domain/Enum/Theme/`, renderer, `app.css` et `assets/styles/motion.css` |
| Modifier logo, couleurs du logo ou favicon | `LogoStyle.php`, `LogoMotif.php`, `src/Service/Appearance/LogoIcons.php`, `templates/branding/` |
| Ajouter un fond prédéfini | `BackgroundPreset.php`, `BackgroundResolver.php`, images sous `assets/images/backgrounds/` |
| Modifier liste des messages, barre supérieure ou navigation | `templates/_partials/_thread_row.html.twig`, `_topbar.html.twig`, `_sidebar.html.twig` |
| Modifier présentation du message ouvert | `templates/mail/_thread_content.html.twig`, `_thread_message.html.twig`, variables de feuille de lecture dans `app.css` |
| Modifier le volet de lecture à droite | `src/Domain/Enum/Mail/ReadingPaneMode.php`, `assets/controllers/settings/reading_pane_controller.js`, `assets/controllers/mail/reading_split_controller.js` |
| Comprendre une différence après rechargement | Renderer PHP, contrôleur JavaScript, sauvegarde HTTP, styles compilés et image Docker réellement exécutée |

Les noms courts d'enums de ce guide désignent les fichiers sous
`src/Domain/Enum/Theme/`, sauf indication contraire.

## 2. Fonctionnalités existantes

| Famille | Possibilités et propriétés principales |
|---|---|
| Thème | System, Light, Paper, Dark, Nord, Vinz, Dusk, Solar, plus les thèmes correspondant aux couleurs de logos ; `theme` |
| Disposition | Flat et Boxed ; `layout` |
| Couleur principale | Accent personnel hexadécimal ; `accent` |
| Surfaces | Opacité des panneaux `paneAlpha`, des fenêtres et menus `popoverAlpha`, flou `paneBlur`, arrondi `radius`, assombrissement du fond `scrimAlpha` |
| Panneau principal | Teinte `mainTint`, opacité `mainAlpha` ; valeurs nulles pour suivre les surfaces |
| Texte | `inkColor`, `inkMuted`, `inkFaint` ; couleurs secondaires dérivables automatiquement |
| Typographie | System, Grotesque, Serif, Monospace ; `fontFamily`, échelle `fontScale` de 0,875 à 1,25 |
| Densité | Comfortable, Cosy, Compact ; `density`, et surcharges `sidebarDensity`, `listDensity`, `readingDensity` |
| Hauteur du corps du message | `messageBodySize` : Adaptive (contenu), Compact (80 px), Cosy (200 px), Comfortable (1080 px), indépendante de la densité ; HTML et texte brut |
| Liste des messages | Coin de compte `accountCorner`, disques expéditeurs `listAvatars`, compteur des onglets `tabUnreadCount`, aperçu de 0 à 2 lignes `previewLines` |
| Non-lus | Subtle, Standard, Strong ; `unreadEmphasis`, modulation du fond et de la barre latérale |
| Animations | Full, Minimal, None ; `motion` ; la préférence système de réduction des mouvements reste prioritaire |
| Fond | Fond du thème, couleur unie, image prédéfinie ou personnelle ; `backgroundKind`, `backgroundSolid`, `backgroundPreset`, `backgroundFile` |
| Logos | Marque pl et neuf motifs ; `logoMotif`, couleurs `logoStyle`, lien avec le thème `logoLinked`, dessin original `logoOriginal` |
| Aperçu | Mini-interface fictive, largeur mémorisée ; ouverture mobile temporaire |
| Portabilité | Export JSON version 1, import et réinitialisation |
| Volet de lecture | Off ou Right ; largeur mémorisée séparément, affichage à droite conditionné par l'espace disponible |

Fonds prédéfinis : Aurora, Dunes, Fog, Granite, Harbour, Linen, Pine et Tide.
L'upload accepte JPEG, PNG et WebP dans `AppearanceController`.

Valeurs initiales vérifiées : **Paper**, **Flat**, accent **`#7d6b4f`**, densité
Comfortable, police System, animations Full, logo **Wax Seal** en dessin original.
Le guide utilisateur `docs/features/appearance.md` mentionne encore Classic comme
logo initial : pour ce point, suivre `LogoMotif::DEFAULT` et `Appearance`.

### Limites à connaître

- La taille du texte de l'interface ne doit pas modifier la mise en forme envoyée
  par l'éditeur de messages ; celui-ci conserve sa propre typographie.
- Deux lignes d'aperçu concernent les lignes empilées ; en affichage large, sujet
  et aperçu partagent une ligne.
- Le réglage des disques expéditeurs a des règles spécifiques ; lire les styles
  et le guide utilisateur avant de promettre leur disparition complète.
- Les densités tactiles conservent des cibles adaptées ; vérifier sur mobile.
- Densité propre au volet ne signifie pas opacité propre au volet : liste et
  lecture partagent une surface.
- Le volet Right peut rester masqué si la carte de courrier est trop étroite,
  même dans une grande fenêtre avec un calendrier docké.

## 3. Chaîne de rendu et de sauvegarde

1. `User::$appearance` est un embeddable Doctrine (`Appearance`), avec colonnes
   préfixées `appearance_`. Les réglages suivent le compte.
2. `src/Twig/AppearanceExtension.php` expose `appearance()`, `appearance_class()`,
   `appearance_theme()`, `appearance_motion()` et `appearance_vars()`.
3. `templates/_layout/app.html.twig` les applique sur `<html>` : classes,
   `data-theme`, `data-motion` et variables CSS en style inline.
4. `AppearanceRenderer` transforme les préférences en variables CSS et choisit
   une encre contrastée pour les boutons portant l'accent.
5. `assets/styles/app.css` définit palettes, utilitaires et composants.
   Tailwind est compilé puis AssetMapper produit les fichiers versionnés servis.
6. Dans les réglages, `assets/controllers/settings/appearance_controller.js` peint les changements
   immédiatement sur la racine du document. Il regroupe la sauvegarde avec un
   délai de 400 ms par défaut.
7. `POST /settings/appearance` vérifie le jeton CSRF `ajax`, appelle
   `Appearance::applyArray()` et enregistre en base. Un échec doit être signalé
   même si le changement a déjà été affiché.
8. Une copie `plmail:appearance` dans localStorage sert à rejouer l'apparence
   sur les écrans sans utilisateur connecté. Le layout la renouvelle aussi.
   `AppearanceCookieSubscriber` conserve le thème et la langue pour les écrans
   d'erreur. Ces copies ne remplacent pas le stockage du compte.

Le mode System est résolu dans le navigateur avec `prefers-color-scheme` pour
éviter un premier affichage dans le mauvais mode.

### Priorités : pourquoi une couleur CSS peut ne pas apparaître

Les variables inline écrites depuis les préférences prennent le dessus sur les
blocs de thème. C'est notamment le cas de `--rgb-accent`. Modifier uniquement
`--rgb-accent` dans le CSS ne suffit donc pas : `Theme::defaults()` fournit
l'accent demandé à partir de `swatch()[2]`, et le sélecteur applique ce défaut.

La sélection d'un layout applique aussi ses défauts dans l'interface. En revanche,
`Appearance::applyArray()` affecte les champs reçus sans réappliquer automatiquement
tous les défauts du thème ou du layout. `applyLayout()` est une méthode distincte.
Lors d'un nouveau chemin d'écriture, vérifier explicitement quels défauts il envoie.

Les couleurs de texte personnelles et les fonds personnalisés peuvent également
masquer une modification du thème. Comparer avec des réglages propres puis avec
des surcharges personnelles, avant et après rechargement.

### Éléments stockés séparément

La largeur de l'aperçu passe par `POST /settings/appearance/pane-state` ; le mode
et la largeur du volet de lecture par `POST /settings/appearance/reading-pane`.
Ces préférences vivent dans les réglages de `User`, hors export de thème.
La réduction de la barre latérale utilise aussi `plmail:sidebarRail` dans
localStorage. Ne pas assimiler toute préférence visuelle à `Appearance`.

## 4. Vocabulaire des couleurs

La hauteur du corps des messages est un réglage distinct des couleurs et des
espacements : `MessageBodySize.php` fournit les choix et `AppearanceRenderer`
écrit `--message-body-height`. Le contrôleur
`assets/controllers/mail/message_frame_controller.js` respecte les tailles fixes
et utilise les mesures postMessage de l'iframe en adaptatif. Le texte brut et
l'aperçu emploient `.mail-body-text`. Les pièces jointes et avertissements restent
en dehors du viewport redimensionné. Le réglage est sauvegardé, exporté et exposé
par JMAP ; migration `Version20261018140000`. Tests dédiés :
`tests/Service/Appearance/MessageBodySizeTest.php` et
`tests/e2e/message-body-size.spec.ts`.

Si le mode adaptatif reste bloqué à 80 px, vérifier le script de mesure et sa
politique CSP : `src/Service/Mail/MessageFrameScript.php` normalise CRLF et CR en LF
avant insertion et calcul d'empreinte, comme le parseur HTML. Sans cette
normalisation sur un checkout Windows, le navigateur bloque le script et aucune
hauteur ne remonte. Ne pas contourner le sandbox ou autoriser tous les scripts.

| Variables | Usage |
|---|---|
| `--rgb-surface`, `--rgb-border`, `--rgb-line` | Surfaces et séparations |
| `--rgb-raised`, `--rgb-sunken` | Relief, fonds secondaires et survols avec alpha |
| `--rgb-ink`, `--rgb-ink-soft`, `--rgb-ink-muted`, `--rgb-ink-faint` | Hiérarchie des textes |
| `--rgb-accent`, `--rgb-accent-ink` | Actions et encre sur les boutons accentués |
| `--rgb-field`, `--rgb-field-border` | Champs de saisie |
| `--rgb-danger`, `--rgb-warning`, `--rgb-success`, `--rgb-info` | Couleurs sémantiques des états |
| `--rgb-inverse`, `--rgb-inverse-ink` | Surfaces inversées, notamment infobulles |
| `--app-bg` | Fond global, éventuellement remplacé par une préférence |
| `--rgb-sheet-*` | Couleurs de la feuille de lecture des messages |
| Variables `*-alpha` | Transparences des surfaces, bordures et états |

Les canaux RGB utilisent des triplets séparés par des espaces, pour composer
`rgb(var(--rgb-accent) / alpha)`. Chaque palette explicite porte les **42 variables**
de l'inventaire actuel de `ThemeVariableCompletenessTest`. Copier un bloc complet
évite d'hériter de couleurs inadaptées lors d'un changement de thème.

Les paramètres personnels (flou, densité, typographie, etc.) ne doivent pas être
recopiés dans chaque palette. L'inventaire et la liste `KNOBS` du test sont la
référence pour distinguer les deux.

La feuille de lecture a ses propres liens et états : une couleur claire lisible
sur le fond sombre de Nord peut devenir illisible sur sa feuille claire.

### Exemple local : Vinz

`Theme::Vinz` / `data-theme="vinz"` reprend les fonds et textes de Nord.
Accent des boutons : teal `#0f766e`, encre blanche ; relief et survols : orange
`#fdba74` avec alpha ; avertissements : jaune `#fde047` ; information : violet
`#d8b4fe`. Les couleurs de lecture sont adaptées séparément.
Vinz est sombre et n'a pas de `LogoStyle` homonyme ; le logo suit donc les règles
de repli de `effectiveLogoStyle()` / `effectiveLogoPaint()`.

## 5. Procédures de modification

### Ajouter un thème

1. Ajouter un cas dans `Theme.php`, un `swatch()` cohérent et le déclarer sombre
   dans `isDark()` si nécessaire. `defaults()` en déduit l'accent.
2. Ajouter la palette complète `:root[data-theme="nom"]` dans `app.css`.
   Conserver `color-scheme` et des variables de feuille de lecture appropriés.
3. Ajouter `settings.appearance.theme.nom` dans chaque `translations/messages.*.yaml`
   pour un thème sans `LogoStyle` homonyme.
4. Le sélecteur Twig distingue les thèmes via `theme.logoStyle` : ceux sans
   couleur de logo homonyme restent visibles dans la rangée principale ; les
   autres utilisent aussi la grille « Plus ». Contrôler ce placement.
5. Vérifier sélection, accent initial, retour depuis un autre thème, sauvegarde
   et rechargement. Construire puis déployer l'image locale.

Un ajout de valeur d'enum seule ne crée pas une nouvelle colonne. Vérifier tout
de même les contraintes de stockage : la colonne du thème a une longueur de 16.
Un nouveau champ persistant exige, lui, d'examiner une migration Doctrine.

### Ajouter un réglage personnel

Suivre toute la chaîne : propriété/bornes dans `Appearance`, `applyArray()` et
`toArray()`, migration éventuelle, renderer, contrôles Twig et traduction,
comportement immédiat JavaScript, sauvegarde et tests. L'aperçu doit suivre.
Si la propriété est exposée aux clients JMAP, mettre aussi à jour
`src/Jmap/Mapper/AppearanceMapper.php`, les méthodes Appearance/get et /set et
`src/Jmap/Session/SessionBuilder.php` selon le contrat voulu.

Le format JMAP est distinct de l'export JSON : ne pas remplacer son mapper par
`toArray()`. L'export est versionné à 1 et ne transporte pas l'image personnelle ;
un fond Custom est exporté comme fond du thème. L'import refuse les autres versions.

### Modifier un composant existant

Repérer son template et les utilitaires réellement utilisés avant de changer une
couleur globale. Préférer les rôles sémantiques existants. Vérifier les états normal,
survol, focus, actif et désactivé ainsi que le rendu sombre, clair et mobile.
Une modification de `--rgb-info` touche tous les états d'information, pas seulement
le composant qui a motivé la demande.

## 6. Vérifications adaptées

| Changement | Couverture existante à consulter/exécuter |
|---|---|
| Palette | `tests/Service/Appearance/ThemeVariableCompletenessTest.php`, `ThemeInkContrastTest.php` |
| Accent/boutons | `AccentInkContrastTest.php`, `AccentTextContrastTest.php` dans le même dossier |
| Liste, police ou densité | `ListAndTypographyVariablesTest.php`, `tests/e2e/appearance/list-display.spec.ts`, `density.spec.ts` |
| Sélection et persistance | `tests/e2e/appearance/theme.spec.ts`, `theme-switch-parity.spec.ts`, `layout.spec.ts` |
| Fonds et aperçu | `tests/e2e/appearance/background.spec.ts`, `preview-pane.spec.ts` |
| Écrans étroits et échelle | `tests/e2e/appearance-shots.spec.ts` |
| Logos | `tests/Entity/Embeddable/AppearanceLogoTest.php`, tests sous `tests/Domain/Enum/Theme/` |
| JMAP | `tests/Jmap/Method/Settings/Appearance*MethodTest.php`, `tests/Jmap/Session/AppearanceCapabilityTest.php` |

Les tests de contraste vérifient aussi les textes d'état sur leurs fonds teintés
et les couleurs de lecture, avec un seuil de 4,5:1. Une mesure du bouton seul ne
valide pas tout le thème.

La stack de tests est **`compose.test.yaml`**, distincte de l'instance personnelle.
Les scripts disponibles sont déclarés dans `package.json`. Pour une suite PHP
ciblée, une fois la stack de tests préparée :

```powershell
docker compose -f compose.test.yaml run --rm --no-deps --entrypoint docker-php-entrypoint app sh bin/test-unit.sh tests/Service/Appearance
```

Le script prépare une base de tests dédiée. Les scénarios Playwright préparent
leurs utilisateurs et leurs données : les lancer contre la stack de tests.
Ne pas lancer les commandes de réinitialisation de tests sur l'instance personnelle.
Pour une modification documentaire seule, vérifier les chemins, les liens et le diff
suffit ; ces suites n'ont pas été exécutées pour la rédaction de ce guide.

Sous Windows, le montage des sources peut ralentir fortement les tests. Lors de
la vérification de la recherche avancée, `AppVersion` exécutait notamment
`git describe --tags --always --dirty` à chaque requête sans version fournie ;
cela pouvait dépasser la limite PHP. Sur une stack de tests dédiée, fournir une
`APP_VERSION` explicite évite ce parcours Git. Adapter les délais de test au besoin,
sans masquer une véritable erreur serveur ni modifier les données de l'instance locale.

## 7. Voir les changements dans l'instance locale

Configuration constatée le 18 octobre 2026 :

- `compose.yaml` seul décrit l'image publiée `ghcr.io/karatektus/pl_mail:latest`.
- `compose.override.yaml`, ajouté localement, est chargé automatiquement par un
  `docker compose` sans option `-f`. Il construit `pl_mail-local:latest` avec la
  cible `frankenphp_prod` pour php, worker et secrets-init.
- Sur un nouveau checkout, `compose.vinz.yaml` fournit la configuration de
  construction du fork : `docker compose -f compose.yaml -f compose.vinz.yaml up -d --build`.
  Ce fichier versionné remplace le besoin de recréer l'override local ; voir
  `docs/releases/Vinz-1.0.0.md` pour la première version du fork.
- Cette image **copie** le code à la construction : modifier un fichier du dépôt
  ne modifie pas immédiatement l'application exécutée.
- Accès HTTPS : `https://localhost:8443`, HTTP : `http://localhost:8085`.
  Ces ports viennent du `.env` local ; ils ne sont pas universels.

Depuis la racine du dépôt, après une modification à rendre visible :

```powershell
docker compose build php
docker compose up -d --no-build php worker
docker compose ps
curl.exe -k -s -o NUL -w '%{http_code}' https://localhost:8443/healthz
```

Attendre que php soit healthy et que `/healthz` réponde 200, puis recharger les
réglages avec Ctrl+F5. Le worker n'a pas de healthcheck ; `up --wait php worker`
peut donc signaler une erreur de vérification même si les services ont démarré.
La reconstruction compile déjà Tailwind et AssetMapper : ne pas s'arrêter à un
simple redémarrage de l'ancienne image.

Les scripts d'entrée doivent garder des fins de ligne LF sous Windows ;
`.gitattributes` fixe `*.sh text eol=lf`. Un message « generate-secrets: no such
file or directory » avec un fichier présent peut révéler un shebang en CRLF.

`compose.dev.yaml` propose une autre organisation : montage du code et service
Tailwind en surveillance. Elle n'est pas la configuration actuellement utilisée.
Avec `-f`, Compose utilise les fichiers explicitement demandés ; relire leur
composition avant de mélanger la configuration locale et celle de développement.
Ne pas supprimer les volumes pour actualiser un thème : ils portent les données.

## 8. Autres surfaces à ne pas oublier

- `src/Service/Appearance/PublicAppearanceResolver.php` et le DTO `PublicAppearance`
  habillent les pages publiques selon leur propriétaire ; les images de fond
  personnelles protégées ne sont pas exposées aux visiteurs.
- `src/Service/Onboarding/Step/AppearanceStepHandler.php` et
  `templates/onboarding/steps/_appearance.html.twig` constituent un autre accès
  aux réglages.
- `bin/build-site.php` consomme aussi `Theme::cases()` et `swatch()` pour le site
  de démonstration. Si la demande concerne ce site, vérifier sa génération.
- `DESIGN.md` décrit les choix visuels ; `docs/features/appearance.md` décrit
  l'usage ; `docs/CLIENT_DEVELOPMENT.md` décrit les clients. Ce guide donne la
  carte technique pour intervenir rapidement, sans les recopier intégralement.

## 9. Entretien de cette cartographie

À chaque changement concerné, actualiser les fonctions disponibles, chemins,
priorités, procédures et tests touchés. Signaler explicitement ce qui a été testé
et ce qui reste une observation du code. Éviter de dupliquer tous les détails
d'implémentation ou des numéros de ligne qui se périment à chaque modification.

## Étoiles de couleur

Le switch `starColors` dans Apparence active par défaut le cycle jaune, orange, rouge, vert, turquoise, marine, noir, puis sans étoile. Désactivé, le clic bascule On/Off et les favoris apparaissent jaunes. Les index locaux `starColor` des messages et conversations sont stockés séparément du drapeau fournisseur. Points d’entrée : `ThreadStatusController`, `ThreadStatusUpdater`, `StarColor`, `_thread_row.html.twig` et `message_actions_controller.js`. Migration : `Version20261018160000`.

Le motif `Vinz` ajoute la favicon fournie dans la liste Logo. Son PNG est embarqué dans `templates/branding/motif/vinz.svg.twig` pour les trois rendus (logo, tuile et favicon) ; ses couleurs originales restent fixes. Source conservée dans `public/icons/vinz.png`.

## Style des labels

`labelStyle` (`LabelStyle.php`) choisit Discret (défaut) ou Plein dans Apparence.
Le renderer et les options Twig partagent les variables de visibilité ;
`_thread_labels.html.twig` et `_label_plain.html.twig` rendent les pastilles.
Le mode Plein utilise la couleur du label (palette Tailwind 500) et le noir ou
le blanc offrant le meilleur contraste WCAG selon la luminance relative.
La préférence suit sauvegarde, export/import et JMAP ; migration `Version20261019120000`.

## Liste Neat et inversion du panneau central

`listDensity = neat` ajoute une ligne fixe de 30 px, un avatar de 22 px et les
labels avant le sujet. Neat est proposé uniquement pour Message list, après Compact.
Le sujet est suivi de `|` puis du début du message, tronqué selon la largeur disponible.
Les labels sont affichés uniquement avant le sujet ; leurs copies standard restent masquées.
Les hooks `data-row-neat-labels`, `data-row-neat-text`, `data-row-content` et la classe racine `list-neat`
pilotent le rendu réel et la miniature ; les autres densités gardent leur disposition.

`invertMessagePanel` (désactivé par défaut) ajoute la classe racine
`invert-message-panel`. Avec `.dark`, les conteneurs `data-message-panel` reçoivent
une palette locale claire ; la navigation et les réglages gardent leur thème.
`messagePanelContrast` (défaut 70, borné 0–100) interpole entre #808080 et #ffffff.
Le renderer et le contrôleur écrivent `--message-panel-grey` avec la même formule.
L’inversion prend le dessus sur la teinte et l’ancienne opacité principale du panneau tant
qu’elle est active et le thème sombre. Elle suit aussi le thème System.
Stockage : migration `Version20261019140000`, export/import et contrat JMAP.

### Opacité du bloc de courrier

`messagePanelOpacity` (0–100, défaut 100) est le curseur suivant Contrast strength.
La variable `--message-panel-opacity` règle uniquement le fond de `data-message-panel`,
pas l’opacité du texte ou des boutons. Elle multiplie l’opacité principale existante
et reste active quand le panneau est inversé (dont le fond était auparavant opaque).
L’aperçu partage ce contrat. Sauvegarde, export/import et JMAP suivent `Appearance` ;
migration `Version20261019160000`.

### Raccourcis permanents Settings et Reply

`_partials/_topbar.html.twig` ajoute un engrenage `data-topbar-settings` près de
Help et Sync, vers la même destination que le menu utilisateur (Health si un
compte nécessite une intervention). `mail/_message_actions.html.twig` ajoute
`data-message-quick-reply` entre la date et le menu à trois points : même route,
paramètres de conversation et contrôleur `compose--frame-target` que Reply dans
le menu, vers `compose_inline` ou `compose_dock`. Noms accessibles et infobulles
réutilisent les traductions existantes ; les boutons des menus restent disponibles.

### Navigation entre les résultats ouverts

La barre de `mail/_thread_content.html.twig` propose deux boutons précédent/suivant
à droite du titre. `mail_pane_controller.js` mémorise les liens de la page de liste
au clic d’ouverture et conserve leur ordre pendant la lecture (y compris pour une
recherche de non-lus). Les extrémités et le chargement désactivent les boutons.
Le retour conserve l’URL de recherche, ses filtres, son tri et sa page ; une nouvelle
navigation de liste réinitialise le parcours. Le parcours couvre la page de résultats
chargée, sans franchir la pagination. Test : `tests/e2e/search-navigation.spec.ts`.

### Contraste des lignes lues et onglet actif

`row-read` dans `assets/styles/app.css` pose un gris neutre (128 128 128 à 12 %)
aux messages lus de `_thread_row.html.twig` et de l’aperçu Apparence. Les non-lus
conservent `row-unread` et leur réglage d’accentuation ; le survol reste prioritaire.
Dans `mail/inbox.html.twig`, les onglets de catégorie réservent une bordure basse
de 4 px, colorée avec l’accent pour l’onglet actif, transparente au repos ailleurs.
