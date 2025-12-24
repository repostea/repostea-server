<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Models\User;
use InvalidArgumentException;

/**
 * Service for handling comment moderation operations.
 */
final class CommentModerationService
{
    /**
     * Valid moderation actions.
     */
    public const VALID_ACTIONS = ['hide', 'unhide', 'delete', 'restore'];

    /**
     * Hide a comment.
     *
     * @return array{success: bool, message: string, comment?: Comment}
     */
    public function hide(Comment $comment, User $moderator, ?string $reason = null): array
    {
        $comment->update([
            'status' => 'hidden',
            'moderation_reason' => $reason,
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => __('messages.comments.hidden'),
            'comment' => $comment->fresh(),
        ];
    }

    /**
     * Unhide a comment.
     *
     * @return array{success: bool, message: string, comment?: Comment}
     */
    public function unhide(Comment $comment): array
    {
        $comment->update([
            'status' => 'published',
            'moderation_reason' => null,
            'moderated_by' => null,
            'moderated_at' => null,
        ]);

        return [
            'success' => true,
            'message' => __('messages.comments.unhidden'),
            'comment' => $comment->fresh(),
        ];
    }

    /**
     * Delete a comment by moderator (marks as deleted, clears content).
     *
     * @return array{success: bool, message: string, comment?: Comment}
     */
    public function deleteByModerator(Comment $comment, User $moderator, ?string $reason = null): array
    {
        $comment->update([
            'status' => 'deleted_by_moderator',
            'content' => '[deleted by moderator]',
            'moderation_reason' => $reason,
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => __('messages.comments.deleted_by_moderator'),
            'comment' => $comment->fresh(),
        ];
    }

    /**
     * Restore a moderated comment.
     *
     * @return array{success: bool, message: string, comment?: Comment}
     */
    public function restore(Comment $comment): array
    {
        // Only allow restore for hidden or deleted_by_moderator
        if (! in_array($comment->status, ['hidden', 'deleted_by_moderator'], true)) {
            return [
                'success' => false,
                'message' => __('messages.comments.cannot_restore'),
            ];
        }

        $comment->update([
            'status' => 'published',
            'moderation_reason' => null,
            'moderated_by' => null,
            'moderated_at' => null,
        ]);

        return [
            'success' => true,
            'message' => __('messages.comments.restored'),
            'comment' => $comment->fresh(),
        ];
    }

    /**
     * Execute a moderation action.
     *
     * @return array{success: bool, message: string, comment?: Comment}
     */
    public function moderate(Comment $comment, string $action, User $moderator, ?string $reason = null): array
    {
        if (! in_array($action, self::VALID_ACTIONS, true)) {
            throw new InvalidArgumentException("Invalid moderation action: {$action}");
        }

        return match ($action) {
            'hide' => $this->hide($comment, $moderator, $reason),
            'unhide' => $this->unhide($comment),
            'delete' => $this->deleteByModerator($comment, $moderator, $reason),
            'restore' => $this->restore($comment),
        };
    }

    /**
     * Check if a comment is moderated (hidden or deleted by moderator).
     */
    public function isModerated(Comment $comment): bool
    {
        return in_array($comment->status, ['hidden', 'deleted_by_moderator'], true);
    }

    /**
     * Check if a comment can be restored.
     */
    public function canRestore(Comment $comment): bool
    {
        return in_array($comment->status, ['hidden', 'deleted_by_moderator'], true);
    }

    /**
     * Get moderation info for a comment.
     *
     * @return array{is_moderated: bool, status: string, reason: ?string, moderated_by: ?int, moderated_at: ?string}
     */
    public function getModerationInfo(Comment $comment): array
    {
        return [
            'is_moderated' => $this->isModerated($comment),
            'status' => $comment->status,
            'reason' => $comment->moderation_reason,
            'moderated_by' => $comment->moderated_by,
            'moderated_at' => $comment->moderated_at?->toIso8601String(),
        ];
    }
}
