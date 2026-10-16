<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Notification;
use App\Models\Partner;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DonationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeLocalPastor(Church $church): User
    {
        $user = User::create([
            'name' => 'Pasteur Local',
            'email' => 'pasteur.local@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $church->id,
            'is_active' => true,
        ]);

        $user->syncRoles([RoleName::LOCAL_PASTOR->value]);

        return $user;
    }

    private function makeDonation(Church $church, ?string $partnerEmail): Donation
    {
        $partner = Partner::create([
            'first_name' => 'Jean',
            'last_name' => 'Doe',
            'email' => $partnerEmail,
            'church_id' => $church->id,
        ]);

        return Donation::create([
            'amount' => 15000,
            'date' => now(),
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => CategoryDon::firstOrFail()->id,
        ]);
    }

    public function test_creating_a_donation_notifies_staff(): void
    {
        $this->seed();

        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $pastor = $this->makeLocalPastor($church);

        $donation = $this->makeDonation($church, 'donor@example.test');

        app(NotificationService::class)->notifyNewDonation($donation);

        $notifications = Notification::where('type', 'NEW_DONATION')->get();

        $this->assertCount(2, $notifications);
        $this->assertTrue($notifications->contains('user_id', $pastor->id));
        $this->assertTrue($notifications->every(fn ($n) => $n->data['isForDonor'] === false));
        $this->assertEquals(15000.0, (float) $notifications->first()->data['amount']);
    }

    public function test_creating_a_donation_notifies_the_donor_when_they_have_an_account(): void
    {
        $this->seed();

        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $donor = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $this->makeLocalPastor($church);

        $donation = $this->makeDonation($church, $donor->email);

        app(NotificationService::class)->notifyNewDonation($donation);

        $donorNotification = Notification::where('user_id', $donor->id)->firstOrFail();

        $this->assertTrue($donorNotification->data['isForDonor']);
        $this->assertCount(1, Notification::where('is_read', false)->where('user_id', $donor->id)->get());
        // Le donateur ne reçoit pas la notification "staff".
        $this->assertCount(1, Notification::where('user_id', $donor->id)->get());
    }

    public function test_no_notifications_when_there_are_no_recipients(): void
    {
        CategoryDon::create(['name' => 'Dime', 'color' => '#3B82F6']);

        $church = Church::create(['name' => 'Eglise isolee', 'type' => \App\Enums\ChurchType::LOCAL]);

        $donation = $this->makeDonation($church, null);

        app(NotificationService::class)->notifyNewDonation($donation);

        $this->assertDatabaseCount('notifications', 0);
    }
}
