<?php

namespace MonkeysLegion\Notifications;

use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Attributes\AttributeProcessor;
use MonkeysLegion\Events\ListenerProvider;

class NotificationListener
{
    /**
     * Map of Event Class => Array of Notifications/Actions
     *
     * @var array<string, list<string|callable(object, NotificationManager): void>>
     */
    protected array $mapping = [];

    public function __construct(
        protected NotificationManager $manager,
        protected AttributeProcessor $processor,
        protected ListenerProvider $dispatcher
    ) {}

    /**
     * Register an event-to-notification mapping dynamically.
     *
     * @param string|list<string|callable(object, NotificationManager): void>|callable(object, NotificationManager): void $targets
     */
    public function listen(string $eventClass, string|array|callable $targets): void
    {
        $this->dispatcher->add($eventClass, [$this, 'handle']);
        if (is_array($targets)) {
            $cleanTargets = [];
            foreach ($targets as $target) {
                if (is_string($target) || is_callable($target)) {
                    $cleanTargets[] = $target;
                }
            }
            $this->mapping[$eventClass] = $cleanTargets;
            return;
        }

        $this->mapping[$eventClass] = [$targets];
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
        if (isset($event->entity) && is_object($event->entity)) {
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
        if (
            is_string($target)
            && class_exists($target)
            && is_subclass_of($target, \MonkeysLegion\Notifications\Contracts\NotificationInterface::class)
            && isset($event->entity)
            && $event->entity instanceof NotifiableInterface
        ) {
            $notification = new $target($event); 
            $this->manager->send($event->entity, $notification);
        }
    }
}
