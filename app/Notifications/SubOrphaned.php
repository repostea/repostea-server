<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Sub;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class SubOrphaned extends Notification
{
    use Queueable;

    public function __construct(
        public Sub $sub,
        public User $formerOwner,
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
        $subUrl = '/s/' . $this->sub->name;

        $bodyHtml = 'El propietario <strong>@' . e($this->formerOwner->username) . '</strong> '
                  . 'ha abandonado la comunidad '
                  . '<a href="' . $subUrl . '" class="text-primary hover:text-primary-dark dark:hover:text-primary-light font-medium hover:underline">s/' . e($this->sub->name) . '</a>. '
                  . 'Como moderador, tienes prioridad para reclamar la propiedad.';

        $bodyHtml .= '<a href="' . $subUrl . '" class="inline-flex items-center gap-1 text-xs text-primary hover:text-primary-dark dark:hover:text-primary-light font-medium hover:underline mt-2">Reclamar propiedad <i class="fas fa-arrow-right text-xs"></i></a>';

        return [
            'title' => 'Comunidad sin propietario',
            'body' => $bodyHtml,
            'icon' => 'fas fa-crown text-yellow-500',
            'type' => 'sub_orphaned',
            'sub_id' => $this->sub->id,
            'former_owner_id' => $this->formerOwner->id,
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
