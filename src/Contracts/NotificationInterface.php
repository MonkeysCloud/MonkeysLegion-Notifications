<?php

namespace MonkeysLegion\Notifications\Contracts;

interface NotificationInterface
{
    /**
     * Get the channels the notification should be sent on.
     *
     * @return array<int, string>
     */
    public function via(NotifiableInterface $notifiable): array;

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(NotifiableInterface $notifiable): array;

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(NotifiableInterface $notifiable): array;

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(NotifiableInterface $notifiable): mixed;
}
