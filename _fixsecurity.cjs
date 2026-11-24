const fs = require('fs');
const p = 'mobile/scripts/security-check.mjs';
let s = fs.readFileSync(p, 'utf8');

const oldBlock = `check('F7 : purge locale à la déconnexion et au changement de compte', () => {
  const database = read('src/db/database.ts');
  assert.ok(/export async function clearLocalData\\(\\)/.test(database), 'clearLocalData absent');
  for (const table of ['entities', 'outbox', 'conflicts', 'meta']) {
    assert.ok(new RegExp(\`DELETE FROM \${table}\`).test(database), \`table \${table} non purgée\`);
  }
  const auth = read('src/context/AuthContext.tsx');
  assert.ok(/clearLocalData\\(\\)/.test(auth), 'clearLocalData non appelé dans AuthContext');
  assert.ok(/isSameUser\\(/.test(auth), 'changement d'utilisateur non détecté');
});`;

const newBlock = `check('F7 : aucune donnée métier stockée dans un fichier', () => {
  // Toutes les données (catégories, dons, partenaires, églises, cellules,
  // utilisateurs) sont dans la base distante : aucun cache fichier ni base locale.
  for (const file of ['src/db/database.ts', 'src/db/sync.ts', 'src/api/cache.ts', 'src/db/storage.ts']) {
    assert.ok(!exists(file), \`\${file} existe encore : des données seraient stockées hors base\`);
  }
  assert.ok(!/expo-sqlite|openDatabaseAsync/.test(sourceText), 'expo-sqlite est encore utilisé');
  assert.ok(!/churchmanager-cache|readStaleCache|writeCache/.test(sourceText), 'un cache fichier est encore utilisé');
  const auth = read('src/context/AuthContext.tsx');
  assert.ok(/api<\\{ user: User \\}>\\('\\/me'\\)/.test(auth), 'l utilisateur n est pas rechargé depuis la base');
  assert.ok(/deleteItemAsync\\(TOKEN_KEY\\)/.test(auth), 'le jeton n est pas effacé à la déconnexion');
});`;

if (!s.includes(oldBlock)) {
  console.error('F7 block not found');
  process.exit(1);
}
s = s.replace(oldBlock, newBlock);
fs.writeFileSync(p, s, 'utf8');
console.log('done');
