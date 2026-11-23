// Vérifications de sécurité du code source et, en mode complet, de l'APK.
// Aucune dépendance externe : uniquement les modules intégrés à Node.
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert';
import { execFileSync } from 'node:child_process';

const ROOT = process.cwd();
const REPO_ROOT = path.resolve(ROOT, '..');
const SOURCE_ONLY = process.argv.includes('--source-only');

const results = [];

function check(label, fn) {
  try {
    fn();
    results.push({ label, ok: true });
  } catch (error) {
    results.push({ label, ok: false, detail: error instanceof Error ? error.message : String(error) });
  }
}

function skip(label, detail) {
  results.push({ label, ok: true, skipped: true, detail });
}

function read(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf8');
}

function exists(relativePath) {
  return fs.existsSync(path.join(ROOT, relativePath));
}

function walk(relativeDir) {
  const absolute = path.join(ROOT, relativeDir);
  if (!fs.existsSync(absolute)) {
    return [];
  }

  const files = [];
  for (const entry of fs.readdirSync(absolute, { withFileTypes: true })) {
    if (['node_modules', 'build', '.expo', '.git'].includes(entry.name)) {
      continue;
    }
    const relative = path.join(relativeDir, entry.name).replace(/\\/g, '/');
    if (entry.isDirectory()) {
      files.push(...walk(relative));
    } else if (entry.isFile()) {
      files.push(relative);
    }
  }
  return files;
}

const sourceFiles = [
  ...walk('src').filter((file) => /\.(ts|tsx)$/.test(file)),
  ...(exists('App.tsx') ? ['App.tsx'] : []),
];
const sourceText = sourceFiles.map((file) => read(file)).join('\n');
const manifests = walk('android/app/src').filter((file) => file.endsWith('AndroidManifest.xml'));
const manifestText = manifests.map((file) => read(file)).join('\n');

check('F1 : la release n\'est pas signée avec la clé de debug', () => {
  const gradle = read('android/app/build.gradle');
  const buildTypes = gradle.slice(gradle.indexOf('buildTypes {'), gradle.indexOf('packagingOptions {'));
  const release = buildTypes.slice(buildTypes.indexOf('release {'));
  assert.ok(!/signingConfig\s+signingConfigs\.debug/.test(release), 'release utilise encore signingConfigs.debug');
  assert.ok(/signingConfig\s+signingConfigs\.release/.test(release), 'release ne référence pas signingConfigs.release');
  for (const variable of [
    'ANDROID_KEYSTORE_FILE',
    'ANDROID_KEYSTORE_PASSWORD',
    'ANDROID_KEYALIAS',
    'ANDROID_KEYPASSWORD',
  ]) {
    assert.ok(gradle.includes(variable), `variable ${variable} absente du build.gradle`);
  }
});

check('F1 : debug.keystore n\'est pas versionné', () => {
  const tracked = execFileSync('git', ['-C', REPO_ROOT, 'ls-files'], {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'ignore'],
  });
  const paths = tracked.split(/\r?\n/).map((line) => line.trim().replace(/\\/g, '/'));
  assert.ok(
    !paths.some((file) => file.endsWith('mobile/android/app/debug.keystore')),
    'mobile/android/app/debug.keystore est encore suivi par Git',
  );
  assert.ok(read('.gitignore').includes('android/app/debug.keystore'), 'chemin absent du .gitignore mobile');
});

check('F2 : trafic clair réservé au réseau local', () => {
  assert.ok(!/"usesCleartextTraffic"\s*:\s*true/i.test(read('app.json')), 'app.json active usesCleartextTraffic');
  const main = read('android/app/src/main/AndroidManifest.xml');
  // Android ne sait pas expresser de plage d'IP dans network_security_config :
  // le clair est autorisé au niveau système puis filtré par isLocalNetworkHost().
  assert.ok(
    /android:usesCleartextTraffic\s*=\s*"true"/.test(main),
    'le manifeste n’autorise pas le réseau local en clair',
  );
  assert.ok(!/networkSecurityConfig/.test(main), 'la configuration réseau permissive est référencée en release');
  const client = read('src/api/client.ts');
  assert.ok(client.includes('isLocalNetworkHost'), 'le filtre des hôtes locaux est absent');
  for (const variant of ['debug', 'debugOptimized']) {
    assert.ok(
      read(`android/app/src/${variant}/AndroidManifest.xml`).includes('@xml/network_security_config'),
      `le manifeste ${variant} ne référence pas network_security_config`,
    );
  }
});

check('F2 : network_security_config limité aux hôtes locaux', () => {
  const config = read('android/app/src/main/res/xml/network_security_config.xml');
  const domains = [...config.matchAll(/<domain[^>]*>([^<]+)<\/domain>/g)].map((match) => match[1].trim());
  assert.ok(domains.length > 0, 'aucun domaine déclaré');
  for (const domain of domains) {
    assert.ok(['localhost', '127.0.0.1', '10.0.2.2'].includes(domain), `domaine inattendu : ${domain}`);
  }
  assert.ok(/cleartextTrafficPermitted="false"/.test(config), 'le base-config doit interdire le clair');
});

check('F2 : aucune URL HTTP en dur dans le code source', () => {
  assert.ok(!/http:\/\//i.test(sourceText), 'une URL http:// reste dans le code source');
  assert.ok(!/172\.16\.|192\.168\./.test(sourceText), 'une IP privée reste en dur dans le code source');
});

check('F3 : plus de copie publique de la base de données', () => {
  assert.ok(
    !/\b(backupDatabase|restoreDatabaseBackup|backupDatabaseFile|publicBackupUri|readFromDocuments)\b/.test(sourceText),
    'une fonction de copie/restauration publique est encore appelée',
  );
  const storage = read('src/db/storage.ts');
  assert.ok(storage.includes('deleteDocument(relativePath, DATABASE_FILE)'), 'suppression de l\'ancienne copie absente');
  const kotlin = read('android/app/src/main/java/com/churchmanager/benin/FinanceMediaStoreModule.kt');
  assert.ok(!/fun readDocument\s*\(/.test(kotlin), 'la lecture publique de documents reste exposée');
});

check('F4 : les règles de sauvegarde excluent la base et SecureStore', () => {
  for (const file of [
    'android/app/src/main/res/xml/secure_store_backup_rules.xml',
    'android/app/src/main/res/xml/secure_store_data_extraction_rules.xml',
  ]) {
    const rules = read(file);
    assert.ok(/domain="database"\s+path="finance\.sqlite"/.test(rules), `${file} n'exclut pas finance.sqlite`);
    assert.ok(/SecureStore\.xml/.test(rules), `${file} n'exclut pas SecureStore.xml`);
  }
  const main = read('android/app/src/main/AndroidManifest.xml');
  assert.ok(/android:fullBackupContent="@xml\/secure_store_backup_rules"/.test(main), 'fullBackupContent non branché');
  assert.ok(/android:dataExtractionRules="@xml\/secure_store_data_extraction_rules"/.test(main), 'dataExtractionRules non branché');
});

check('F5 : validation stricte de l\'URL de l\'API', () => {
  const client = read('src/api/client.ts');
  for (const expected of [
    'new URL(trimmed)',
    "parsed.protocol !== 'https:'",
    'parsed.username',
    "parsed.search = ''",
    "parsed.hash = ''",
    'normalizeApiUrl',
    '__DEV__',
  ]) {
    assert.ok(client.includes(expected), `validation manquante : ${expected}`);
  }
  assert.ok(!/message:\s*text/.test(client), 'parseResponse renvoie encore le texte brut');
  assert.ok(/status >= 500/.test(client), 'les erreurs 5xx ne sont pas figées');
  const auth = read('src/context/AuthContext.tsx');
  assert.ok(/setApiUrl\(storedUrl\)/.test(auth), 'l\'URL stockée n\'est pas revalidée au démarrage');
  assert.ok(/deleteItemAsync\(API_URL_KEY\)/.test(auth), 'une URL stockée invalide n\'est pas supprimée');
});

check('F5 : le serveur ne peut pas imposer l\'URL de l\'API', () => {
  assert.ok(
    !/updateApiUrl\s*\(\s*response\s*\.\s*api_url\s*\)/.test(sourceText),
    'response.api_url est appliquée automatiquement',
  );
  assert.ok(!/updateApiUrl\s*\([^)]*api_url/.test(sourceText), 'une valeur distante alimente updateApiUrl');
  const profile = read('src/screens/ProfileScreen.tsx');
  assert.ok(profile.includes('proposedApiUrl'), 'aucune proposition d\'URL affichée à l\'administrateur');
  assert.ok(profile.includes('Confirmer l’adresse de l’API'), 'confirmation explicite absente');
});

check('F5 : le droit de configuration est recalculé depuis /me', () => {
  const auth = read('src/context/AuthContext.tsx');
  assert.ok(/isSuperAdmin\(response\.user\)/.test(auth), 'le droit n\'est pas recalculé depuis /me');
  assert.ok(!/getItemAsync\(LEGACY_ADMIN_DEVICE_KEY\)/.test(auth), 'l\'ancien drapeau est encore lu');
  assert.ok(!/setItemAsync\(LEGACY_ADMIN_DEVICE_KEY\)/.test(auth), 'l\'ancien drapeau est encore écrit');
});

check('F6 : permissions Android minimales', () => {
  assert.ok(!/MANAGE_EXTERNAL_STORAGE|POST_NOTIFICATIONS|SYSTEM_ALERT_WINDOW|WRITE_EXTERNAL_STORAGE/.test(read('app.json')), 'permissions excessives dans app.json');
  assert.ok(/"INTERNET"/.test(read('app.json')), 'permission INTERNET manquante');
  const forbidden = /MANAGE_EXTERNAL_STORAGE|POST_NOTIFICATIONS|READ_EXTERNAL_STORAGE|SYSTEM_ALERT_WINDOW/;
  assert.ok(!forbidden.test(manifestText), 'permission excessive dans un manifeste');
  for (const file of manifests) {
    const text = read(file);
    for (const line of text.split(/\r?\n/)) {
      if (/WRITE_EXTERNAL_STORAGE/.test(line)) {
        assert.ok(line.endsWith('maxSdkVersion="28"/>'), `WRITE_EXTERNAL_STORAGE non plafonné dans ${file}`);
      }
    }
  }
  assert.ok(!/POST_NOTIFICATIONS/.test(read('src/native/permissions.ts')), 'notification demandée côté JS');
});

check('F7 : purge locale à la déconnexion et au changement de compte', () => {
  const database = read('src/db/database.ts');
  assert.ok(/export async function clearLocalData\(\)/.test(database), 'clearLocalData absent');
  for (const table of ['entities', 'outbox', 'conflicts', 'meta']) {
    assert.ok(new RegExp(`DELETE FROM ${table}`).test(database), `table ${table} non purgée`);
  }
  const auth = read('src/context/AuthContext.tsx');
  assert.ok(/clearLocalData\(\)/.test(auth), 'clearLocalData non appelé dans AuthContext');
  assert.ok(/isSameUser\(/.test(auth), 'changement d\'utilisateur non détecté');
});

check('F8 : messages d\'erreur génériques côté natif', () => {
  const kotlin = read('android/app/src/main/java/com/churchmanager/benin/FinanceMediaStoreModule.kt');
  assert.ok(!/error\.message|error\.stackTrace/.test(kotlin), 'détails techniques exposés dans reject');
  assert.ok(!/promise\.reject\([^)]*error/.test(kotlin), 'exception transmise au JavaScript');
});

check('F9/F8 : journalisation et messages d\'erreur maîtrisés', () => {
  for (const file of sourceFiles) {
    const text = read(file);
    let index = text.indexOf('console.');
    while (index >= 0) {
      const before = text.slice(Math.max(0, index - 160), index);
      assert.ok(/__DEV__/.test(before), `console non gardé dans ${file}`);
      index = text.indexOf('console.', index + 1);
    }
  }
  assert.ok(!/Adresse utilisée/.test(read('src/screens/LoginScreen.tsx')), 'l\'adresse est encore affichée dans une erreur');
});

check('F10 : validation des montants, églises et tailles de fichiers', () => {
  const donations = read('src/screens/DonationsScreen.tsx');
  assert.ok(/Number\.isFinite\(parsedAmount\)/.test(donations), 'montant non validé');
  assert.ok(/parsedAmount <= 0/.test(donations), 'montant nul ou négatif accepté');
  const admin = read('src/screens/AdminScreen.tsx');
  assert.ok(/assignedChurchId \? Number\(assignedChurchId\) : null/.test(admin), 'assigned_church_id non normalisé');
  assert.ok(read('src/api/client.ts').includes('MAX_UPLOAD_BYTES'), 'limite de taille absente de apiUpload');
  assert.ok(read('src/components/ImportExportCard.tsx').includes('UPLOAD_TOO_LARGE_MESSAGE'), 'limite absente de l\'UI');
});

function toolExists(command) {
  try {
    execFileSync(command, ['version'], {
      stdio: 'ignore',
      shell: process.platform === 'win32',
    });
    return true;
  } catch (error) {
    return error?.code !== 'ENOENT';
  }
}

/** Windows ne résout pas toujours les scripts .bat via execFile sans shell. */
function resolveTool(command) {
  const candidates = process.platform === 'win32'
    ? [command, `${command}.exe`, `${command}.bat`, `${command}.cmd`]
    : [command];

  return candidates.find((candidate) => toolExists(candidate)) ?? null;
}

if (!SOURCE_ONLY) {
  const candidates = [
    'android/app/build/outputs/apk/release/app-release.apk',
    ...walk('.').filter((file) => /^FinanceMobile-.*\.apk$/.test(path.basename(file))),
  ].filter((file) => exists(file));
  const apk = candidates.length > 0 ? path.join(ROOT, candidates[candidates.length - 1]) : null;

  if (!apk) {
    skip('APK : manifeste (mode complet)', 'aucun APK présent');
    skip('APK : signature de release (mode complet)', 'aucun APK présent');
  } else {
    const aapt = resolveTool('aapt');
    if (aapt) {
      check('APK : manifeste (mode complet)', () => {
        const output = execFileSync(aapt, ['dump', 'xmltree', apk, 'AndroidManifest.xml'], {
          encoding: 'utf8',
          stdio: ['ignore', 'pipe', 'ignore'],
          shell: process.platform === 'win32',
        });
        assert.ok(!/usesCleartextTraffic/i.test(output), 'l\'APK demande du trafic en clair');
        assert.ok(!/MANAGE_EXTERNAL_STORAGE|POST_NOTIFICATIONS|SYSTEM_ALERT_WINDOW/.test(output), 'permissions excessives dans l\'APK');
      });
    } else {
      skip('APK : manifeste (mode complet)', 'aapt absent : contrôle ignoré');
    }

    const apksigner = resolveTool('apksigner');
    if (apksigner) {
      check('APK : signature de release (mode complet)', () => {
        let signature = '';
        try {
          signature = execFileSync(apksigner, ['verify', '--print-certs', apk], {
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'ignore'],
            shell: process.platform === 'win32',
          });
        } catch (error) {
          signature = `${error.stdout ?? ''}\n${error.stderr ?? ''}`;
        }
        assert.ok(signature.trim() !== '', 'signature de l\'APK illisible');
        assert.ok(!/CN=Android Debug/i.test(signature), 'l\'APK est signé avec le certificat de debug');
      });
    } else {
      skip('APK : signature de release (mode complet)', 'apksigner absent : contrôle ignoré');
    }
  }
}

console.log(`Contrôles de sécurité FinanceMobile — mode ${SOURCE_ONLY ? 'source uniquement' : 'complet'}`);
for (const result of results) {
  console.log(`${result.skipped ? 'IGNORÉ' : result.ok ? 'OK   ' : 'ÉCHEC'} ${result.label}`);
  if (!result.ok && result.detail) {
    console.log(`      ${result.detail}`);
  }
}

const failed = results.filter((result) => !result.ok).length;
const skipped = results.filter((result) => result.skipped).length;
console.log(`\n${results.length - failed - skipped}/${results.length} contrôles réussis, ${skipped} ignoré(s).`);
if (failed > 0) {
  process.exitCode = 1;
}
