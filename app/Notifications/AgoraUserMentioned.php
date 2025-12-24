<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AgoraMessage;
use App\Models\User;
use App\Notifications\Concerns\HasWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class AgoraUserMentioned extends Notification
{
    use HasWebPush;

    use Queueable;

    public function __construct(
        public AgoraMessage $message,
        public User $mentioner,
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
        return 'agora_mentions';
    }

    protected function getPushTitle(): string
    {
        return __('notifications.push.agora_mention_title');
    }

    protected function getPushBody(): string
    {
        $username = $this->message->is_anonymous ? __('common.anonymous') : $this->mentioner->username;

        return __('notifications.push.agora_mention_body', ['user' => $username]);
    }

    protected function getPushUrl(): string
    {
        $rootId = $this->message->root_id ?? $this->message->id;

        return '/agora/' . $rootId . '#agora-' . $this->message->id;
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $locale = $notifiable->locale ?? 'es';
        $rootId = $this->message->root_id ?? $this->message->id;
        $messageUrl = '/agora/' . $rootId . '#agora-' . $this->message->id;
        $userUrl = '/u/' . $this->mentioner->username;

        $anonymousText = __('notifications.anonymous_user', [], $locale);
        $mentionerHtml = $this->message->is_anonymous
            ? '<span class="text-gray-500 italic">' . $anonymousText . '</span>'
            : '<a href="' . $userUrl . '" class="text-primary hover:text-primary-dark dark:hover:text-primary-light font-medium hover:underline">@' . e($this->mentioner->username) . '</a>';

        $bodyHtml = $mentionerHtml . ' '
                  . __('notifications.agora_mentioned_you', [], $locale) . ':'
                  . '<div class="mt-2 p-3 bg-gray-100 dark:bg-neutral-700 rounded border-l-2 border-gray-400 dark:border-gray-500">'
                  . '<p class="italic text-sm text-gray-700 dark:text-gray-300">' . e(truncate_content($this->message->content, 200)) . '</p>'
                  . '</div>'
                  . '<a href="' . $messageUrl . '" class="inline-flex items-center gap-1 text-xs text-primary hover:text-primary-dark dark:hover:text-primary-light font-medium hover:underline mt-2">' . __('common.view', [], $locale) . ' <i class="fas fa-arrow-right text-xs"></i></a>';

        return [
            'title' => __('notifications.agora_new_mention', [], $locale),
            'body' => $bodyHtml,
            'icon' => 'fas fa-at text-purple-500',
            'type' => 'agora_mention',
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
