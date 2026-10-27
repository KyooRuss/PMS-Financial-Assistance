<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\Role;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\User;
use App\Models\WaterDistrict;
use App\Support\ProcessingTimes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProcessingTimeTest extends TestCase
{
    use RefreshDatabase;

    private User $lwd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-30 12:00'));
        $district = WaterDistrict::factory()->create(['name' => 'Hillside Water District', 'category' => 'C']);
        $this->lwd = User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
    }

    /**
     * A project whose timeline is [office, step, days after submission] entries.
     *
     * @param  list<array{0: string, 1: int, 2: float}>  $timeline
     */
    private function projectWithTimeline(array $timeline, array $attributes = []): Project
    {
        $project = Project::factory()->for($this->lwd)->create($attributes + ['status' => ProjectStatus::UnderEvaluation, 'current_step' => 5]);
        $start = Carbon::parse('2026-09-01 08:00');

        foreach ($timeline as [$office, $step, $days]) {
            ProjectLog::factory()->for($project)->by($office, $step)->create([
                'created_at' => $start->copy()->addMinutes((int) round($days * 24 * 60)),
            ]);
        }

        return $project->fresh();
    }

    public function test_time_is_credited_to_the_office_that_acted(): void
    {
        $this->projectWithTimeline([['LWD', 1, 0], ['WSUDD', 2, 2], ['ES', 3, 8], ['LWRD', 4, 12]]);
        $this->projectWithTimeline([['LWD', 1, 0], ['WSUDD', 2, 4], ['ES', 3, 6], ['LWRD', 4, 7]]);

        $departments = (new ProcessingTimes)->byDepartment()->keyBy(fn ($row) => $row['role']->value);

        $this->assertEqualsWithDelta(3 * 24, $departments['wsudd']['average'], 0.01);  // 2 and 4 days
        $this->assertEqualsWithDelta(4 * 24, $departments['es']['average'], 0.01);     // 6 and 2 days
        $this->assertEqualsWithDelta(6 * 24, $departments['es']['longest'], 0.01);
        $this->assertSame(2, $departments['lwrd']['count']);
        $this->assertFalse($departments->has('lwd')); // the submission itself is not a handling
    }

    public function test_water_district_time_on_a_return_is_its_own_row(): void
    {
        // WSUDD returns after 1 day, the LWD takes 5 days to fix it, WSUDD endorses 1 day later.
        $this->projectWithTimeline([['LWD', 1, 0], ['WSUDD', 2, 1], ['LWD', 2, 6], ['WSUDD', 2, 7]]);

        $rows = (new ProcessingTimes)->bySteps()['regular']->keyBy('label');

        $this->assertEqualsWithDelta(24, $rows['WSUDD – Completeness check']['average'], 0.01);
        $this->assertSame(2, $rows['WSUDD – Completeness check']['count']);
        $this->assertEqualsWithDelta(5 * 24, $rows['LWD – Fixing returned items']['average'], 0.01);
    }

    public function test_flow_and_period_filters(): void
    {
        $this->projectWithTimeline([['LWD', 1, 0], ['WSUDD', 2, 2]]);                                      // regular, Sept 3
        $this->projectWithTimeline([['LWD', 1, 0], ['WSUDD/UDEV', 2, 10]], ['type' => ProjectType::Emergency, 'current_step' => 3]); // emergency, Sept 11

        $this->assertSame(1, (new ProcessingTimes('regular'))->byDepartment()->first()['count']);
        $this->assertEqualsWithDelta(240, (new ProcessingTimes('emergency'))->byDepartment()->first()['average'], 0.01);
        $this->assertArrayNotHasKey('regular', (new ProcessingTimes('emergency'))->bySteps());

        // Only handlings that ended on or after Sept 10.
        $this->assertSame(1, (new ProcessingTimes(null, Carbon::parse('2026-09-10')))->byDepartment()->first()['count']);
    }

    public function test_current_waits_and_completion_time(): void
    {
        $waiting = $this->projectWithTimeline([['LWD', 1, 0], ['WSUDD', 2, 1], ['ES', 3, 3], ['LWRD', 4, 5]]); // at CPIC since Sept 6
        $this->assertSame(Role::Cpic, $waiting->current_role);

        $done = $this->projectWithTimeline([['LWD', 1, 0], ['Legal', 15, 20]], ['status' => ProjectStatus::Completed, 'current_step' => 15]);

        $times = new ProcessingTimes;
        $cpic = $times->waiting()->firstWhere('role', Role::Cpic);

        $this->assertSame(1, $cpic['count']);
        $this->assertEqualsWithDelta(24 * 24 + 4, $cpic['longest'], 0.01); // Sept 6 08:00 → Sept 30 12:00
        $this->assertEqualsWithDelta(20 * 24, $times->averageCompletionHours(), 0.01);
        $this->assertSame(1, $times->completedCount());
        $this->assertNull($done->current_role);
    }

    public function test_per_office_rows_include_offices_with_only_waiting_requests(): void
    {
        $this->projectWithTimeline([['LWD', 1, 0], ['WSUDD', 2, 1], ['ES', 3, 3], ['LWRD', 4, 5]]); // at CPIC, which has not acted yet

        $offices = (new ProcessingTimes)->perOffice();

        $this->assertSame([Role::Wsudd, Role::Es, Role::Lwrd, Role::Cpic], $offices->pluck('role')->all());
        $this->assertEqualsWithDelta(24, $offices[0]['average'], 0.01);
        $this->assertSame(0, $offices[0]['waiting']);
        $this->assertNull($offices[3]['average']);
        $this->assertSame(1, $offices[3]['waiting']);
    }

    public function test_report_page_for_lwua_staff_only(): void
    {
        $this->projectWithTimeline([['LWD', 1, 0], ['WSUDD', 2, 2], ['ES', 3, 8]]);

        $this->actingAs(User::factory()->create(['role' => Role::Administrator]))
            ->get(route('reports.processing-time'))
            ->assertOk()
            ->assertSee('Average days per office')
            ->assertSee('Requests in progress')
            ->assertSee('Waiting now')
            ->assertSee('Show step-by-step details')
            ->assertSee('ES – Rapid assessment of WD-POW')
            ->assertSee('6 days')
            ->assertDontSee('handling')
            ->assertDontSee('6.0');

        $this->actingAs(User::factory()->create(['role' => Role::SystemAdmin]))->get(route('reports.processing-time'))->assertOk();
        $this->actingAs($this->lwd)->get(route('reports.processing-time'))->assertForbidden();
    }

    public function test_format(): void
    {
        $this->assertSame('4 h', ProcessingTimes::format(3.5));
        $this->assertSame('1 h', ProcessingTimes::format(0.2));
        $this->assertSame('15 h', ProcessingTimes::format(15));
        $this->assertSame('1 day', ProcessingTimes::format(30));
        $this->assertSame('3 days', ProcessingTimes::format(60));
        $this->assertSame('12 days', ProcessingTimes::format(293));
        $this->assertSame('—', ProcessingTimes::format(null));
    }
}
