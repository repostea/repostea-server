<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SocialMediaController extends Controller
{
    public function index(): View
    {
        // Get recent posts shared on Twitter/X
        $twitterPosts = Post::query()
            ->whereNotNull('twitter_posted_at')
            ->with(['user', 'twitterPostedBy'])
            ->orderByDesc('twitter_posted_at')
            ->limit(50)
            ->get();

        // Get statistics
        $stats = [
            'total_posted' => Post::whereNotNull('twitter_posted_at')->count(),
            'manual_posts' => Post::where('twitter_post_method', 'manual')->count(),
            'auto_posts' => Post::where('twitter_post_method', 'auto')->count(),
            'by_votes' => Post::where('twitter_post_reason', 'popular_votes')->count(),
            'by_article' => Post::where('twitter_post_reason', 'original_article')->count(),
            'pending' => Post::query()
                ->where('status', 'published')
                ->whereNotNull('frontpage_at')
                ->whereNull('twitter_posted_at')
                ->count(),
        ];

        // Get current configuration
        $config = [
            'auto_post_enabled' => SystemSetting::get('twitter_auto_post_enabled', config('twitter.auto_post_enabled', false)),
            'min_votes' => SystemSetting::get('twitter_min_votes', config('twitter.min_votes_to_post', 50)),
            'post_delay_minutes' => SystemSetting::get('twitter_post_delay_minutes', config('twitter.post_delay_minutes', 30)),
            'max_days_back' => SystemSetting::get('twitter_max_days_back', 3),
            'auto_post_original_articles' => SystemSetting::get('twitter_auto_post_articles', config('twitter.auto_post_original_articles', true)),
            'is_configured' => ! empty(config('twitter.api_key')) && ! empty(config('twitter.access_token')),
        ];

        return view('admin.social.index', compact('twitterPosts', 'stats', 'config'));
    }

    public function updateConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'auto_post_enabled' => 'nullable|boolean',
            'min_votes' => 'required|integer|min:1|max:1000',
            'post_delay_minutes' => 'required|integer|min:0|max:1440',
            'max_days_back' => 'required|integer|min:1|max:30',
            'auto_post_original_articles' => 'nullable|boolean',
        ]);

        SystemSetting::set('twitter_auto_post_enabled', $validated['auto_post_enabled'] ?? false, 'boolean');
        SystemSetting::set('twitter_min_votes', (int) $validated['min_votes'], 'integer');
        SystemSetting::set('twitter_post_delay_minutes', (int) $validated['post_delay_minutes'], 'integer');
        SystemSetting::set('twitter_max_days_back', (int) $validated['max_days_back'], 'integer');
        SystemSetting::set('twitter_auto_post_articles', $validated['auto_post_original_articles'] ?? false, 'boolean');

        return redirect()->route('admin.social')->with('success', 'Social media configuration updated successfully.');
    }
}
