# Affichage et personnalisation

Pour trouver les points d'entrée par demande, consulter `docs/CARTE_DU_CODE.md`.
Pour les composants visuels partagés (cartes, boutons, champs, modales, menus,
confirmations, notifications, infobulles), lire aussi `docs/COMPOSANTS_UI.md` et
réutiliser les contrats existants. Actualiser ces guides si l'intervention change
un composant ou sa cartographie.

Pour une demande concernant les thèmes, couleurs, boutons, disposition, densité,
typographie, logos, fonds ou autres réglages d'affichage, lire d'abord
`docs/PERSONNALISATION.md`, puis vérifier les sources ciblées par cette cartographie.

Après une modification dans ce périmètre, actualiser les sections du guide qui
changent. Le code reste la référence ; ne pas recopier une information du guide
sans vérifier sa pertinence pour la demande.

Pour rendre une modification visible sur l'instance locale, suivre la procédure
Docker du guide et vérifier l'image réellement exécutée. Distinguer les contrôles
du code, les tests exécutés et la vérification de l'instance dans le compte rendu.
Utiliser la stack de tests dédiée pour les scénarios qui préparent ou réinitialisent
des données. Ne pas supprimer les volumes de l'instance personnelle pour un thème.
