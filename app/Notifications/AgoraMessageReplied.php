<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AgoraMessage;
use App\Models\User;
use App\Notifications\Concerns\HasWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class AgoraMessageReplied extends Notification
{
    use HasWebPush;

    use Queueable;

    public function __construct(
        public AgoraMessage $reply,
        public AgoraMessage $parentMessage,
        public User $replier,
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
        return 'agora_replies';
    }

    protected function getPushTitle(): string
    {
        return __('notifications.push.agora_reply_title');
    }

    protected function getPushBody(): string
    {
        $username = $this->reply->is_anonymous ? __('common.anonymous') : $this->replier->username;

        return __('notifications.push.agora_reply_body', ['user' => $username]);
    }

    protected function getPushUrl(): string
    {
        return '/agora/' . $this->parentMessage->root_id . '#agora-' . $this->reply->id;
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $locale = $notifiable->locale ?? 'es';
        $replyUrl = '/agora/' . $this->parentMessage->root_id . '#agora-' . $this->reply->id;
        $userUrl = '/u/' . $this->replier->username;

        $anonymousText = __('notifications.anonymous_user', [], $locale);
        $replierHtml = $this->reply->is_anonymous
            ? '<span class="text-gray-500 italic">' . $anonymousText . '</span>'
            : '<a href="' . $userUrl . '" class="text-primary hover:text-primary-dark dark:hover:text-primary-light font-medium hover:underline">@' . e($this->replier->username) . '</a>';

        $bodyHtml = $replierHtml . ' '
                  . __('notifications.agora_replied_to_message', [], $locale) . ':'
                  . '<div class="mt-2 p-3 bg-gray-100 dark:bg-neutral-700 rounded border-l-2 border-gray-400 dark:border-gray-500">'
                  . '<p class="italic text-sm text-gray-700 dark:text-gray-300">' . e(truncate_content($this->reply->content, 200)) . '</p>'
                  . '</div>'
                  . '<a href="' . $replyUrl . '" class="inline-flex items-center gap-1 text-xs text-primary hover:text-primary-dark dark:hover:text-primary-light font-medium hover:underline mt-2">' . __('common.view', [], $locale) . ' <i class="fas fa-arrow-right text-xs"></i></a>';

        return [
            'title' => __('notifications.agora_new_reply', [], $locale),
            'body' => $bodyHtml,
            'icon' => 'fas fa-reply text-blue-500',
            'type' => 'agora_reply',
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
