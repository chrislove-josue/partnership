const fs = require('fs');
const p = 'mobile/src/screens/ProfileScreen.tsx';
let s = fs.readFileSync(p, 'utf8');

s = s.replace(/import \{ countPendingChanges, getMeta, initDatabase, listConflicts \} from '\.\.\/db\/database';\n/, '');
s = s.replace(/import \{ syncNow \} from '\.\.\/db\/sync';\n/, '');
s = s.replace(
  'const { user, session, refreshUser, logout, apiUrl, canConfigureApiUrl, updateApiUrl, markSynced } = useAuth();',
  'const { user, session, refreshUser, logout, apiUrl, canConfigureApiUrl, updateApiUrl } = useAuth();',
);
s = s.replace(/  const \[syncInfo, setSyncInfo\] = useState\([^\n]*\n/, '');
s = s.replace(/  const \[syncing, setSyncing\] = useState\([^\n]*\n/, '');

// Supprimer loadSyncInfo + effet + synchronize (du "const loadSyncInfo" à la fin de synchronize)
const loadStart = s.indexOf('  const loadSyncInfo = useCallback(async () => {');
const syncEndMarker = '  };\n\n  const save = async () => {';
const syncEnd = s.indexOf(syncEndMarker, loadStart);
if (loadStart >= 0 && syncEnd >= 0) {
  s = s.slice(0, loadStart) + '  const save = async () => {' + s.slice(syncEnd + syncEndMarker.length);
}

// Supprimer la carte "Données hors-ligne"
const cardStart = s.indexOf('      <Card>\n        <SectionTitle>Données hors-ligne</SectionTitle>');
if (cardStart >= 0) {
  const cardEnd = s.indexOf('      </Card>', cardStart) + '      </Card>\n\n'.length;
  s = s.slice(0, cardStart) + s.slice(cardEnd);
}

// Supprimer la ligne "Dernière synchronisation" de la carte Sécurité
s = s.replace(/\s*Dernière synchronisation : \{formatSessionDate\(session\.lastSyncAt\)\}\n/, '\n');

fs.writeFileSync(p, s, 'utf8');
console.log('done');
