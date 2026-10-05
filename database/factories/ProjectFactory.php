<?php

namespace Database\Factories;

use App\Enums\FundingPurpose;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'water_district_id' => fn (array $attributes) => User::find($attributes['user_id'])->water_district_id,
            'type' => ProjectType::Regular50MBelow,
            'funding_purpose' => null,
            'title' => fake()->randomElement(['Water Supply Expansion', 'Pipeline Rehabilitation', 'New Production Well', 'Reservoir Construction']).' – '.fake()->city(),
            'description' => fake()->sentence(12),
            'amount' => fake()->numberBetween(5, 50) * 1_000_000,
            'status' => ProjectStatus::UnderEvaluation,
            'current_step' => 2,
            'submitted_at' => fake()->dateTimeBetween('-6 months'),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ProjectStatus::Draft, 'current_step' => 1, 'submitted_at' => null]);
    }

    public function emergency(FundingPurpose $purpose = FundingPurpose::Capex): static
    {
        return $this->state([
            'type' => ProjectType::Emergency,
            'funding_purpose' => $purpose,
            'title' => 'Emergency Rehabilitation – '.fake()->city(),
        ]);
    }
}
