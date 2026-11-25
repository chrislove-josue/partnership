const fs = require('fs');
const p = 'app/Http/Controllers/Api/DataController.php';
let s = fs.readFileSync(p, 'utf8');
const oldLine = `->when($request->filled('church_id'), fn ($query) => $query->where('church_id', $request->integer('church_id')))`;
const newLine = `->when($request->filled('church_id'), function ($query) use ($request): void {
                $ids = app(\\App\\Services\\ChurchDescendantService::class)->forChurch($request->integer('church_id'));
                $query->whereIn('church_id', $ids);
            })`;
const count = s.split(oldLine).length - 1;
if (count !== 3) { console.error('expected 3 occurrences, got '+count); process.exit(1); }
s = s.split(oldLine).join(newLine);
fs.writeFileSync(p, s, 'utf8');
console.log('replaced '+count);
