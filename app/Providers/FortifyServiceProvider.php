<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::authenticateUsing(static function (Request $request) {
            $user = User::where('email', $request->email)->first();

            if (! empty($user) && Hash::check($request->password, $user->password)) {
                return $user;
            }
        });

        RateLimiter::for('login', static function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', static fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));

        $this->configureJsonResponses();
    }

    private function configureJsonResponses(): void
    {
        Fortify::loginView(static fn () => response()->json(['message' => 'Authentication required'], 401));

        Fortify::registerView(static fn () => response()->json(['message' => 'Registration form'], 200));

        Fortify::requestPasswordResetLinkView(static fn () => response()->json(['message' => 'Password reset request form'], 200));

        Fortify::resetPasswordView(static fn ($request) => response()->json(['message' => 'Password reset form', 'token' => $request->token], 200));

        Fortify::verifyEmailView(static fn () => response()->json(['message' => 'Email verification required'], 200));

        Fortify::confirmPasswordView(static fn () => response()->json(['message' => 'Password confirmation required'], 423));

        Fortify::twoFactorChallengeView(static fn () => response()->json(['message' => 'Two factor authentication required'], 423));
    }
}
