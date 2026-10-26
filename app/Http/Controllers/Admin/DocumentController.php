<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequirementStage;
use App\Http\Controllers\Controller;
use App\Models\ProjectDocument;
use App\Models\WaterDistrict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every file uploaded in PMS, by water districts and LWUA offices.
 */
class DocumentController extends Controller
{
    /** Filter => label. */
    public const KINDS = [
        'submission' => 'Water district submissions',
        'lwua' => 'LWUA documents and letters',
        'internal' => 'Internal office files',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $kind = array_key_exists((string) $request->query('kind'), self::KINDS) ? $request->query('kind') : null;

        $documents = ProjectDocument::query()
            ->with(['project.waterDistrict', 'requirement', 'uploader'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->orWhere('original_name', 'like', "%{$search}%")
            ))
            ->when($kind === 'submission', fn (Builder $query) => $query->whereHas('requirement', fn (Builder $requirement) => $requirement
                ->whereIn('stage', [RequirementStage::PreEvaluation, RequirementStage::PostEvaluation])))
            ->when($kind === 'lwua', fn (Builder $query) => $query->where('internal', false)->where(fn (Builder $query) => $query
                ->whereNull('requirement_id')
                ->orWhereHas('requirement', fn (Builder $requirement) => $requirement->where('stage', RequirementStage::LwuaUnit))))
            ->when($kind === 'internal', fn (Builder $query) => $query->where('internal', true))
            ->when($request->integer('district'), fn (Builder $query, $district) => $query->whereHas('project', fn (Builder $project) => $project->where('water_district_id', $district)))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.documents', [
            'documents' => $documents,
            'districts' => WaterDistrict::orderBy('name')->get(),
            'kind' => $kind,
        ]);
    }
}
