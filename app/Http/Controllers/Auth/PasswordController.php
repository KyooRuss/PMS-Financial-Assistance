<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Only the System Admin can change their password at any time (from My Profile).
 * Everyone else sets it once, to replace the temporary password the System Admin gave them.
 */
class PasswordController extends Controller
{
    /**
     * The one-time "Set your password" screen for an account with a temporary password.
     */
    public function setup(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.set-password');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->must_change_password || $request->user()->hasRole(Role::SystemAdmin), 403);

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $hadTemporaryPassword = $request->user()->must_change_password;

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        return $hadTemporaryPassword
            ? redirect()->route('dashboard')->with('success', __('Password changed. Welcome to PMS.'))
            : back()->with('status', 'password-updated');
    }
}
