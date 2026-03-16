<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForcedPasswordChangeController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.force-password-change', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        Auth::logoutOtherDevices($validated['password']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        $this->logActivity(
            'users',
            'password_changed',
            'User changed password after a forced reset or first login.',
            $request->user(),
            $request->user()
        );

        return redirect()
            ->intended(route('dashboard', absolute: false))
            ->with('status', 'Password updated successfully.');
    }
}