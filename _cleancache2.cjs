const fs = require('fs');
const path = require('path');

function walk(dir, out = []) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p, out);
    else if (/\.(ts|tsx)$/.test(e.name)) out.push(p);
  }
  return out;
}

// Supprime une option d'un objet littéral en respectant les parenthèses.
function removeObjectKey(text, key) {
  let out = text;
  let changed = true;
  while (changed) {
    changed = false;
    const idx = out.indexOf(key + ':');
    if (idx < 0) break;
    // Vérifier que c'est une clé d'objet (précédée de '{' ou ',' + espaces)
    const before = out.slice(Math.max(0, idx - 40), idx);
    if (!/[{,]\s*$/.test(before)) {
      // chercher la suivante
      const next = out.indexOf(key + ':', idx + 1);
      if (next >= 0) { out = out.slice(0, idx) + '\u0000' + out.slice(idx + 1); out = out.replace('\u0000', ''); changed = true; continue; }
      break;
    }
    // Trouver la fin de la valeur (virgule ou } au même niveau)
    let i = idx + key.length + 1;
    let depth = 0;
    let end = -1;
    for (; i < out.length; i++) {
      const ch = out[i];
      if (ch === '(' || ch === '[' || ch === '{') depth++;
      else if (ch === ')' || ch === ']' || ch === '}') {
        if (depth === 0) { end = i; break; }
        depth--;
      } else if (ch === ',' && depth === 0) { end = i; break; }
    }
    if (end < 0) break;
    // Supprimer de idx à end (inclure la virgule si présente)
    let from = idx;
    // remonter sur les espaces et éventuellement la virgule précédente
    while (from > 0 && /\s/.test(out[from - 1])) from--;
    if (out[from - 1] === ',') { from--; while (from > 0 && /\s/.test(out[from - 1])) from--; }
    out = out.slice(0, from) + out.slice(end + (out[end] === ',' ? 1 : 0));
    changed = true;
  }
  return out;
}

for (const file of walk('mobile/src')) {
  let s = fs.readFileSync(file, 'utf8');
  const before = s;

  // 1. imports : retirer invalidateCache / clearCache / syncNow de la liste
  s = s.replace(/import\s*\{([^}]*)\}\s*from\s*(['"])([^'"]*api\/client)\2;/g, (m, names, q, mod) => {
    const cleaned = names.split(',').map((n) => n.trim()).filter((n) => n && !['invalidateCache', 'clearCache', 'syncNow', 'cacheKey'].includes(n));
    if (cleaned.length === 0) return '';
    return `import { ${cleaned.join(', ')} } from ${q}${mod}${q};`;
  });

  // 2. retirer les props d'options dans les appels api(...) : lignes entières
  s = s.replace(/^\s*cacheKey:[^\n]*\n/gm, '');
  s = s.replace(/^\s*refresh,?\s*\n/gm, '');
  s = s.replace(/\s*refresh\s*=\s*false/g, '');
  s = s.replace(/,\s*cacheKey:[^}\n]*/g, '');
  s = s.replace(/\{\s*cacheKey:[^}\n]*\}\s*\)/g, ')');

  // 3. retirer les appels invalidateCache(...) / clearCache() (lignes seules)
  s = s.replace(/^\s*invalidateCache\([^)]*\);\s*\n/gm, '');
  s = s.replace(/^\s*clearCache\(\);\s*\n/gm, '');

  if (s !== before) {
    fs.writeFileSync(file, s, 'utf8');
    console.log('cleaned', file.replace('mobile/src/', ''));
  }
}
