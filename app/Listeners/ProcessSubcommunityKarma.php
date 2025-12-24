<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SubCommentCreated;
use App\Events\SubContentUpvoted;
use App\Events\SubMemberJoined;
use App\Events\SubPostCreated;
use App\Models\Comment;
use App\Models\Post;
use App\Services\SubcommunityKarmaService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class ProcessSubcommunityKarma implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private SubcommunityKarmaService $karmaService,
    ) {}

    /**
     * Handle new member event in subcommunity.
     */
    public function handleMemberJoined(SubMemberJoined $event): void
    {
        try {
            $this->karmaService->awardKarmaForNewMember($event->sub);
        } catch (Exception $e) {
            Log::error('Error awarding karma for new sub member', [
                'sub_id' => $event->sub->id,
                'user_id' => $event->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle new post event in subcommunity.
     */
    public function handlePostCreated(SubPostCreated $event): void
    {
        try {
            $this->karmaService->awardKarmaForNewPost($event->sub);
        } catch (Exception $e) {
            Log::error('Error awarding karma for new sub post', [
                'sub_id' => $event->sub->id,
                'post_id' => $event->post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle new comment event in subcommunity.
     */
    public function handleCommentCreated(SubCommentCreated $event): void
    {
        try {
            $this->karmaService->awardKarmaForNewComment($event->sub);
        } catch (Exception $e) {
            Log::error('Error awarding karma for new sub comment', [
                'sub_id' => $event->sub->id,
                'comment_id' => $event->comment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle content upvote event in subcommunity.
     */
    public function handleContentUpvoted(SubContentUpvoted $event): void
    {
        try {
            if ($event->content instanceof Post) {
                $this->karmaService->awardKarmaForPostUpvote($event->sub);
            } elseif ($event->content instanceof Comment) {
                $this->karmaService->awardKarmaForCommentUpvote($event->sub);
            }
        } catch (Exception $e) {
            Log::error('Error awarding karma for sub content upvote', [
                'sub_id' => $event->sub->id,
                'content_type' => get_class($event->content),
                'content_id' => $event->content->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register the listeners for the events.
     */
    public function subscribe($events): array
    {
        return [
            SubMemberJoined::class => 'handleMemberJoined',
            SubPostCreated::class => 'handlePostCreated',
            SubCommentCreated::class => 'handleCommentCreated',
            SubContentUpvoted::class => 'handleContentUpvoted',
        ];
    }
}
