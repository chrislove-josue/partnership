<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\PeriodType;
use App\Enums\RoleName;
use App\Mail\PartnershipReportMail;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use App\Services\PartnershipReportExporter;
use App\Services\PartnershipReportSender;
use App\Services\PartnershipReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Tests\TestCase;

class PartnershipReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, int $churchId, string $email, ?int $managerOf = null): User
    {
        return $this->makeUserWithRoles([$role], $churchId, $email, $managerOf);
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function makeUserWithRoles(array $roles, int $churchId, string $email, ?int $managerOf = null): User
    {
        $user = User::create([
            'name' => 'User '.implode('-', $roles),
            'email' => $email,
            'password' => Hash::make('password'),
            'assigned_church_id' => $churchId,
            'is_active' => true,
        ]);
        $user->syncRoles($roles);

        if ($managerOf !== null) {
            Church::whereKey($managerOf)->update(['manager_id' => $user->id]);
        }

        return $user;
    }

    private function makePartner(Church $church, string $email): Partner
    {
        return Partner::create([
            'first_name' => 'Jean',
            'last_name' => 'Doe',
            'email' => $email,
            'church_id' => $church->id,
        ]);
    }

    private function makeDonation(Partner $partner, Church $church, CategoryDon $category, string $date, float $amount): Donation
    {
        return Donation::create([
            'amount' => $amount,
            'date' => $date,
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_period_ranges_are_strictly_bounded(): void
    {
        $service = app(PartnershipReportService::class);

        $year = $service->resolvePeriodRange(PeriodType::YEAR, 2025, null);
        $this->assertSame('2025-01-01', $year['from']->toDateString());
        $this->assertSame('2025-12-31', $year['to']->toDateString());
        $this->assertSame('Année 2025', $year['label']);

        $month = $service->resolvePeriodRange(PeriodType::MONTH, 2026, 2);
        $this->assertSame('2026-02-01', $month['from']->toDateString());
        $this->assertSame('2026-02-28', $month['to']->toDateString());
        $this->assertSame('Février 2026', $month['label']);

        $quarter = $service->resolvePeriodRange(PeriodType::QUARTER, 2026, 1);
        $this->assertSame('2026-01-01', $quarter['from']->toDateString());
        $this->assertSame('2026-03-31', $quarter['to']->toDateString());
        $this->assertSame('T1 2026', $quarter['label']);
    }

    public function test_report_aggregates_only_donations_of_the_selected_year(): void
    {
        $this->seed();

        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $category = CategoryDon::firstOrFail();
        $partner = $this->makePartner($church, 'jean@example.test');

        $this->makeDonation($partner, $church, $category, '2025-01-01 08:00:00', 1000);
        $this->makeDonation($partner, $church, $category, '2025-12-31 20:00:00', 2000);
        $this->makeDonation($partner, $church, $category, '2026-01-01 08:00:00', 5000);

        $service = app(PartnershipReportService::class);

        $report2025 = $service->buildReport($church->id, PeriodType::YEAR, 2025, null);
        $this->assertSame(3000.0, $report2025['totals']['amount']);
        $this->assertSame(2, $report2025['totals']['donationCount']);
        $this->assertSame(3000.0, $report2025['categories'][0]['amount']);

        $report2026 = $service->buildReport($church->id, PeriodType::YEAR, 2026, null);
        $this->assertSame(5000.0, $report2026['totals']['amount']);
        $this->assertSame(1, $report2026['totals']['donationCount']);
    }

    public function test_report_lists_registered_partners_without_donation(): void
    {
        $this->seed();

        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $category = CategoryDon::firstOrFail();
        $active = $this->makePartner($church, 'actif@example.test');
        $inactive = $this->makePartner($church, 'inactif@example.test');

        $this->makeDonation($active, $church, $category, '2026-02-10 10:00:00', 4500);

        $report = app(PartnershipReportService::class)
            ->buildReport($church->id, PeriodType::MONTH, 2026, 2);

        $this->assertSame(1, $report['totals']['partnersWithDonation']);
        $this->assertSame(1, $report['totals']['partnersWithoutDonation']);
        $this->assertSame(2, $report['totals']['registeredPartners']);
        $this->assertSame([$inactive->id], array_column($report['partnersWithoutDonation'], 'id'));
        $this->assertSame('Jean Doe', $report['partners'][0]['name']);
    }

    public function test_access_is_checked_before_generating_a_preview(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = Church::create(['name' => 'SUB', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);
        $otherLocal = Church::create(['name' => 'AUTRE', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);

        $pastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'local@example.test');
        $sender = app(PartnershipReportSender::class);

        $this->expectException(AuthorizationException::class);
        $sender->preview($pastor, $otherLocal->id, PeriodType::YEAR, 2026, null);
    }

    public function test_group_report_is_sent_to_active_super_admins_excluding_sender(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sender = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();
        $other = $this->makeUser(RoleName::SUPER_ADMIN->value, $group->id, 'autre-admin@example.test');
        $inactive = $this->makeUser(RoleName::SUPER_ADMIN->value, $group->id, 'inactif@example.test');
        $inactive->update(['is_active' => false]);

        $result = app(PartnershipReportService::class)->resolveRecipients($sender, $group->id);
        $emails = array_column($result['recipients'], 'email');

        $this->assertContains($other->email, $emails);
        $this->assertNotContains($sender->email, $emails);
        $this->assertNotContains($inactive->email, $emails);
        $this->assertSame('hiérarchie nationale (super administrateurs)', $result['targetLabel']);
    }

    public function test_staff_report_goes_to_own_church_manager(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $groupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test', $group->id);
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $pastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'pasteur@example.test', $local->id);
        $staff = $this->makeUser(RoleName::STAFF->value, $local->id, 'staff@example.test');

        $result = app(PartnershipReportService::class)->resolveRecipients($staff, $local->id);
        $cc = array_column($result['cc'], 'email');

        $this->assertSame([$pastor->email], array_column($result['recipients'], 'email'));
        $this->assertContains($groupPastor->email, $cc);
        $this->assertContains('libertsinzo7@gmail.com', $cc);
        $this->assertSame('pasteur de votre église', $result['targetLabel']);
    }

    public function test_staff_report_copies_subgroup_and_group_pastors(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $groupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test', $group->id);
        $sub = Church::create(['name' => 'CE CALAVI', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $subPastor = $this->makeUser(RoleName::SUBGROUP_PASTOR->value, $sub->id, 'sous-groupe@example.test', $sub->id);
        $local = Church::create(['name' => 'CE PARAKOU', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);
        $pastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'pasteur@example.test', $local->id);
        $staff = $this->makeUser(RoleName::STAFF->value, $local->id, 'staff@example.test');

        $result = app(PartnershipReportService::class)->resolveRecipients($staff, $local->id);
        $cc = array_column($result['cc'], 'email');

        $this->assertSame([$pastor->email], array_column($result['recipients'], 'email'));
        $this->assertContains($subPastor->email, $cc);
        $this->assertContains($groupPastor->email, $cc);
        $this->assertContains('libertsinzo7@gmail.com', $cc);
        $this->assertSame('pasteur de votre église', $result['targetLabel']);
    }

    public function test_role_name_uses_the_highest_function(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $user = $this->makeUserWithRoles([
            RoleName::LOCAL_PASTOR->value,
            RoleName::GROUP_PASTOR->value,
        ], $group->id, 'cumul@example.test');

        $this->assertSame(RoleName::GROUP_PASTOR->value, $user->roleName());
    }

    public function test_implied_roles_are_added_to_a_composed_account(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $user = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'cumul@example.test');
        $user->syncImpliedRoles();

        $roles = $user->refresh()->getRoleNames()->all();

        $this->assertContains(RoleName::GROUP_PASTOR->value, $roles);
        $this->assertContains(RoleName::SUBGROUP_PASTOR->value, $roles);
        $this->assertContains(RoleName::LOCAL_PASTOR->value, $roles);
    }

    public function test_subgroup_manager_is_the_recipient_for_a_local_report(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        Church::whereKey($group->id)->update(['manager_id' => null]);
        $groupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test', $group->id);
        $sub = Church::create(['name' => 'SUB', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);
        $subManager = $this->makeUser(RoleName::GROUP_PASTOR->value, $local->id, 'sous-groupe-groupe@example.test', $sub->id);
        $localPastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'local@example.test', $local->id);

        $result = app(PartnershipReportService::class)->resolveRecipients($localPastor, $local->id);
        $to = array_column($result['recipients'], 'email');
        $cc = array_column($result['cc'], 'email');

        $this->assertSame([$subManager->email], $to);
        $this->assertContains($groupPastor->email, $cc);
        $this->assertNotContains($groupPastor->email, $to);
        $this->assertSame('pasteur de votre sous-groupe', $result['targetLabel']);
    }

    public function test_staff_on_a_group_church_is_rejected(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $staff = $this->makeUser(RoleName::STAFF->value, $group->id, 'staff@example.test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('église locale');

        app(PartnershipReportService::class)->resolveRecipients($staff, $group->id);
    }

    public function test_staff_report_uses_the_church_manager_before_other_pastors(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $pastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'pasteur@example.test', $local->id);
        $otherPastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'autre-pasteur@example.test');
        $inactivePastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'inactif@example.test');
        $inactivePastor->update(['is_active' => false]);
        $staff = $this->makeUser(RoleName::STAFF->value, $local->id, 'staff@example.test');

        $result = app(PartnershipReportService::class)->resolveRecipients($staff, $local->id);
        $emails = array_column($result['recipients'], 'email');

        $this->assertSame([$pastor->email], $emails);
        $this->assertNotContains($otherPastor->email, $emails);
        $this->assertNotContains($inactivePastor->email, $emails);
        $this->assertSame('pasteur de votre église', $result['targetLabel']);
    }

    public function test_group_pastor_can_be_the_local_church_manager_recipient(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $pastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe-local@example.test', $local->id);
        $staff = $this->makeUser(RoleName::STAFF->value, $local->id, 'staff@example.test');

        $result = app(PartnershipReportService::class)->resolveRecipients($staff, $local->id);

        $this->assertSame([$pastor->email], array_column($result['recipients'], 'email'));
    }

    public function test_subgroup_pastor_can_be_the_local_church_manager_recipient(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = Church::create(['name' => 'SUB', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);
        $pastor = $this->makeUser(RoleName::SUBGROUP_PASTOR->value, $sub->id, 'sous-groupe-local@example.test', $local->id);
        $staff = $this->makeUser(RoleName::STAFF->value, $local->id, 'staff@example.test');

        $result = app(PartnershipReportService::class)->resolveRecipients($staff, $local->id);

        $this->assertSame([$pastor->email], array_column($result['recipients'], 'email'));
    }

    public function test_local_pastor_report_climbs_hierarchy_to_group_pastors(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        Church::whereKey($group->id)->update(['manager_id' => null]);
        $groupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test');
        $otherGroupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'autre-groupe@example.test');
        $sub = Church::create(['name' => 'SUB', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);
        $localPastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'local@example.test', $local->id);

        $result = app(PartnershipReportService::class)->resolveRecipients($localPastor, $local->id);
        $emails = array_column($result['recipients'], 'email');

        $this->assertContains($groupPastor->email, $emails);
        $this->assertContains($otherGroupPastor->email, $emails);
        $this->assertCount(2, $emails);
        $this->assertSame('pasteur de votre groupe', $result['targetLabel']);
    }

    public function test_group_manager_is_the_preferred_recipient(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $groupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test', $group->id);
        $otherGroupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'autre-groupe@example.test');
        $sub = Church::create(['name' => 'SUB', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);
        $localPastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'local@example.test', $local->id);

        $result = app(PartnershipReportService::class)->resolveRecipients($localPastor, $local->id);

        $this->assertSame([$groupPastor->email], array_column($result['recipients'], 'email'));
        $this->assertNotContains($otherGroupPastor->email, array_column($result['recipients'], 'email'));
    }

    public function test_subgroup_report_is_sent_to_group_pastor(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        Church::whereKey($group->id)->update(['manager_id' => null]);
        $groupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test', $group->id);
        $sub = Church::create(['name' => 'CE CALAVI', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $subPastor = $this->makeUser(RoleName::SUBGROUP_PASTOR->value, $sub->id, 'sous-groupe@example.test', $sub->id);

        $result = app(PartnershipReportService::class)->resolveRecipients($subPastor, $sub->id);

        $this->assertSame([$groupPastor->email], array_column($result['recipients'], 'email'));
        $this->assertSame('pasteur de votre groupe', $result['targetLabel']);
        $this->assertContains('pasteur de groupe ('.$group->name.')', $result['chain']);
        $this->assertNotContains($subPastor->email, array_column($result['recipients'], 'email'));
    }

    public function test_staff_of_subgroup_also_reports_to_group_pastor(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        Church::whereKey($group->id)->update(['manager_id' => null]);
        $groupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test', $group->id);
        $sub = Church::create(['name' => 'CE CALAVI', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $staff = $this->makeUser(RoleName::STAFF->value, $sub->id, 'staff-sous-groupe@example.test');

        $result = app(PartnershipReportService::class)->resolveRecipients($staff, $sub->id);

        $this->assertSame([$groupPastor->email], array_column($result['recipients'], 'email'));
        $this->assertSame('pasteur de votre groupe', $result['targetLabel']);
    }

    public function test_local_pastor_reports_to_subgroup_pastor_with_group_pastor_in_copy(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $groupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test', $group->id);
        $sub = Church::create(['name' => 'CE CALAVI', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $subPastor = $this->makeUser(RoleName::SUBGROUP_PASTOR->value, $sub->id, 'sous-groupe@example.test', $sub->id);
        $otherSub = Church::create(['name' => 'AUTRE SUB', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $otherSubPastor = $this->makeUser(RoleName::SUBGROUP_PASTOR->value, $otherSub->id, 'autre-sous-groupe@example.test', $otherSub->id);
        $local = Church::create(['name' => 'CE PARAKOU', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);
        $localPastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'local@example.test', $local->id);
        $admin = $this->makeUser(RoleName::SUPER_ADMIN->value, $group->id, 'admin@example.test');

        $result = app(PartnershipReportService::class)->resolveRecipients($localPastor, $local->id);
        $cc = array_column($result['cc'], 'email');

        $this->assertSame([$subPastor->email], array_column($result['recipients'], 'email'));
        $this->assertContains($groupPastor->email, $cc);
        $this->assertContains($admin->email, $cc);
        $this->assertContains('libertsinzo7@gmail.com', $cc);
        $this->assertNotContains($otherSubPastor->email, $cc);
        $this->assertNotContains($localPastor->email, $cc);
        $this->assertSame('pasteur de votre sous-groupe', $result['targetLabel']);
        $this->assertContains('pasteur de sous-groupe (CE CALAVI)', $result['chain']);
        $this->assertContains('pasteur de groupe (CE SIKECODJI) (copie)', $result['chain']);
    }

    public function test_staff_report_without_a_church_pastor_fails_clearly(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $staff = $this->makeUser(RoleName::STAFF->value, $local->id, 'staff@example.test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aucun pasteur local actif');

        app(PartnershipReportService::class)->resolveRecipients($staff, $local->id);
    }

    public function test_sending_copies_the_hierarchy_when_confirmed(): void
    {
        Mail::fake();
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $groupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test', $group->id);
        $localPastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'local@example.test', $local->id);
        $admin = $this->makeUser(RoleName::SUPER_ADMIN->value, $group->id, 'admin@example.test');

        $sent = app(PartnershipReportSender::class)
            ->send($localPastor, $local->id, PeriodType::MONTH, 2026, 2, true);

        $this->assertTrue($sent['sent']);
        $this->assertSame([$groupPastor->email], array_column($sent['to'], 'email'));
        $this->assertContains($admin->email, array_column($sent['cc'], 'email'));

        Mail::assertSent(PartnershipReportMail::class, function (PartnershipReportMail $mail) use ($groupPastor, $admin): bool {
            return $mail->hasTo($groupPastor->email) && $mail->hasCc($admin->email);
        });
    }

    public function test_send_uses_only_the_checked_copies(): void
    {
        Mail::fake();
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        Church::whereKey($group->id)->update(['manager_id' => null]);
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $groupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test', $group->id);
        $localPastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'local@example.test', $local->id);
        $kept = $this->makeUser(RoleName::SUPER_ADMIN->value, $group->id, 'copie-conservee@example.test');
        $removed = $this->makeUser(RoleName::SUPER_ADMIN->value, $group->id, 'copie-retiree@example.test');

        $sent = app(PartnershipReportSender::class)
            ->send($localPastor, $local->id, PeriodType::MONTH, 2026, 2, true, [$kept->email]);

        $this->assertSame([$kept->email], array_column($sent['cc'], 'email'));
        $this->assertNotContains($removed->email, array_column($sent['cc'], 'email'));

        Mail::assertSent(PartnershipReportMail::class, function (PartnershipReportMail $mail) use ($groupPastor, $kept, $removed): bool {
            return $mail->hasTo($groupPastor->email)
                && $mail->hasCc($kept->email)
                && ! $mail->hasCc($removed->email)
                && ! $mail->hasCc('libertsinzo7@gmail.com');
        });
    }

    public function test_send_can_remove_all_copies(): void
    {
        Mail::fake();
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        Church::whereKey($group->id)->update(['manager_id' => null]);
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test', $group->id);
        $localPastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'local@example.test', $local->id);
        $admin = $this->makeUser(RoleName::SUPER_ADMIN->value, $group->id, 'admin@example.test');

        $sent = app(PartnershipReportSender::class)
            ->send($localPastor, $local->id, PeriodType::MONTH, 2026, 2, true, []);

        $this->assertSame([], $sent['cc']);

        Mail::assertSent(PartnershipReportMail::class, function (PartnershipReportMail $mail) use ($admin): bool {
            return ! $mail->hasCc($admin->email) && ! $mail->hasCc('libertsinzo7@gmail.com');
        });
    }

    public function test_sending_requires_explicit_confirmation_and_attaches_the_xlsx(): void
    {
        Mail::fake();
        $this->seed();

        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $category = CategoryDon::firstOrFail();
        $partner = $this->makePartner($church, 'jean@example.test');
        $this->makeDonation($partner, $church, $category, '2026-02-10 10:00:00', 4500);

        $manager = $this->makeUser(RoleName::SUPER_ADMIN->value, $church->id, 'manager@example.test');
        Church::whereKey($church->id)->update(['manager_id' => $manager->id]);
        $sender = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();

        $senderService = app(PartnershipReportSender::class);
        $parameters = [$sender, $church->id, PeriodType::MONTH, 2026, 2];

        $preview = $senderService->preview(...$parameters);
        $this->assertFalse($preview['sent']);
        $this->assertTrue($preview['confirmRequired']);
        Mail::assertNothingSent();

        $notConfirmed = $senderService->send(...[...$parameters, false]);
        $this->assertFalse($notConfirmed['sent']);
        Mail::assertNothingSent();

        $sent = $senderService->send(...[...$parameters, true]);
        $this->assertTrue($sent['sent']);
        $this->assertStringEndsWith('.xlsx', $sent['fileName']);

        Mail::assertSent(PartnershipReportMail::class, function (PartnershipReportMail $mail) use ($manager): bool {
            return $mail->hasTo($manager->email)
                && count($mail->attachments()) === 1
                && str_contains($mail->envelope()->subject, 'Février 2026');
        });
    }

    public function test_export_builds_four_sheets_with_the_expected_totals(): void
    {
        $this->seed();

        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $category = CategoryDon::firstOrFail();
        $active = $this->makePartner($church, 'actif@example.test');
        $this->makePartner($church, 'inactif@example.test');
        $this->makeDonation($active, $church, $category, '2026-02-10 10:00:00', 4500);

        $report = app(PartnershipReportService::class)
            ->buildReport($church->id, PeriodType::MONTH, 2026, 2);
        $xlsx = app(PartnershipReportExporter::class)->toXlsx($report);

        $path = tempnam(sys_get_temp_dir(), 'rapport').'.xlsx';
        file_put_contents($path, $xlsx);

        try {
            $workbook = IOFactory::load($path);

            $this->assertSame(
                ['Résumé', 'Dons par catégorie', 'Partenaires', 'Sans don sur la période'],
                $workbook->getSheetNames(),
            );
            $this->assertSame('4 500 FCFA', $workbook->getSheetByName('Résumé')->getCell('B6')->getValue());
            $this->assertSame('TOTAL', $workbook->getSheetByName('Dons par catégorie')->getCell('A3')->getValue());
        } finally {
            @unlink($path);
        }
    }

    public function test_send_dialog_shows_the_group_pastor_and_the_copies(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $groupPastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id, 'groupe@example.test', $group->id);
        $localPastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id, 'local@example.test', $local->id);
        $this->actingAs($localPastor);

        $component = \Livewire\Livewire::test(\App\Filament\Pages\PartnershipReport::class)->instance();
        $description = (new \ReflectionMethod($component, 'sendModalDescription'))->invoke($component);

        $this->assertStringContainsString('À (pasteur de votre groupe) : '.$groupPastor->email, $description);
        $this->assertStringContainsString('Copie :', $description);

        $schema = (new \ReflectionMethod($component, 'sendCcSchema'))->invoke($component);

        $this->assertNotNull($schema);
        $this->assertInstanceOf(\Filament\Forms\Components\CheckboxList::class, $schema[0]);
        $this->assertArrayHasKey('libertsinzo7@gmail.com', $schema[0]->getOptions());
        $this->assertContains('libertsinzo7@gmail.com', $schema[0]->getDefaultState());
    }

    public function test_send_action_sends_after_confirmation(): void
    {
        Mail::fake();
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $this->makeUser(RoleName::SUPER_ADMIN->value, $group->id, 'destinataire@example.test');
        $admin = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Pages\PartnershipReport::class)
            ->call('mountAction', 'send')
            ->call('callMountedAction');

        Mail::assertSent(PartnershipReportMail::class, fn (PartnershipReportMail $mail): bool => $mail->hasTo('destinataire@example.test'));
    }

    public function test_export_action_downloads_the_xlsx(): void
    {
        $this->seed();

        $admin = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Pages\PartnershipReport::class)
            ->call('mountAction', 'export')
            ->assertFileDownloaded();
    }

    public function test_page_recomputes_the_report_when_the_year_changes(): void
    {
        $this->seed();

        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $category = CategoryDon::firstOrFail();
        $partner = $this->makePartner($church, 'jean@example.test');
        $this->makeDonation($partner, $church, $category, '2025-06-10 10:00:00', 3000);
        $this->makeDonation($partner, $church, $category, '2026-06-10 10:00:00', 7000);

        $admin = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Pages\PartnershipReport::class)
            ->set('data.type', 'year')
            ->set('data.year', 2025)
            ->assertSee('3 000 FCFA')
            ->set('data.year', 2026)
            ->assertSee('7 000 FCFA');
    }

    public function test_report_page_renders_when_a_required_filter_is_empty(): void
    {
        $this->seed();

        $admin = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Pages\PartnershipReport::class)
            ->set('data.church_id', null)
            ->assertSee('Aucune église sélectionnée')
            ->set('data.type', null)
            ->assertSee('Aucune église sélectionnée');
    }

    public function test_report_page_renders_for_a_super_admin(): void
    {
        $this->seed();

        $admin = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();

        $this->actingAs($admin)
            ->get('/dashboard/partnership-report')
            ->assertOk()
            ->assertSee('Rapport de partenariat')
            ->assertSee('Total des dons')
            ->assertSee('Dons par catégorie');
    }

    public function test_missing_church_raises_a_clear_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Église introuvable');

        app(PartnershipReportService::class)->buildReport(999999, PeriodType::YEAR, 2026, null);
    }
}
