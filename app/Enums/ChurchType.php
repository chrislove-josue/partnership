<?php

namespace App\Enums;

enum ChurchType: string
{
    case GROUP = 'GROUP';
    case SUB_GROUP = 'SUB_GROUP';
    case LOCAL = 'LOCAL';

    public function label(): string
    {
        return match ($this) {
            self::GROUP => 'Groupe',
            self::SUB_GROUP => 'Sous-groupe',
            self::LOCAL => 'Église locale',
        };
    }
}
