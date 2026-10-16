<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cellules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('leader_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cellules');
    }
};
