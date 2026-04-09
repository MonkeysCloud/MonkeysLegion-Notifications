<?php

namespace Tests\Unit;

use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Traits\Notifiable;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversTrait(Notifiable::class)]
class NotifiableTraitTest extends TestCase
{
    #[Test]
    public function it_can_route_notification_for_mail()
    {
        $user = new class {
            use Notifiable;
            public string $email = 'test@example.com';
        };

        $this->assertEquals('test@example.com', $user->routeNotificationFor('mail'));
    }

    #[Test]
    public function it_can_use_custom_routing_method()
    {
        $user = new class {
            use Notifiable;
            public function routeNotificationForMail()
            {
                return 'custom@example.com';
            }
        };

        $this->assertEquals('custom@example.com', $user->routeNotificationFor('mail'));
    }

    #[Test]
    public function it_handles_database_routing_by_id()
    {
        $user = new class {
            use Notifiable;
            public string $id = 'uuid-123';
        };

        $this->assertEquals('uuid-123', $user->routeNotificationFor('database'));
    }
}
