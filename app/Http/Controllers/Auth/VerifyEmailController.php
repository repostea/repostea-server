<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class VerifyEmailController extends Controller
{
    /**
     * Mark the user's email address as verified.
     */
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        // Try to get user locale for better UX, fallback to 'es'
        try {
            $user = User::find($id);
            $locale = $user?->locale ?? 'es';
        } catch (Exception $e) {
            $locale = 'es';
        }

        // Check if URL has expired by looking at the expires parameter
        if ($request->has('expires') && now()->getTimestamp() > $request->get('expires')) {
            // URL has expired
            return redirect(config('app.client_url') . "/{$locale}?verified=0&error=expired");
        }

        // Check if signature is valid
        if (! $request->hasValidSignature()) {
            // Signature is invalid (manipulated)
            return redirect(config('app.client_url') . "/{$locale}?verified=0&error=invalid");
        }

        // Find user by ID
        $user = User::findOrFail($id);

        // Get user's preferred locale (default to 'es' if not set)
        $locale = $user->locale ?? 'es';

        // Verify the hash matches
        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect(config('app.client_url') . "/{$locale}?verified=0&error=invalid");
        }

        // Check if already verified - still redirect to onboarding in case they haven't completed it
        if ($user->hasVerifiedEmail()) {
            return redirect(config('app.client_url') . "/{$locale}/onboarding?verified=1&already=true");
        }

        // Mark as verified
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // Redirect to onboarding after email verification (using user's preferred locale)
        return redirect(config('app.client_url') . "/{$locale}/onboarding?verified=1");
    }
}
