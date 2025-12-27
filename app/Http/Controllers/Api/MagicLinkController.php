<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\MagicLinkLogin;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MagicLinkController extends Controller
{
    public function sendMagicLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'cf-turnstile-response' => ['required', Rule::turnstile()],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }
        $token = Str::random(60);

        Cache::put('magic_link_' . $token, $user->id, 900);

        $magicLink = URL::temporarySignedRoute(
            'auth.magic-link.verify',
            Carbon::now()->addMinutes(15),
            [
                'token' => $token,
            ],
        );
        Log::info($magicLink);

        $locale = $user->locale ?? 'es';
        $magicLink = config('app.client_url') . "/{$locale}/auth/magic-link?token=" . $token;
        $user->notify(new MagicLinkLogin($magicLink));

        return response()->json([
            'message' => __('auth.magic_link_sent'),
        ]);
    }

    public function verifyMagicLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $token = $validated['token'];
        $userId = Cache::get('magic_link_' . $token);

        if ($userId === null) {
            throw ValidationException::withMessages([
                'token' => [__('notifications.magic_link.invalid_token')],
            ]);
        }

        $user = User::find($userId);

        if ($user === null) {
            throw ValidationException::withMessages([
                'token' => [__('auth.user_not_found')],
            ]);
        }

        Cache::forget('magic_link_' . $token);

        $authToken = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $authToken,
        ]);
    }
}
