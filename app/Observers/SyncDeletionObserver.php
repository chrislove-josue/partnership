<?php

namespace App\Observers;

use App\Models\CategoryDon;
use App\Models\Cellule;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\SyncDeletion;
use Illuminate\Database\Eloquent\Model;

/** Enregistre les suppressions pour la synchronisation incrémentale de l'APK. */
class SyncDeletionObserver
{
    private const ENTITIES = [
        Church::class => 'churches',
        Cellule::class => 'cellules',
        CategoryDon::class => 'categories',
        Partner::class => 'partners',
        Donation::class => 'donations',
    ];

    public function deleted(Model $model): void
    {
        $entity = self::ENTITIES[$model::class] ?? null;

        if ($entity === null) {
            return;
        }

        SyncDeletion::query()->create([
            'entity' => $entity,
            'entity_id' => $model->getKey(),
            'deleted_at' => now(),
        ]);
    }
}
