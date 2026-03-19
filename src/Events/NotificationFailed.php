<?php

namespace MonkeysLegion\Notifications\Events;

use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use Throwable;

class NotificationFailed
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly NotifiableInterface $notifiable,
        public readonly NotificationInterface $notification,
        public readonly string $channel,
        public readonly ?Throwable $exception = null
    ) {
    }
}
