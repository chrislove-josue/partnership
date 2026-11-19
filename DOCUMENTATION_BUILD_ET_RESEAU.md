# Documentation — build, démarrage réseau et APK FinanceMobile

Ce document regroupe **toutes les commandes utiles** pour le projet Laravel (back-office + API) et l’application mobile Expo `FinanceMobile`.

- Racine Laravel : `C:\Project\partnership`
- Application mobile : `C:\Project\partnership\mobile`
- PHP utilisé : **8.3** (`C:\php\php.bat` → `C:\php-8.3\php.exe`)
- PHP 8.5 conservé : `C:\php\php85.bat`
- Port API : **8000**
- Port Metro/Expo : **19000**

---

## 1. Vérifier l’environnement

Dans PowerShell, depuis la racine du projet :

```powershell
cd C:\Project\partnership
php -v
composer --version
node --version
npm --version
java -version
```

Résultat attendu pour `php -v` :

```text
PHP 8.3.35 ...
```

Si `php -v` affiche 8.5, utiliser :

```powershell
C:\php-8.3\php.exe -v
```

Le wrapper `C:\php\php.bat` pointe déjà vers PHP 8.3. Le wrapper pour l’ancien PHP est `C:\php\php85.bat`.

---

## 2. Installer le projet Laravel

```powershell
cd C:\Project\partnership
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --force
```

Base de données déjà configurée dans `.env` :

```dotenv
DB_HOST=127.0.0.1
DB_DATABASE=partnership
```

Si la base est vide et que les données de départ sont nécessaires :

```powershell
php artisan db:seed --force
```

Pour nettoyer les caches après modification de `.env` :

```powershell
php artisan optimize:clear
```

---

## 3. Configurer l’accès depuis tous les appareils du réseau

### 3.1 Trouver l’IP de la machine

```powershell
Get-NetIPAddress -AddressFamily IPv4 |
  Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
  Select-Object IPAddress, InterfaceAlias
```

Sur cette machine, l’IP Wi-Fi actuelle est par exemple :

```text
172.16.206.66
```

Cette IP peut changer selon le réseau. La vérifier avant chaque test sur téléphone.

### 3.2 Autoriser le port 8000 dans le pare-feu Windows

À exécuter dans un **PowerShell administrateur** :

```powershell
netsh advfirewall firewall add rule name="Laravel 8000" dir=in action=allow protocol=TCP localport=8000
```

Pour supprimer la règle plus tard :

```powershell
netsh advfirewall firewall delete rule name="Laravel 8000"
```

### 3.3 Autoriser les origines mobiles dans Laravel

Dans `C:\Project\partnership\.env`, ajouter ou modifier :

```dotenv
MOBILE_APP_ORIGINS=*
APP_URL=http://0.0.0.0:8000
```

Puis :

```powershell
php artisan optimize:clear
```

`*` est pratique pour un réseau local. En production, remplacer par les domaines/IP autorisés.

### 3.4 Démarrer l’API Laravel sur toutes les interfaces

```powershell
cd C:\Project\partnership
php artisan serve --host=0.0.0.0 --port=8000
```

Laisser ce terminal ouvert.

Depuis un autre appareil du même réseau, tester dans un navigateur :

```text
http://172.16.206.66:8000
```

Remplacer `172.16.206.66` par l’IP réelle de la machine.

---

## 4. Configurer l’adresse de l’API dans l’application mobile

L’adresse peut être changée **directement depuis l’APK**, sans recompiler :

- à la connexion : champ **Adresse du serveur** → **Utiliser cette adresse** ;
- après connexion : **Profil → Connexion au serveur**.

Utiliser :

```text
http://IP_DE_LA_MACHINE:8000/api
```

Exemple :

```text
http://172.16.206.66:8000/api
```

Ne jamais utiliser `localhost` ou `127.0.0.1` depuis un téléphone : cela désignerait le téléphone lui-même.

Pour le développement Expo, `mobile/.env` peut aussi contenir :

```dotenv
EXPO_PUBLIC_API_URL=http://172.16.206.66:8000/api
```

Créer le fichier si besoin :

```powershell
cd C:\Project\partnership\mobile
copy .env.example .env
```

---

## 5. Démarrer Expo pour développement sur le réseau

Terminal 1 — API Laravel :

```powershell
cd C:\Project\partnership
php artisan serve --host=0.0.0.0 --port=8000
```

Terminal 2 — Expo/Metro :

```powershell
cd C:\Project\partnership\mobile
npm.cmd install
npm.cmd start -- --host lan --port 19000
```

Le QR code affiché peut être scanné avec Expo Go sur un téléphone du même Wi-Fi.

Si le QR code ne s’affiche pas ou si le téléphone ne joint pas le PC :

```powershell
npm.cmd start -- --host lan --port 19000 --clear
```

---

## 6. Vérifications avant build

### Back-end

```powershell
cd C:\Project\partnership
php artisan test --filter=MobileApiTest
php artisan test --filter=MobileImportApiTest
php artisan test
```

Dernier résultat connu : **137 tests réussis (753 assertions)**.

### Mobile

```powershell
cd C:\Project\partnership\mobile
npm.cmd run typecheck
```

---

## 6 bis. Import / export Excel dans l’APK

Les écrans **Dons** et **Partenaires** proposent les mêmes actions que le back-office Filament :

- **Importer depuis Excel** : choisir un fichier `.xlsx` depuis le téléphone ;
- **Télécharger le modèle** : obtenir et partager `modele_dons.xlsx` ou `modele_partenaires.xlsx`.

Endpoints utilisés par l’application (tous protégés par `auth:sanctum`) :

```text
POST   /api/donations/import
GET    /api/donations/template
POST   /api/partners/import
GET    /api/partners/template
```

Les règles sont exactement celles du site web :

- première feuille uniquement, en-têtes normalisés ;
- église et catégorie créées automatiquement si absentes ;
- dédup des dons (`partenaire + église + catégorie + montant + date`) ;
- partenaire retrouvé par email / téléphone / handle KC, ou créé avec `firstName + lastName` ;
- import partenaires avec cellule, adresse, date de naissance et don optionnel ;
- contrôle hiérarchique des droits sur l’église, erreurs affichées par ligne.

Le site ne propose pas d’export brut des listes de dons ou de partenaires : le téléchargement disponible est celui des **modèles d’import**. L’export du rapport de partenariat reste dans **Rapports**.

---

## 6 ter. Historique des connexions et déconnexions

Chaque connexion et déconnexion est enregistrée dans la table `login_histories`, quel que soit le canal :

| Canal | Enregistrement |
| --- | --- |
| Site web (Filament, y compris lien magique) | Événements Laravel `Login` / `Logout` captés par `App\Listeners\RecordLoginHistory` |
| Application mobile | `AuthController::login()` / `logout()` via `LoginHistoryService` (`source = mobile`) |

Chaque ligne contient l’utilisateur, l’événement, la source (site web ou application mobile), l’adresse IP, l’appareil et la date/heure.

Consultation par l’utilisateur :

- **Site web** : menu **Mon compte → Historique de connexion** (`/dashboard/login-history`), paginé et limité à ses propres lignes.
- **Application mobile** : **Profil → Sécurité → Historique de connexion** (`GET /api/auth/history`), limité à ses propres lignes.

Aucun historique n’est envoyé par e-mail et aucune donnée d’un autre utilisateur n’est exposée.

```bash
php artisan test --filter=LoginHistoryTest
```

---

## 7. Générer les assets (logo et icônes)

Les sources sont dans `mobile/assets/` :

- `icon.png` — icône Android classique ;
- `adaptive-icon.png` — icône adaptive Android ;
- `logo.png` — logo affiché sur l’écran de connexion ;
- `generate-assets.ps1` — script de génération.

Pour régénérer les images après modification du script :

```powershell
cd C:\Project\partnership\mobile
powershell -NoProfile -ExecutionPolicy Bypass -File .\assets\generate-assets.ps1
```

Le logo doit porter la mention **« Gestion financière »**.

---

## 8. Build local de l’APK (méthode recommandée)

### 8.1 Prérequis

Java 17 et Android SDK sont déjà installés :

```text
JAVA_HOME      C:\Program Files\Eclipse Adoptium\jdk-17.0.21.9-hotspot
ANDROID_HOME   C:\Users\HP\AppData\Local\Android\Sdk
```

Dans chaque nouveau terminal PowerShell, positionner les variables :

```powershell
$env:JAVA_HOME='C:\Program Files\Eclipse Adoptium\jdk-17.0.21.9-hotspot'
$env:ANDROID_HOME='C:\Users\HP\AppData\Local\Android\Sdk'
$env:ANDROID_SDK_ROOT=$env:ANDROID_HOME
$env:PATH="$env:JAVA_HOME\bin;$env:ANDROID_HOME\platform-tools;$env:ANDROID_HOME\cmdline-tools\latest\bin;$env:PATH"
```

### 8.2 Commands complètes

```powershell
cd C:\Project\partnership\mobile
npm.cmd install
npm.cmd run typecheck
npx.cmd expo prebuild --platform android
```

Si le dossier `android/` est corrompu ou après un changement de dépendances natives :

```powershell
npx.cmd expo prebuild --platform android --clean
```

Puis compiler :

```powershell
cd C:\Project\partnership\mobile\android
.\gradlew.bat assembleRelease --no-daemon
```

Copier l’APK final :

```powershell
cd C:\Project\partnership
copy .\mobile\android\app\build\outputs\apk\release\app-release.apk .\mobile\FinanceMobile-1.3.0.apk /Y
```

Le numéro de version est aligné dans `mobile/app.json`, `mobile/package.json` et `mobile/android/app/build.gradle` (`versionCode` / `versionName`). Pour une nouvelle version, incrémenter ces trois fichiers avant le build. Attention : `expo prebuild` réécrit `build.gradle` et remet `versionCode 1` — le remettre à la valeur voulue **après** le prebuild, avant Gradle.

APK prêts à installer :

```text
C:\Project\partnership\mobile\FinanceMobile-1.0.0.apk   (ancienne version)
C:\Project\partnership\mobile\FinanceMobile-1.1.0.apk   (ancienne version)
C:\Project\partnership\mobile\FinanceMobile-1.2.0.apk   (ancienne version, historique de connexion)
C:\Project\partnership\mobile\FinanceMobile-1.3.0.apk   (version actuelle, tableau de bord enrichi)
```

### 8.3 Installer sur un téléphone branché en USB

```powershell
cd C:\Project\partnership\mobile
adb install -r FinanceMobile-1.3.0.apk
```

Ou copier simplement le fichier `.apk` sur le téléphone et l’ouvrir.

---

## 9. Build EAS (alternative cloud)

```powershell
cd C:\Project\partnership\mobile
npm.cmd install -g eas-cli
eas login
eas build -p android --profile apk
```

Le profil `apk` est défini dans `mobile/eas.json`.

Le build local reste la méthode de référence sur cette machine.

---

## 10. Problèmes fréquents

### Le téléphone ne se connecte pas à l’API

Vérifier dans cet ordre :

1. PC et téléphone sur le même Wi-Fi.
2. Laravel démarré avec `--host=0.0.0.0`.
3. Pare-feu Windows autorisant le port 8000.
4. Adresse dans l’app : `http://IP_PC:8000/api`.
5. IP actuelle de la machine (elle peut changer).
6. `MOBILE_APP_ORIGINS=*` puis `php artisan optimize:clear`.
7. L’APK autorise le trafic HTTP clair (`android:usesCleartextTraffic="true"` dans `AndroidManifest.xml`).

### `http://IP:8000/api` renvoie 404

C’est normal : il n’existe pas de route `/api` racine. Utiliser plutôt :

```text
http://IP:8000/api/login   (POST)
http://IP:8000/api/me      (GET, avec token)
```

Une réponse `405` sur `/api/login` en GET est également normale : cette route attend un POST.

### `php` affiche encore 8.5

Utiliser explicitement :

```powershell
C:\php-8.3\php.exe -v
```

Ou vérifier que `C:\php\php.bat` contient :

```bat
@echo off
"C:\php-8.3\php.exe" %*
```

### Erreur Gradle / Java

Vérifier :

```powershell
java -version
```

Doit afficher Java 17. Repositionner `JAVA_HOME`, `ANDROID_HOME` et `PATH` comme au paragraphe 8.1.

### L’APK est plus ancien que les modifications

Recompiler puis recopier :

```powershell
cd C:\Project\partnership\mobile\android
.\gradlew.bat assembleRelease --no-daemon
cd ..
copy .\android\app\build\outputs\apk\release\app-release.apk .\FinanceMobile-1.2.0.apk /Y
```

---

## 11. Résumé rapide

```powershell
# API accessible sur le réseau
cd C:\Project\partnership
php artisan serve --host=0.0.0.0 --port=8000

# Expo en réseau
cd C:\Project\partnership\mobile
npm.cmd start -- --host lan --port 19000

# Build APK
npm.cmd run typecheck
npx.cmd expo prebuild --platform android
cd android
.\gradlew.bat assembleRelease --no-daemon
cd ..
copy .\android\app\build\outputs\apk\release\app-release.apk .\FinanceMobile-1.2.0.apk /Y
```

Adresse à saisir dans l’application :

```text
http://172.16.206.66:8000/api
```

(à remplacer par l’IP actuelle de la machine).
