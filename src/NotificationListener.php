<?php

namespace MonkeysLegion\Notifications;

use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Attributes\AttributeProcessor;

class NotificationListener
{
    /**
     * Map of Event Class => Array of Notifications/Actions
     */
    protected array $mapping = [];

    public function __construct(
        protected NotificationManager $manager,
        protected AttributeProcessor $processor
    ) {}

    /**
     * Register an event-to-notification mapping dynamically.
     */
    public function listen(string $eventClass, string|array|callable $targets): void
    {
        $this->mapping[$eventClass] = (array) $targets;
    }

    /**
     * The Entry Point called by the Event Dispatcher.
     */
    public function handle(object $event): void
    {
        $eventClass = get_class($event);

        // 1. Check if we have dynamic mappings for this specific event
        if (isset($this->mapping[$eventClass])) {
            foreach ($this->mapping[$eventClass] as $target) {
                $this->executeTarget($event, $target);
            }
        }

        // 2. Always check for Attribute-driven notifications (Global Logic)
        if (isset($event->entity)) {
            $this->processor->process($event->entity);
        }
    }

    protected function executeTarget(object $event, mixed $target): void
    {
        // If the target is a Closure, call it directly
        if (is_callable($target)) {
            $target($event, $this->manager);
            return;
        }

        // If it's a Notification class string and the event has an entity
        if (is_string($target) && isset($event->entity) && $event->entity instanceof NotifiableInterface) {
            $notification = new $target($event); 
            $this->manager->send($event->entity, $notification);
        }
    }
}