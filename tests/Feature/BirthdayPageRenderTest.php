<?php
namespace Tests\Feature;
use App\Filament\Pages\Birthdays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BirthdayPageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_birthdays_page_renders(): void
    {
        $this->seed();
        $user = \App\Models\User::where('email', 'sounesatchougo@gmail.com')->firstOrFail();
        $this->actingAs($user);
        Livewire::test(Birthdays::class)->assertOk();
    }
}
