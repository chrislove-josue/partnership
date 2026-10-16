<?php

namespace App\Policies;

use App\Enums\ChurchType;
use App\Models\Church;
use App\Models\User;
use App\Services\ChurchAccessService;

class ChurchPolicy
{
    public function __construct(private readonly ChurchAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canManageChurches($user);
    }

    public function view(User $user, Church $church): bool
    {
        return $this->access->canAccessChurchData($user, $church->id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $this->access->canCreateChurch($user, ChurchType::SUB_GROUP)
            || $this->access->canCreateChurch($user, ChurchType::LOCAL);
    }

    public function update(User $user, Church $church): bool
    {
        return $this->access->canModifyChurchData($user, $church->id);
    }

    public function delete(User $user, Church $church): bool
    {
        return $this->access->canModifyChurchData($user, $church->id);
    }
}
