const fs = require('fs');
const p = 'mobile/src/screens/ReportsScreen.tsx';
const lines = fs.readFileSync(p, 'utf8').split('\n');
// Remplacer les lignes 26..34 (1-indexées) par les constantes de période.
const start = 26 - 1;
const end = 34;
const replacement = `const PERIODS = [
  { label: 'Par mois', value: 'month' },
  { label: 'Par trimestre', value: 'quarter' },
  { label: 'Par année', value: 'year' },
];

const MONTHS = [
  { label: 'Janvier', value: '1' }, { label: 'Février', value: '2' }, { label: 'Mars', value: '3' },
  { label: 'Avril', value: '4' }, { label: 'Mai', value: '5' }, { label: 'Juin', value: '6' },
  { label: 'Juillet', value: '7' }, { label: 'Août', value: '8' }, { label: 'Septembre', value: '9' },
  { label: 'Octobre', value: '10' }, { label: 'Novembre', value: '11' }, { label: 'Décembre', value: '12' },
];

const QUARTERS = [
  { label: 'T1', value: '1' }, { label: 'T2', value: '2' }, { label: 'T3', value: '3' }, { label: 'T4', value: '4' },
];`.split('\n');
const out = [...lines.slice(0, start), ...replacement, ...lines.slice(end)].join('\n');
fs.writeFileSync(p, out, 'utf8');
console.log('done');
