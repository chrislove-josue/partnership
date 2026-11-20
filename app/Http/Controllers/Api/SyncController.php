<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /** Suppressions survenues depuis la dernière synchronisation du téléphone. */
    public function deletions(Request $request): JsonResponse
    {
        $since = $request->string('since')->toString();

        $deletions = SyncDeletion::query()
            ->when($since !== '', fn ($query) => $query->where('deleted_at', '>', $since))
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
