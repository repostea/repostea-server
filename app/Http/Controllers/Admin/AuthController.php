<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    /**
     * Show admin login form.
     */
    public function showLoginForm()
    {
        if (Auth::guard('web')->check()) {
            // If already logged in, check if user is admin/moderator
            if (Auth::guard('web')->user()->isModerator()) {
                return redirect()->route('admin.dashboard');
            }

            // If not admin/moderator, logout and show login
            Auth::guard('web')->logout();
        }

        return view('admin.auth.login');
    }

    /**
     * Handle admin login.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
            'cf-turnstile-response' => 'required|turnstile',
        ]);

        $remember = $request->boolean('remember');
        $login = Str::lower($credentials['email']);

        // Find user by email or username
        $user = User::where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        // Use web guard explicitly with found user's email
        if (Auth::guard('web')->attempt(['email' => $user->email, 'password' => $credentials['password']], $remember)) {
            $request->session()->regenerate();

            // Check if user has admin/moderator role
            if (! Auth::guard('web')->user()->isModerator()) {
                Auth::guard('web')->logout();

                throw ValidationException::withMessages([
                    'email' => 'You do not have permission to access the admin panel.',
                ]);
            }

            return redirect()->intended(route('admin.dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
