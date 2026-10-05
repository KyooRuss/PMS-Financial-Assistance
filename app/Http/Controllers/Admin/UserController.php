<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\User;
use App\Models\WaterDistrict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Account management. LWUA creates every account; accounts are made inactive, never deleted,
 * so names stay on the timeline and on uploaded files.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->with('waterDistrict')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when(Role::tryFrom((string) $request->query('role')), fn (Builder $query, $role) => $query->where('role', $role))
            ->when($request->query('status') === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['role' => Role::Lwd]), 'districts' => WaterDistrict::orderBy('name')->get()]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->except('password'),
            'password' => $request->validated('password'),
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', __('Account created for :name. Give them the temporary password; they will set their own on first login.', ['name' => $user->full_name]));
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'districts' => WaterDistrict::orderBy('name')->get()]);
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $user->update([...$request->validated(), 'water_district_id' => $request->validated('water_district_id')]);

        return redirect()->route('admin.users.index')->with('success', __('Account updated: :name.', ['name' => $user->full_name]));
    }

    /**
     * Make an account inactive (cannot log in) or active again.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, __('You cannot deactivate your own account.'));

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active
            ? __(':name can log in again.', ['name' => $user->full_name])
            : __(':name is now inactive and can no longer log in.', ['name' => $user->full_name]));
    }

    /**
     * Give the user a new temporary password; they must change it on their next login.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validateWithBag('resetPassword', ['temporary_password' => ['required', 'string', Password::min(8)]]);

        $user->update(['password' => $validated['temporary_password'], 'must_change_password' => true]);

        return back()->with('success', __('Temporary password set for :name. They will be asked to change it when they log in.', ['name' => $user->full_name]));
    }
}
