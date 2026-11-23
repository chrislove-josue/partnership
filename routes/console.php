<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', fn () => $this->comment(Inspiring::quote()))
    ->purpose('Display an inspiring quote');

// Alertes d'anniversaire (J-7, J-3, jour J) — vérifié chaque matin.
Schedule::command('birthdays:notify')->dailyAt('07:00');
