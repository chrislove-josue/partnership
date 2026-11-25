const fs = require('fs');
const p = 'app/Http/Controllers/Api/DataController.php';
let s = fs.readFileSync(p, 'utf8');

// Totaux : locale = direct, groupe/sous-groupe = descendance
s = s.replace(
`        // Chaque église affiche le total de ses dons ET de ceux de toute sa
        // descendance (groupes et sous-groupes n'ont pas de dons directs).
        $descendants = app(ChurchDescendantService::class);
        $totals = [];

        foreach ($churches as $church) {
            $ids = $descendants->forChurch($church->id);`,
`        // Une église locale = ses dons directs ; un groupe/sous-groupe =
        // la somme des dons de sa descendance.
        $descendants = app(ChurchDescendantService::class);
        $totals = [];

        foreach ($churches as $church) {
            $ids = $descendants->forFilter($church->id);`);

// Les 3 filtres church_id
s = s.replaceAll(
  "app(ChurchDescendantService::class)->forChurch($request->integer('church_id'))",
  "app(ChurchDescendantService::class)->forFilter($request->integer('church_id'))",
);

fs.writeFileSync(p, s, 'utf8');
console.log('done');
