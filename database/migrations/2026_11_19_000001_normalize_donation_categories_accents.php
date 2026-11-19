<?php

use App\Support\DonationCategoryNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Corrige les libellés qui diffèrent encore de la forme canonique (accents inclus). */
    public function up(): void
    {
        DB::transaction(function (): void {
            // Comparaison en PHP : la collation MySQL considère 'Ecole' et 'École' comme égaux.
            $categories = DB::table('category_dons')->get(['id', 'name'])->all();

            foreach ($categories as $category) {
                $canonical = DonationCategoryNames::normalize($category->name);

                if ($canonical === $category->name) {
                    continue;
                }

                $existing = collect($categories)
                    ->first(fn (object $other): bool => $other->id !== $category->id && $other->name === $canonical);

                if ($existing) {
                    DB::table('donations')->where('category_id', $category->id)->update(['category_id' => $existing->id]);
                    DB::table('category_dons')->where('id', $category->id)->delete();

                    continue;
                }

                DB::table('category_dons')->where('id', $category->id)->update(['name' => $canonical]);
            }
        });
    }

    public function down(): void
    {
        // Migration de données : les dons fusionnés ne peuvent pas être répartis à nouveau.
    }
};
