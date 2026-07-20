# Saidi Équipe — application Android native (équipe/admin)

Application **100% native (Kotlin + Jetpack Compose)** pour l'équipe du
magasin. Aucune WebView : chaque écran est natif, rapide, pensé mobile.
Elle parle au site via l'API `/api/v1/*` (jeton sécurisé par employé,
permissions identiques à l'admin web).

## Écrans
- **Connexion** — email + mot de passe des comptes équipe existants.
  (⚙️ « Adresse du serveur » permet de pointer vers un autre domaine.)
- **Accueil** — commandes/recettes du jour, en attente, stock bas,
  graphique 14 jours, dernières commandes.
- **Commandes** — recherche + filtres par statut, détail complet,
  changer le statut, **modifier les prix** (journalisé), **expédier via
  Noest**, **rembourser** (espèces/avoir/livreur), appeler ou WhatsApp
  le client en un geste.
- **Produits** — recherche, filtre stock bas, **scanner code-barres/QR**
  (caméra) pour trouver un produit par référence, modification rapide
  prix/stock/visibilité.
- **Clients & dettes** — soldes, limite de crédit, historique du grand
  livre, nouvelle écriture (paiement/dette/ajustement), appel/WhatsApp.
- **Alertes** — flux des notifications admin, badge non-lues.

Chaque onglet n'apparaît que si l'employé a la permission correspondante
(RBAC identique au site).

## Construire l'APK
```bash
cd android
echo "sdk.dir=$HOME/Library/Android/sdk" > local.properties   # une fois
export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"
gradle :app:assembleRelease
# → app/build/outputs/apk/release/app-release.apk
```

## Déploiement côté serveur
L'app requiert l'API (branche `feature/android-app`) :
fichiers `app/Http/Controllers/Api/*`, `app/Models/ApiToken.php`,
`app/Http/Middleware/AuthApiToken.php`, `routes/api.php`,
`bootstrap/app.php` + la migration **additive** `api_tokens`
(`php artisan migrate` — ne touche aucune table existante).

## Notifications push (FCM) — optionnel
L'app est prête pour Firebase Cloud Messaging :
1. Créez un projet sur https://console.firebase.google.com (gratuit),
   ajoutez une app Android `dz.saidi.staff`.
2. Téléchargez `google-services.json` dans `android/app/`.
3. Reconstruisez : le plugin Google s'active automatiquement.
En attendant, les alertes passent par le badge in-app + Telegram.

## Signature
Signé avec `keystore/saidi-release.keystore` (alias `saidi`,
mot de passe `saidi2026`, surchargeable via `SAIDI_KEYSTORE_PASS`).
**Jamais dans git — gardez une copie du keystore en lieu sûr.**
