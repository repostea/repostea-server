<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Achievement;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\GuestNameGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ApiAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = Str::lower($validated['email']);
        $throttleKey = $login . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => [__('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ])],
            ]);
        }

        // Try to find user by email or username
        $user = User::where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Prevent deleted users from logging in
        if ($user->is_deleted) {
            throw ValidationException::withMessages([
                'email' => [__('auth.account_already_deleted')],
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Delete only tokens that haven't been used in 30 days (instead of all tokens)
        // This allows users to stay logged in on multiple devices
        $user->tokens()
            ->where(function ($query): void {
                $query->where('last_used_at', '<', now()->subDays(30))
                    ->orWhere(function ($q): void {
                        $q->whereNull('last_used_at')
                            ->where('created_at', '<', now()->subDays(30));
                    });
            })
            ->delete();

        $newToken = $user->createToken('auth_token');

        // Store device info for session management
        $newToken->accessToken->update([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $token = $newToken->plainTextToken;

        // Check if email verification is required and user hasn't verified
        $emailVerificationMode = SystemSetting::get('email_verification', 'optional');
        $requiresVerification = $emailVerificationMode === 'required' &&
                               ! $user->is_guest &&
                               ! $user->hasVerifiedEmail();

        // Set the user in the request so UserResource can detect it's own profile
        $request->setUserResolver(fn () => $user);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'email_verification_required' => $requiresVerification,
        ]);
    }

    public function guestLogin(Request $request): JsonResponse
    {
        // Check if guest access is enabled
        $guestAccess = SystemSetting::get('guest_access', 'enabled');

        if ($guestAccess === 'disabled') {
            return ApiResponse::error(__('auth.guest_disabled'), null, 403);
        }

        // Create a temporary guest user with beautiful names
        $displayName = GuestNameGenerator::generateName();
        $guestUser = User::create([
            'display_name' => $displayName,
            'username' => GuestNameGenerator::generateUsername($displayName),
            'email' => 'guest_' . Str::random(8) . '@temp.local',
            'password' => Hash::make(Str::random(32)),
            'is_guest' => true,
            'email_verified_at' => now(),
        ]);

        $newToken = $guestUser->createToken('guest_token');

        // Store device info for session management
        $newToken->accessToken->update([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $token = $newToken->plainTextToken;

        // Set the user in the request so UserResource can detect it's own profile
        $request->setUserResolver(fn () => $guestUser);

        return response()->json([
            'user' => new UserResource($guestUser),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, __('auth.logout_success'));
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['currentLevel', 'streak', 'achievements']);
        $postsCount = $user->posts()->count();
        $commentsCount = $user->comments()->count();

        $achievements = Achievement::get()->map(static function ($achievement) use ($user) {
            $userAchievement = $user->achievements()
                ->where('achievement_id', $achievement->id)
                ->first();

            return [
                'id' => $achievement->id,
                'key' => $achievement->slug,
                'name' => $achievement->translated_name,
                'description' => $achievement->translated_description,
                'icon' => $achievement->icon,
                'type' => $achievement->type,
                'progress' => $userAchievement ? $userAchievement->pivot->progress : 0,
                'unlocked' => $userAchievement && $userAchievement->pivot->unlocked_at ? true : false,
                'unlocked_at' => $userAchievement ? $userAchievement->pivot->unlocked_at : null,
            ];
        });

        return response()->json([
            'data' => new UserResource($user),
            'posts_count' => $postsCount,
            'comments_count' => $commentsCount,
            'member_since' => $user->created_at->format('M Y'),
            'achievements' => [
                'items' => $achievements->groupBy('type'),
                'unlocked_count' => $achievements->where('unlocked', true)->count(),
                'total_count' => $achievements->count(),
            ],
        ]);
    }
}
