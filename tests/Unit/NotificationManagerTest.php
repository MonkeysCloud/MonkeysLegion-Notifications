<?php

namespace Tests\Unit;

use MonkeysLegion\Notifications\Channels\ChannelInterface;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\NotificationManager;
use MonkeysLegion\Queue\Contracts\ShouldQueue;
use MonkeysLegion\Queue\Dispatcher\QueueDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(NotificationManager::class)]
class NotificationManagerTest extends TestCase
{
    #[Test]
    public function it_can_send_a_notification_to_a_channel()
    {
        $notifiable = $this->createStub(NotifiableInterface::class);
        $notification = $this->createStub(NotificationInterface::class);
        $channel = $this->createMock(ChannelInterface::class);

        $notification->method('via')->willReturn(['test-channel']);
        
        $manager = new NotificationManager();
        $manager->extend('test-channel', fn() => $channel);

        $channel->expects($this->once())
            ->method('send')
            ->with($notifiable, $notification);

        $manager->send($notifiable, $notification);
    }

    #[Test]
    public function it_dispatches_events_after_sending()
    {
        $notifiable = $this->createStub(NotifiableInterface::class);
        $notification = $this->createStub(NotificationInterface::class);
        $channel = $this->createStub(ChannelInterface::class);
        $events = $this->createMock(EventDispatcherInterface::class);

        $notification->method('via')->willReturn(['test-channel']);
        
        $manager = new NotificationManager($events);
        $manager->extend('test-channel', fn() => $channel);

        $events->expects($this->once())
            ->method('dispatch');

        $manager->send($notifiable, $notification);
    }

    #[Test]
    public function it_queues_notifications_that_implement_should_queue()
    {
        $notifiable = $this->createStub(NotifiableInterface::class);
        $notification = $this->createStub(QueuedNotification::class);
        $queue = $this->createMock(QueueDispatcher::class);

        $notification->method('via')->willReturn(['test-channel']);
        
        $manager = new NotificationManager(null, $queue);
        
        $queue->expects($this->once())
            ->method('dispatch');

        $manager->send($notifiable, $notification);
    }
}

interface QueuedNotification extends NotificationInterface, ShouldQueue {}
