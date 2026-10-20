# Aperçu des pièces jointes Office

Cliquer sur une pièce jointe PDF, Word, Excel ou PowerPoint ouvre le lecteur existant.
Les PDF sont lus directement dans PDF.js. Pour les documents Office, un indicateur
« Préparation de l’aperçu… » précède la conversion en PDF. Le téléchargement garde
le fichier original. Les outils de signature restent réservés aux PDF originaux.

Formats : doc, docx, xls, xlsx, ppt, pptx, odt, ods, odp. La détection utilise le
MIME ; avec application/octet-stream uniquement, elle utilise l’extension.
Les variantes OpenXML à macros ne sont pas proposées. Les animations PowerPoint
et l’interactivité Excel ne sont pas conservées ; le résultat est paginé et dépend
des polices disponibles dans LibreOffice.

## Exploitation Docker

`compose.yaml` et `compose.test.yaml` réutilisent `compose.gotenberg.yaml`.
Gotenberg 8.38.1-libreoffice a un réseau `document-preview` interne, aucun port
publié et aucun volume de courrier. Seul le service web rejoint ce réseau en plus
de son réseau habituel. Les téléchargements externes, webhooks et accès HTTP de
LibreOffice sont désactivés. Le service est limité à 1 CPU, 1 Gio et 256 processus.

La variable serveur `GOTENBERG_URL=http://gotenberg:3000` active les aperçus Office.
Sans cette variable (installation sans Docker ou ancienne stack), ils sont masqués ;
le téléchargement et le lecteur PDF restent disponibles.

Après installation ou modification :

```powershell
docker compose up -d gotenberg
docker compose build php
docker compose up -d --no-build php worker
docker compose ps
```

Pour le VPS Vinz, `bin/update-vps.ps1` reconstruit l’image et installe automatiquement
le service via un complément Compose, sans modifier le compose principal ni Traefik.
Le convertisseur y est limité à 512 Mio et 0,5 CPU pour ce serveur de 2 Gio.
Voir [la procédure VPS](../install/update-vps-vinz.md). Pour une autre installation
avec un compose personnalisé, ajouter le service, le réseau interne et la variable
web ; copier aussi `compose.gotenberg.yaml` si `extends` est utilisé.

## Limites et cache

Entrée : 25 Mio maximum, contrôlée avant récupération distante lorsque la taille
est connue, puis sur le fichier réel. Sortie : 50 Mio maximum, écrite en flux.
Gotenberg : délai total 20 s, corps multipart 27 MB, file d’attente de deux.
Client PHP : durée totale 25 s, sans redirection. Un verrou global limite à une
conversion simultanée par stockage partagé ; une seconde demande reçoit un message
invitant à réessayer. Les PDF natifs ne prennent pas ce verrou.

Le cache privé est sous `var/attachments/office-previews`, dans le volume existant.
Il est indexé par ID de pièce jointe, empreinte SHA-256, format et version de politique.
Les PDF sont publiés par renommage atomique, permissions 0600. Durée de validité :
7 jours ; les anciens PDF sont supprimés lors de la prochaine conversion non cachée.
Ce cache est inclus dans les sauvegardes du volume des pièces jointes.

Le contrôle de propriétaire est effectué avant chaque accès et chaque conversion.
La génération est un POST protégé par CSRF ; le résultat reste servi en attachment,
avec CSP sandbox et nosniff. La session est libérée avant conversion pour laisser
les autres onglets fonctionner. Les messages d’échec sont traduits et le lien de
téléchargement reste disponible.

## Vérification

Tests : `OfficeAttachmentTest`, `OfficePreviewConverterTest` et
`tests/e2e/office-preview.spec.ts`. Le scénario navigateur utilise les trois petits
documents synthétiques de `tests/fixtures/office` et `app:test:seed-attachment --office`,
exclusivement sur la stack de tests. Il vérifie le rendu, les originaux téléchargés,
le rejet sans CSRF, les PDF existants et le repli en cas d’échec.

Sur un montage Windows lent, les scans Symfony en mode debug peuvent dépasser
30 secondes avant même l’ouverture du courrier. La vérification locale a utilisé
un override de la stack de tests avec `APP_DEBUG=0`, une `APP_VERSION` explicite
et `max_execution_time=120` dans un fichier INI réservé aux tests. Après changement
du mode debug, régénérer le cache de tests avec `php bin/console cache:clear
--no-warmup --no-debug` dans le service de tests. Ces adaptations ne concernent
ni les données ni la configuration de l’instance personnelle.
