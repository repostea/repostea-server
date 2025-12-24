<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\Concerns\HasWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class PostCommented extends Notification
{
    use HasWebPush;

    use Queueable;

    public function __construct(
        public Comment $comment,
        public Post $post,
        public User $commenter,
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return $this->getChannelsWithWebPush($notifiable);
    }

    protected function getPushCategory(): string
    {
        return 'post_comments';
    }

    protected function getPushTitle(): string
    {
        return __('notifications.push.post_comment_title');
    }

    protected function getPushBody(): string
    {
        return __('notifications.push.post_comment_body', [
            'user' => $this->commenter->username,
            'post' => truncate_content($this->post->title, 40),
        ]);
    }

    protected function getPushUrl(): string
    {
        return '/posts/' . $this->post->slug . '#c-' . dechex($this->comment->id);
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $commentUrl = '/posts/' . $this->post->slug . '#c-' . dechex($this->comment->id);
        $userUrl = '/u/' . $this->commenter->username;
        $postUrl = '/posts/' . $this->post->slug;

        $bodyHtml = '<a href="' . $userUrl . '" class="text-primary hover:text-primary-dark dark:hover:text-primary-light font-medium hover:underline">@' . e($this->commenter->username) . '</a> '
                  . __('notifications.commented_on') . ' '
                  . '<a href="' . $postUrl . '" class="italic text-primary hover:text-primary-dark dark:hover:text-primary-light hover:underline">"' . e($this->post->title) . '"</a>:'
                  . '<div class="mt-2 p-3 bg-gray-100 dark:bg-neutral-700 rounded border-l-2 border-gray-400 dark:border-gray-500">'
                  . '<p class="italic text-sm text-gray-700 dark:text-gray-300">' . e(truncate_content($this->comment->content, 200)) . '</p>'
                  . '</div>'
                  . '<a href="' . $commentUrl . '" class="inline-flex items-center gap-1 text-xs text-primary hover:text-primary-dark dark:hover:text-primary-light font-medium hover:underline mt-2">' . __('notifications.view') . ' <i class="fas fa-arrow-right text-xs"></i></a>';

        return [
            'title' => __('notifications.new_comment'),
            'body' => $bodyHtml,
            'icon' => 'fas fa-comment text-blue-500',
            'type' => 'comment',
            'url' => $commentUrl,
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
