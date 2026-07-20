# Saidi Papetrie — application Android (APK)

Coquille native minimale : toute la boutique (et l'admin) tourne dans une
WebView plein écran chargée depuis **https://saidi.h47.io**. Aucun contenu
n'est embarqué — chaque mise à jour du site est donc instantanément dans
l'app, sans re-publier d'APK.

## Fonctionnalités de la coquille
- Plein écran, barre de statut orange (marque), icône du logo.
- Session/cookies persistants (connexion admin & client conservée).
- **Téléversement de photos** depuis l'appareil photo ou la galerie
  (formulaire produit admin).
- Téléchargements (PDF tarifs, bordereaux) → navigateur système.
- Liens `tel:`, WhatsApp, Facebook → application correspondante.
- Bouton retour = navigation en arrière dans le site.
- Page « Pas de connexion » avec bouton Réessayer.

## Construire l'APK

Prérequis : Android SDK (ou Android Studio) + Java 17+.

```bash
cd android
echo "sdk.dir=$HOME/Library/Android/sdk" > local.properties   # une fois
export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"
gradle :app:assembleRelease
# → app/build/outputs/apk/release/app-release.apk
```

## Signature
L'APK release est signé avec `keystore/saidi-release.keystore`
(alias `saidi`). **Ce fichier n'est pas dans git** — gardez-en une copie
précieusement : Google Play (et les mises à jour d'une app installée)
exigent la MÊME clé pour toujours. Mot de passe par défaut `saidi2026`,
surchargeable via la variable d'environnement `SAIDI_KEYSTORE_PASS`.

## Installer sur un téléphone
1. Copiez `app-release.apk` sur le téléphone (WhatsApp/USB/Drive).
2. Ouvrez-le → autorisez « Installer des applications inconnues » si demandé.
3. L'icône Saidi apparaît dans le tiroir d'applications.

## Publier sur Google Play (optionnel)
Play exige un **AAB** : `gradle :app:bundleRelease`
(→ `app/build/outputs/bundle/release/app-release.aab`), un compte
développeur Google Play (25 $ une fois), et la même clé de signature.
