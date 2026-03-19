<?php

namespace MonkeysLegion\Notifications\Contracts;

interface NotifiableInterface
{
    /**
     * Get the notification routing information for the given channel.
     */
    public function routeNotificationFor(string $channel): mixed;
}
