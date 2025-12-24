<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\KarmaLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    /**
     * @param  Request  $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        // If user is deleted or soft-deleted, return anonymized data
        // Check deleted_at directly instead of trashed() to avoid issues with withTrashed()
        $isSoftDeleted = $this->resource->deleted_at !== null;

        if ($this->resource->is_deleted || $isSoftDeleted) {
            return [
                'id' => null,
                'username' => '[deleted]',
                'display_name' => '[deleted]',
                'bio' => null,
                'avatar' => null,
                'karma_points' => 0,
                'highest_level_id' => null,
                'is_verified_expert' => false,
                'is_deleted' => true,
                'created_at' => null,
                'tags' => [],
                'badge' => null,
                'posts_count' => 0,
                'comments_count' => 0,
                'votes_count' => 0,
                'current_level' => null,
            ];
        }

        // Only show private data if viewing own profile
        $isOwnProfile = $request->user() && $request->user()->id === $this->resource->id;

        // Karma is always visible (privacy setting removed)

        return [
            'id' => $this->when($isOwnProfile, $this->resource->id),
            'username' => $this->resource->username,
            'display_name' => $this->resource->display_name,
            'bio' => $this->resource->bio,
            'avatar' => $this->resource->avatar,
            'email' => $this->when($isOwnProfile, $this->resource->email),
            'email_verified_at' => $this->when($isOwnProfile, $this->resource->email_verified_at),
            'can_create_subs' => $this->when($isOwnProfile, $this->resource->can_create_subs),
            'karma_points' => $this->resource->karma_points,
            'highest_level_id' => $this->resource->highest_level_id,
            'is_verified_expert' => $this->resource->is_verified_expert,
            'is_deleted' => false,
            'created_at' => $this->resource->created_at->format('Y-m-d'),
            'tags' => $this->whenLoaded('tags'),
            'badge' => $this->resource->getBadge(),
            'posts_count' => $this->resource->posts_count ?? $this->resource->posts()->count(),
            'comments_count' => $this->resource->comments_count ?? $this->resource->comments()->count(),
            'votes_count' => $this->resource->votes_count ?? $this->resource->votes()->count(),
            'current_level' => $this->whenLoaded('currentLevel', function () {
                $currentLevel = $this->resource->currentLevel;

                // Check if user has enough karma for their displayed level
                $hasKarmaForLevel = $this->resource->karma_points >= $currentLevel->required_karma;

                // Find next level (based on highest level, not current karma)
                $nextLevel = KarmaLevel::where('required_karma', '>', $currentLevel->required_karma)
                    ->orderBy('required_karma', 'asc')
                    ->first();

                return [
                    'name' => __($currentLevel->name), // Translated based on user locale
                    'name_key' => $currentLevel->name, // Original translation key
                    'badge' => $currentLevel->badge,
                    'required_karma' => $currentLevel->required_karma,
                    'has_karma_for_level' => $hasKarmaForLevel,
                    'next_level' => $nextLevel ? [
                        'name' => __($nextLevel->name), // Translated
                        'name_key' => $nextLevel->name, // Original key
                        'required_karma' => $nextLevel->required_karma,
                        'badge' => $nextLevel->badge,
                    ] : null,
                ];
            }),
            // Federation/ActivityPub fields (only if user has federation enabled and is indexable)
            'federation' => $this->when(
                $this->shouldShowFederationInfo(),
                fn () => $this->getFederationInfo(),
            ),
        ];
    }

    /**
     * Check if federation info should be shown for this user.
     */
    private function shouldShowFederationInfo(): bool
    {
        $settings = $this->resource->activityPubSettings;

        return $settings !== null
            && $settings->federation_enabled
            && $settings->indexable;
    }

    /**
     * Get federation info for the user.
     */
    private function getFederationInfo(): array
    {
        $settings = $this->resource->activityPubSettings;
        $domain = rtrim((string) config('activitypub.public_domain', config('activitypub.domain')), '/');
        $domain = preg_replace('#^https?://#', '', $domain);

        return [
            'handle' => '@' . $this->resource->username . '@' . $domain,
            'followers_count' => $settings->show_followers_count
                ? ($this->resource->activityPubActor?->followers_count ?? 0)
                : null,
        ];
    }
}
