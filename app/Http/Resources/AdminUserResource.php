<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\KarmaLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin User Resource - Contains all user data including private fields
 * Only to be used in admin contexts.
 */
final class AdminUserResource extends JsonResource
{
    /**
     * @param  Request  $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        // If user is deleted or soft-deleted, return anonymized data
        if ($this->resource->is_deleted || $this->resource->trashed()) {
            return [
                'id' => $this->resource->id, // Admin can see ID even for deleted users
                'username' => '[deleted]',
                'display_name' => '[deleted]',
                'bio' => null,
                'avatar' => null,
                'email' => $this->resource->email, // Admin can see email
                'email_verified_at' => $this->resource->email_verified_at,
                'karma_points' => 0,
                'highest_level_id' => null,
                'is_verified_expert' => false,
                'is_deleted' => true,
                'created_at' => null,
                'deleted_at' => $this->resource->deleted_at,
                'tags' => [],
                'badge' => null,
                'posts_count' => 0,
                'comments_count' => 0,
                'votes_count' => 0,
                'current_level' => null,
            ];
        }

        return [
            'id' => $this->resource->id,
            'username' => $this->resource->username,
            'display_name' => $this->resource->display_name,
            'bio' => $this->resource->bio,
            'avatar' => $this->resource->avatar,
            'email' => $this->resource->email,
            'email_verified_at' => $this->resource->email_verified_at,
            'karma_points' => $this->resource->karma_points,
            'highest_level_id' => $this->resource->highest_level_id,
            'is_verified_expert' => $this->resource->is_verified_expert,
            'is_deleted' => false,
            'created_at' => $this->resource->created_at->format('Y-m-d'),
            'updated_at' => $this->resource->updated_at?->format('Y-m-d H:i:s'),
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
        ];
    }
}
