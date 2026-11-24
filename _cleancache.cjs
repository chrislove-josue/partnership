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

const root = 'mobile/src';
for (const file of walk(root)) {
  let s = fs.readFileSync(file, 'utf8');
  const before = s;

  // Imports : api, getErrorMessage, invalidateCache -> api, getErrorMessage
  s = s.replace(/import \{([^}]*)\} from '\.\.\/api\/client';/g, (m, names) => {
    const cleaned = names
      .split(',')
      .map((n) => n.trim())
      .filter((n) => n && n !== 'invalidateCache' && n !== 'syncNow');
    if (cleaned.length === 0) return m;
    return `import { ${cleaned.join(', ')} } from '../api/client';`;
  });
  s = s.replace(/import \{([^}]*)\} from '\.\.\/\.\.\/api\/client';/g, (m, names) => {
    const cleaned = names
      .split(',')
      .map((n) => n.trim())
      .filter((n) => n && n !== 'invalidateCache' && n !== 'syncNow');
    if (cleaned.length === 0) return m;
    return `import { ${cleaned.join(', ')} } from '../../api/client';`;
  });

  // Options cacheKey / cacheTtl / refresh / skipOffline dans les appels api()
  s = s.replace(/,\s*\{\s*cacheKey:[^\n}]*\}/g, '');
  s = s.replace(/\{\s*cacheKey:[^\n}]*,\s*refresh\s*\}\s*\)/g, ')');
  s = s.replace(/\n\s*cacheKey:[^\n]*,?/g, '');
  s = s.replace(/\n\s*refresh,?/g, '');
  s = s.replace(/,\s*refresh\s*=\s*false/g, '');
  s = s.replace(/\binvalidateCache\([^)]*\);\s*/g, '');

  if (s !== before) {
    fs.writeFileSync(file, s, 'utf8');
    console.log('cleaned', file.replace('mobile/src/', ''));
  }
}
