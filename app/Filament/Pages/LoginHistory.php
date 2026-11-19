<?php

namespace App\Filament\Pages;

use App\Models\LoginHistory as LoginHistoryModel;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthPaginator;
use Illuminate\Support\Facades\Auth;

class LoginHistory extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Historique de connexion';

    protected static string|\UnitEnum|null $navigationGroup = 'Mon compte';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Historique de connexion';

    protected string $view = 'filament.pages.login-history';

    /** @return LengthPaginator<int, LoginHistoryModel> */
    public function getEntries(): LengthPaginator
    {
        return LoginHistoryModel::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(20);
    }
}
