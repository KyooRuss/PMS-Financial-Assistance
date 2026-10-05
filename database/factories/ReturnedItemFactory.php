<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\Requirement;
use App\Models\ReturnedItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnedItem>
 */
class ReturnedItemFactory extends Factory
{
    /**
     * Define the model's default state. Expects a requirement to be given, e.g. ->for($requirement).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'requirement_id' => fn () => Requirement::query()->value('id'),
            'project_log_id' => fn (array $attributes) => ProjectLog::factory()->by('WSUDD', 2)->create(['project_id' => $attributes['project_id']])->id,
            'remarks' => fake()->sentence(),
            'resolved_at' => null,
        ];
    }

    /**
     * The water district already resubmitted a corrected file.
     */
    public function resolved(): static
    {
        return $this->state(['resolved_at' => now()]);
    }
}
