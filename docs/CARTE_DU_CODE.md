# Carte du code par demande

Index des sources vérifié le **18 octobre 2026**. Les chemins sont relatifs au dépôt.
La personnalisation et les composants partagés ont été explorés ; les autres
entrées sont des points de départ, sans audit complet de leur logique métier.

## Guides

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
