<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Services\ChurchAccessService;

class RolePolicy
{
    public function __construct(private readonly ChurchAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canAccessAdminFeatures($user);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->access->canAccessAdminFeatures($user);
    }

    public function create(User $user): bool
    {
        return $this->access->canCrudCategoryAndRole($user);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->access->canCrudCategoryAndRole($user);
    }

    public function delete(User $user, Role $role): bool
    {
        return ! $role->is_system && $this->access->canCrudCategoryAndRole($user);
    }
}
