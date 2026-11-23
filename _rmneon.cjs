const fs = require('fs');
const p = 'README.md';
const lines = fs.readFileSync(p, 'utf8').split('\n');
// lines are 0-indexed; delete 1-indexed lines 270..279
const start = 270 - 1;
const end = 279; // exclusive
const removed = lines.slice(start, end);
console.log('Removing:', JSON.stringify(removed));
const out = [...lines.slice(0, start), ...lines.slice(end)].join('\n');
fs.writeFileSync(p, out, 'utf8');
console.log('done');
