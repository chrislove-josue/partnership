<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Notification;
use App\Models\User;
use App\Services\ChurchAccessService;

class NotificationPolicy
{
    public function __construct(private readonly ChurchAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::READ_OWN_CHURCH_DATA);
    }

    public function view(User $user, Notification $notification): bool
    {
        if ($notification->user_id === $user->id) {
            return true;
        }

        $churchId = $notification->user?->assigned_church_id;

        return $churchId !== null && $this->access->canAccessChurchData($user, $churchId);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::READ_OWN_CHURCH_DATA);
    }

    public function update(User $user, Notification $notification): bool
    {
        return $this->view($user, $notification);
    }

    public function delete(User $user, Notification $notification): bool
    {
        return $this->view($user, $notification);
    }
}
