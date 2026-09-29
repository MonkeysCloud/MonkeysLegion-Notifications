<?php
declare(strict_types=1);

namespace MonkeysLegion\Notifications\Channels;

use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Messages\SlackMessage;

/**
 * MonKeysLegion Framework — Notifications Package
 *
 * Slack notification channel — sends messages via Slack incoming webhooks.
 *
 * The notifiable should implement routeNotificationFor('slack') to return
 * the Slack webhook URL.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class SlackChannel implements ChannelInterface
{
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $webhookUrl = $notifiable->routeNotificationFor('slack');

        if (empty($webhookUrl)) {
            return; // No Slack webhook configured
        }

        $message = $notification->toSlack($notifiable);

        if (!$message instanceof SlackMessage) {
            // Fall back to simple text
            $message = (new SlackMessage())
                ->text($notification->toArray($notifiable)['message'] ?? 'Notification');
        }

        $this->dispatch($webhookUrl, $message->toJson());
    }

    /**
     * Send the payload to the Slack webhook URL.
     */
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
            throw new \RuntimeException("Slack webhook failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("Slack webhook returned HTTP {$httpCode}: {$response}");
        }
    }
}
