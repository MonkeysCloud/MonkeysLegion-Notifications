<?php

namespace MonkeysLegion\Notifications\Traits;

use MonkeysLegion\DI\Traits\ContainerAware;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\NotificationManager;

trait Notifiable
{
    use ContainerAware;

    /**
     * Send the given notification.
     */
    public function notify(NotificationInterface $notification): void
    {
        /** @var NotificationManager $manager */
        $manager = $this->resolve(NotificationManager::class);
        $manager->send($this, $notification);
    }

    /**
     * Get the notification routing information for the given channel.
     */
    public function routeNotificationFor(string $channel): mixed
    {
        if (method_exists($this, $method = 'routeNotificationFor'.ucfirst($channel))) {
            return $this->{$method}();
        }

        return match ($channel) {
            'mail' => $this->email ?? null,
            'database' => $this->id ?? null,
            default => null,
        };
    }
}
