<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AgoraMessage;

final class AgoraMessageObserver
{
    /**
     * Handle the AgoraMessage "created" event.
     */
    public function created(AgoraMessage $message): void
    {
        // Set root_id for new messages
        if ($message->parent_id) {
            // For replies, get root_id from parent
            $parent = AgoraMessage::find($message->parent_id);
            if ($parent) {
                $message->root_id = $parent->root_id ?? $parent->id;
                $message->saveQuietly();

                // Update parent's direct replies count
                $parent->updateRepliesCount();

                // Increment total_replies_count for all ancestors
                $message->incrementAncestorsTotalRepliesCount();
            }
        } else {
            // For top-level messages, root_id = self
            $message->root_id = $message->id;
            $message->saveQuietly();
        }
    }

    /**
     * Handle the AgoraMessage "deleting" event (before soft delete).
     */
    public function deleting(AgoraMessage $message): void
    {
        // Calculate how many total replies will be removed
        $totalToRemove = ($message->total_replies_count ?? 0) + 1;

        // Update parent's direct replies count and ancestors' total count
        if ($message->parent_id) {
            $parent = AgoraMessage::find($message->parent_id);
            if ($parent) {
                // Decrement total_replies_count for all ancestors
                $message->decrementAncestorsTotalRepliesCount($totalToRemove);
            }
        }
    }

    /**
     * Handle the AgoraMessage "deleted" event (after soft delete).
     */
    public function deleted(AgoraMessage $message): void
    {
        // Update parent's direct replies count after deletion
        if ($message->parent_id) {
            $parent = AgoraMessage::withTrashed()->find($message->parent_id);
            $parent?->updateRepliesCount();
        }
    }

    /**
     * Handle the AgoraMessage "restored" event.
     */
    public function restored(AgoraMessage $message): void
    {
        if ($message->parent_id) {
            $parent = AgoraMessage::find($message->parent_id);
            if ($parent) {
                // Restore direct replies count
                $parent->updateRepliesCount();

                // Restore total_replies_count for ancestors
                $totalToRestore = ($message->total_replies_count ?? 0) + 1;
                $this->incrementAncestorsTotalRepliesCountBy($message, $totalToRestore);
            }
        }
    }

    /**
     * Increment ancestors' total_replies_count by a specific amount.
     */
    private function incrementAncestorsTotalRepliesCountBy(AgoraMessage $message, int $amount): void
    {
        if ($message->parent_id) {
            $parent = AgoraMessage::find($message->parent_id);
            if ($parent) {
                $parent->total_replies_count = ($parent->total_replies_count ?? 0) + $amount;
                $parent->saveQuietly();
                $this->incrementAncestorsTotalRepliesCountBy($parent, $amount);
            }
        }
    }
}
