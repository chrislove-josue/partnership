const fs = require('fs');
const p = 'resources/views/filament/pages/partnership-report.blade.php';
const lines = fs.readFileSync(p, 'utf8').split('\n');
// remove 1-indexed lines 121..151 inclusive
const start = 121 - 1;
const end = 151; // exclusive
const removed = lines.slice(start, end);
console.log('Removing:', removed.length, 'lines');
console.log(JSON.stringify(removed.slice(0,3)), '...', JSON.stringify(removed.slice(-2)));
const out = [...lines.slice(0, start), ...lines.slice(end)].join('\n');
fs.writeFileSync(p, out, 'utf8');
console.log('done');
