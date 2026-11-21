<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncDeletion;
use App\Services\ChurchAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /** Suppressions survenues depuis la dernière synchronisation du téléphone. */
    public function deletions(Request $request, ChurchAccessService $access): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $since = $request->string('since')->toString();
        $afterId = $request->integer('after_id');
        // La limite peut être réduite par le client pour découper la réponse,
        // mais reste plafonnée pour éviter une requête disproportionnée.
        $limit = min(max($request->integer('limit', 500), 1), 500);
        $churchIds = $access->accessibleChurchIds($user);

        $rows = SyncDeletion::query()
            ->when($since !== '', fn ($query) => $query->where('deleted_at', '>', $since))
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            // Une suppression locale reste locale : un pasteur ne doit pas pouvoir
            // déduire l'existence d'une église ou d'un partenaire hors de son périmètre.
            ->where(fn ($query) => $query
                ->whereNull('church_id')
                ->orWhereIn('church_id', $churchIds))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (SyncDeletion $deletion): array => [
                'id' => $deletion->id,
                'entity' => $deletion->entity,
                'entity_id' => (string) $deletion->entity_id,
                'deleted_at' => $deletion->deleted_at?->toIso8601String(),
            ])->all(),
            'has_more' => $rows->count() === $limit,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
