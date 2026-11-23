<?php

use App\Models\User;
use App\Services\UserPartnerSync;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $sync = app(UserPartnerSync::class);

        User::query()
            ->whereNotNull('assigned_church_id')
            ->chunkById(200, function ($users) use ($sync): void {
                foreach ($users as $user) {
                    $sync->sync($user);
                }
            });
    }

    public function down(): void
    {
        // Les partenaires créés sont conservés : ils peuvent déjà porter des dons.
    }
};
