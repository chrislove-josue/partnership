<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Partner;
use App\Models\User;
use App\Services\ChurchAccessService;

class PartnerPolicy
{
    public function __construct(private readonly ChurchAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::READ_OWN_CHURCH_DATA);
    }

    public function view(User $user, Partner $partner): bool
    {
        return $this->access->canAccessChurchData($user, $partner->church_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::READ_OWN_CHURCH_DATA);
    }

    public function update(User $user, Partner $partner): bool
    {
        return $this->access->canModifyChurchData($user, $partner->church_id);
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $this->access->canModifyChurchData($user, $partner->church_id);
    }
}
