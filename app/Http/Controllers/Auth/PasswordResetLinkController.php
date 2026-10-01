<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        // ponytail: explicit bypass of reset token logic. Ceiling: open to brute force/unauthorized reset if email is known.
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Email tidak ditemukan di sistem kami.'],
            ]);
        }

        $user->forceFill([
            'password' => \Illuminate\Support\Facades\Hash::make($request->password)
        ])->save();

        return redirect()->route('login')->with('status', 'Password berhasil diubah! Silakan login dengan password baru.');
    }
}
