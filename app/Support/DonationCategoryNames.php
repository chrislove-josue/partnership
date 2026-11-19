<?php

namespace App\Support;

/** Libellés canoniques des catégories de dons, avec tolérance aux anciennes variantes. */
class DonationCategoryNames
{
    /** @var array<int, string> */
    public const CANONICAL = [
        'Dîmes',
        'Offrandes',
        'Rhapsodie des Réalités',
        'Mission InnerCity',
        'École de Guérisons',
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'tithe' => 'Dîmes',
        'tithing' => 'Dîmes',
        'dime' => 'Dîmes',
        'dîme' => 'Dîmes',
        'dimes' => 'Dîmes',
        'dîmes' => 'Dîmes',
        'offering' => 'Offrandes',
        'offerings' => 'Offrandes',
        'offrande' => 'Offrandes',
        'offrandes' => 'Offrandes',
        'rhapsody of realities' => 'Rhapsodie des Réalités',
        'rhapsodie de réalités' => 'Rhapsodie des Réalités',
        'rhapsodie des réalités' => 'Rhapsodie des Réalités',
        'innercity mission' => 'Mission InnerCity',
        'mission innercity' => 'Mission InnerCity',
        'mission urbaine' => 'Mission InnerCity',
        'healing streams' => 'École de Guérisons',
        'école de guérisons' => 'École de Guérisons',
        'ecole de guérisons' => 'École de Guérisons',
        'ecole de guerisons' => 'École de Guérisons',
    ];

    /** Ramène un libellé connu (anglais ou ancien) vers sa forme canonique. */
    public static function normalize(string $name): string
    {
        $name = trim($name);

        return self::ALIASES[mb_strtolower($name)] ?? $name;
    }
}
