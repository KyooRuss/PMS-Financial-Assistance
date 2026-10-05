<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WaterDistrict;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Water districts and their category (A–D). The category decides eligibility for emergency loans.
 */
class WaterDistrictController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('admin.water-districts.index', [
            'districts' => WaterDistrict::query()
                ->withCount(['users', 'projects'])
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->when(in_array($request->query('category'), ['A', 'B', 'C', 'D'], true), fn ($query) => $query->where('category', $request->query('category')))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.water-districts.form', ['district' => new WaterDistrict]);
    }

    public function store(Request $request): RedirectResponse
    {
        $district = WaterDistrict::create($this->validated($request));

        return redirect()->route('admin.water-districts.index')->with('success', __(':name added.', ['name' => $district->name]));
    }

    public function edit(WaterDistrict $waterDistrict): View
    {
        return view('admin.water-districts.form', [
            'district' => $waterDistrict->load(['users' => fn ($query) => $query->orderBy('last_name')])->loadCount('projects'),
        ]);
    }

    public function update(Request $request, WaterDistrict $waterDistrict): RedirectResponse
    {
        $waterDistrict->update($this->validated($request, $waterDistrict));

        return redirect()->route('admin.water-districts.index')->with('success', __(':name updated.', ['name' => $waterDistrict->name]));
    }

    private function validated(Request $request, ?WaterDistrict $district = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(WaterDistrict::class)->ignore($district)],
            'category' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
