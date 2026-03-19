<?php

namespace MonkeysLegion\Notifications\Events;

use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;

class NotificationSent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly NotifiableInterface $notifiable,
        public readonly NotificationInterface $notification,
        public readonly string $channel,
        public readonly mixed $response = null
    ) {
    }
}
