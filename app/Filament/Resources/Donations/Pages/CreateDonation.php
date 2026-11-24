<?php

namespace App\Filament\Resources\Donations\Pages;

use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Donations\DonationResource;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Partner;
use App\Services\DonationAttributionService;
use App\Services\DonationNotifier;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDonation extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = DonationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $service = app(DonationAttributionService::class);

        $partner = Partner::findOrFail($data['partner_id']);
        $category = CategoryDon::findOrFail($data['category_id']);
        $worship = Church::findOrFail($data['worship_church_id']);
        $attribution = $service->churchFor($partner, $category, $worship);

        $actor = Auth::user();

        if ($actor && ! $service->canRecord($actor, $worship, $attribution, $partner)) {
            Notification::make()
                ->title('Enregistrement refusé')
                ->body('Vous ne pouvez pas enregistrer ce don pour cette église.')
                ->danger()
                ->send();

            $this->halt();
        }

        $data['worship_church_id'] = $worship->id;
        $data['church_id'] = $attribution->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        app(DonationNotifier::class)->handle($this->record);
    }
}
