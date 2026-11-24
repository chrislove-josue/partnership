<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->foreignId('worship_church_id')
                ->nullable()
                ->after('church_id')
                ->constrained('churches')
                ->nullOnDelete();
        });

        // Les dons existants ont eu lieu dans l'église à laquelle ils sont rattachés.
        DB::table('donations')->update(['worship_church_id' => DB::raw('church_id')]);
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('worship_church_id');
        });
    }
};
