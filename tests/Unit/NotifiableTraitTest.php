<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\Notifications\Traits\Notifiable;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;

class NotifiableTraitTest extends TestCase
{
    public function test_it_can_route_notification_for_mail()
    {
        $user = new class {
            use Notifiable;
            public string $email = 'test@example.com';
        };

        $this->assertEquals('test@example.com', $user->routeNotificationFor('mail'));
    }

    public function test_it_can_use_custom_routing_method()
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

    public function test_it_handles_database_routing_by_id()
    {
        $user = new class {
            use Notifiable;
            public string $id = 'uuid-123';
        };

        $this->assertEquals('uuid-123', $user->routeNotificationFor('database'));
    }
}
