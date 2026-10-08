<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * In-app (database) notifications. Each one stores a small payload:
 * kind, title, message and the subject it links to ({type, id}) so the SPA can route to it.
 */
abstract class HireFlowNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{kind:string, title:string, message:string, subject:array{type:string, id:int}|null} */
    abstract public function toArray(object $notifiable): array;
}
