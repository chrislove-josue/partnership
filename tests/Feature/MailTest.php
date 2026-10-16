<?php

namespace Tests\Feature;

use App\Mail\DonationConfirmationMail;
use App\Mail\UserWelcomeMail;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use App\Services\DonationNotifier;
use App\Services\UserInviter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailTest extends TestCase
{
    use RefreshDatabase;

    public function test_donation_confirmation_is_sent_when_partner_has_email(): void
    {
        Mail::fake();
        $this->seed();

        $church = Church::firstOrFail();
        $category = CategoryDon::firstOrFail();
        $partner = Partner::create([
            'first_name' => 'Jean',
            'last_name' => 'Doe',
            'email' => 'jean@example.test',
            'church_id' => $church->id,
        ]);

        $donation = Donation::create([
            'amount' => 5000,
            'date' => now(),
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]);

        $sent = app(DonationNotifier::class)->sendConfirmation($donation);

        $this->assertTrue($sent);
        Mail::assertSent(DonationConfirmationMail::class, fn ($mail) => $mail->hasTo('jean@example.test'));
    }

    public function test_donation_confirmation_is_skipped_without_email(): void
    {
        Mail::fake();
        $this->seed();

        $church = Church::firstOrFail();
        $category = CategoryDon::firstOrFail();
        $partner = Partner::create([
            'first_name' => 'Sans',
            'last_name' => 'Email',
            'church_id' => $church->id,
        ]);

        $donation = Donation::create([
            'amount' => 1000,
            'date' => now(),
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]);

        $sent = app(DonationNotifier::class)->sendConfirmation($donation);

        $this->assertFalse($sent);
        Mail::assertNothingSent();
    }

    public function test_welcome_email_is_sent_and_generates_magic_token(): void
    {
        Mail::fake();
        $this->seed();

        $user = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();

        $sent = app(UserInviter::class)->sendWelcome($user, 'azerty123', 'Super Admin');

        $this->assertTrue($sent);
        Mail::assertSent(UserWelcomeMail::class, fn ($mail) => $mail->hasTo('libertsinzo7@gmail.com'));
        $this->assertDatabaseCount('magic_tokens', 1);
    }
}
