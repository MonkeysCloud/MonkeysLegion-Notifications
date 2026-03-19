<?php

namespace MonkeysLegion\Notifications\Channels;

use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Query\QueryBuilder;

class DatabaseChannel implements ChannelInterface
{
    /**
     * Create a new database channel instance.
     */
    public function __construct(
        protected QueryBuilder $query,
        protected string $table = 'notifications'
    ) {
    }

    /**
     * Send the given notification.
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $this->query->insert($this->table, [
            'id' => $this->generateUuid(),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => (string) $notifiable->routeNotificationFor('database'),
            'data' => json_encode($this->getData($notifiable, $notification)),
            'read_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get the data for the notification.
     *
     * @return array<string, mixed>
     */
    protected function getData(NotifiableInterface $notifiable, NotificationInterface $notification): array
    {
        if (method_exists($notification, 'toDatabase')) {
            return $notification->toDatabase($notifiable);
        }

        return $notification->toArray($notifiable);
    }

    /**
     * Generate a UUID v4.
     */
    protected function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
