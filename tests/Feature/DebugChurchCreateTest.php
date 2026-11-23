<?php
namespace Tests\Feature;
use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Filament\Resources\Churches\Pages\CreateChurch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DebugChurchCreateTest extends TestCase
{
    use RefreshDatabase;
    public function test_dbg(): void
    {
        $this->seed();
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $component = Livewire::actingAs($admin)->test(CreateChurch::class)
            ->fillForm(['name' => 'CE NOUVEAU', 'type' => ChurchType::LOCAL->value])
            ->call('create');
        dump($component->errors()->all());
        dump($component->errors()->toArray());
    }
}
