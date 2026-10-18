# Composants visuels partagés

Inventaire des sources consultées le **18 octobre 2026**, complément du guide de
[personnalisation](PERSONNALISATION.md). Les chemins sont relatifs au dépôt.
Les usages et exceptions ci-dessous sont vérifiés dans le code ; tous les écrans
n'ont pas été ouverts et les suites citées n'ont pas été exécutées pour ce scan.

## Organisation

Twig construit le HTML, Tailwind les styles, Stimulus les interactions et Turbo
les navigations/remplacements de fragments. `assets/app.js` charge les comportements
globaux, `assets/stimulus_bootstrap.js` initialise Stimulus. L'identifiant
`ui--modal` correspond au contrôleur de `assets/controllers/ui/modal_controller.js`.
Le layout `templates/_layout/app.html.twig` lie les styles : ils ne sont pas
importés dans le JavaScript.

Les composants sont partagés à plusieurs niveaux : template pour une structure,
macro pour des classes/attributs, contrôleur pour un comportement, utilitaire CSS
pour un style. Les boutons n'ont pas tous un template commun.

## Inventaire et usages

| Élément | Définition et comportement | Usage réel à consulter |
|---|---|---|
| Carte de réglages | `templates/settings/_card.html.twig` | `templates/settings/_appearance.html.twig` |
| Habillage des cartes | `templates/_partials/_card_chrome.html.twig` | Réglages et `templates/admin/address/_frame.html.twig` |
| Fenêtre modale | `templates/_partials/_modal.html.twig`, `assets/controllers/ui/modal_controller.js` | Aide aux raccourcis dans `templates/_partials/_list_toolbar.html.twig` |
| Recherche avancée | `templates/search/_advanced.html.twig`, `assets/controllers/mail/advanced_search_controller.js` | Bouton à droite du champ de recherche dans la barre supérieure ; utilise la coque modale commune |
| Formulaire modal | `templates/_partials/_modal_form.html.twig` | `templates/label/_form.html.twig`, `templates/calendar/_form.html.twig` |
| Champs Symfony | `templates/_layout/form/modal_form_theme.html.twig`, enregistré dans `config/packages/twig.yaml` | Formulaires de labels et calendriers |
| Champs de rédaction | `templates/_layout/form/compose_theme.html.twig` | `templates/compose/_window.html.twig` et formulaires de rédaction |
| Select enrichi | `templates/_partials/_select.html.twig`, `assets/controllers/ui/select_controller.js`, `assets/styles/tom-select.css` | `templates/calendar/_event_editor.html.twig`, `templates/settings/filters/_editor.html.twig` |
| Menu déroulant | `assets/controllers/ui/dropdown_controller.js` et HTML propre au menu | `templates/_partials/_sort_menu.html.twig`, `_move_menu.html.twig`, `_label_menu.html.twig` |
| Confirmation | `templates/_partials/_confirm_dialog.html.twig`, `assets/controllers/ui/confirm_controller.js`, `assets/confirm.js` | Déclencheurs `data-turbo-confirm` et appels `askConfirm()` |
| Notification temporaire | `templates/_partials/_toast.html.twig`, `_toast.stream.html.twig`, `assets/controllers/ui/toast_controller.js` | `assets/toast.js`, réponses Turbo Stream |
| Erreur de requête | `assets/request_errors.js`, `assets/csrf.js` | Sauvegarde dans `assets/controllers/settings/appearance_controller.js` |
| Infobulle | `assets/controllers/ui/tooltip_controller.js`, style `tooltip-bubble` | Éléments portant `title` ou `data-tooltip` |
| Copie de texte | `assets/controllers/ui/clipboard_controller.js` | Référence d'erreur créée par `assets/toast.js` |
| Champ fichier | `assets/controllers/ui/file_field_controller.js`, thème de formulaire | Widgets de fichier Symfony |
| Séparateur redimensionnable | `assets/controllers/ui/split_controller.js` | Panneaux utilisant ce contrôleur, notamment l'aperçu des réglages |
| Navigation latérale | `assets/controllers/ui/sidebar_controller.js`, `sidebar_drawer_controller.js` | `templates/_partials/_sidebar.html.twig` |

## Boutons et couleurs : attention aux états locaux

Dans `assets/styles/app.css`, `bg-accent` et `text-accent-ink` fournissent fond et
encre d'action principale, `hover:bg-accent-strong` un survol assombri.
`bg-hover`, `text-ink-soft`, `text-ink-faint` servent aux commandes secondaires.
`btn-quiet` utilise `--rgb-raised` et son alpha, renforcés au survol.
`focus-visible:ring-accent` matérialise le focus clavier.

Dimensions, espacements, arrondis et variantes de nombreux boutons restent dans
les templates. Chercher le bouton avant de changer une variable globale.

**Exception constatée, non corrigée pendant ce scan :** la branche principale du
bouton de validation dans `_modal_form.html.twig` impose `active:bg-blue-800` ;
sa branche danger utilise des rouges Tailwind explicites. Modifier l'accent du
thème ne recolore donc pas tous ses états. Vérifier normal, survol, appui, focus
et désactivation lors d'une demande d'uniformisation, notamment pour Vinz.

Une icône seule doit conserver un nom accessible traduit, un focus visible et une
zone de clic adaptée. Une infobulle ne remplace pas le nom accessible.

## Cartes et surfaces

Les macros `shell()`, `band()`, `body()` de `_card_chrome.html.twig` retournent des
classes. Réglages et panneaux administratifs repliables partagent ainsi l'habillage
sans recevoir le même HTML ou comportement.

Dans les réglages, préférer un `embed` de `settings/_card.html.twig`, avec
`heading`, `subheading`, `padded`, `class` et les blocs `body` / `actions`.
`padded` est faux par défaut : une liste dont les lignes ont déjà leur marge
interne ne doit pas recevoir une deuxième marge via `chrome.body()`.

Distinguer les utilitaires `pane`, `pane-flat`, `popover`, `main-pane`, `mail-sheet`
dans `app.css`. Les panneaux, surfaces flottantes et feuilles de lecture n'utilisent
pas tous les mêmes variables. Consulter le guide de personnalisation pour les
opacités et la palette propre à la lecture des messages.

## Modales et formulaires

La coque globale contient un Turbo Frame **`modal`**. Un déclencheur porte
`data-controller="ui--modal"`, l'action `click->ui--modal#open` et
`data-ui--modal-src-value`. Titre et taille sont optionnels. La réponse chargée
doit correspondre au frame attendu.

La coque définit les tailles `narrow`, `form`, `wide`, `split`, `full` dans son
HTML, afin que Tailwind voie les classes lors de la compilation. Éviter des noms
de classes construits uniquement à l'exécution.

Le contrôleur gère Escape, le confinement du focus et son retour au déclencheur.
La fermeture par le fond examine le geste complet pour ne pas fermer lors d'une
sélection de texte terminée hors du panneau. Préserver aussi le défilement interne
et la superposition au-dessus de la fenêtre de rédaction.

`_modal_form.html.twig` fournit frame, erreurs globales, corps et pied de formulaire.
Paramètres utiles : `title`, `submit_label`, `cancel_label`, `danger`, `form_attr` ;
blocs `before_fields`, `after_fields`. Consulter ce contrat avant de redessiner un
pied local. Le thème de formulaire global agit au-delà des seules modales ; la
rédaction possède un thème distinct.

## Selects et menus

Pour un select écrit à la main, importer `_partials/_select.html.twig` et appeler
`select.enhance(bare, searchAfter)`, avec les classes `select-field` appropriées.
Les formulaires Symfony reçoivent l'enrichissement via leur thème.

Tom Select écrit dans le select natif et émet `change` ; le select reste la source
du formulaire. `bare` fournit une variante sans cadre ; `searchAfter` règle le
seuil de recherche, actuellement 8. Le message « aucun résultat » est traduit par
Twig. Vérifier clavier, absence de JavaScript et reconnexion après remplacement Turbo.

Un menu `ui--dropdown` possède une cible `menu` et utilise l'attribut HTML
**`hidden`**, pas seulement une classe CSS. Le contrôleur gère bascule, clic
extérieur, Escape et l'événement `opened`. Les menus métier ajoutent leurs règles,
par exemple le recalcul des horaires du menu de report à l'ouverture. Le contrôleur
générique ne garantit pas toute la navigation clavier d'un menu spécialisé.

## Confirmations, notifications et erreurs

`assets/confirm.js` raccorde Turbo au dialogue commun et exporte
`askConfirm(message)` pour les actions avec `fetch()`. `data-turbo-confirm` ne
protège pas arbitrairement un appel fetch. Le dialogue natif `<dialog>` place le
focus sur Annuler et résout une promesse ; sur un layout sans dialogue, la fonction
retombe sur `window.confirm()`. Le module prévoit aussi les formulaires sans Turbo.

Les notifications serveur et JavaScript partagent `_toast.html.twig`.
`showToast(message, { type, reference })` dans `assets/toast.js` clone les modèles
du layout dans `toast-region`. Types : `success`, `error`, `info` ; durée par défaut
du contrôleur : 4 secondes, modifiable par le template. Une référence d'erreur
peut être affichée et copiée. Réutiliser ce chemin plutôt qu'un habillage créé
dans chaque contrôleur.

`assets/request_errors.js` intercepte les erreurs Turbo et fournit `requestFailed()`
aux appels directs. Il laisse notamment 422 au rendu des erreurs de formulaire,
401/403 aux traitements de session/autorisations. `data-turbo-errors="self"`
indique qu'un composant traite ses erreurs. Pour les POST JSON au jeton partagé
`ajax`, réutiliser `jsonCsrfHeaders()` de `assets/csrf.js` ; les jetons propres à
une action conservent leur chemin spécifique.

## Infobulles, badges, icônes et mouvements

Le contrôleur global transforme `title` en `data-tooltip` et relie la bulle par
`aria-describedby`. La bulle est placée sur le body pour éviter le découpage et
les problèmes de positionnement des panneaux avec flou. Éviter les copies locales
destinées seulement à contourner un parent `overflow-hidden`.

`app.css` fournit `badge-danger`, `badge-warning`, `badge-success`, `badge-neutral`,
`badge-info`, `alert-danger`, `alert-info`, `pill-done`, `pill-failed`, `chip-filter`.
Ces rôles touchent plusieurs composants : vérifier les fonds teintés et la lecture.

Font Awesome est déclaré dans `importmap.php` et lié par le layout ; les logos ont
un système distinct. Si JavaScript change un glyphe, vérifier sa disponibilité dans
la famille retenue : remplacer `fa-copy` par `fa-check` ne suffit pas si la famille
de départ ne contient pas ce second glyphe.

`assets/motion.js` et `assets/styles/motion.css` centralisent les mouvements ;
modales et notifications utilisent notamment `leave()`. Préserver Full/Minimal/None
et la réduction système. Ne pas attendre uniquement `transitionend` quand une
animation peut être désactivée.

## Vérifications adaptées

| Sujet | Tests existants à consulter/exécuter selon le changement |
|---|---|
| Cartes et marges | `tests/Infrastructure/Templating/CardsUseTheChromeMacrosTest.php`, `tests/e2e/settings-card-insets.spec.ts`, `tests/e2e/admin-card-insets.spec.ts` |
| Contrat des menus | `tests/Infrastructure/Templating/DropdownMenusUseHiddenAttributeTest.php` |
| Selects | `tests/e2e/select-widget.spec.ts`, aide `tests/e2e/support/select.ts` |
| Confirmations | `tests/e2e/confirm-dialog.spec.ts`, aide `tests/e2e/support/confirm.ts` |
| Infobulles | `tests/e2e/tooltip.spec.ts` |
| Erreurs de requêtes | `tests/e2e/request-errors.spec.ts` |
| Recherche avancée | `tests/e2e/search-advanced.spec.ts`, `tests/js/advanced-search.test.mjs`, `tests/Service/Search/SearchQueryParserTest.php` |
| Rédaction et police | `tests/e2e/compose.spec.ts`, `tests/e2e/compose-typography.spec.ts` |
| Palettes et contrastes | Suites détaillées dans `PERSONNALISATION.md` |

Vérifier le scénario métier touché, les états pertinents et les variantes
clair/sombre, clavier et mobile. Les tests créant des données utilisent la stack
dédiée. Le guide de personnalisation décrit la reconstruction de l'instance locale.
Actualiser cet inventaire quand un composant, un contrat ou un usage change.

Les tests autonomes de conversion de recherche se lancent avec
`node --experimental-default-type=module --test tests/js/advanced-search.test.mjs`.
La modale conserve les termes hors de ses champs, les valeurs avec espaces ou
guillemets et rend exclusifs lu/non lu. Elle émet `mail:advanced-search` vers le
contrôleur de recherche, qui réutilise sa navigation et son historique existants.
