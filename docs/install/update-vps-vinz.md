# Mettre a jour le VPS Vinz depuis le PC

Cette procedure concerne l'installation existante sur `vmail.lagratte.net` :
Compose `/home/migration/vmail/compose.yaml`, projet `vmail`, image de production
`pl_mail-local:latest`, services `php`, `worker`, `database` et `secrets-init`.
Le compte administrateur utilise est `ubuntu`. Le script ne modifie pas Traefik,
les autres stacks, les volumes ou les secrets de production.

## Utilisation

Depuis PowerShell, dans le depot local, apres validation des nouveautes :

```powershell
.\bin\update-vps.ps1
```

Le script reconstruit automatiquement le service `php` avec `compose.vinz.yaml`
avant de transferer l'image `pl-mail-vinz:local`. Les modifications locales non
commitees sont donc incluses. Il ne demarre pas les workers locaux et ne transfere
aucune base ou secret du PC. `-SkipBuild` permet de reutiliser une image deja validee ;
une image personnalisee (`-Image`) exige cette option.

Une connexion SSH fonctionnelle avec `ubuntu@91.214.190.192` et les droits sudo sont
necessaires. Les demandes SSH/sudo restent interactives ; aucun mot de passe ne doit
etre inscrit dans les scripts. Pour adapter la destination :

```powershell
.\bin\update-vps.ps1 -Server 91.214.190.192 -User ubuntu -Port 22
```

`-WhatIf` affiche l'intention sans exporter ni transferer de fichier.

### Cle SSH et connexion

Le script se connecte au compte `ubuntu`, pas au compte temporaire `migration`.
Sa cle publique doit etre autorisee dans `/home/ubuntu/.ssh/authorized_keys`.
Supprimer l'ancienne cle du compte `migration` ne configure pas cet acces.
Pour selectionner une cle privee personnelle :

```powershell
.\bin\update-vps.ps1 -IdentityFile "$env:USERPROFILE\.ssh\vmail_deploy" -CheckSshOnly
.\bin\update-vps.ps1 -IdentityFile "$env:USERPROFILE\.ssh\vmail_deploy"
```

`-CheckSshOnly` teste uniquement la connexion, sans export ni deploiement.
Le script teste aussi SSH avant tout export d'image et utilise l'algorithme
`curve25519-sha256` compatible avec le client OpenSSH Windows observe.
Ne pas desactiver la verification de l'identite du serveur ni transmettre la cle privee.

## Ce que fait le script

1. Reconstruit puis exporte l'image locale et verifie son empreinte apres transfert SSH.
2. Verrouille les mises a jour concurrentes et controle les images actives.
3. Verifie l'espace disque et prepare Gotenberg, puis arrete temporairement le web et le worker.
4. Sauvegarde la base et le stockage avec l'ancienne image ; archive aussi tout le
   volume de secrets, notamment les cles JWT. Conserve une reference de l'ancienne image.
5. Importe la nouvelle image, lui attribue le nom attendu par le Compose du VPS et
   installe le complement Compose des apercus et actualise les services applicatifs.
   Les migrations s'executent au demarrage.
6. Verifie `/healthz`, les checks database/queue/workers et les images reellement
   executees, la connexion a Gotenberg, puis controle l'acces HTTPS public.
7. Supprime les archives d'image de cet envoi apres succes, en conservant la sauvegarde.

Le compose principal reste intact. Le complement gere est
`/home/migration/vmail/compose.vinz-preview.json`, avec sa definition
`compose.gotenberg.yaml`. Les commandes d'administration Compose doivent utiliser
`-p vmail -f compose.yaml -f compose.vinz-preview.json`. Les reseaux et labels du web
sont conserves. Gotenberg rejoint uniquement un reseau interne, sans port public ni
volume de courrier ; sur ce VPS de 2 Gio, il est limite a 512 Mio et 0,5 CPU.
Les deux fichiers du complement sont aussi sauvegardes lors des mises a jour suivantes.

Les sauvegardes sont sous `/home/migration/vmail/backups/<date UTC>/`, en acces
prive. Elles occupent de la place et contiennent donnees personnelles et secrets :
les conserver hors VPS, avec protection adaptee et separation durable des secrets
et de la base. Aucune suppression automatique des sauvegardes n'est effectuee.

Si la sauvegarde ou l'import echoue avant tout nouveau demarrage, le script tente
de relancer l'ancienne version. Apres le debut du deploiement, il ne revient pas
automatiquement en arriere : une migration peut avoir modifie le schema. Consulter
les journaux et le guide [Sauvegarde et restauration](backup-restore.md).

## Verification utilisateur

Apres succes, ouvrir `https://vmail.lagratte.net`, recharger avec Ctrl+F5, verifier les
nouveautes, une piece jointe et une synchronisation. Un etat de sante correct ne
remplace pas ces verifications fonctionnelles.

## Evolution possible via le depot

Le workflow `.github/workflows/docker.yml` construit deja des images dans GHCR.
Dans la configuration examinee, les pushes automatiques concernent `main` et les
tags `v*`, pas `develop`. Une publication du fork avec un canal explicite permettrait
ensuite au VPS de telecharger ses images directement, sans transfert depuis le PC.
Cette evolution n'est pas activee par ces scripts.

## Transfert et espace disque

Le transfert utilise `image.tar.gz` (compression .NET de PowerShell), avec empreinte
SHA-256 vérifiée avant import Docker. Le contrôle d’espace réserve les fichiers de
courrier, trois fois la taille PostgreSQL, puis au moins 1,5 Gio (ou la taille totale
de l’image applicative décompressée + 512 Mio si davantage). Il ajoute 2 Gio si
l’image du convertisseur n’est pas déjà installée. Aucune ancienne sauvegarde
n’est supprimée pour faire de la place. Un convertisseur déjà présent est réutilisé.

Le contrôle HTTPS public attend la publication du conteneur sain par Traefik :
une réponse 404 temporaire juste après redémarrage est réessayée pendant environ
une minute, au lieu de déclarer un échec après une seule requête.
