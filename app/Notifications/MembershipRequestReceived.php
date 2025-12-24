<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Sub;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class MembershipRequestReceived extends Notification
{
    use Queueable;

    public function __construct(
        public Sub $sub,
        public User $requester,
        public ?string $message = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $userUrl = '/u/' . $this->requester->username;
        $settingsUrl = '/s/' . $this->sub->name . '/settings';

        $bodyHtml = '<a href="' . $userUrl . '" class="text-primary hover:text-primary-dark dark:hover:text-primary-light font-medium hover:underline">@' . e($this->requester->username) . '</a> '
                  . 'ha solicitado unirse a tu comunidad '
                  . '<strong>s/' . e($this->sub->name) . '</strong>';

        if ($this->message) {
            $bodyHtml .= '<div class="mt-2 p-3 bg-gray-100 dark:bg-neutral-700 rounded border-l-2 border-gray-400 dark:border-gray-500">'
                      . '<p class="italic text-sm text-gray-700 dark:text-gray-300">' . e($this->truncate($this->message, 200)) . '</p>'
                      . '</div>';
        }

        $bodyHtml .= '<a href="' . $settingsUrl . '" class="inline-flex items-center gap-1 text-xs text-primary hover:text-primary-dark dark:hover:text-primary-light font-medium hover:underline mt-2">' . __('notifications.manage_requests') . ' <i class="fas fa-arrow-right text-xs"></i></a>';

        return [
            'title' => __('notifications.new_membership_request'),
            'body' => $bodyHtml,
            'icon' => 'fas fa-user-plus text-blue-500',
            'type' => 'membership_request',
            'sub_id' => $this->sub->id,
            'requester_id' => $this->requester->id,
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }
}
