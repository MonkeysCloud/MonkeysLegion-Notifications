<?php

namespace MonkeysLegion\Notifications;

use MonkeysLegion\Notifications\Channels\ChannelInterface;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Events\NotificationFailed;
use MonkeysLegion\Notifications\Events\NotificationSent;
use MonkeysLegion\Notifications\Jobs\SendNotificationJob;
use MonkeysLegion\Queue\Contracts\ShouldQueue;
use MonkeysLegion\Queue\Dispatcher\QueueDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

class NotificationManager
{
    /**
     * Store the factory functions here
     *
     * @var array<string, callable(): ChannelInterface>
     */
    protected array $customCreators = [];

    /**
     * The array of registered channels.
     *
     * @var array<string, ChannelInterface>
     */
    protected array $channels = [];

    public function __construct(
        protected ?EventDispatcherInterface $events = null,
        protected ?QueueDispatcher $queue = null
    ) {}

    /**
     * Send the given notification to the given notifiable.
     *
     * @param NotifiableInterface|array<int, NotifiableInterface> $notifiables
     */
    public function send(NotifiableInterface|array $notifiables, NotificationInterface $notification): void
    {
        $notifiables = is_array($notifiables) ? $notifiables : [$notifiables];
        foreach ($notifiables as $notifiable) {
            $channels = $notification->via($notifiable);

            if (empty($channels)) {
                continue;
            }

            if ($notification instanceof ShouldQueue && $this->queue) {
                // Background sending
                $this->queue($notifiable, $notification);
                continue;
            }

            foreach ($channels as $channel) {
                $this->sendToChannel($notifiable, $notification, $channel);
            }
        }
    }

    /**
     * Send the notification immediately.
     *
     * @param NotifiableInterface|array<int, NotifiableInterface> $notifiables
     */
    public function sendNow(NotifiableInterface|array $notifiables, NotificationInterface $notification): void
    {
        $notifiables = is_array($notifiables) ? $notifiables : [$notifiables];

        foreach ($notifiables as $notifiable) {
            $channels = $notification->via($notifiable);

            foreach ($channels as $channel) {
                $this->sendToChannel($notifiable, $notification, $channel);
            }
        }
    }

    /**
     * Send the notification to a specific channel.
     */
    protected function sendToChannel(NotifiableInterface $notifiable, NotificationInterface $notification, string $channelName): void
    {
        try {
            // 1. Try to get it from the local cache
            $channel = $this->channels[$channelName] ?? null;

            // 2. If not cached, resolve it from the Framework Container
            if (!$channel) {
                $channel = $this->resolveChannel($channelName);
            }
            $channel->send($notifiable, $notification);

            $this->events?->dispatch(new NotificationSent(
                $notifiable,
                $notification,
                $channelName,
                null
            ));
        } catch (Throwable $e) {
            $this->events?->dispatch(new NotificationFailed(
                $notifiable,
                $notification,
                $channelName,
                $e
            ));

            throw $e;
        }
    }

    /**
     * Queue the notification for background dispatch.
     */
    protected function queue(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        if ($this->queue === null) {
            return;
        }

        $this->queue->dispatch(new SendNotificationJob($notifiable, $notification));
    }

    /**
     * Resolve a channel instance from the container.
     */
    protected function resolveChannel(string $channelName): ChannelInterface
    {
        // 1. Check if we have a creator for this channel (e.g., 'mail')
        if (array_key_exists($channelName, $this->customCreators)) {
            $channel = ($this->customCreators[$channelName])();

            // Cache it so we don't recreate it for the next notification
            return $this->channels[$channelName] = $channel;
        }

        throw new \RuntimeException("Driver [{$channelName}] not supported.");
    }

    /**
     * Register a channel.
     */
    public function extend(string $channel, callable $customCreator): self
    {
        $this->customCreators[$channel] = $customCreator;
        return $this;
    }
}
