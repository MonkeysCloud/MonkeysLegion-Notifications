<?php

namespace Tests\Unit;

use MonkeysLegion\Notifications\Attributes\AttributeProcessor;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\NotificationListener;
use MonkeysLegion\Notifications\NotificationManager;
use MonkeysLegion\Events\ListenerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationListener::class)]
class NotificationListenerTest extends TestCase
{
    #[Test]
    public function it_registers_listener_on_listen()
    {
        $manager = $this->createStub(NotificationManager::class);
        $processor = $this->createStub(AttributeProcessor::class);
        $dispatcher = new ListenerProvider(); // Use real object as it is final

        $listener = new NotificationListener($manager, $processor, $dispatcher);
        $listener->listen('SomeEvent', ['SomeNotification']);

        $listeners = iterator_to_array($dispatcher->getListenersForEvent(new class('SomeEvent') {
            public function __construct(public string $class) {}
        }));

        $this->assertCount(0, $listeners); // It expects exact match by default in getListenersForEvent

        // Actually, getListenersForEvent(object $event) uses $event::class;
        $event = new class {}; // This will be class@anonymous...
        
        // Let's test with a named event class
        $event = new TestEvent(new class implements NotifiableInterface {
             public function routeNotificationFor(string $channel): mixed { return null; }
        });
        
        $listener->listen(TestEvent::class, ['SomeNotification']);
        $listeners = iterator_to_array($dispatcher->getListenersForEvent($event));
        
        $this->assertCount(1, $listeners);
        $this->assertEquals([$listener, 'handle'], $listeners[0]);
    }

    #[Test]
    public function it_sends_notification_when_event_is_handled()
    {
        $manager = $this->createMock(NotificationManager::class);
        $processor = $this->createStub(AttributeProcessor::class);
        $dispatcher = new ListenerProvider();
        $entity = $this->createStub(NotifiableInterface::class);

        $listener = new NotificationListener($manager, $processor, $dispatcher);
        $listener->listen(TestEvent::class, [DummyNotificationInListener::class]);

        $event = new TestEvent($entity);

        $manager->expects($this->once())
            ->method('send')
            ->with($entity, $this->isInstanceOf(DummyNotificationInListener::class));

        $listener->handle($event);
    }

    #[Test]
    public function it_calls_processor_when_entity_is_present_in_event()
    {
        $manager = $this->createStub(NotificationManager::class);
        $processor = $this->createMock(AttributeProcessor::class);
        $dispatcher = new ListenerProvider();
        $entity = $this->createStub(NotifiableInterface::class);

        $listener = new NotificationListener($manager, $processor, $dispatcher);

        $event = new TestEvent($entity);

        $processor->expects($this->once())
            ->method('process')
            ->with($entity);

        $listener->handle($event);
    }
}

class TestEvent 
{
    public function __construct(public object $entity) {}
}

class DummyNotificationInListener implements NotificationInterface
{
    public function __construct(public mixed $data = null) {}
    public function via(NotifiableInterface $notifiable): array { return []; }
    public function toArray(NotifiableInterface $notifiable): array { return []; }
    public function toDatabase(NotifiableInterface $notifiable): array { return []; }
    public function toMail(NotifiableInterface $notifiable): mixed { return null; }
}
