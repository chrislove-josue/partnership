<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\LoginHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly LoginHistoryService $history) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => "L'adresse email est obligatoire.",
            'email.email' => "L'adresse email est invalide.",
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email ou mot de passe incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Ce compte est désactivé. Contactez un administrateur.'],
            ]);
        }

        $user->tokens()->where('name', 'mobile')->delete();

        $token = $user->createToken('mobile');
        $this->history->record(
            $user,
            LoginHistory::EVENT_LOGIN,
            LoginHistory::SOURCE_MOBILE,
            $request,
            (string) $token->accessToken->id,
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        $this->history->record(
            $user,
            LoginHistory::EVENT_LOGOUT,
            LoginHistory::SOURCE_MOBILE,
            $request,
            $token ? (string) $token->id : null,
        );
        $token?->delete();

        return response()->json(['message' => 'Déconnexion effectuée.']);
    }

    public function history(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 30), 100));
        $history = $request->user()
            ->loginHistories()
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $history->getCollection()->map(fn (LoginHistory $entry): array => [
                'id' => $entry->id,
                'event' => $entry->event,
                'event_label' => $entry->eventLabel(),
                'source' => $entry->source,
                'source_label' => $entry->sourceLabel(),
                'ip_address' => $entry->ip_address,
                'user_agent' => $entry->user_agent,
                'happened_at' => $entry->created_at?->toISOString(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        $user->loadMissing('assignedChurch:id,name,type');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'handle_kc' => $user->handle_kc,
            'role' => $user->roleName(),
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->permissionNames(),
            'assigned_church_id' => $user->assigned_church_id,
            'assigned_church' => $user->assignedChurch ? [
                'id' => $user->assignedChurch->id,
                'name' => $user->assignedChurch->name,
                'type' => $user->assignedChurch->type,
            ] : null,
        ];
    }
}
