<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\Notifications\Attributes\AttributeProcessor;
use MonkeysLegion\Notifications\Attributes\Notify;
use MonkeysLegion\Notifications\NotificationManager;

class AttributeProcessorTest extends TestCase
{
    public function test_it_can_scan_class_attributes()
    {
        $manager = $this->createStub(NotificationManager::class);
        $processor = new class($manager) extends AttributeProcessor {
            public bool $triggered = false;
            protected function triggerNotification(object $entity, Notify $notify, ?\ReflectionProperty $property = null): void
            {
                $this->triggered = true;
            }
        };

        $dummy = new #[Notify('SomeNotification')] class {};
        
        $processor->process($dummy);
        
        $this->assertTrue($processor->triggered);
    }

    public function test_it_can_scan_property_attributes()
    {
        $manager = $this->createStub(NotificationManager::class);
        $processor = new class($manager) extends AttributeProcessor {
            public bool $triggered = false;
            protected function triggerNotification(object $entity, Notify $notify, ?\ReflectionProperty $property = null): void
            {
                if ($property && $property->getName() === 'status') {
                    $this->triggered = true;
                }
            }
        };

        $dummy = new class {
            #[Notify('SomeNotification')]
            public string $status = 'paid';
        };
        
        $processor->process($dummy);
        
        $this->assertTrue($processor->triggered);
    }
}
