<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Services\LoginHistoryService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class RecordLoginHistory
{
    public function __construct(private readonly LoginHistoryService $history) {}

    public function handle(Login|Logout $event): void
    {
        $this->history->record(
            $event->user,
            $event instanceof Login ? LoginHistory::EVENT_LOGIN : LoginHistory::EVENT_LOGOUT,
            LoginHistory::SOURCE_WEB,
            request(),
        );
    }
}
