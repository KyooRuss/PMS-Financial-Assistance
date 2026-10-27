<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Models\WaterDistrict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploaded files open in a view-only viewer; the raw PDF is only served to that viewer.
 */
class DocumentViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $waterDistrictUser;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $district = WaterDistrict::factory()->create(['category' => 'C']);
        $this->waterDistrictUser = User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
        $this->project = Project::factory()->for($this->waterDistrictUser)->create();
    }

    public function test_opening_a_file_shows_the_viewer_page(): void
    {
        $document = ProjectDocument::factory()->for($this->project)->create(['original_name' => 'board-resolution.pdf', 'uploaded_by' => $this->waterDistrictUser->id]);

        $this->actingAs($this->waterDistrictUser)->get(route('documents.show', $document))
            ->assertOk()
            ->assertViewIs('documents.viewer')
            ->assertSee('board-resolution.pdf')
            ->assertSee(route('documents.file', $document), false);
    }

    public function test_the_raw_file_is_only_served_to_the_viewer(): void
    {
        $document = ProjectDocument::factory()->for($this->project)->create(['original_name' => 'board-resolution.pdf', 'uploaded_by' => $this->waterDistrictUser->id]);

        // Pasting the file's address into the browser shows the viewer instead.
        $this->actingAs($this->waterDistrictUser)->get(route('documents.file', $document))
            ->assertRedirect(route('documents.show', $document));

        $response = $this->actingAs($this->waterDistrictUser)
            ->get(route('documents.file', $document), ['X-PMS-Viewer' => '1'])
            ->assertOk();

        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_water_district_cannot_open_internal_office_files(): void
    {
        $document = ProjectDocument::factory()->for($this->project)->internal()->create([
            'uploaded_by' => User::factory()->create(['role' => Role::Es])->id,
        ]);

        $this->actingAs($this->waterDistrictUser)->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($this->waterDistrictUser)->get(route('documents.file', $document), ['X-PMS-Viewer' => '1'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => Role::Lwrd]))->get(route('documents.file', $document), ['X-PMS-Viewer' => '1'])->assertOk();
    }
}
