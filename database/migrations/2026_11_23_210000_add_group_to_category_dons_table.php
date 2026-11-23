<?php

use App\Enums\CategoryGroup;
use App\Models\CategoryDon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_dons', function (Blueprint $table): void {
            $table->string('group')
                ->default(CategoryGroup::LOCAL_PARTNERSHIP->value)
                ->after('color');
        });

        // Rattache les catégories existantes à leur famille selon leur libellé.
        foreach (CategoryDon::all() as $category) {
            $category->update(['group' => CategoryGroup::fromName($category->name)]);
        }
    }

    public function down(): void
    {
        Schema::table('category_dons', function (Blueprint $table): void {
            $table->dropColumn('group');
        });
    }
};
