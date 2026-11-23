<?php

namespace Tests\Feature;

use App\Enums\CategoryGroup;
use App\Models\CategoryDon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_group_is_guessed_from_the_name(): void
    {
        $this->assertSame(CategoryGroup::CHURCH_FINANCES, CategoryGroup::fromName('Dîmes'));
        $this->assertSame(CategoryGroup::CHURCH_FINANCES, CategoryGroup::fromName('Action de grâce'));
        $this->assertSame(CategoryGroup::CHURCH_FINANCES, CategoryGroup::fromName('Semences'));
        $this->assertSame(CategoryGroup::MINISTRY_PARTNERSHIP, CategoryGroup::fromName('Rhapsodie des Réalités'));
        $this->assertSame(CategoryGroup::MINISTRY_PARTNERSHIP, CategoryGroup::fromName('Mission InnerCity'));
        $this->assertSame(CategoryGroup::MINISTRY_PARTNERSHIP, CategoryGroup::fromName('École de Guérisons'));
        $this->assertSame(CategoryGroup::LOCAL_PARTNERSHIP, CategoryGroup::fromName('Projet de construction'));
    }

    public function test_a_new_category_gets_a_default_group(): void
    {
        $category = CategoryDon::create(['name' => 'Projet toiture', 'color' => '#FF0000']);

        $this->assertSame(CategoryGroup::LOCAL_PARTNERSHIP, $category->group);
    }

    public function test_the_api_exposes_the_group_of_categories(): void
    {
        $this->seed();
        $admin = User::role(\App\Enums\RoleName::SUPER_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/categories')->assertOk();

        $response->assertJsonFragment(['name' => 'Dîmes', 'group' => 'church_finances']);
        $response->assertJsonFragment(['name' => 'Rhapsodie des Réalités', 'group' => 'ministry_partnership']);
    }

    public function test_the_api_requires_a_valid_group_when_creating_a_category(): void
    {
        $this->seed();
        $admin = User::role(\App\Enums\RoleName::SUPER_ADMIN->value)->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Nouvelle catégorie',
                'color' => '#123456',
                'group' => 'inconnu',
            ])
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Nouvelle catégorie',
                'color' => '#123456',
                'group' => 'church_finances',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('category_dons', [
            'name' => 'Nouvelle catégorie',
            'group' => 'church_finances',
        ]);
    }
}
