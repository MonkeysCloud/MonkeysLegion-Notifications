<?php
declare(strict_types=1);

namespace MonkeysLegion\Notifications\Channels;

use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;

/**
 * MonKeysLegion Framework — Notifications Package
 *
 * Generic webhook notification channel with HMAC signing and retry.
 *
 * The notifiable should implement routeNotificationFor('webhook') to return
 * the webhook URL. The webhook secret is used for HMAC-SHA256 signing.
 *
 * Sends a POST request with JSON payload and the following headers:
 *   Content-Type: application/json
 *   X-Webhook-Signature: sha256=<hex hmac>
 *   X-Webhook-Event: <event name>
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class WebhookChannel implements ChannelInterface
{
    public function __construct(
        private readonly string $secret = '',
        private readonly int $timeout = 30,
        private readonly int $maxRetries = 3,
    ) {}

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $webhookUrl = $notifiable->routeNotificationFor('webhook');

        if (empty($webhookUrl)) {
            return;
        }

        $payload = json_encode(
            $notification->toArray($notifiable),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        $signature = $this->secret !== ''
            ? hash_hmac('sha256', $payload, $this->secret)
            : '';

        $eventName = $notification->toArray($notifiable)['event'] ?? 'notification';

        $this->dispatch($webhookUrl, $payload, $signature, $eventName);
    }

    private function dispatch(string $url, string $payload, string $signature, string $event): void
    {
        $headers = [
            'Content-Type: application/json',
            'X-Webhook-Event: ' . $event,
        ];

        if ($signature !== '') {
            $headers[] = 'X-Webhook-Signature: sha256=' . $signature;
        }

        $attempt = 0;
        $lastError = '';

        while ($attempt < $this->maxRetries) {
            $attempt++;
            $lastError = '';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                return; // Success
            }

            $lastError = $response !== false
                ? "HTTP {$httpCode}: {$response}"
                : "cURL error: {$error}";

            // Exponential backoff: 1s, 2s, 4s...
            if ($attempt < $this->maxRetries) {
                usleep(($attempt ** 2) * 1_000_000);
            }
        }

        throw new \RuntimeException("Webhook failed after {$attempt} attempts: {$lastError}");
    }
}
