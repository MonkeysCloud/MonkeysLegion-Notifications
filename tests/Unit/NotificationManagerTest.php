<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\Notifications\NotificationManager;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Channels\ChannelInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use MonkeysLegion\Queue\Dispatcher\QueueDispatcher;
use MonkeysLegion\Queue\Contracts\ShouldQueue;

class NotificationManagerTest extends TestCase
{
    public function test_it_can_send_a_notification_to_a_channel()
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

    public function test_it_dispatches_events_after_sending()
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

    public function test_it_queues_notifications_that_implement_should_queue()
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
