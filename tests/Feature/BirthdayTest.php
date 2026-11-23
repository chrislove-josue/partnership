<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\Notification;
use App\Models\Partner;
use App\Models\User;
use App\Services\BirthdayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BirthdayTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
    }

    private function makePastor(): User
    {
        $user = User::create([
            'name' => 'Pasteur Anniversaire',
            'email' => 'pasteur.birthday@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $this->church->id,
            'is_active' => true,
        ]);

        $user->syncRoles([RoleName::LOCAL_PASTOR->value]);

        return $user;
    }

    private function makePartnerWithBirthdate(string $birthdate): Partner
    {
        return Partner::create([
            'first_name' => 'Anne',
            'last_name' => 'Niversaire',
            'email' => 'anne.'.uniqid().'@example.test',
            'birthdate' => $birthdate,
            'church_id' => $this->church->id,
        ]);
    }

    public function test_month_birthdays_lists_partners_sorted_by_day(): void
    {
        $this->makePartnerWithBirthdate('1990-11-25');
        $this->makePartnerWithBirthdate('1985-11-03');
        $this->makePartnerWithBirthdate('1992-12-10'); // autre mois

        $items = app(BirthdayService::class)->monthBirthdays(11, 2026);

        $this->assertCount(2, $items);
        $this->assertSame([3, 25], $items->pluck('day')->all());
        $this->assertSame(41, $items->first()['age']); // 2026 - 1985
        $this->assertSame('CE SIKECODJI', $items->first()['church']);
    }

    public function test_month_birthdays_respects_church_scope(): void
    {
        $this->makePartnerWithBirthdate('1990-11-25');

        $items = app(BirthdayService::class)->monthBirthdays(11, 2026, []);

        $this->assertCount(0, $items);
    }

    public function test_leap_day_birthday_is_listed_on_february_28_in_common_years(): void
    {
        $this->makePartnerWithBirthdate('2000-02-29');

        $items = app(BirthdayService::class)->monthBirthdays(2, 2026);

        $this->assertCount(1, $items);
        $this->assertSame(28, $items->first()['day']);
        $this->assertSame('2026-02-28', $items->first()['date']);
    }

    public function test_notify_upcoming_sends_j7_j3_and_day_of(): void
    {
        $pastor = $this->makePastor();
        $today = Carbon::today();

        $this->makePartnerWithBirthdate($today->copy()->addDays(7)->subYears(30)->toDateString());
        $this->makePartnerWithBirthdate($today->copy()->addDays(3)->subYears(25)->toDateString());
        $this->makePartnerWithBirthdate($today->subYears(40)->toDateString());

        $created = app(BirthdayService::class)->notifyUpcoming();

        $this->assertSame(3, $created);

        $notifications = Notification::where('type', NotificationType::BIRTHDAY->value)->get();

        // Une alerte par partenaire, envoyée à chaque membre de la hiérarchie.
        $this->assertSame([0, 3, 7], $notifications->pluck('data.delay')->unique()->sort()->values()->all());
        $this->assertSame(3, $notifications->pluck('data.partnerId')->unique()->count());
        $this->assertTrue($notifications->contains('user_id', $pastor->id));
        $this->assertTrue($notifications->contains(
            fn (Notification $n): bool => str_contains($n->title, 'aujourd'),
        ));
    }

    public function test_notify_upcoming_is_idempotent_within_the_same_day(): void
    {
        $this->makePastor();
        $today = Carbon::today();

        $this->makePartnerWithBirthdate($today->subYears(30)->toDateString());

        $service = app(BirthdayService::class);

        $this->assertSame(1, $service->notifyUpcoming());
        $this->assertSame(0, $service->notifyUpcoming());

        $this->assertSame(
            1,
            Notification::where('type', NotificationType::BIRTHDAY->value)
                ->pluck('data.partnerId')
                ->unique()
                ->count(),
        );
    }

    public function test_birthday_api_returns_scoped_month_list(): void
    {
        $user = User::where('email', 'sounesatchougo@gmail.com')->firstOrFail();
        $this->makePartnerWithBirthdate('1990-11-25');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/birthdays?month=11&year=2026');

        $response->assertOk()
            ->assertJsonPath('month', 11)
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.day', 25)
            ->assertJsonPath('data.0.name', 'Anne Niversaire');
    }

    public function test_birthday_api_validates_month_and_year(): void
    {
        $user = User::where('email', 'sounesatchougo@gmail.com')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/birthdays?month=13')
            ->assertUnprocessable();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/birthdays?year=1999')
            ->assertUnprocessable();
    }

    public function test_birthday_api_requires_authentication(): void
    {
        $this->getJson('/api/birthdays')->assertUnauthorized();
    }
}
