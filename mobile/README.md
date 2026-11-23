# FinanceMobile

Application Expo / React Native pour le back-office ChurchManager (Christ Embassy Bénin). Elle consomme l’API Laravel du projet (`/api`) avec les mêmes règles d’accès que Filament.

> Documentation complète des commandes de build et de démarrage réseau : [`DOCUMENTATION_BUILD_ET_RESEAU.md`](../DOCUMENTATION_BUILD_ET_RESEAU.md).

## Démarrage

```bash
cd mobile
npm install
cp .env.example .env
npm start
```

Dans `.env`, renseignez `EXPO_PUBLIC_API_URL` :

- émulateur Android : `http://10.0.2.2:8000/api`
- simulateur iOS : `http://localhost:8000/api`
- téléphone physique sur le réseau local : l’adresse IP privée du serveur, terminée par `/api`
- serveur public : une URL **HTTPS** (ex. `https://api.exemple.fr/api`)

L’application accepte le trafic en clair **uniquement pour le réseau local** : IP privées (`10.x`, `172.16-31.x`, `192.168.x`, `169.254.x`, boucle locale) et noms se terminant par `.local`. Toute autre adresse doit utiliser HTTPS, y compris dans la release. L’URL est validée avec `new URL()` ; les identifiants intégrés, les paramètres de requête et les fragments sont refusés. Android ne permettant pas de limiter le clair à des plages d’IP dans `network_security_config`, le trafic clair est autorisé au niveau du manifeste puis filtré dans `src/api/client.ts` — aucune IP n’est écrite en dur.

Le serveur Laravel doit tourner sur `http://localhost:8000` avec la base migrée (y compris la table `personal_access_tokens`). Le projet Laravel est aligné sur **PHP 8.3** (`composer.json` : `platform.php = 8.3.35`, Laravel 12).

## APK prêt à installer

L’APK de release se trouve ici :

```
mobile/FinanceMobile-1.0.0.apk
mobile/FinanceMobile-1.1.0.apk
mobile/FinanceMobile-1.2.0.apk
mobile/FinanceMobile-1.3.0.apk
mobile/FinanceMobile-1.3.1.apk
mobile/FinanceMobile-1.3.2.apk
mobile/FinanceMobile-1.5.2.apk
mobile/FinanceMobile-1.5.3.apk
mobile/FinanceMobile-1.5.4.apk
mobile/FinanceMobile-1.5.5.apk
mobile/FinanceMobile-1.5.6.apk
mobile/FinanceMobile-1.5.7.apk
```

**Les APK livrés restent signés avec la clé de debug** : ils s'installent directement sur Android, mais ne doivent pas être distribués comme release. Aucune clé de production n'est fournie dans ce dépôt et `android/app/debug.keystore` n'est plus versionné.

Pour compiler une release signée, fournissez une clé de production via l'environnement :

```bash
ANDROID_KEYSTORE_FILE=/chemin/vers/release.jks \
ANDROID_KEYSTORE_PASSWORD=... \
ANDROID_KEYALIAS=... \
ANDROID_KEYPASSWORD=... \
cd android && ./gradlew assembleRelease
```

Sans ces quatre variables, toute tâche Gradle dont le nom contient `release` échoue immédiatement : il n'y a plus de repli silencieux sur la signature debug.

L’adresse de l’API peut être modifiée **depuis l’application**, sans recompiler :

- à la connexion : champ **Adresse du serveur** puis **Utiliser cette adresse**, disponible avant toute authentification pour permettre la première connexion à une nouvelle installation ;
- ensuite dans **Profil → Connexion au serveur**, réservé au super administrateur. Le droit de configuration est recalculé depuis `/me` à chaque démarrage : un ancien drapeau stocké sur l'appareil n'est plus accepté.
- le site web peut **proposer** une adresse, mais l'administrateur doit la confirmer explicitement avant qu'elle soit appliquée.

Sur un téléphone physique, utilisez une URL HTTPS accessible depuis l'appareil. La copie publique non chiffrée de `finance.sqlite` n'est plus créée ; l'ancienne copie est supprimée à la déconnexion ou au changement de compte, avec toutes les tables locales (entités, outbox, conflits, métadonnées).

## Cache local

Les réponses GET sont mises en cache dans le dossier temporaire de l’application (`expo-file-system`) pendant **5 minutes**. Les écrans s’affichent donc instantanément, même avec une connexion lente, et les données sont rafraîchies automatiquement à l’ouverture suivante. En cas de perte réseau, la dernière version connue est utilisée. Le cache est vidé à la déconnexion et après chaque modification (don, partenaire, utilisateur…).

Le serveur met également les rapports de partenariat en cache (`REPORT_CACHE_TTL`, 5 minutes par défaut). Toute écriture sur un don, un partenaire, une église, une catégorie ou une cellule incrémente la version du cache : le rapport affiché est donc toujours à jour après une modification.

## Session et cookies

Le jeton Sanctum, les informations du compte et les détails de la session sont conservés dans le stockage sécurisé du téléphone (`expo-secure-store`). **Profil → Sécurité** affiche la date d’ouverture de la session et la dernière synchronisation avec le serveur. Si le serveur répond `401`, la session est fermée automatiquement ; si le réseau est indisponible, l’application reste consultable avec le cache local. L’APK n’utilise pas de cookies (authentification par jeton Bearer). Sur le site web, la session Laravel est stockée en base et protégée par les cookies `XSRF-TOKEN` et de session ; une bannière explique les cookies utilisés et mémorise la prise de connaissance dans `churchmanager_cookie_consent`.

## Fonctionnalités

- Connexion email / mot de passe avec jeton Sanctum stocké de façon sécurisée.
- Tableau de bord : églises, partenaires, dons, notifications non lues.
- Dons : consultation, filtres, création, suppression, import Excel et téléchargement du modèle.
- Partenaires : recherche, création, modification, suppression, import Excel et téléchargement du modèle.
- Rapport de partenariat : génération, export Excel, aperçu des destinataires, copies cochées par défaut et envoi après confirmation explicite.
- Notifications : liste et marquage comme lues.
- Profil : nom, email, téléphone, identifiant KingsChat et mot de passe.
- Historique de connexion : consultation des connexions et déconnexions du compte (site web et application mobile), avec date, source et adresse IP.
- Tableau de bord enrichi : total général des dons, dons du mois, derniers dons, principales catégories, répartition des églises et dernières notifications. L’église de groupe (CE SIKECODJI) est comptée à la fois comme groupe, sous-groupe et église locale : 1 groupe, 2 sous-groupes et 10 locales.
- Administration : églises, cellules, catégories de dons, utilisateurs et rôles (selon les droits du compte connecté).

## Vérification

```bash
npm run typecheck
npm run security:check -- --source-only
```

`security:check` n'utilise que les modules intégrés à Node. Le mode `--source-only` vérifie le code source, les manifestes, les règles de sauvegarde et l'absence de keystore versionné sans exiger un APK reconstruit. Le mode complet (`npm run security:check`) vérifie en plus le dernier APK présent avec `aapt` et `apksigner` **s'ils sont installés** ; si ces outils sont absents, ces contrôles sont ignorés et signalés comme tels. Les APK historiques du dossier restent signés en debug tant qu'une clé de production n'est pas fournie.

Contrôles couverts : signature de release, keystore suivi par Git, trafic clair, URL HTTP en dur, copie publique de la base, exclusions de sauvegarde, validation HTTPS, URL imposée par le serveur, permissions Android, purge locale, messages d'erreur, journalisation et limites de validation.

Les tests back-end de l’API se lancent depuis la racine Laravel :

```bash
php artisan test --filter=MobileApiTest
php artisan test --filter=MobileImportApiTest
php artisan test --filter=LoginHistoryTest
```
