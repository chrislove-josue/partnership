# Suivi des Partenariats — Christ Embassy Bénin

Application de suivi des partenaires et des dons pour l'église Christ Embassy Benin.

Portage Laravel 13 de l'application Next.js 15 + Prisma originale, avec **Filament v4** pour le
back-office et **MySQL** pour la base de données.

## Stack

- **Framework** : Laravel 13 (PHP 8.3+)
- **UI back-office** : Filament v4 (`/dashboard`)
- **Base de données** : MySQL 8 / XAMPP
- **Authentification** : Filament Panel (guard `web`, sessions) + liens magiques
- **Autorisations** : `spatie/laravel-permission` (rôles + permissions), scopage par église
- **Emails** : SMTP Mailjet (`in-v3.mailjet.com`, port 587)

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Vérifier que `.env` pointe vers votre base MySQL :

```
DB_CONNECTION=mysql
DB_DATABASE=partnership
DB_USERNAME=root
DB_PASSWORD=
```

Puis créer les tables et les données initiales :

```bash
php artisan migrate:fresh --seed
```

Lancer le serveur de développement :

```bash
php artisan serve
```

La racine `/` et toute route protégée renvoient directement sur la connexion
[http://localhost:8000/login](http://localhost:8000/login). Le back-office est accessible sur
[http://localhost:8000/dashboard](http://localhost:8000/dashboard) après connexion
(`/dashboard/login` reste un alias de `/login`).

### Connexion

La page `/login` affiche des messages explicites, identiques à ceux de l'application Next.js.
Chaque message apparaît dans une alerte globale sous les champs, sans encadrer un champ en
particulier :

| Situation | Message |
| --- | --- |
| Email ou mot de passe vide | Veuillez renseigner l'email et le mot de passe. |
| Email inconnu | Aucun compte n'existe avec cette adresse email. |
| Compte sans mot de passe (OAuth) | Ce compte n'a pas de mot de passe. Utilisez un autre moyen de connexion. |
| Compte désactivé | Ce compte est désactivé. Contactez l'administrateur. |
| Mot de passe erroné | Mot de passe incorrect. |

Logique dans `App\Filament\Pages\Auth\Login`, redirection dans `App\Http\Middleware\Authenticate`.
Aucun compte n'est créé pour `sounesatchougo@gmail.com`.

### Design

Le back-office reprend l'identité visuelle de l'application Next.js : violet `#9333EA` à indigo
`#4F46E5`, fond `#F9FAFB`, cartes blanches, police Geist et sidebar de `18rem`. La page de
connexion utilise le fond sombre dégradé et la carte en verre dépoli de Next.js. Le sélecteur
clair/sombre/système reste disponible dans la barre latérale ; le mode clair est utilisé par défaut.

La configuration du panneau se trouve dans `app/Providers/Filament/AdminPanelProvider.php` ;
les surcharges CSS sont dans `public/css/filament/admin/theme.css` (elles importent le thème
Filament compilé). Aucun build Vite n'est nécessaire pour le panneau.

Les widgets du tableau de bord sont rendus immédiatement, sans squelette « Chargement… ». Les
libellés internes Filament absents de la traduction française sont complétés dans
`app/Providers/AppServiceProvider.php`.

### Pages d'erreur

Les erreurs HTTP 4xx et 5xx sont rendues par des vues françaises dans `resources/views/errors/` :
une vue par code (400, 401, 402, 403, 404, 405, 408, 419, 422, 429, 500, 501, 502, 503, 504)
et deux vues génériques `4xx.blade.php` / `5xx.blade.php` pour les codes non couverts. Elles
partagent `layout.blade.php` : carte ChurchManager, code de statut, message clair et boutons de
retour au tableau de bord ou de connexion. Le sélecteur clair/sombre/système (clair par défaut)
reste cohérent avec celui du panneau Filament. Aucun détail technique de l'exception n'est affiché.

### Compte initial

Créé par `database/seeders/UserSeeder.php` :

- **Email** : `libertsinzo7@gmail.com` (modifiable dans le seeder)
- **Mot de passe** : `azerty123`
- **Rôle** : `SUPER_ADMIN` (accès complet)

⚠️ Modifier ce mot de passe dès la première connexion.

## Commandes utiles

| Commande | Description |
| --- | --- |
| `php artisan serve` | Serveur de développement |
| `php artisan migrate:fresh --seed` | Recréer la base + données initiales |
| `php artisan db:seed --class=RoleAndPermissionSeeder` | Rôles et permissions uniquement |
| `php artisan migrate` | Appliquer les migrations en attente |
| `php artisan db:seed` | Rejouer le seeder complet |
| `php artisan test` | Lancer les tests (PHPUnit, SQLite en mémoire) |
| `./vendor/bin/pint` | Formater le code PHP |
| `php database/convert-pg-dump.php <dump.sql>` | Convertir le dump PostgreSQL en export JSON |
| `php artisan legacy:import --force` | Importer les données de l'ancienne base (voir « Migration des données ») |

Après modification de `database/migrations/*` ou d'un modèle, un `php artisan serve` relancé
suffit (Pas besoin de `composer dump-autoload` en développement).

## Hiérarchie des églises

Le modèle `Church` est auto-référencé : `GROUP → SUB_GROUP → LOCAL` via `parent_id`.

- `GROUP` : église racine (ex. `CE SIKECODJI`)
- `SUB_GROUP` : sous-groupe d'une église de groupe
- `LOCAL` : église locale, fille d'un sous-groupe

Le service `App\Services\ChurchAccessService` centralise le scopage : chaque utilisateur ne
voit que les données de sa branche (`accessibleChurchIds()`).

## Rapport de partenariat

Page Filament `/dashboard/partnership-report` (port de `lib/report.ts`) : choisir une église,
puis un mois, un trimestre ou une année. Les bornes sont strictes (une année = 1er janvier →
31 décembre). La page affiche les totaux, les dons par catégorie, les partenaires ayant donné
et ceux sans don sur la période.

- **Exporter en Excel** : classeur 4 feuilles généré avec PhpSpreadsheet.
- **Envoyer à la hiérarchie** : aperçu des destinataires dans un dialogue de confirmation,
  puis envoi uniquement après validation (pièce jointe Excel). Aucun envoi automatique.
  Chaîne : STAFF → pasteur de son église ; pasteur local/sous-groupe → niveau parent ;
  église groupe → super administrateurs actifs. L'expéditeur est toujours exclu.

Logique dans `App\Services\PartnershipReportService`, `PartnershipReportExporter` et
`PartnershipReportSender` ; email dans `App\Mail\PartnershipReportMail`.

## Imports Excel

Les pages « Partenaires » et « Dons » du back-office proposent **Importer depuis Excel** et
**Télécharger le modèle** (port des routes Next.js `/api/partners/import` et
`/api/donations/import`).

- Colonnes du modèle : `firstName`, `lastName`, `email`, `phone`, `handleKC`, `churchName`,
  `celluleName`, `address`, `birthdate`, `donationAmount`, `donationDate`,
  `donationCategoryName`.
- Partenaire retrouvé par email, téléphone ou identifiant KingsChat (insensible à la casse) ;
  créé automatiquement si `firstName` + `lastName` sont présents.
- Église, cellule et catégorie manquantes créées automatiquement (église `LOCAL` rattachée au
  groupe ; catégorie de couleur `#1F4E78`).
- Dates strictes : `2026-02-31` est rejeté. Un don identique est ignoré (`skipped`) lors de
  l'import des dons.
- Chaque ligne est validée séparément : un résultat détaillé indique succès, erreurs et lignes
  ignorées. Une ligne sur une église hors périmètre est refusée.

Services : `App\Services\Import\PartnerImportService`, `DonationImportService`,
`SpreadsheetReader` et `ImportTemplateGenerator` (helpers partagés dans `ImportHelpers`).

## Rôles et permissions

Définis dans `app/Enums/RoleName.php` et `app/Enums/PermissionName.php`, semés par
`RoleAndPermissionSeeder`.

| Rôle | Permission clé |
| --- | --- |
| `SUPER_ADMIN` | toutes les permissions |
| `GROUP_PASTOR` | lecture des enfants, gestion des sous-groupes/locaux/personnel, églises |
| `SUBGROUP_PASTOR` | lecture des enfants, gestion des locaux/personnel |
| `LOCAL_PASTOR` | lecture de sa propre église, personnel |
| `STAFF` | lecture de sa propre église |

## Structure du projet

```
app/
  Enums/                 ChurchType, NotificationType, RoleName, PermissionName
  Filament/
    Resources/           Eglises, Utilisateurs, Partenaires, Cellules, Dons, Catégories, Rôles, Notifications
    Widgets/             StatsOverview, DonationsChart, LatestDonations
    Resources/Concerns/  ScopesByChurch (filtra les queries par église accessible)
  Http/Controllers/      MagicLoginController (connexion par lien magique)
  Mail/                  UserWelcomeMail, DonationConfirmationMail
  Models/                Church, User, Partner, Cellule, Donation, CategoryDon, Notification, MagicToken, Account, Role
  Policies/              Autorisations par ressource (délèguent à ChurchAccessService)
  Providers/Filament/    AdminPanelProvider (panel /dashboard, login, profil)
  Services/              ChurchAccessService, DonationNotifier, NotificationService, MagicTokenService, UserInviter
  Console/Commands/      ImportLegacyData (migration depuis l'ancienne base)
database/
  migrations/            Churches, Church columns, Cellules, Partners, CategoryDons, Donations, Notifications, MagicTokens, Accounts
  seeders/               RoleAndPermission, Church, User, CategoryDon, DatabaseSeeder
resources/views/emails/  user-welcome, donation-confirmation
tests/Feature/           AuthTest, ChurchAccessTest, MailTest, DonationNotificationTest, CreateDonationPageTest, FilamentScopingQueryTest, ImportLegacyDataTest, ErrorPagesTest
```

## Emails

Deux scénarios sont branchés automatiquement :

- **Création d'utilisateur** → email de bienvenue avec mot de passe temporaire et lien de connexion
  (jeton magique valable 24 h, stocké haché en base). Voir `UserInviter` + `MagicLoginController`.
- **Création d'un don** → email de confirmation envoyé au partenaire s'il a une adresse email.
  Voir `DonationNotifier`.

En cas d'échec d'envoi, l'erreur est journalisée (`storage/logs`) et n'interrompt pas la création.

## Liens magiques

`/magic?token=<token>` connecte l'utilisateur et marque le jeton comme utilisé. Un jeton ne
peut être utilisé qu'une seule fois et expire au bout de 24 h. Le token est stocké haché
(SHA-256) dans la table `magic_tokens`.

## Tests

```bash
php artisan test
```

Couverture actuelle :

- `AuthTest` : rendu de la page de connexion, redirection des invités, connexion par lien magique (et usage unique).
- `ChurchAccessTest` : scopage des églises par rôle (super admin, sous-groupe, local).
- `MailTest` : envoi des confirmations de don et emails de bienvenue.

Les tests tournent sur SQLite en mémoire (`phpunit.xml`), sans impact sur la base MySQL.

## Migration des données

Les données de l'ancienne application Next.js (PostgreSQL) sont migrées vers la base MySQL
via un export JSON intermédiaire.

**Fonctionnement :**

1. Convertir le dump PostgreSQL (`churchmanager_backup.sql`) en JSON :

   ```bash
   php database/convert-pg-dump.php <chemin vers churchmanager_backup.sql>
   ```

   Le script lit les instructions `INSERT` du dump et produit `storage/app/legacy-export.json`
   (l'export précédent est conservé en `.bak`). Les identifiants Prisma sont conservés pour
   reconstruire les relations.

   Pour récupérer une version plus récente depuis la base Neon en ligne, générer d'abord le dump :
   retirer les paramètres Prisma `pool_timeout`, `connection_limit` et `connect_timeout` de
   `DATABASE_URL` (non reconnus par `pg_dump`), puis :

   ```bash
   pg_dump --dbname="<DATABASE_URL>" --column-inserts --no-owner --no-privileges \
     --file=storage/app/neondb-backup.sql
   php database/convert-pg-dump.php storage/app/neondb-backup.sql
   ```

2. Recréer les tables, puis importer :

   ```bash
   php artisan migrate:fresh --seed
   php artisan legacy:import --force
   # ou avec un chemin explicite :
   php artisan legacy:import --path=storage/app/legacy-export.json --force
   ```

La commande vide les tables métier (églises, utilisateurs, cellules, partenaires, dons,
notifications) puis réimporte en respectant la hiérarchie et les clés étrangères :

- Églises importées en deux passes (parents puis liens `parent_id`), puis responsables (`manager_id`).
- Utilisateurs importés avec leurs rôles (résolus par nom) et leur église assignée.
- Cellules/partenaires importés en deux passes (relation circulaire `leader_id` ↔ `cellule_id`).
- Dons et notifications réattachés par identifiants mappés.
- Liens magiques importés avec leur jeton déjà haché (SHA-256).
- Les hachages bcrypt `$2a$`/`$2b$` (générés par Node) sont normalisés en `$2y$` pour que
  Laravel puisse les vérifier. Les mots de passe par défaut de l'ancienne base sont `azerty123`.
- Le super admin historique (`libertsinzo7@gmail.com`, présent dans l'ancienne base) est
  confirmé en `SUPER_ADMIN` à la fin pour ne pas perdre l'accès. Aucun compte n'est créé pour
  `sounesatchougo@gmail.com`.

La commande est idempotente : on peut la relancer sans risque.

## Notes d'administration

- **Langue** : l'application et le back-office sont en français (`APP_LOCALE=fr`).
- **Fuseau horaire** : `Africa/Porto-Novo`.
- **Docker** : non requis ici, MySQL étant servi par XAMPP.
- **Emails** : les identifiants Mailjet sont dans `.env` (`MAIL_USERNAME` / `MAIL_PASSWORD`).
  Sans elles, l'application tourne mais l'envoi est silencieusement ignoré (erreurs journalisées).
