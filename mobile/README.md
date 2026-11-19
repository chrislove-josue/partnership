# ChurchManager Mobile

Application Expo / React Native pour le back-office ChurchManager (Christ Embassy Bénin). Elle consomme l’API Laravel du projet (`/api`) avec les mêmes règles d’accès que Filament.

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

Le serveur Laravel doit tourner sur `http://localhost:8000` avec la base migrée (y compris la table `personal_access_tokens`).

## Fonctionnalités

- Connexion email / mot de passe avec jeton Sanctum stocké de façon sécurisée.
- Tableau de bord : églises, partenaires, dons, notifications non lues.
- Dons : consultation, filtres, création et suppression selon les permissions.
- Partenaires : recherche, création, modification et suppression.
- Rapport de partenariat : génération, export Excel, aperçu des destinataires, copies cochées par défaut et envoi après confirmation explicite.
- Notifications : liste et marquage comme lues.
- Profil : nom, email, téléphone, identifiant KingsChat et mot de passe.
- Administration : églises, cellules, catégories de dons, utilisateurs et rôles (selon les droits du compte connecté).

## Vérification

```bash
npm run typecheck
```

Les tests back-end de l’API se lancent depuis la racine Laravel :

```bash
php artisan test --filter=MobileApiTest
```
