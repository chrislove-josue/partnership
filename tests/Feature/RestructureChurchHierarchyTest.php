<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestructureChurchHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_restructure_command_builds_the_target_tree(): void
    {
        $this->artisan('church:restructure', ['--force' => true]);

        $root = Church::where('name', 'Bénin Groupe 1')->firstOrFail();
        $sg1 = Church::where('name', 'Sous-groupe 1')->firstOrFail();
        $sg2 = Church::where('name', 'Sous-groupe 2')->firstOrFail();
        $mother1 = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $mother2 = Church::where('name', 'CE CALAVI')->firstOrFail();

        $this->assertSame(ChurchType::GROUP, $root->type);
        $this->assertNull($root->parent_id);

        $this->assertSame($root->id, $sg1->parent_id);
        $this->assertSame($root->id, $sg2->parent_id);
        $this->assertSame(ChurchType::SUB_GROUP, $sg1->type);
        $this->assertSame(ChurchType::SUB_GROUP, $sg2->type);

        $this->assertSame(ChurchType::LOCAL, $mother1->type);
        $this->assertSame($sg1->id, $mother1->parent_id);
        $this->assertSame(ChurchType::LOCAL, $mother2->type);
        $this->assertSame($sg2->id, $mother2->parent_id);

        $groupPastor = User::where('email', 'yaoadjaka@yahoo.fr')->first();
        if ($groupPastor) {
            $this->assertSame($root->id, $groupPastor->assigned_church_id);
            $this->assertTrue($groupPastor->hasRole(RoleName::GROUP_PASTOR->value));
        }

        // Idempotence
        $this->assertSame(1, Church::where('name', 'Bénin Groupe 1')->count());
        $this->assertSame(ChurchType::GROUP, $root->fresh()->type);
    }
}
