<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Vote;
use App\Observers\CommentObserver;
use App\Observers\PostObserver;
use App\Observers\VoteObserver;
use App\Services\KarmaService;
use App\Services\KarmaServiceInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(KarmaServiceInterface::class, KarmaService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        Vote::observe(VoteObserver::class);
        Post::observe(PostObserver::class);
        Comment::observe(CommentObserver::class);

        Route::macro('localizedRoute', static function ($name, $parameters = [], $absolute = true) {
            if (! isset($parameters['locale'])) {
                $parameters['locale'] = App::getLocale();
            }

            return route($name, $parameters, $absolute);
        });

        // Define admin access gate
        Gate::define('access-admin', fn ($user) => $user->roles()
            ->whereIn('slug', ['admin', 'moderator'])
            ->exists());

        // Define specific admin-only gate
        Gate::define('admin-only', fn ($user) => $user->roles()
            ->where('slug', 'admin')
            ->exists());

        // Share menu counters with admin layout
        View::composer('admin.layout', function ($view): void {
            if (auth()->check() && Gate::allows('access-admin')) {
                $menuCounters = [
                    'pending_reports' => DB::table('reports')->where('status', 'pending')->count(),
                    'pending_users' => DB::table('users')->where('status', 'pending')->count(),
                    'pending_legal_reports' => DB::table('legal_reports')->whereIn('status', ['pending', 'under_review'])->count(),
                ];
                $view->with('menuCounters', $menuCounters);
            }
        });
    }
}
