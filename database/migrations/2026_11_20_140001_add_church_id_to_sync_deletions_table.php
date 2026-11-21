<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_deletions', function (Blueprint $table): void {
            // Les suppressions globales (catégories) restent visibles par tous ;
            // les autres ne sont renvoyées qu'au personnel de l'église concernée.
            // Pas de clé étrangère : une église supprimée doit rester identifiable
            // dans l'historique de synchronisation.
            $table->unsignedBigInteger('church_id')->nullable()->after('entity_id');
            $table->index(['church_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sync_deletions', function (Blueprint $table): void {
            $table->dropIndex(['church_id', 'deleted_at']);
            $table->dropColumn('church_id');
        });
    }
};
