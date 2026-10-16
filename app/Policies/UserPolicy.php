<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ChurchAccessService;

class UserPolicy
{
    public function __construct(private readonly ChurchAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canManageUsers($user);
    }

    public function view(User $user, User $model): bool
    {
        if (! $this->access->canManageUsers($user)) {
            return false;
        }

        return $user->isSuperAdmin()
            || $this->access->canAccessChurchData($user, $model->assigned_church_id);
    }

    public function create(User $user): bool
    {
        return $this->access->canManageUsers($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->access->canManageUser($user, $model->roleName() ?? '')
            && $this->access->canModifyChurchData($user, $model->assigned_church_id);
    }

    public function delete(User $user, User $model): bool
    {
        return ! $model->isSuperAdmin() && $this->update($user, $model);
    }
}
