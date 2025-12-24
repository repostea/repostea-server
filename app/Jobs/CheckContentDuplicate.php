<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Comment;
use App\Models\Post;
use App\Services\DuplicateContentDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class CheckContentDuplicate implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public function __construct(
        private string $contentType,
        private int $contentId,
        private int $userId,
    ) {}

    public function handle(DuplicateContentDetector $detector): void
    {
        if ($this->contentType === 'post') {
            $this->checkPost($detector);
        } elseif ($this->contentType === 'comment') {
            $this->checkComment($detector);
        }
    }

    private function checkPost(DuplicateContentDetector $detector): void
    {
        $post = Post::find($this->contentId);

        if (! $post) {
            return;
        }

        $duplicateCheck = $detector->checkDuplicatePost(
            userId: $this->userId,
            title: $post->title,
            content: $post->content,
            hoursToCheck: 24,
            excludePostId: $this->contentId,
        );

        if ($duplicateCheck['is_duplicate']) {
            Log::warning('Duplicate post detected asynchronously', [
                'post_id' => $this->contentId,
                'user_id' => $this->userId,
                'similarity' => $duplicateCheck['similarity'],
                'duplicate_post_id' => $duplicateCheck['duplicate_post']->id,
            ]);

            // Save detection to database
            \App\Models\SpamDetection::create([
                'user_id' => $this->userId,
                'content_type' => 'post',
                'content_id' => $this->contentId,
                'detection_type' => 'duplicate',
                'similarity' => $duplicateCheck['similarity'],
                'metadata' => [
                    'duplicate_of_id' => $duplicateCheck['duplicate_post']->id,
                    'duplicate_of_title' => $duplicateCheck['duplicate_post']->title,
                ],
            ]);
        }
    }

    private function checkComment(DuplicateContentDetector $detector): void
    {
        $comment = Comment::find($this->contentId);

        if (! $comment) {
            return;
        }

        $duplicateCheck = $detector->checkDuplicateComment(
            userId: $this->userId,
            content: $comment->content,
            hoursToCheck: 24,
            excludeCommentId: $this->contentId,
        );

        if ($duplicateCheck['is_duplicate']) {
            Log::warning('Duplicate comment detected asynchronously', [
                'comment_id' => $this->contentId,
                'user_id' => $this->userId,
                'similarity' => $duplicateCheck['similarity'],
            ]);

            // Save detection to database
            \App\Models\SpamDetection::create([
                'user_id' => $this->userId,
                'content_type' => 'comment',
                'content_id' => $this->contentId,
                'detection_type' => 'duplicate',
                'similarity' => $duplicateCheck['similarity'],
                'metadata' => [
                    'duplicate_of_id' => $duplicateCheck['duplicate_comment']->id,
                    'duplicate_of_content' => substr($duplicateCheck['duplicate_comment']->content, 0, 100),
                ],
            ]);
        }
    }
}
