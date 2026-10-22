# Carte du code par demande

Index des sources vérifié le **18 octobre 2026**. Les chemins sont relatifs au dépôt.
La personnalisation et les composants partagés ont été explorés ; les autres
entrées sont des points de départ, sans audit complet de leur logique métier.

## Guides

- [Mise a jour du VPS Vinz](install/update-vps-vinz.md) : construction et deploiement en une commande depuis le PC, sauvegarde et controles ; `bin/vps-preview-config.py` ajoute le complement Compose prive de Gotenberg.
- [Affichage et personnalisation](PERSONNALISATION.md) : palettes, réglages,
  sauvegarde, priorités, tests et actualisation de l'instance Docker.
- [Composants visuels partagés](COMPOSANTS_UI.md) : cartes, boutons, champs,
  modales, menus, selects, confirmations, notifications, infobulles et exceptions.
- [Guide utilisateur de l'apparence](features/appearance.md) : comportement des
  réglages ; confronter les valeurs par défaut au code actuel.
- [Principes visuels](../DESIGN.md) et [conventions](../CODESTYLE.md).

## Points d'entrée

| Demande | Où commencer | Suite à suivre |
|---|---|---|
| Ajouter un thème, modifier Vinz | `src/Domain/Enum/Theme/Theme.php`, `assets/styles/app.css` | Guide de personnalisation, traductions et construction locale |
| Changer les boutons ou leurs états | `assets/styles/app.css`, template du bouton | Inventaire des composants et couleurs imposées localement |
| Modifier les cartes | `templates/_partials/_card_chrome.html.twig`, `templates/settings/_card.html.twig` | Usages réglages/administration et tests des marges |
| Ajouter un champ | `config/packages/twig.yaml`, `templates/_layout/form/modal_form_theme.html.twig` | Formulaire dans `src/Form/`, validation et sauvegarde |
| Ouvrir une fenêtre | `templates/_partials/_modal.html.twig`, `templates/_partials/_modal_form.html.twig` | `assets/controllers/ui/modal_controller.js`, frame et focus |
| Ajouter menu ou select | `templates/_partials/_select.html.twig`, `assets/controllers/ui/dropdown_controller.js` | Contrôleur de select, styles Tom Select ou menu métier |
| Ajouter confirmation ou notification | `assets/confirm.js`, `assets/toast.js` | Partial partagé, traduction, erreurs et chemin Turbo/fetch |
| Modifier la recherche avancée | `templates/search/_advanced.html.twig`, `assets/controllers/mail/advanced_search_controller.js` | Conversion des champs dans `assets/advanced_search.js`, parseur `src/Service/Search/SearchQueryParser.php`, navigation dans `assets/controllers/mail/search_controller.js` |
| Changer une ligne de courrier | `templates/_partials/_thread_row.html.twig` | `assets/controllers/mail/message_row_controller.js`, `src/Controller/Mail/MailController.php` |
| Modifier la barre d'actions | `templates/_partials/_list_toolbar.html.twig` | `assets/controllers/mail/list_toolbar_controller.js`, contrôleur serveur de l'action |
| Modifier le message ouvert | `templates/mail/_thread_content.html.twig`, `templates/mail/_thread_message.html.twig` | `assets/controllers/mail/thread_read_controller.js`, `assets/controllers/mail/thread_message_controller.js` |
| Modifier la rédaction | `templates/compose/_window.html.twig` | `assets/controllers/compose/compose_controller.js`, `assets/controllers/compose/compose_toolbar_controller.js`, `src/Controller/Mail/ComposeController.php` |
| Changer navigation ou présentation mobile | `templates/_layout/_mailbox.html.twig`, `templates/_partials/_sidebar.html.twig`, `templates/_partials/_topbar.html.twig` | Contrôleurs sidebar/sidebar_drawer sous `assets/controllers/ui/`, règles responsive |
| Changer la séparation liste/lecture | `assets/controllers/mail/reading_split_controller.js` | `src/Domain/Enum/Mail/ReadingPaneMode.php`, réglages dédiés et largeur disponible |
| Changer la hauteur du corps d'un message | `src/Domain/Enum/Theme/MessageBodySize.php`, `assets/controllers/mail/message_frame_controller.js` | Préférence `messageBodySize`, variable `--message-body-height`, template `templates/mail/_message_body.html.twig` ; indépendant de la densité |
| Ajouter une préférence visuelle | `src/Entity/Embeddable/Appearance.php` | Renderer, interface, sauvegarde, migration éventuelle, export et JMAP |
| Comprendre le démarrage frontend | `assets/app.js`, `assets/stimulus_bootstrap.js`, `templates/_layout/app.html.twig` | Comportements globaux, importmap et styles compilés |
| Une modification n'apparaît pas | `compose.override.yaml`, `Dockerfile` | Image exécutée et procédure de reconstruction de `PERSONNALISATION.md` |

## Méthode d'intervention

1. Identifier le template et un usage comparable.
2. Distinguer style partagé, HTML, comportement JavaScript et données serveur.
   Déterminer si la demande vise un écran ou tous les usages du composant.
3. Lire les attributs `data-controller`, `data-action`, cibles Stimulus et frames
   Turbo avant de modifier la structure.
4. Suivre la sauvegarde si une option ou action est ajoutée. Respecter les chemins
   existants de validation, CSRF, erreurs et confirmation.
5. Vérifier le scénario et les variantes pertinentes, avec la stack dédiée pour
   les tests qui créent ou réinitialisent des données.
6. Actualiser les guides concernés et l'instance locale si nécessaire. Distinguer
   lecture du code, tests exécutés et observations à l'écran dans le compte rendu.

## Explorations encore utiles

Le parcours complet d'un message (données, liste, lecture, actions), les brouillons
et l'éditeur, les mises à jour temps réel et les panneaux calendrier restent à
cartographier en profondeur. Les points d'entrée ci-dessus ne garantissent pas
le comportement de toute leur logique.

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
Le snippet est masqué pour tenir sur une seule ligne, à toutes les largeurs.
Les hooks `data-row-neat-labels`, `data-row-content` et la classe racine `list-neat`
pilotent le rendu réel et la miniature ; les autres densités gardent leur disposition.

`invertMessagePanel` (désactivé par défaut) ajoute la classe racine
`invert-message-panel`. Avec `.dark`, les conteneurs `data-message-panel` reçoivent
une palette locale claire ; la navigation et les réglages gardent leur thème.
`messagePanelContrast` (défaut 70, borné 0–100) interpole entre #808080 et #ffffff.
Le renderer et le contrôleur écrivent `--message-panel-grey` avec la même formule.
L’inversion prend le dessus sur la teinte et l’ancienne opacité principale du panneau tant
qu’elle est active et le thème sombre. Elle suit aussi le thème System.
Stockage : migration `Version20261019140000`, export/import et contrat JMAP.

## Aperçus Office

Le lecteur PDF partagé ouvre aussi Word, Excel et PowerPoint :
`OfficeAttachment`, `OfficePreviewConverter`, `AttachmentController::officePdf()`,
`AttachmentPreviewExtension::canOpen()`, `_attachment_chip.html.twig` et
`_attachment_preview.html.twig`. Le lecteur envoie un POST CSRF pour les dérivés
Office ; les PDF natifs conservent leur GET et leurs outils de signature.
Le téléchargement vise toujours l’original. Voir [le guide Office](features/office-preview.md)
pour Gotenberg, les limites, le cache privé et la stack de tests.

## Sécurité des contenus distants

L’onglet Security précède Images dans `templates/settings/index.html.twig` et
conserve les cartes du mot de passe et de la double authentification.
`settings/_content_security.html.twig` propose HTTPS uniquement, activé par défaut.
`ImageSettingsController::updateSecurity()` vérifie le CSRF et stocke
`User::remoteHttpsOnly` dans le sac de réglages, sans migration.
`MessageRenderer` transmet ce choix à `RemoteContentBlocker` (images et fonds CSS).
`ImageProxySigner` signe aussi la politique HTTP : modifier le paramètre invalide
le lien du proxy anonyme. `ImageProxyFetcher` vérifie la politique avant le cache
et chaque redirection ; caches séparés pour les deux politiques. Le mode permissif
reste limité aux ports HTTP 80 et HTTPS 443, avec protection SSRF et TLS inchangées.
Les liens sortants et pièces jointes ne sont pas concernés. Les imports de signature
utilisant le fetcher sans préférence restent HTTPS uniquement.

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

### Créer un label depuis un filtre

Dans `settings/filters/_editor.html.twig`, l’action Appliquer un label propose
Créer un label. `rules/rule_builder_controller.js` ouvre la modale partagée via
`LabelController::new()` avec `context=rule` et le nom courant de la règle.
Le formulaire `LabelType` conserve le choix racine/parent, la couleur, le CSRF,
la validation des doublons et les parents limités à l’utilisateur connecté.
Après création, `label/_rule_created.stream.html.twig` transmet le label à
`#filter-label-result` ; le contrôleur actualise ses choix et sélectionne le label
dans l’action d’origine sans remplacer l’éditeur ni sauvegarder la règle.
Annuler laisse l’éditeur intact. Le label créé existe indépendamment de la règle.
Test de parcours : `tests/e2e/filter-create-label.spec.ts` (racine et sous-label).

### Téléchargement des pièces jointes

`mail/_attachment_chip.html.twig` ajoute un lien de téléchargement direct aux
PDF et documents Office ouvrables dans le lecteur. Il apparaît au survol ou au
focus clavier, et reste visible sur écran tactile ; le bouton voisin ouvre le lecteur.
`_attachment_download_all.html.twig` propose le ZIP dès deux pièces non inline,
dans les deux vues de lecture. `AttachmentController::downloadAll()` vérifie la
propriété du message et des pièces, récupère les originaux via `AttachmentResolver`,
neutralise les chemins, conserve les noms en doublon avec un suffixe et supprime
le ZIP temporaire après l’envoi. Les liens de téléchargement échappent à Turbo.

### Dépôt de fichiers et collage dans la rédaction

`compose/_window.html.twig` définit deux surfaces `data-compose-drop-zone` :
`body` (zone de saisie) et `attachments` (barres de boutons). Le contrôleur
`compose_controller.js` accepte les fichiers, surligne la zone pendant le survol,
insère les images déposées dans le corps riche à la position du dépôt et envoie
les autres fichiers comme PJ. Sur les boutons, tous les fichiers deviennent des
PJ. En texte brut, les images déposées deviennent aussi des PJ.
Le collage et le sélecteur d’images capturent un Range propre avant tout envoi ;
`compose_toolbar_controller.js` utilise cette position même si le focus change
pendant la requête. Un lot d’images conserve son ordre. Le déplacement de texte
sans fichier reste natif. Les limites, le CSRF et les routes d’envoi existants
sont réutilisés. Régression : `tests/e2e/compose-file-drop.spec.ts`.


## Filtre de compte et date de récupération IMAP

`retrievalAccount` stocke l’ID interne du compte choisi dans le constructeur de
règles : `MailRuleController`, `FilterVocabulary`, `EmailFilterCompiler` et
`FilterDescriber`. La même comparaison SQL s’applique aux messages existants et
au pipeline d’ingestion. Le formulaire `AccountType` propose à la création
`imapRetrieveSince`, conservé sur `Account` (migration `Version20261022120000`).
`MessageSyncer::retrievalCriteria` ajoute `SINCE` aux critères UID de l’import
historique (`ImapMailboxImporter`) et de chaque synchronisation. La date inclut
le jour choisi et porte sur INTERNALDATE côté serveur. Aucun repli sans limite
n’est effectué en cas de refus du serveur. Les comptes existants gardent null.
Les sauvegardes conservent la date et remappent les IDs de compte des règles.
