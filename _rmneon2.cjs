const fs = require('fs');
const p = 'app/Console/Commands/ImportLegacyData.php';
let s = fs.readFileSync(p, 'utf8');
s = s.replace('Postgres/Neon', 'Postgres/Prisma');
fs.writeFileSync(p, s, 'utf8');
console.log('done', s.includes('Postgres/Neon'));
