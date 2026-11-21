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
        return $this->access->canManageChurches($user)
            || in_array($user->roleName(), [
                RoleName::GROUP_PASTOR->value,
                RoleName::SUBGROUP_PASTOR->value,
            ], true);
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
        return $user->isSuperAdmin();
    }

    public function toggleActive(User $user, Church $church): bool
    {
        return $this->access->canToggleChurchActive($user, $church->id);
    }
}
