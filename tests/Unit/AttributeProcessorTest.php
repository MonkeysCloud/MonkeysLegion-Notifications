<?php

namespace Tests\Unit;

use MonkeysLegion\Notifications\Attributes\AttributeProcessor;
use MonkeysLegion\Notifications\Attributes\Notify;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\NotificationManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeProcessor::class)]
#[CoversClass(Notify::class)]
class DummyNotification implements NotificationInterface
{
    public function __construct(public mixed $data = null) {}
    public function via(NotifiableInterface $notifiable): array { return []; }
    public function toArray(NotifiableInterface $notifiable): array { return []; }
    public function toDatabase(NotifiableInterface $notifiable): array { return []; }
    public function toMail(NotifiableInterface $notifiable): mixed { return null; }
}

class ClassLevelEntity implements NotifiableInterface
{
    public function routeNotificationFor(string $channel): mixed { return null; }
}

#[Notify(DummyNotification::class)]
class AnnotatedClassEntity extends ClassLevelEntity {}

class PropertyLevelEntity extends ClassLevelEntity
{
    #[Notify(DummyNotification::class)]
    public string $status = 'paid';
}

class GetterLevelEntity extends ClassLevelEntity
{
    #[Notify(DummyNotification::class)]
    public function getCalculatedTotal(): float { return 150.75; }
}

#[Notify('Tests\\Unit\\MissingNotificationClass')]
class MissingNotificationEntity extends ClassLevelEntity {}

#[Notify(DummyNotification::class)]
class NonNotifiableAnnotatedEntity
{
}

class AttributeProcessorTest extends TestCase
{
    #[Test]
    public function it_can_scan_class_attributes()
    {
        $manager = $this->createStub(NotificationManager::class);
        $processor = new class($manager) extends AttributeProcessor {
            public bool $triggered = false;
            protected function triggerNotification(object $entity, Notify $notify, mixed $data = null): void
            {
                $this->triggered = true;
            }
        };

        $dummy = new AnnotatedClassEntity();
        $processor->process($dummy);
        $this->assertTrue($processor->triggered);
    }

    #[Test]
    public function it_can_scan_property_attributes()
    {
        $manager = $this->createStub(NotificationManager::class);
        $processor = new class($manager) extends AttributeProcessor {
            public mixed $passedData = null;
            protected function triggerNotification(object $entity, Notify $notify, mixed $data = null): void
            {
                $this->passedData = $data;
            }
        };

        $dummy = new PropertyLevelEntity();
        $processor->process($dummy);
        $this->assertEquals('paid', $processor->passedData);
    }

    #[Test]
    public function it_can_scan_getter_attributes()
    {
        $manager = $this->createStub(NotificationManager::class);
        $processor = new class($manager) extends AttributeProcessor {
            public mixed $passedData = null;
            protected function triggerNotification(object $entity, Notify $notify, mixed $data = null): void
            {
                $this->passedData = $data;
            }
        };

        $dummy = new GetterLevelEntity();
        $processor->process($dummy);
        $this->assertEquals(150.75, $processor->passedData);
    }

    #[Test]
    public function it_throws_when_notification_class_is_missing(): void
    {
        $manager = $this->createStub(NotificationManager::class);
        $processor = new AttributeProcessor($manager);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Notification class [Tests\\Unit\\MissingNotificationClass] not found.');

        $processor->process(new MissingNotificationEntity());
    }

    #[Test]
    public function it_sends_real_notification_for_notifiable_entity(): void
    {
        $manager = $this->createMock(NotificationManager::class);
        $processor = new AttributeProcessor($manager);
        $entity = new AnnotatedClassEntity();

        $manager->expects($this->once())
            ->method('send')
            ->with($entity, $this->isInstanceOf(DummyNotification::class));

        $processor->process($entity);
    }

    #[Test]
    public function it_ignores_annotated_entities_that_are_not_notifiable(): void
    {
        $manager = $this->createMock(NotificationManager::class);
        $processor = new AttributeProcessor($manager);

        $manager->expects($this->never())->method('send');

        $processor->process(new NonNotifiableAnnotatedEntity());
        $this->addToAssertionCount(1);
    }
}
