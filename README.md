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

Le back-office est accessible sur [http://localhost:8000/dashboard](http://localhost:8000/dashboard).

### Compte initial

Créé par le seeder :

- **Email** : `libertsinzo7@gmail.com`
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

Après modification de `database/migrations/*` ou d'un modèle, un `php artisan serve` relancé
suffit (Pas besoin de `composer dump-autoload` en développement).

## Hiérarchie des églises

Le modèle `Church` est auto-référencé : `GROUP → SUB_GROUP → LOCAL` via `parent_id`.

- `GROUP` : église racine (ex. `CE SIKECODJI`)
- `SUB_GROUP` : sous-groupe d'une église de groupe
- `LOCAL` : église locale, fille d'un sous-groupe

Le service `App\Services\ChurchAccessService` centralise le scopage : chaque utilisateur ne
voit que les données de sa branche (`accessibleChurchIds()`).

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
  Services/              ChurchAccessService, DonationNotifier, MagicTokenService, UserInviter
database/
  migrations/            Churches, Church columns, Cellules, Partners, CategoryDons, Donations, Notifications, MagicTokens, Accounts
  seeders/               RoleAndPermission, Church, User, CategoryDon, DatabaseSeeder
resources/views/emails/  user-welcome, donation-confirmation
tests/Feature/           AuthTest, ChurchAccessTest, MailTest
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

## Notes d'administration

- **Langue** : l'application et le back-office sont en français (`APP_LOCALE=fr`).
- **Fuseau horaire** : `Africa/Porto-Novo`.
- **Docker** : non requis ici, MySQL étant servi par XAMPP.
- **Emails** : les identifiants Mailjet sont dans `.env` (`MAIL_USERNAME` / `MAIL_PASSWORD`).
  Sans elles, l'application tourne mais l'envoi est silencieusement ignoré (erreurs journalisées).
