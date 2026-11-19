<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Anciens libellés (imports anglais ou orthographe incomplète) vers les libellés canoniques.
     * « Mission InnerCity » reste en anglais, comme demandé.
     *
     * @var array<string, string>
     */
    private const RENAMES = [
        'Tithe' => 'Dîmes',
        'Offering' => 'Offrandes',
        'Rhapsody of Realities' => 'Rhapsodie des Réalités',
        'InnerCity Mission' => 'Mission InnerCity',
        'Mission Urbaine' => 'Mission InnerCity',
        'Healing Streams' => 'École de Guérisons',
        'Ecole de Guérisons' => 'École de Guérisons',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            // La comparaison PHP est nécessaire : la collation MySQL ignore les accents,
            // donc where('name', 'École de Guérisons') trouve aussi 'Ecole de Guérisons'.
            $categories = DB::table('category_dons')->get(['id', 'name'])->all();

            foreach (self::RENAMES as $from => $to) {
                $source = collect($categories)->first(fn (object $category): bool => $category->name === $from);

                if (! $source) {
                    continue;
                }

                $target = collect($categories)->first(fn (object $category): bool => $category->name === $to);

                if (! $target || $target->id === $source->id) {
                    DB::table('category_dons')->where('id', $source->id)->update(['name' => $to]);

                    continue;
                }

                DB::table('donations')->where('category_id', $source->id)->update(['category_id' => $target->id]);
                DB::table('category_dons')->where('id', $source->id)->delete();
            }
        });
    }

    public function down(): void
    {
        // Migration de données : les dons fusionnés ne peuvent pas être répartis à nouveau.
    }
};
