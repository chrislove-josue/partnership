<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\CategoryDon;
use App\Models\Cellule;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Services\ChurchAccessService;
use App\Services\DonationAttributionService;
use App\Services\DonationNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DataController extends Controller
{
    public function __construct(
        private readonly ChurchAccessService $access,
        private readonly DonationNotifier $notifier,
        private readonly DonationAttributionService $attribution,
    ) {}

    /**
     * Options utilisées par la synchronisation incrémentale de l'APK.
     * La limite est plafonnée et le curseur `after_id` permet de reprendre
     * exactement là où le téléphone s'est arrêté, même au-delà de 500 lignes.
     *
     * @return array{0: bool, 1: int, 2: int}
     */
    private function syncOptions(Request $request): array
    {
        return [
            $request->boolean('sync'),
            $request->integer('after_id'),
            min(max($request->integer('limit', 500), 1), 500),
        ];
    }

    public function churches(Request $request): JsonResponse
    {
        [$isSync, $afterId, $limit] = $this->syncOptions($request);
        $since = $request->string('updated_after')->toString();
        $churches = $this->access->accessibleChurches($request->user());

        if ($since !== '') {
            $churches = $churches->filter(
                fn (Church $church): bool => (string) $church->updated_at?->toIso8601String() > $since,
            );
        }
        if ($afterId > 0) {
            $churches = $churches->filter(fn (Church $church): bool => $church->id > $afterId);
        }

        $hasMore = false;
        if ($isSync) {
            $churches = $churches->sortBy('id')->values();
            $hasMore = $churches->count() > $limit;
            $churches = $churches->take($limit);
        }

        // L'APK affiche le total par église : il doit être calculé ici comme
        // dans le tableau de bord Filament (dons rattachés directement).
        $totals = Donation::query()
            ->selectRaw('church_id, COALESCE(SUM(amount), 0) as total')
            ->whereIn('church_id', $churches->pluck('id'))
            ->groupBy('church_id')
            ->pluck('total', 'church_id');

        return response()->json([
            'data' => $churches->map(fn (Church $church): array => [
                'id' => $church->id,
                'name' => $church->name,
                'type' => $church->type?->value ?? (string) $church->type,
                'parent_id' => $church->parent_id,
                'is_active' => $church->is_active,
                'total_donations' => (float) ($totals[$church->id] ?? 0),
                'updated_at' => $church->updated_at?->toIso8601String(),
            ])->values()->all(),
            'has_more' => $hasMore,
        ]);
    }

    public function partners(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Partner::class);

        [$isSync, $afterId, $limit] = $this->syncOptions($request);

        $query = Partner::query()
            ->with(['church:id,name', 'cellule:id,name'])
            ->withTotalDonations($this->access->accessibleChurchIds($request->user()))
            // Tous les partenaires sont consultables : un partenaire peut faire
            // son culte dans une autre église que la sienne.
            ->when($request->filled('updated_after'), fn ($query) => $query->where('updated_at', '>', $request->string('updated_after')->toString()))
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('handle_kc', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('church_id'), fn ($query) => $query->where('church_id', $request->integer('church_id')))
            ->when($request->filled('cellule_id'), fn ($query) => $query->where('cellule_id', $request->integer('cellule_id')));

        if ($isSync) {
            $query->orderBy('id');
        } else {
            $query->orderBy('last_name')->orderBy('first_name');
        }
        $rows = $query->limit($isSync ? $limit : 300)->get();
        $hasMore = $isSync && $rows->count() === $limit;

        return response()->json(['data' => $rows->map(fn (Partner $partner): array => [
            'id' => $partner->id,
            'first_name' => $partner->first_name,
            'last_name' => $partner->last_name,
            'full_name' => $partner->full_name,
            'email' => $partner->email,
            'phone' => $partner->phone,
            'handle_kc' => $partner->handle_kc,
            'address' => $partner->address,
            'birthdate' => $partner->birthdate?->toDateString(),
            'church_id' => $partner->church_id,
            'church' => $partner->church?->name,
            'cellule_id' => $partner->cellule_id,
            'cellule' => $partner->cellule?->name,
            'is_user' => $partner->isUserAccount(),
            'total_donations' => (float) ($partner->total_donations ?? 0),
            'updated_at' => $partner->updated_at?->toIso8601String(),
        ])->all(), 'has_more' => $hasMore]);
    }

    public function cellules(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Cellule::class);

        [$isSync, $afterId, $limit] = $this->syncOptions($request);

        $query = Cellule::query()
            ->with(['church:id,name', 'leader:id,first_name,last_name'])
            ->withTotalDonations()
            ->whereIn('church_id', $this->access->accessibleChurchIds($request->user()))
            ->when($request->filled('updated_after'), fn ($query) => $query->where('updated_at', '>', $request->string('updated_after')->toString()))
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->filled('church_id'), fn ($query) => $query->where('church_id', $request->integer('church_id')));

        if ($isSync) {
            $query->orderBy('id');
        } else {
            $query->orderBy('name');
        }
        $rows = $query->limit($isSync ? $limit : 300)->get();
        $hasMore = $isSync && $rows->count() === $limit;

        return response()->json(['data' => $rows->map(fn (Cellule $cellule): array => [
            'id' => $cellule->id,
            'name' => $cellule->name,
            'church_id' => $cellule->church_id,
            'church' => $cellule->church?->name,
            'leader_id' => $cellule->leader_id,
            'leader' => $cellule->leader?->full_name,
            'total_donations' => (float) ($cellule->total_donations ?? 0),
            'updated_at' => $cellule->updated_at?->toIso8601String(),
        ])->all(), 'has_more' => $hasMore]);
    }

    public function categories(Request $request): JsonResponse
    {
        [$isSync, $afterId, $limit] = $this->syncOptions($request);

        $query = CategoryDon::query()
            ->when($request->filled('updated_after'), fn ($query) => $query->where('updated_at', '>', $request->string('updated_after')->toString()))
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId));

        if ($isSync) {
            $query->orderBy('id');
        } else {
            $query->orderBy('name');
        }
        $rows = $query->limit($isSync ? $limit : 300)->get();
        $hasMore = $isSync && $rows->count() === $limit;

        return response()->json([
            'data' => $rows->map(fn (CategoryDon $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'group' => $category->group?->value,
                'group_label' => $category->group?->label(),
                'updated_at' => $category->updated_at?->toIso8601String(),
            ])->all(),
            'has_more' => $hasMore,
        ]);
    }

    public function donations(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Donation::class);

        [$isSync, $afterId, $limit] = $this->syncOptions($request);

        $churchIds = $this->access->accessibleChurchIds($request->user());

        $query = Donation::query()
            ->with([
                'partner:id,first_name,last_name,email,phone,handle_kc',
                'church:id,name',
                'worshipChurch:id,name',
                'category:id,name,color,group',
            ])
            ->where('is_active', true)
            // Seule l'église créditée peut consulter ses dons. Une église
            // externe peut enregistrer un don pour un partenaire visiteur,
            // mais elle n'a pas accès à son historique de dons.
            ->whereIn('church_id', $churchIds)
            ->when($request->filled('updated_after'), fn ($query) => $query->where('updated_at', '>', $request->string('updated_after')->toString()))
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->when($request->filled('church_id'), fn ($query) => $query->where('church_id', $request->integer('church_id')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('partner_id'), fn ($query) => $query->where('partner_id', $request->integer('partner_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('date', '<=', $request->string('date_to')->toString()));

        if ($isSync) {
            $query->orderBy('id');
        } else {
            $query->latest('date')->latest('id');
        }
        $rows = $query->limit($isSync ? $limit : 300)->get();
        $hasMore = $isSync && $rows->count() === $limit;

        return response()->json(['data' => $rows->map(fn (Donation $donation): array => [
            'id' => $donation->id,
            'amount' => (float) $donation->amount,
            'date' => $donation->date?->toIso8601String(),
            'church_id' => $donation->church_id,
            'church' => $donation->church?->name,
            'worship_church_id' => $donation->worship_church_id,
            'worship_church' => $donation->worshipChurch?->name,
            'partner_id' => $donation->partner_id,
            'partner' => $donation->partner?->full_name,
            'category_id' => $donation->category_id,
            'category' => $donation->category?->name,
            'category_group' => $donation->category?->group?->value,
            'category_group_label' => $donation->category?->group?->label(),
            'color' => $donation->category?->color,
            'updated_at' => $donation->updated_at?->toIso8601String(),
        ])->all(), 'has_more' => $hasMore]);
    }

    public function updateDonation(Request $request, Donation $donation): JsonResponse
    {
        Gate::authorize('update', $donation);

        $churchIds = $this->access->accessibleChurchIds($request->user());

        $data = $request->validate([
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'date' => ['sometimes', 'required', 'date'],
            'church_id' => ['sometimes', 'required', 'integer', Rule::in($churchIds)],
            'worship_church_id' => ['sometimes', 'nullable', 'integer', Rule::in($churchIds)],
            'partner_id' => ['sometimes', 'required', 'integer', Rule::exists('partners', 'id')],
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('category_dons', 'id')],
        ]);

        $worshipId = (int) ($data['worship_church_id'] ?? $data['church_id'] ?? $donation->worship_church_id ?? $donation->church_id);
        $partnerId = (int) ($data['partner_id'] ?? $donation->partner_id);
        $categoryId = (int) ($data['category_id'] ?? $donation->category_id);

        $worship = Church::findOrFail($worshipId);
        $partner = Partner::findOrFail($partnerId);
        $category = CategoryDon::findOrFail($categoryId);
        $attribution = $this->attribution->churchFor($partner, $category, $worship);

        if (! $this->attribution->canRecord($request->user(), $worship, $attribution, $partner)) {
            abort(403, 'Vous ne pouvez pas enregistrer ce don pour cette église.');
        }

        $data['church_id'] = $attribution->id;
        $data['worship_church_id'] = $worship->id;

        $donation->update($data);

        return response()->json(['message' => 'Don mis à jour.', 'donation' => $donation->load(['church:id,name', 'worshipChurch:id,name', 'partner:id,first_name,last_name'])]);
    }

    public function deactivateDonation(Donation $donation): JsonResponse
    {
        Gate::authorize('toggleActive', $donation);

        if (! $donation->is_active) {
            return response()->json(['message' => 'Ce don est déjà désactivé.'], 409);
        }

        $donation->update(['is_active' => false]);

        return response()->json(['message' => 'Don désactivé.', 'donation' => $donation]);
    }

    public function activateDonation(Donation $donation): JsonResponse
    {
        Gate::authorize('toggleActive', $donation);

        if ($donation->is_active) {
            return response()->json(['message' => 'Ce don est déjà actif.'], 409);
        }

        $donation->update(['is_active' => true]);

        return response()->json(['message' => 'Don réactivé.', 'donation' => $donation]);
    }

    public function destroyDonation(Donation $donation): JsonResponse
    {
        Gate::authorize('delete', $donation);
        $donation->delete();

        return response()->json(['message' => 'Don supprimé.']);
    }

    public function storeDonation(Request $request): JsonResponse
    {
        Gate::authorize('create', Donation::class);

        $user = $request->user();
        $churchIds = $this->access->accessibleChurchIds($user);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'church_id' => ['required', 'integer', Rule::in($churchIds)],
            'partner_id' => ['required', 'integer', Rule::exists('partners', 'id')],
            'category_id' => ['required', 'integer', Rule::exists('category_dons', 'id')],
        ], [
            'amount.required' => 'Le montant est obligatoire.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'amount.min' => 'Le montant doit être supérieur à zéro.',
            'date.required' => 'La date est obligatoire.',
            'date.date' => 'La date est invalide.',
            'church_id.required' => 'L’église du culte est obligatoire.',
            'partner_id.required' => 'Le partenaire est obligatoire.',
            'category_id.required' => 'La catégorie est obligatoire.',
            'church_id.in' => 'Vous n’avez pas accès à cette église.',
            'partner_id.exists' => 'Ce partenaire est introuvable.',
            'category_id.exists' => 'Cette catégorie est introuvable.',
        ]);

        if (! $request->user()->hasPermission(PermissionName::READ_OWN_CHURCH_DATA)) {
            abort(403, 'Accès refusé.');
        }

        $worship = Church::findOrFail($data['church_id']);
        $partner = Partner::findOrFail($data['partner_id']);
        $category = CategoryDon::findOrFail($data['category_id']);

        $attribution = $this->attribution->churchFor($partner, $category, $worship);

        if (! $this->attribution->canRecord($user, $worship, $attribution, $partner)) {
            abort(403, 'Vous ne pouvez pas enregistrer ce don pour cette église.');
        }

        $donation = Donation::query()->create([
            'amount' => $data['amount'],
            'date' => $data['date'],
            'church_id' => $attribution->id,
            'worship_church_id' => $worship->id,
            'partner_id' => $partner->id,
            'category_id' => $category->id,
        ]);

        $this->notifier->handle($donation);
        $donation->load(['partner:id,first_name,last_name', 'church:id,name', 'category:id,name,color']);

        return response()->json([
            'message' => 'Don enregistré.',
            'donation' => [
                'id' => $donation->id,
                'amount' => (float) $donation->amount,
                'date' => $donation->date?->toIso8601String(),
                'church_id' => $donation->church_id,
                'church' => $donation->church?->name,
                'worship_church_id' => $donation->worship_church_id,
                'worship_church' => $donation->worshipChurch?->name,
                'partner_id' => $donation->partner_id,
                'partner' => $donation->partner?->full_name,
                'category_id' => $donation->category_id,
                'category' => $donation->category?->name,
            ],
        ], 201);
    }
}
