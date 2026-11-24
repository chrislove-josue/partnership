const fs = require('fs');
const p = 'mobile/src/navigation/AppNavigator.tsx';
const lines = fs.readFileSync(p, 'utf8').split('\n');
// Supprimer l'effet de synchronisation (lignes 96..124, 1-indexées)
const start = 96 - 1;
const end = 124;
console.log('removing', start + 1, 'to', end, ':', lines[start].trim(), '...', lines[end-1].trim());
const out = [...lines.slice(0, start), ...lines.slice(end)].join('\n');
fs.writeFileSync(p, out, 'utf8');
console.log('done');
