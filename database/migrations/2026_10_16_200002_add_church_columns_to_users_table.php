<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('assigned_church_id')->nullable()->after('password')->constrained('churches')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('assigned_church_id');
            $table->string('phone')->nullable()->unique()->after('is_active');
            $table->string('handle_kc')->nullable()->unique()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_church_id');
            $table->dropColumn(['is_active', 'phone', 'handle_kc']);
        });
    }
};
