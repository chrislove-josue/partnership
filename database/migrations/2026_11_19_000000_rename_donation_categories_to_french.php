<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Anciens libellés (imports anglais ou orthographe incomplète) vers les libellés français.
     *
     * @var array<string, string>
     */
    private const RENAMES = [
        'Tithe' => 'Dîmes',
        'Offering' => 'Offrandes',
        'Rhapsody of Realities' => 'Rhapsodie des Réalités',
        'InnerCity Mission' => 'Mission Urbaine',
        'Mission InnerCity' => 'Mission Urbaine',
        'Healing Streams' => 'École de Guérisons',
        'Ecole de Guérisons' => 'École de Guérisons',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::RENAMES as $from => $to) {
                $source = DB::table('category_dons')->where('name', $from)->first();

                if (! $source) {
                    continue;
                }

                $target = DB::table('category_dons')->where('name', $to)->first();

                if (! $target) {
                    DB::table('category_dons')->where('id', $source->id)->update(['name' => $to]);

                    continue;
                }

                if ($target->id !== $source->id) {
                    DB::table('donations')->where('category_id', $source->id)->update(['category_id' => $target->id]);
                    DB::table('category_dons')->where('id', $source->id)->delete();
                }
            }
        });
    }

    public function down(): void
    {
        // Migration de données : les dons fusionnés ne peuvent pas être répartis à nouveau.
    }
};
