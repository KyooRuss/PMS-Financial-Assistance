<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectLog>
 */
class ProjectLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => null,
            'office' => 'LWD',
            'action' => fake()->sentence(4),
            'remarks' => null,
            'step' => 1,
        ];
    }

    /**
     * An entry by the given office on the given step, e.g. ->by('WSUDD', 2).
     */
    public function by(string $office, int $step): static
    {
        return $this->state(['office' => $office, 'step' => $step]);
    }
}
