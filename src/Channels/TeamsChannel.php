<?php
declare(strict_types=1);

namespace MonkeysLegion\Notifications\Channels;

use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Messages\TeamsMessage;

/**
 * MonKeysLegion Framework — Notifications Package
 *
 * Microsoft Teams notification channel — sends messages via Teams incoming webhooks.
 *
 * The notifiable should implement routeNotificationFor('teams') to return
 * the Teams webhook URL.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class TeamsChannel implements ChannelInterface
{
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $webhookUrl = $notifiable->routeNotificationFor('teams');

        if (empty($webhookUrl)) {
            return;
        }

        $message = $notification->toTeams($notifiable);

        if (!$message instanceof TeamsMessage) {
            $message = (new TeamsMessage())
                ->title('Notification')
                ->text($notification->toArray($notifiable)['message'] ?? '');
        }

        $this->dispatch($webhookUrl, $message->toJson());
    }

    private function dispatch(string $webhookUrl, string $payload): void
    {
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("Teams webhook failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("Teams webhook returned HTTP {$httpCode}: {$response}");
        }
    }
}
