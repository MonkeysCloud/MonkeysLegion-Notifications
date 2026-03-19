<?php

namespace MonkeysLegion\Notifications\Channels;

use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;

interface ChannelInterface
{
    /**
     * Send the given notification.
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void;
}
