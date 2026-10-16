<?php

namespace App\Policies;

use App\Models\CategoryDon;
use App\Models\User;
use App\Services\ChurchAccessService;

class CategoryDonPolicy
{
    public function __construct(private readonly ChurchAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canAccessAdminFeatures($user);
    }

    public function view(User $user, CategoryDon $category): bool
    {
        return $this->access->canAccessAdminFeatures($user);
    }

    public function create(User $user): bool
    {
        return $this->access->canCrudCategoryAndRole($user);
    }

    public function update(User $user, CategoryDon $category): bool
    {
        return $this->access->canCrudCategoryAndRole($user);
    }

    public function delete(User $user, CategoryDon $category): bool
    {
        return $this->access->canCrudCategoryAndRole($user);
    }
}
