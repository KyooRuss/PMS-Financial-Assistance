<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use App\Models\WaterDistrict;
use Database\Seeders\RequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private WaterDistrict $district;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => Role::SystemAdmin, 'first_name' => 'Sys', 'last_name' => 'Admin']);
        $this->district = WaterDistrict::factory()->create(['name' => 'Hillside Water District', 'category' => 'C']);
    }

    private function lwdUser(): User
    {
        return User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $this->district->id]);
    }

    public function test_only_the_system_admin_can_open_the_admin_area(): void
    {
        foreach (['admin.overview', 'admin.projects.index', 'admin.users.index', 'admin.water-districts.index'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
            $this->actingAs(User::factory()->create(['role' => Role::Administrator]))->get(route($route))->assertForbidden();
            $this->actingAs($this->lwdUser())->get(route($route))->assertForbidden();
        }
    }

    public function test_system_admin_lands_on_the_overview(): void
    {
        $user = $this->lwdUser();
        $project = Project::factory()->for($user)->create(['status' => ProjectStatus::Submitted, 'current_step' => 2]);
        $project->log($user, 'Submitted the request and requirements', office: 'LWD');

        $this->actingAs($this->admin)->get('/dashboard')->assertRedirect(route('admin.overview'));
        $this->actingAs($this->admin)->get(route('admin.overview'))
            ->assertSee('Requests submitted')->assertSee('Waiting right now')->assertSee('WSUDD / UDEV');
    }

    public function test_only_the_admin_can_click_an_office_in_the_process_flow(): void
    {
        $this->seed(RequirementSeeder::class);
        $user = $this->lwdUser();
        $project = Project::factory()->for($user)->create(['status' => ProjectStatus::Submitted, 'current_step' => 2]);
        $waitingOnEs = route('admin.projects.index', ['waiting' => Role::Es->value]);

        $this->actingAs($this->admin)->get(route('office.projects.show', $project))
            ->assertOk()
            ->assertSee('href="'.e($waitingOnEs).'"', false)
            ->assertSee('href="'.e(route('admin.projects.index', ['waiting' => Role::Wsudd->value])).'"', false);

        // Following the link lists the requests waiting on that office.
        $this->actingAs($this->admin)->get($waitingOnEs)->assertOk();

        $this->actingAs(User::factory()->create(['role' => Role::Wsudd]))->get(route('office.projects.show', $project))
            ->assertOk()->assertDontSee($waitingOnEs, false);
        $this->actingAs($user)->get(route('dashboard', ['project' => $project->id]))
            ->assertOk()->assertDontSee($waitingOnEs, false);
    }

    public function test_admin_can_view_any_project_but_not_act(): void
    {
        $this->seed(RequirementSeeder::class);
        $project = Project::factory()->for($this->lwdUser())->create(['status' => ProjectStatus::Submitted, 'current_step' => 2]);

        $this->actingAs($this->admin)->get(route('admin.projects.index', ['q' => $project->reference]))->assertSee($project->reference);
        // The Action box is only shown to the office whose turn it is, so the admin sees no buttons.
        $this->actingAs($this->admin)->get(route('office.projects.show', $project))
            ->assertOk()->assertSee('All Projects')->assertDontSee('Flag for return')->assertDontSee('Complete – Endorse');
        $this->actingAs($this->admin)->post(route('office.projects.endorse', $project))->assertForbidden();
    }

    public function test_projects_can_be_filtered(): void
    {
        $user = $this->lwdUser();
        $returned = Project::factory()->for($user)->create(['status' => ProjectStatus::Returned]);
        $completed = Project::factory()->for($user)->create(['status' => ProjectStatus::Completed]);

        // Look for the reference in its table cell; the search box placeholder also mentions "P001".
        $row = fn (Project $project) => '>'.$project->reference.'</td>';

        $this->actingAs($this->admin)->get(route('admin.projects.index', ['status' => 'returned']))
            ->assertSee($row($returned), false)->assertDontSee($row($completed), false);
        $this->actingAs($this->admin)->get(route('admin.projects.index', ['q' => $completed->reference]))
            ->assertSee($row($completed), false)->assertDontSee($row($returned), false);
    }

    public function test_there_is_no_files_page(): void
    {
        $this->actingAs($this->admin)->get('/admin/files')->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.overview'))->assertDontSee('/admin/files');
    }

    public function test_admin_creates_an_account_with_a_temporary_password(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@hillside.test',
            'role' => 'lwd', 'water_district_id' => $this->district->id, 'password' => 'Temp-1234',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'maria@hillside.test')->sole();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->waterDistrict->is($this->district));

        // First login: every page sends them to the one-time "Set your password" screen.
        auth()->logout();
        $this->post('/login', ['email' => 'maria@hillside.test', 'password' => 'Temp-1234']);
        $this->get('/dashboard')->assertRedirect(route('password.setup'));
        $this->get(route('password.setup'))->assertOk()->assertSee('Set your password')->assertSee('Temporary Password');

        $this->put('/password', ['current_password' => 'Temp-1234', 'password' => 'My-own-pass-99', 'password_confirmation' => 'My-own-pass-99'])
            ->assertRedirect(route('dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->get('/dashboard')->assertOk();

        // After that, they can't change it again.
        $this->get(route('password.setup'))->assertRedirect(route('dashboard'));
        $this->put('/password', ['current_password' => 'My-own-pass-99', 'password' => 'Another-pass-1', 'password_confirmation' => 'Another-pass-1'])
            ->assertForbidden();
    }

    public function test_account_rules(): void
    {
        // A water district user needs a water district; office staff must not have one.
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.test', 'role' => 'lwd', 'password' => 'Temp-1234',
        ])->assertSessionHasErrors('water_district_id');

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.test', 'role' => 'es', 'water_district_id' => $this->district->id, 'password' => 'Temp-1234',
        ])->assertSessionHasErrors('water_district_id');

        // The admin cannot demote or deactivate themselves.
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'first_name' => 'Sys', 'last_name' => 'Admin', 'email' => $this->admin->email, 'role' => 'es',
        ])->assertSessionHasErrors('role');
        $this->actingAs($this->admin)->patch(route('admin.users.toggle-active', $this->admin))->assertForbidden();
    }

    public function test_inactive_accounts_cannot_log_in_and_are_signed_out(): void
    {
        $user = $this->lwdUser();

        $this->actingAs($this->admin)->patch(route('admin.users.toggle-active', $user));
        $this->assertFalse($user->fresh()->is_active);

        // Already signed in: signed out on the next request.
        $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();

        // Cannot log in again.
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account is inactive. Please contact LWUA.']);
        $this->assertGuest();

        // Reactivated, they can.
        $this->actingAs($this->admin)->patch(route('admin.users.toggle-active', $user));
        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_reset_password_sets_a_temporary_one(): void
    {
        $user = $this->lwdUser();

        $this->actingAs($this->admin)->put(route('admin.users.reset-password', $user), ['temporary_password' => 'short'])
            ->assertSessionHasErrorsIn('resetPassword', 'temporary_password');
        $this->actingAs($this->admin)->put(route('admin.users.reset-password', $user), ['temporary_password' => 'Reset-5678']);

        $this->assertTrue($user->fresh()->must_change_password);
        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'Reset-5678']);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_water_districts_can_be_added_and_recategorised(): void
    {
        $this->actingAs($this->admin)->post(route('admin.water-districts.store'), ['name' => 'Lakeside Water District', 'category' => 'B'])
            ->assertRedirect(route('admin.water-districts.index'));

        $district = WaterDistrict::where('name', 'Lakeside Water District')->sole();

        $this->actingAs($this->admin)->post(route('admin.water-districts.store'), ['name' => 'Lakeside Water District', 'category' => 'B'])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->put(route('admin.water-districts.update', $district), ['name' => $district->name, 'category' => 'X'])
            ->assertSessionHasErrors('category');
        $this->actingAs($this->admin)->put(route('admin.water-districts.update', $district), ['name' => $district->name, 'category' => 'D']);

        $this->assertSame('D', $district->fresh()->category);
    }

    public function test_there_are_no_departments_or_activity_log_pages(): void
    {
        $user = $this->lwdUser();
        $project = Project::factory()->for($user)->create(['status' => ProjectStatus::Submitted, 'current_step' => 2]);
        $project->log($user, 'Submitted the request and requirements', office: 'LWD');

        $this->actingAs($this->admin)->get('/admin/departments')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/activity')->assertNotFound();

        // The Overview still lists recent activity, without a link to a full log.
        $this->actingAs($this->admin)->get(route('admin.overview'))
            ->assertSee('Recent activity')->assertSee('Submitted the request and requirements')
            ->assertDontSee('Activity Log')->assertDontSee('Departments');
    }
}
