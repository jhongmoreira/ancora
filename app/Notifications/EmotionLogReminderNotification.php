<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class EmotionLogReminderNotification extends Notification
{
    public function __construct(protected Reminder $reminder) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Âncora')
            ->body($this->reminder->label ?: 'Hora de fazer um registro emocional.')
            ->data(['url' => '/registros/novo']);
    }
}
