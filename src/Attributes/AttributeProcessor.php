<?php

namespace MonkeysLegion\Notifications\Attributes;

use MonkeysLegion\Notifications\NotificationManager;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

class AttributeProcessor
{
    public function __construct(
        protected NotificationManager $manager
    ) {
    }

    /**
     * Process attributes on a given object.
     */
    public function process(object $entity): void
    {
        $reflection = new ReflectionClass($entity);

        // Check Class-level Attributes
        $classAttributes = $reflection->getAttributes(Notify::class);
        foreach ($classAttributes as $attribute) {
            $this->triggerNotification($entity, $attribute->newInstance());
        }

        // Check Property-level Attributes
        foreach ($reflection->getProperties() as $property) {
            $propAttributes = $property->getAttributes(Notify::class);
            foreach ($propAttributes as $attribute) {
                // Only trigger if it's actually been initialized
                if ($property->isInitialized($entity)) {
                    $this->triggerNotification($entity, $attribute->newInstance(), $property);
                }
            }
        }
    }

    /**
     * Trigger the notification based on attribute settings.
     */
    protected function triggerNotification(object $entity, Notify $notify, ?ReflectionProperty $property = null): void
    {
        // 1. Safety check: Can this entity receive notifications?
        if (!$entity instanceof NotifiableInterface) {
            return;
        }

        // 2. Get the Notification class name from the Attribute
        $notificationClass = $notify->notification;

        if (!class_exists($notificationClass)) {
            throw new RuntimeException("Notification class [{$notificationClass}] not found.");
        }

        // 3. Instantiate the Notification
        // We pass the entity (and property value if it exists) to the constructor
        $notification = $property 
            ? new $notificationClass($property->getValue($entity))
            : new $notificationClass($entity);

        // 4. Send it!
        $this->manager->send($entity, $notification);
    }
}
