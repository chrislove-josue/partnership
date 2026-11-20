<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChurchStaffCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_it_creates_one_active_staff_member_per_church(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = Church::create(['name' => 'CE CALAVI', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'CE PARAKOU', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);

        $this->artisan('church:ensure-staff')->assertSuccessful();

        $this->assertSame(3, User::role(RoleName::STAFF->value)->count());

        foreach ([$group, $sub, $local] as $church) {
            $staff = User::where('assigned_church_id', $church->id)
                ->role(RoleName::STAFF->value)
                ->firstOrFail();

            $this->assertSame('Personnel '.$church->name, $staff->name);
            $this->assertSame('staff.'.strtolower(str_replace(' ', '-', $church->name)).'@churchmanager.test', $staff->email);
            $this->assertTrue($staff->is_active);
            $this->assertNotNull($staff->email_verified_at);
            $this->assertTrue(Hash::check('azerty123', $staff->password));
            $this->assertTrue($staff->hasRole(RoleName::STAFF->value));
        }
    }

    public function test_it_is_idempotent_and_reactivates_disabled_staff(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();

        $this->artisan('church:ensure-staff')->assertSuccessful();
        $this->artisan('church:ensure-staff')->assertSuccessful();

        $this->assertSame(1, User::role(RoleName::STAFF->value)->count());

        $staff = User::role(RoleName::STAFF->value)->firstOrFail();
        $staff->update(['is_active' => false]);

        $this->artisan('church:ensure-staff')->assertSuccessful();

        $this->assertSame(1, User::role(RoleName::STAFF->value)->count());
        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_it_can_limit_the_generation_to_one_church(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $local = Church::create(['name' => 'CE PARAKOU', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);

        $this->artisan('church:ensure-staff', ['--church' => (string) $local->id])->assertSuccessful();

        $this->assertSame(1, User::role(RoleName::STAFF->value)->count());
        $this->assertSame(
            $local->id,
            User::role(RoleName::STAFF->value)->firstOrFail()->assigned_church_id,
        );
    }
}
