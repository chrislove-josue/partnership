<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPartnerSyncTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private Church $otherChurch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $this->otherChurch = Church::create([
            'name' => 'CE AUTRE',
            'type' => ChurchType::LOCAL,
            'parent_id' => $this->church->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function makeUser(array $attributes = []): User
    {
        return User::create($attributes + [
            'name' => 'Nestor ATCHOUGO',
            'email' => 'compte-'.uniqid().'@example.test',
            'password' => Hash::make('azerty123'),
            'assigned_church_id' => $this->church->id,
            'is_active' => true,
        ]);
    }

    public function test_creating_a_user_creates_a_partner_in_the_assigned_church(): void
    {
        $user = $this->makeUser([
            'name' => 'Nestor ATCHOUGO',
            'email' => 'nestor@example.test',
            'phone' => '61981233',
            'handle_kc' => 'nestor.kc',
        ]);

        $partner = Partner::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Nestor', $partner->first_name);
        $this->assertSame('ATCHOUGO', $partner->last_name);
        $this->assertSame('nestor@example.test', $partner->email);
        $this->assertSame('61981233', $partner->phone);
        $this->assertSame('nestor.kc', $partner->handle_kc);
        $this->assertSame($this->church->id, $partner->church_id);
    }

    public function test_updating_the_account_updates_the_partner_and_moves_it_to_the_new_church(): void
    {
        $user = $this->makeUser(['email' => 'pasteur@example.test']);
        $partner = Partner::where('user_id', $user->id)->firstOrFail();

        $user->update([
            'name' => 'Jean Pierre DOSSOU',
            'email' => 'jp.dossou@example.test',
            'phone' => '61000000',
            'assigned_church_id' => $this->otherChurch->id,
        ]);

        $partner->refresh();

        $this->assertSame('Jean Pierre', $partner->first_name);
        $this->assertSame('DOSSOU', $partner->last_name);
        $this->assertSame('jp.dossou@example.test', $partner->email);
        $this->assertSame('61000000', $partner->phone);
        $this->assertSame($this->otherChurch->id, $partner->church_id);
    }

    public function test_an_existing_unlinked_partner_with_the_same_email_is_reused(): void
    {
        $existing = Partner::create([
            'first_name' => 'Ancien',
            'last_name' => 'Nom',
            'email' => 'pasteur@example.test',
            'church_id' => $this->church->id,
        ]);

        $user = $this->makeUser(['email' => 'pasteur@example.test']);

        // Aucun partenaire non rattaché ne subsiste : l'existant a été repris.
        $this->assertSame(0, Partner::whereNull('user_id')->count());
        $this->assertSame($user->id, $existing->refresh()->user_id);
    }

    public function test_a_linked_partner_is_not_stolen_by_another_account(): void
    {
        $first = $this->makeUser(['email' => 'first@example.test']);
        $firstPartner = Partner::where('user_id', $first->id)->firstOrFail();
        $firstPartner->update(['email' => 'shared@example.test']);

        $second = $this->makeUser(['email' => 'shared@example.test']);
        $secondPartner = Partner::where('user_id', $second->id)->firstOrFail();

        $this->assertSame($first->id, $firstPartner->refresh()->user_id);
        $this->assertNotSame($firstPartner->id, $secondPartner->id);
        // L'adresse déjà utilisée par un autre partenaire n'est pas recopiée.
        $this->assertNull($secondPartner->email);
    }

    public function test_deleting_the_account_keeps_the_partner_and_its_donations(): void
    {
        $user = $this->makeUser(['email' => 'partant@example.test']);
        $partner = Partner::where('user_id', $user->id)->firstOrFail();

        $donation = Donation::create([
            'amount' => 5000,
            'date' => now(),
            'partner_id' => $partner->id,
            'church_id' => $this->church->id,
            'category_id' => CategoryDon::firstOrFail()->id,
        ]);

        $user->delete();

        $this->assertDatabaseHas('partners', ['id' => $partner->id, 'user_id' => null]);
        $this->assertDatabaseHas('donations', ['id' => $donation->id]);
    }

    public function test_a_user_without_assigned_church_has_no_partner(): void
    {
        $user = $this->makeUser(['assigned_church_id' => null]);

        $this->assertNull(Partner::where('user_id', $user->id)->first());
    }

    public function test_unassigning_the_church_detaches_the_partner(): void
    {
        $user = $this->makeUser(['email' => 'detache@example.test']);
        $partner = Partner::where('user_id', $user->id)->firstOrFail();

        $user->update(['assigned_church_id' => null]);

        $this->assertDatabaseHas('partners', ['id' => $partner->id, 'user_id' => null]);
        $this->assertSame($this->church->id, $partner->refresh()->church_id);
    }

    public function test_api_created_user_is_also_stored_as_a_partner(): void
    {
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Pasteur Local',
                'email' => 'pasteur.local@example.test',
                'password' => 'azerty123',
                'roles' => [RoleName::LOCAL_PASTOR->value],
                'assigned_church_id' => $this->otherChurch->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('partners', [
            'email' => 'pasteur.local@example.test',
            'first_name' => 'Pasteur',
            'last_name' => 'Local',
            'church_id' => $this->otherChurch->id,
        ]);
    }

    public function test_every_seeded_user_has_a_partner(): void
    {
        $this->assertGreaterThan(0, User::count());

        foreach (User::all() as $user) {
            $this->assertNotNull($user->partner, "Le compte {$user->email} n'a pas de partenaire.");
        }
    }

    public function test_the_api_flags_partners_linked_to_a_user_account(): void
    {
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $plain = Partner::create([
            'first_name' => 'Donateur',
            'last_name' => 'Simple',
            'church_id' => $this->church->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/partners')->assertOk();

        $response->assertJsonFragment(['email' => $admin->email, 'is_user' => true]);
        $response->assertJsonFragment(['id' => $plain->id, 'is_user' => false]);
    }

    public function test_the_api_refuses_to_delete_the_partner_of_a_user_account(): void
    {
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $partner = $admin->partner;

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/partners/{$partner->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('partners', ['id' => $partner->id, 'user_id' => $admin->id]);
    }

    public function test_the_api_still_deletes_a_plain_partner(): void
    {
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $partner = Partner::create([
            'first_name' => 'Donateur',
            'last_name' => 'Simple',
            'church_id' => $this->church->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/partners/{$partner->id}")
            ->assertOk();

        $this->assertDatabaseMissing('partners', ['id' => $partner->id]);
    }
}
