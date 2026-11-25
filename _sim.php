<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Church;
use App\Services\ChurchHierarchyService;
use App\Services\ChurchAccessService;

// Simulation de la structure cible SANS écrire en base
$root = new Church(['name' => 'Bénin Groupe 1', 'type' => \App\Enums\ChurchType::GROUP]);
$root->id = 100;
$sg1 = new Church(['name' => 'Sous-groupe 1', 'type' => \App\Enums\ChurchType::SUB_GROUP, 'parent_id' => 100]);
$sg1->id = 101;
$mother = new Church(['name' => 'CE SIKECODJI', 'type' => \App\Enums\ChurchType::LOCAL, 'parent_id' => 101]);
$mother->id = 1;
$deep = new Church(['name' => 'Église fille profonde', 'type' => \App\Enums\ChurchType::LOCAL, 'parent_id' => 1]);
$deep->id = 999;

echo "ancêtres de la fille profonde: ".implode(',', $deep->ancestorIds())."\n";
echo "le groupe racine (100) est ancêtre: ".(in_array(100, $deep->ancestorIds(), true) ? 'OUI' : 'NON')."\n";

$hierarchy = app(ChurchHierarchyService::class);
echo "parent autorisé pour SUB_GROUP sous GROUP: ".($hierarchy->isAllowedParent(\App\Enums\ChurchType::SUB_GROUP, $root) ? 'OUI' : 'NON')."\n";
echo "parent autorisé pour LOCAL sous SUB_GROUP: ".($hierarchy->isAllowedParent(\App\Enums\ChurchType::LOCAL, $sg1) ? 'OUI' : 'NON')."\n";
