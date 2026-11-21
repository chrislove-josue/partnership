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
        $churchIds = $access->accessibleChurchIds($user);

        $deletions = SyncDeletion::query()
            ->when($since !== '', fn ($query) => $query->where('deleted_at', '>', $since))
            // Une suppression locale reste locale : un pasteur ne doit pas pouvoir
            // déduire l'existence d'une église ou d'un partenaire hors de son périmètre.
            ->where(fn ($query) => $query
                ->whereNull('church_id')
                ->orWhereIn('church_id', $churchIds))
            ->orderBy('deleted_at')
            ->limit(500)
            ->get()
            ->map(fn (SyncDeletion $deletion): array => [
                'entity' => $deletion->entity,
                'entity_id' => (string) $deletion->entity_id,
                'deleted_at' => $deletion->deleted_at?->toIso8601String(),
            ])->all();

        return response()->json([
            'data' => $deletions,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
