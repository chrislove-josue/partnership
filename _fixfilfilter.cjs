const fs = require('fs');
const files = [
  'app/Filament/Resources/Donations/Tables/DonationsTable.php',
  'app/Filament/Resources/Partners/Tables/PartnersTable.php',
  'app/Filament/Resources/Cellules/Tables/CellulesTable.php',
];
for (const p of files) {
  let s = fs.readFileSync(p, 'utf8');
  const before = s;
  s = s.replace('->forChurch((int) $state[\'value\']);', '->forFilter((int) $state[\'value\']);');
  if (s === before) { console.error('not found in '+p); process.exit(1); }
  fs.writeFileSync(p, s, 'utf8');
  console.log('fixed '+p);
}
