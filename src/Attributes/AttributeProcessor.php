<?php

namespace MonkeysLegion\Notifications\Attributes;

use MonkeysLegion\Notifications\NotificationManager;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use ReflectionClass;
use RuntimeException;

class AttributeProcessor
{
    public function __construct(
        protected NotificationManager $manager
    ) {
    }

   /**
     * Process attributes on a given object, its properties, and its getters.
     */
    public function process(object $entity): void
    {
        $reflection = new ReflectionClass($entity);

        // 1. Check Class-level Attributes
        foreach ($reflection->getAttributes(Notify::class) as $attribute) {
            $this->triggerNotification($entity, $attribute->newInstance());
        }

        // 2. Check Property-level Attributes
        foreach ($reflection->getProperties() as $property) {
            if ($property->isInitialized($entity)) {
                foreach ($property->getAttributes(Notify::class) as $attribute) {
                    $this->triggerNotification($entity, $attribute->newInstance(), $property->getValue($entity));
                }
            }
        }

        // 3. Check Method-level Attributes (Getters)
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // Check if it's a getter (starts with get, is, or has) and has no required parameters
            if (preg_match('/^(get|is|has)/', $method->getName()) && $method->getNumberOfRequiredParameters() === 0) {
                $methodAttributes = $method->getAttributes(Notify::class);
                foreach ($methodAttributes as $attribute) {
                    // Invoke the getter to get the value for the notification
                    $value = $method->invoke($entity);
                    $this->triggerNotification($entity, $attribute->newInstance(), $value);
                }
            }
        }
    }

    /**
     * Trigger the notification based on attribute settings.
     */
    protected function triggerNotification(object $entity, Notify $notify, mixed $data = null): void
    {
        if (!$entity instanceof NotifiableInterface) {
            return;
        }

        $notificationClass = $notify->notification;

        if (!class_exists($notificationClass)) {
            throw new RuntimeException("Notification class [{$notificationClass}] not found.");
        }

        // If data was provided (from a property or getter), pass that. 
        // Otherwise, pass the whole entity (class-level).
        $notification = ($data !== null) 
            ? new $notificationClass($data)
            : new $notificationClass($entity);

        if (!$notification instanceof NotificationInterface) {
            throw new RuntimeException("Notification class [{$notificationClass}] must implement NotificationInterface.");
        }

        $this->manager->send($entity, $notification);
    }
}
