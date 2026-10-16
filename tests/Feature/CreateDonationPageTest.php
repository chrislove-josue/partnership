<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Resources\Donations\Pages\CreateDonation;
use App\Mail\DonationConfirmationMail;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class CreateDonationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_donation_from_the_panel_sends_email_and_notifications(): void
    {
        $this->seed();
        Mail::fake();

        $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $category = CategoryDon::firstOrFail();

        $partner = Partner::create([
            'first_name' => 'Marie',
            'last_name' => 'Kponou',
            'email' => 'marie.kponou@example.test',
            'church_id' => $church->id,
        ]);

        Livewire::actingAs($superAdmin)
            ->test(CreateDonation::class)
            ->fillForm([
                'amount' => 25000,
                'date' => now()->format('Y-m-d H:i:s'),
                'church_id' => $church->id,
                'partner_id' => $partner->id,
                'category_id' => $category->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('donations', ['amount' => 25000]);

        Mail::assertSent(DonationConfirmationMail::class);

        $this->assertDatabaseHas('notifications', [
            'type' => 'NEW_DONATION',
            'user_id' => $superAdmin->id,
        ]);
    }
}
