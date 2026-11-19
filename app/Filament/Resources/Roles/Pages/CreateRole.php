<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = RoleResource::class;
}
