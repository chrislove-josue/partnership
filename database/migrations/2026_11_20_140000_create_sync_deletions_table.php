<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_deletions', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 40);
            $table->unsignedBigInteger('entity_id');
            $table->timestamp('deleted_at');
            $table->index(['entity', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_deletions');
    }
};
