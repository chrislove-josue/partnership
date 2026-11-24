const fs = require('fs');
const p = 'mobile/src/api/client.ts';
const lines = fs.readFileSync(p, 'utf8').split('\n');
// Supprimer les lignes 286..488 (1-indexées) : patterns, filtres locaux, tryReadLocal,
// mirrorOnlineWrite, queueOfflineWrite, buildLocalRecord, isNetworkError, triggerSync.
const start = 286 - 1;
const end = 488; // exclusif -> supprime jusqu'à la ligne 488 incluse
console.log('Removing lines', start + 1, 'to', end, ':', lines[start].trim(), '...', lines[end-1].trim());
const out = [...lines.slice(0, start), ...lines.slice(end)].join('\n');
fs.writeFileSync(p, out, 'utf8');
console.log('done');
