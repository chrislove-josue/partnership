<?php

namespace App\Filament\Resources\Donations\Pages;

use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Donations\DonationResource;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Partner;
use App\Services\DonationAttributionService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditDonation extends EditRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = DonationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $service = app(DonationAttributionService::class);

        $partner = Partner::findOrFail($data['partner_id']);
        $category = CategoryDon::findOrFail($data['category_id']);
        $worship = Church::findOrFail($data['worship_church_id']);
        $attribution = $service->churchFor($partner, $category, $worship);

        $actor = Auth::user();

        if ($actor && ! $service->canRecord($actor, $worship, $attribution, $partner)) {
            Notification::make()
                ->title('Modification refusée')
                ->body('Vous ne pouvez pas enregistrer ce don pour cette église.')
                ->danger()
                ->send();

            $this->halt();
        }

        $data['worship_church_id'] = $worship->id;
        $data['church_id'] = $attribution->id;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
