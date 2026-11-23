<?php

namespace App\Enums;

enum NotificationType: string
{
    case NEW_DONATION = 'NEW_DONATION';
    case NEW_PARTNER = 'NEW_PARTNER';
    case BIRTHDAY = 'BIRTHDAY';
    case SYSTEM = 'SYSTEM';

    public function label(): string
    {
        return match ($this) {
            self::NEW_DONATION => 'Nouveau don',
            self::NEW_PARTNER => 'Nouveau partenaire',
            self::BIRTHDAY => 'Anniversaire',
            self::SYSTEM => 'Système',
        };
    }
}
