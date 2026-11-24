const fs = require('fs');
const p = 'mobile/src/api/client.ts';
const lines = fs.readFileSync(p, 'utf8').split('\n');
// Remplacer les lignes 286..317 (1-indexées) par un api() simple.
const start = 286 - 1;
const end = 317; // exclusif
const replacement = `/**
 * Toutes les données sont lues et écrites directement dans la base distante.
 * Aucun cache fichier, aucune copie locale : une panne réseau affiche une erreur.
 */
export async function api<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  return performRequest<T>(path, options);
}`.split('\n');
const out = [...lines.slice(0, start), ...replacement, ...lines.slice(end)].join('\n');
fs.writeFileSync(p, out, 'utf8');
console.log('done');
