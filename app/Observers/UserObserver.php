<?php

namespace App\Observers;

use App\Models\Partner;
use App\Models\User;
use App\Services\UserPartnerSync;

/** Maintient le partenaire miroir de chaque compte utilisateur. */
class UserObserver
{
    public function __construct(private readonly UserPartnerSync $sync) {}

    public function saved(User $user): void
    {
        if ($user->wasRecentlyCreated || $user->wasChanged(UserPartnerSync::SYNCED_FIELDS)) {
            $this->sync->sync($user);
        }
    }

    public function deleted(User $user): void
    {
        // Le partenaire est conservé : il peut porter des dons. On le détache
        // simplement du compte supprimé.
        Partner::query()->where('user_id', $user->id)->update(['user_id' => null]);
    }
}
