<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('type', ['GROUP', 'SUB_GROUP', 'LOCAL']);
            $table->foreignId('parent_id')->nullable()->constrained('churches')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('churches');
    }
};
