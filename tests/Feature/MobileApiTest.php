<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Mail\PartnershipReportMail;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Notification;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_mobile_requests_are_rejected(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_user_can_log_in_and_read_his_profile(): void
    {
        $this->seed();
        $user = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'azerty123',
        ])->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', RoleName::SUPER_ADMIN->value);

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'mauvais-mot-de-passe',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_profile_api_updates_phone_and_kingschat_handle(): void
    {
        $this->seed();
        $user = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '+229 97 00 00 00',
                'handle_kc' => 'handle_mobile',
            ])
            ->assertOk()
            ->assertJsonPath('user.phone', '+229 97 00 00 00')
            ->assertJsonPath('user.handle_kc', 'handle_mobile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '+229 97 00 00 00',
            'handle_kc' => 'handle_mobile',
        ]);
    }

    public function test_mobile_donation_creation_is_scoped_to_accessible_churches(): void
    {
        Mail::fake();
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $other = Church::query()->create([
            'name' => 'Autre groupe',
            'type' => ChurchType::GROUP,
        ]);
        $admin = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();
        $partner = Partner::query()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'church_id' => $group->id,
        ]);
        $category = CategoryDon::query()->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/donations', [
                'amount' => 5000,
                'date' => now()->toDateString(),
                'church_id' => $other->id,
                'partner_id' => $partner->id,
                'category_id' => $category->id,
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/donations', [
                'amount' => 5000,
                'date' => now()->toDateString(),
                'church_id' => $group->id,
                'partner_id' => $partner->id,
                'category_id' => $category->id,
            ])
            ->assertCreated()
            ->assertJsonPath('donation.amount', 5000);

        $this->assertDatabaseCount('donations', 1);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id]);
    }

    public function test_report_send_requires_explicit_confirmation(): void
    {
        Mail::fake();
        $this->seed();

        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sender = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();

        $recipient = User::factory()->create([
            'email' => 'pasteur@example.test',
            'assigned_church_id' => $church->id,
        ]);
        $recipient->syncRoles([RoleName::SUPER_ADMIN->value]);

        $payload = [
            'church_id' => $church->id,
            'type' => 'year',
            'year' => now()->year,
        ];

        $this->actingAs($sender, 'sanctum')
            ->getJson('/api/reports/partnership/recipients?'.http_build_query($payload))
            ->assertOk()
            ->assertJsonPath('confirmRequired', true)
            ->assertJsonFragment(['email' => 'pasteur@example.test']);

        $this->actingAs($sender, 'sanctum')
            ->postJson('/api/reports/partnership/send', $payload + ['confirm' => false])
            ->assertOk()
            ->assertJsonPath('sent', false);

        Mail::assertNothingSent();

        $this->actingAs($sender, 'sanctum')
            ->postJson('/api/reports/partnership/send', $payload + ['confirm' => true, 'cc_emails' => []])
            ->assertOk()
            ->assertJsonPath('sent', true);

        Mail::assertSent(PartnershipReportMail::class);
    }

    public function test_notifications_are_private_to_the_authenticated_user(): void
    {
        $this->seed();
        $admin = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();
        $other = User::factory()->create();

        $mine = Notification::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => \App\Enums\NotificationType::NEW_DONATION->value,
            'title' => 'Notification mobile',
            'message' => 'Test',
            'data' => [],
            'is_read' => false,
            'user_id' => $admin->id,
        ]);
        Notification::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => \App\Enums\NotificationType::NEW_DONATION->value,
            'title' => 'Notification privée',
            'message' => 'Test',
            'data' => [],
            'is_read' => false,
            'user_id' => $other->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonFragment(['title' => 'Notification mobile']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/notifications/'.$mine->id.'/read')
            ->assertOk()
            ->assertJsonPath('notification.is_read', true);
    }
}
