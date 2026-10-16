<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Cellule;
use App\Models\User;
use App\Services\ChurchAccessService;

class CellulePolicy
{
    public function __construct(private readonly ChurchAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::READ_OWN_CHURCH_DATA);
    }

    public function view(User $user, Cellule $cellule): bool
    {
        return $this->access->canAccessChurchData($user, $cellule->church_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::READ_OWN_CHURCH_DATA);
    }

    public function update(User $user, Cellule $cellule): bool
    {
        return $this->access->canModifyChurchData($user, $cellule->church_id);
    }

    public function delete(User $user, Cellule $cellule): bool
    {
        return $this->access->canModifyChurchData($user, $cellule->church_id);
    }
}
