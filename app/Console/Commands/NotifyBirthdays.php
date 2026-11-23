<?php

namespace App\Console\Commands;

use App\Services\BirthdayService;
use Illuminate\Console\Command;

class NotifyBirthdays extends Command
{
    protected $signature = 'birthdays:notify';

    protected $description = 'Envoie les alertes d\'anniversaire dues aujourd\'hui (J-7, J-3, jour J) à la hiérarchie';

    public function handle(BirthdayService $birthdays): int
    {
        $created = $birthdays->notifyUpcoming();

        $this->info($created.' alerte(s) d\'anniversaire créée(s).');

        return self::SUCCESS;
    }
}
