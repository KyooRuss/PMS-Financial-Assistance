<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<ProjectDocument>
 */
class ProjectDocumentFactory extends Factory
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
            'requirement_id' => null,
            'project_log_id' => null,
            'internal' => false,
            'uploaded_by' => User::factory(),
            'original_name' => fake()->slug(3).'.pdf',
            'path' => fn (array $attributes) => 'projects/'.$attributes['project_id'].'/'.fake()->uuid().'.pdf',
            'size' => 20480,
        ];
    }

    /**
     * Writes a small placeholder PDF to the private disk, so "View" can open it.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (ProjectDocument $document) {
            Storage::disk('local')->put($document->path, '%PDF-1.4 placeholder');
        });
    }

    /**
     * A file uploaded for a checklist item.
     */
    public function forRequirement(Requirement $requirement): static
    {
        return $this->state(['requirement_id' => $requirement->id]);
    }

    /**
     * An office file that only LWUA can see.
     */
    public function internal(): static
    {
        return $this->state(['internal' => true]);
    }
}
