<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Donation;
use App\Models\User;
use App\Services\ChurchAccessService;

class DonationPolicy
{
    public function __construct(private readonly ChurchAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::READ_OWN_CHURCH_DATA);
    }

    public function view(User $user, Donation $donation): bool
    {
        return $this->access->canAccessChurchData($user, $donation->church_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::READ_OWN_CHURCH_DATA);
    }

    public function update(User $user, Donation $donation): bool
    {
        return $this->access->canModifyChurchData($user, $donation->church_id);
    }

    public function delete(User $user, Donation $donation): bool
    {
        return $this->access->canModifyChurchData($user, $donation->church_id);
    }
}
