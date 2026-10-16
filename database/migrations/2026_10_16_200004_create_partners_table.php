<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable()->unique();
            $table->string('handle_kc')->nullable()->unique();
            $table->string('address')->nullable();
            $table->date('birthdate')->nullable();
            $table->foreignId('cellule_id')->nullable()->constrained('cellules')->nullOnDelete();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('cellules', function (Blueprint $table) {
            $table->foreign('leader_id')->references('id')->on('partners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cellules', function (Blueprint $table) {
            $table->dropForeign(['leader_id']);
        });

        Schema::dropIfExists('partners');
    }
};
