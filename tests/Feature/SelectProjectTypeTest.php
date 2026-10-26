<?php

namespace Tests\Feature;

use App\Enums\ProjectType;
use App\Enums\Role;
use App\Models\User;
use App\Models\WaterDistrict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelectProjectTypeTest extends TestCase
{
    use RefreshDatabase;

    private function lwdUser(string $category): User
    {
        $district = WaterDistrict::factory()->create(['name' => "Category {$category} WD", 'category' => $category]);

        return User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
    }

    public function test_modal_lists_all_three_types(): void
    {
        $this->actingAs($this->lwdUser('C'))
            ->get('/dashboard')
            ->assertSee('Select Project Type')
            ->assertSee(route('projects.create', ProjectType::RegularAbove50M))
            ->assertSee(route('projects.create', ProjectType::Regular50MBelow))
            ->assertSee(route('projects.create', ProjectType::Emergency))
            ->assertSeeInOrder(['1 – Within Threshold', '2 – Above Threshold', '3 – Emergency']);
    }

    public function test_emergency_is_disabled_in_the_modal_for_category_a_and_b(): void
    {
        foreach (['A', 'B'] as $category) {
            $this->actingAs($this->lwdUser($category))
                ->get('/dashboard')
                ->assertSee(route('projects.create', ProjectType::Regular50MBelow))
                ->assertDontSee(route('projects.create', ProjectType::Emergency))
                ->assertSee("Not available to Category {$category} water districts");
        }
    }

    public function test_category_c_and_d_can_open_the_emergency_form(): void
    {
        foreach (['C', 'D'] as $category) {
            $this->actingAs($this->lwdUser($category))
                ->get(route('projects.create', ProjectType::Emergency))
                ->assertOk()
                ->assertSee('Request a Project');
        }
    }

    public function test_category_a_cannot_open_the_emergency_form_by_url(): void
    {
        $this->actingAs($this->lwdUser('A'))
            ->get(route('projects.create', ProjectType::Emergency))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    public function test_regular_forms_open_for_every_category(): void
    {
        $user = $this->lwdUser('A');

        $this->actingAs($user)->get(route('projects.create', ProjectType::RegularAbove50M))->assertOk()->assertSee('Above Threshold')->assertDontSee('Annex');
        $this->actingAs($user)->get(route('projects.create', ProjectType::Regular50MBelow))->assertOk()->assertSee('Within Threshold')->assertDontSee('Annex');
    }

    public function test_unknown_type_is_not_found(): void
    {
        $this->actingAs($this->lwdUser('C'))->get('/projects/create/something-else')->assertNotFound();
    }

    public function test_lwua_offices_cannot_request_projects(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Wsudd]))
            ->get(route('projects.create', ProjectType::Regular50MBelow))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => Role::Wsudd]))
            ->get('/dashboard')
            ->assertDontSee('Select Project Type');
    }
}
