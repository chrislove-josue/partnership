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
- téléphone physique : `http://IP_DE_LA_MACHINE:8000/api` (même réseau Wi-Fi)

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
```

Il est signé avec la clé de debug générée par Expo : il s’installe directement sur Android, mais n’est pas destiné au Play Store. L’adresse de l’API peut être modifiée **depuis l’application**, sans recompiler :

- à la connexion : champ **Adresse du serveur** puis **Utiliser cette adresse** ;
- ensuite dans **Profil → Connexion au serveur**.

Pour un téléphone physique, utilisez l’IP de la machine qui héberge Laravel, par exemple `http://192.168.1.10:8000/api`. Le PC et le téléphone doivent être sur le même réseau, et le serveur doit écouter sur `0.0.0.0` (`php artisan serve --host=0.0.0.0`).

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
```

Les tests back-end de l’API se lancent depuis la racine Laravel :

```bash
php artisan test --filter=MobileApiTest
php artisan test --filter=MobileImportApiTest
php artisan test --filter=LoginHistoryTest
```
