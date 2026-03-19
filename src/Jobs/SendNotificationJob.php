<?php

namespace MonkeysLegion\Notifications\Jobs;

use MonkeysLegion\DI\Traits\ContainerAware;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\NotificationManager;
use MonkeysLegion\Queue\Contracts\ShouldQueue;
use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;

class SendNotificationJob implements ShouldQueue, DispatchableJobInterface
{
    use ContainerAware;
    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly NotifiableInterface $notifiable,
        public readonly NotificationInterface $notification
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $manager = $this->resolve(NotificationManager::class);
        $manager->sendNow($this->notifiable, $this->notification);
    }
}
