<?php

namespace App\Http\Controllers\Api;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\CategoryDon;
use App\Models\Cellule;
use App\Models\Church;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Services\ChurchAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ManagementController extends Controller
{
    public function __construct(private readonly ChurchAccessService $access) {}

    // ── Églises ─────────────────────────────────────────────────────────

    public function storeChurch(Request $request): JsonResponse
    {
        Gate::authorize('create', Church::class);

        $data = $this->validateChurch($request);
        $type = ChurchType::from($data['type']);

        if (! $this->access->canCreateChurch($request->user(), $type)) {
            abort(403, 'Vous ne pouvez pas créer ce type d’église.');
        }

        $church = Church::query()->create($data + ['type' => $type]);

        return response()->json(['message' => 'Église créée.', 'church' => $this->churchPayload($church)], 201);
    }

    public function updateChurch(Request $request, Church $church): JsonResponse
    {
        Gate::authorize('update', $church);

        $data = $this->validateChurch($request, $church);

        if (isset($data['type'])) {
            $type = ChurchType::from($data['type']);

            if (! $this->access->canCreateChurch($request->user(), $type)) {
                abort(403, 'Vous ne pouvez pas définir ce type d’église.');
            }

            $data['type'] = $type;
        }

        if (isset($data['parent_id']) && $this->isDescendant($church, (int) $data['parent_id'])) {
            return response()->json(['message' => 'Une église ne peut pas avoir une de ses descendantes comme parente.'], 422);
        }

        $church->update($data);

        return response()->json(['message' => 'Église mise à jour.', 'church' => $this->churchPayload($church)]);
    }

    public function destroyChurch(Church $church): JsonResponse
    {
        Gate::authorize('delete', $church);

        if ($church->children()->exists() || User::query()->where('assigned_church_id', $church->id)->exists()) {
            return response()->json(['message' => 'Cette église contient encore des sous-églises ou des utilisateurs.'], 409);
        }

        $church->delete();

        return response()->json(['message' => 'Église supprimée.']);
    }

    public function deactivateChurch(Church $church): JsonResponse
    {
        Gate::authorize('toggleActive', $church);

        if (! $church->is_active) {
            return response()->json(['message' => 'Cette église est déjà désactivée.'], 409);
        }

        $church->update(['is_active' => false]);

        return response()->json([
            'message' => 'Église désactivée.',
            'church' => $this->churchPayload($church),
            'data' => $this->churchPayload($church),
        ]);
    }

    public function activateChurch(Church $church): JsonResponse
    {
        Gate::authorize('toggleActive', $church);

        if ($church->is_active) {
            return response()->json(['message' => 'Cette église est déjà active.'], 409);
        }

        $church->update(['is_active' => true]);

        return response()->json([
            'message' => 'Église réactivée.',
            'church' => $this->churchPayload($church),
            'data' => $this->churchPayload($church),
        ]);
    }

    // ── Cellules ────────────────────────────────────────────────────────

    public function storeCellule(Request $request): JsonResponse
    {
        Gate::authorize('create', Cellule::class);
        $data = $this->validateCellule($request);
        $cellule = Cellule::query()->create($data);

        return response()->json(['message' => 'Cellule créée.', 'cellule' => $cellule], 201);
    }

    public function updateCellule(Request $request, Cellule $cellule): JsonResponse
    {
        Gate::authorize('update', $cellule);
        $cellule->update($this->validateCellule($request, $cellule));

        return response()->json(['message' => 'Cellule mise à jour.', 'cellule' => $cellule]);
    }

    public function destroyCellule(Cellule $cellule): JsonResponse
    {
        Gate::authorize('delete', $cellule);
        $cellule->delete();

        return response()->json(['message' => 'Cellule supprimée.']);
    }

    // ── Partenaires ─────────────────────────────────────────────────────

    public function storePartner(Request $request): JsonResponse
    {
        Gate::authorize('create', Partner::class);
        $partner = Partner::query()->create($this->validatePartner($request));

        return response()->json(['message' => 'Partenaire créé.', 'partner' => $partner], 201);
    }

    public function updatePartner(Request $request, Partner $partner): JsonResponse
    {
        Gate::authorize('update', $partner);
        $partner->update($this->validatePartner($request, $partner));

        return response()->json(['message' => 'Partenaire mis à jour.', 'partner' => $partner]);
    }

    public function destroyPartner(Partner $partner): JsonResponse
    {
        Gate::authorize('delete', $partner);
        $partner->delete();

        return response()->json(['message' => 'Partenaire supprimé.']);
    }

    // ── Catégories de dons ──────────────────────────────────────────────

    public function storeCategory(Request $request): JsonResponse
    {
        Gate::authorize('create', CategoryDon::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('category_dons', 'name')],
            'color' => ['required', 'string', 'max:20'],
        ], [
            'name.required' => 'Le nom de la catégorie est obligatoire.',
            'name.unique' => 'Cette catégorie existe déjà.',
            'color.required' => 'La couleur est obligatoire.',
        ]);

        $category = CategoryDon::query()->create($data);

        return response()->json(['message' => 'Catégorie créée.', 'category' => $category], 201);
    }

    public function updateCategory(Request $request, CategoryDon $category): JsonResponse
    {
        Gate::authorize('update', $category);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('category_dons', 'name')->ignore($category->id)],
            'color' => ['required', 'string', 'max:20'],
        ]);

        $category->update($data);

        return response()->json(['message' => 'Catégorie mise à jour.', 'category' => $category]);
    }

    public function destroyCategory(CategoryDon $category): JsonResponse
    {
        Gate::authorize('delete', $category);

        if ($category->donations()->exists()) {
            return response()->json(['message' => 'Cette catégorie est utilisée par des dons.'], 409);
        }

        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée.']);
    }

    // ── Utilisateurs et rôles ───────────────────────────────────────────

    public function users(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with('assignedChurch:id,name')
            ->whereIn('assigned_church_id', $this->access->accessibleChurchIds($request->user()))
            ->orWhere(function ($query) use ($request): void {
                if (! $request->user()->isSuperAdmin()) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderBy('name')
            ->limit(300)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'handle_kc' => $user->handle_kc,
                'role' => $user->roleName(),
                'roles' => $user->getRoleNames()->values()->all(),
                'assigned_church_id' => $user->assigned_church_id,
                'assigned_church' => $user->assignedChurch?->name,
                'is_active' => $user->is_active,
            ])->all();

        return response()->json(['data' => $users]);
    }

    public function roles(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        return response()->json([
            'data' => Role::query()->orderBy('name')->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => RoleName::tryFrom($role->name)?->label() ?? $role->name,
                ])->all(),
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone')],
            'handle_kc' => ['nullable', 'string', 'max:255', Rule::unique('users', 'handle_kc')],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(array_column(RoleName::cases(), 'value'))],
            'assigned_church_id' => ['required', 'integer', Rule::in($this->access->accessibleChurchIds($request->user()))],
            'is_active' => ['boolean'],
        ]);

        if (! $this->access->canAssignRoles($request->user(), $data['roles'], $data['assigned_church_id'])) {
            abort(403, 'Vous ne pouvez pas attribuer ce rôle dans cette église.');
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'handle_kc' => $data['handle_kc'] ?? null,
            'password' => Hash::make($data['password']),
            'assigned_church_id' => $data['assigned_church_id'],
            'is_active' => $data['is_active'] ?? true,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles($data['roles']);
        $user->syncImpliedRoles();

        return response()->json(['message' => 'Compte créé.', 'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roleName(),
        ]], 201);
    }

    public function updateUser(Request $request, User $target): JsonResponse
    {
        Gate::authorize('update', $target);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'phone' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($target->id)],
            'handle_kc' => ['nullable', 'string', 'max:255', Rule::unique('users', 'handle_kc')->ignore($target->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(array_column(RoleName::cases(), 'value'))],
            'assigned_church_id' => ['sometimes', 'integer', Rule::in($this->access->accessibleChurchIds($request->user()))],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['roles']) && ! $this->access->canAssignRoles(
            $request->user(),
            $data['roles'],
            $data['assigned_church_id'] ?? $target->assigned_church_id,
        )) {
            abort(403, 'Vous ne pouvez pas attribuer ce rôle dans cette église.');
        }

        if (array_key_exists('password', $data)) {
            if ($data['password'] === null || $data['password'] === '') {
                unset($data['password']);
            } else {
                $data['password'] = Hash::make($data['password']);
            }
        }

        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        $target->update($data);

        if ($roles !== null) {
            $target->syncRoles($roles);
            $target->syncImpliedRoles();
        }

        return response()->json(['message' => 'Compte mis à jour.', 'user' => [
            'id' => $target->id,
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->roleName(),
        ]]);
    }

    public function destroyUser(User $target): JsonResponse
    {
        Gate::authorize('delete', $target);
        $target->delete();

        return response()->json(['message' => 'Compte supprimé.']);
    }

    // ── Validateurs partagés ────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validateChurch(Request $request, ?Church $church = null): array
    {
        $churchIds = $this->access->accessibleChurchIds($request->user());

        return $request->validate([
            'name' => ['required', 'string', 'max:255', $church
                ? Rule::unique('churches', 'name')->ignore($church->id)
                : Rule::unique('churches', 'name')],
            'type' => ['required', Rule::enum(ChurchType::class)],
            'parent_id' => ['nullable', 'integer', Rule::in($churchIds)],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ], [
            'name.required' => 'Le nom de l’église est obligatoire.',
            'name.unique' => 'Cette église existe déjà.',
            'type.required' => 'Le type est obligatoire.',
        ]);
    }

    /** @return array<string, mixed> */
    private function validateCellule(Request $request, ?Cellule $cellule = null): array
    {
        $churchIds = $this->access->accessibleChurchIds($request->user());
        $churchId = $cellule?->church_id ?? $request->integer('church_id');

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'church_id' => ['required', 'integer', Rule::in($churchIds)],
            'leader_id' => ['nullable', 'integer', Rule::exists('partners', 'id')->where('church_id', $churchId)],
        ], [
            'name.required' => 'Le nom de la cellule est obligatoire.',
            'church_id.required' => 'L’église est obligatoire.',
        ]);
    }

    /** @return array<string, mixed> */
    private function validatePartner(Request $request, ?Partner $partner = null): array
    {
        $churchIds = $this->access->accessibleChurchIds($request->user());
        $churchId = $partner?->church_id ?? $request->integer('church_id');
        $ignore = fn (string $column) => $partner
            ? Rule::unique('partners', $column)->ignore($partner->id)
            : Rule::unique('partners', $column);

        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', $ignore('email')],
            'phone' => ['nullable', 'string', 'max:255', $ignore('phone')],
            'handle_kc' => ['nullable', 'string', 'max:255', $ignore('handle_kc')],
            'address' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],
            'church_id' => ['required', 'integer', Rule::in($churchIds)],
            'cellule_id' => ['nullable', 'integer', Rule::exists('cellules', 'id')->where('church_id', $churchId)],
        ], [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required' => 'Le nom est obligatoire.',
        ]);
    }

    /** @return array<string, mixed> */
    private function churchPayload(Church $church): array
    {
        return [
            'id' => $church->id,
            'name' => $church->name,
            'type' => $church->type?->value ?? (string) $church->type,
            'parent_id' => $church->parent_id,
            'manager_id' => $church->manager_id,
        ];
    }

    private function isDescendant(Church $church, int $candidateId): bool
    {
        return in_array($candidateId, $church->descendantIds(), true);
    }
}
