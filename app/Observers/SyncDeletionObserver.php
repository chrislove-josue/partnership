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
            'church_id' => $this->churchIdFor($model),
            'deleted_at' => now(),
        ]);
    }

    private function churchIdFor(Model $model): ?int
    {
        if ($model instanceof Church) {
            // Le périmètre est calculé après la suppression : on rattache la
            // suppression à l'église parente encore visible pour le pasteur.
            // Une église racine n'a plus de parent visible : sa suppression est
            // globale, sinon les super administrateurs ne la recevraient jamais.
            return $model->parent_id;
        }

        if ($model instanceof Cellule) {
            return $model->church_id;
        }

        // Les catégories sont globales et partagées : aucune restriction d'église.
        return $model instanceof Partner || $model instanceof Donation
            ? $model->church_id
            : null;
    }
}
