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
for (const file of walk('mobile/src')) {
  let s = fs.readFileSync(file, 'utf8');
  const before = s;
  // objets vides laissés par la suppression de cacheKey
  s = s.replace(/,\s*\{\s*\}\s*\)/g, ')');
  s = s.replace(/,\s*\)\s*(?=[;\n])/g, ')');
  if (s !== before) { fs.writeFileSync(file, s, 'utf8'); console.log('cleaned', file.replace('mobile/src/', '')); }
}
