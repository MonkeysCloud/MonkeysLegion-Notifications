<?php

namespace MonkeysLegion\Notifications\Providers;

use MonkeysLegion\Core\Attributes\Provider;
use MonkeysLegion\DI\Traits\ContainerAware;
use MonkeysLegion\Events\ListenerProvider;
use MonkeysLegion\Mail\Mailer;
use MonkeysLegion\Mlc\Config;
use MonkeysLegion\Notifications\Attributes\AttributeProcessor;
use MonkeysLegion\Notifications\Channels\MailChannel;
use MonkeysLegion\Notifications\Channels\DatabaseChannel;
use MonkeysLegion\Notifications\NotificationListener;
use MonkeysLegion\Notifications\NotificationManager;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Queue\Contracts\QueueDispatcherInterface;
use MonkeysLegion\Template\Renderer;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Provider]
class NotificationServiceProvider
{
    use ContainerAware;

    public static ?NotificationManager $manager = null;
    public static ?AttributeProcessor $processor = null;
    public static ?ListenerProvider $listenerProvider = null;
    public static ?NotificationListener $listener = null;

    public function register(
        Mailer $mailer,
        Renderer $renderer,
        QueryBuilder $queryBuilder,
        ?EventDispatcherInterface $events = null,
        ?QueueDispatcherInterface $queue = null,
        ?Config $config = null
    ): void {
        $container = $this->container();

        // pre-register the NotificationManager so that it can be injected into the NotificationListener
        self::$manager = new NotificationManager(
            events: $events,
            queue: $queue,
        );
        // Make the manager available in the container for injection
        $container->set(NotificationManager::class, self::$manager);

        // Couple MailChannel with the Mailer service
        self::$manager->extend('mail', function () use ($mailer, $renderer) {
            return new MailChannel($mailer, $renderer);
        });

        // Couple DatabaseChannel with QueryBuilder and Config
        self::$manager->extend('database', function () use ($queryBuilder, $config) {
            $table = $config?->get('table', 'notifications') ?? 'notifications';
            return new DatabaseChannel(
                $queryBuilder,
                is_string($table) ? $table : 'notifications'
            );
        });

        // pre-register the AttributeProcessor so that it can be injected into the NotificationListener
        self::$processor = new AttributeProcessor(self::$manager);
        $container->set(AttributeProcessor::class, self::$processor);

        // pre-register the ListenerProvider so that it can be injected into the NotificationListener
        self::$listenerProvider = new ListenerProvider();
        $container->set(ListenerProvider::class, self::$listenerProvider);

        // Now we can create the NotificationListener with the pre-registered dependencies
        self::$listener = new NotificationListener(
            manager: self::$manager,
            processor: self::$processor,
            dispatcher: self::$listenerProvider
        );
        $container->set(NotificationListener::class, self::$listener);
    }

    public static function getManager(): NotificationManager
    {
        if (!self::$manager) {
            throw new \RuntimeException('NotificationManager not registered yet.');
        }
        return self::$manager;
    }

    public function getAttributeProcessor(): AttributeProcessor
    {
        if (!self::$processor) {
            throw new \RuntimeException('AttributeProcessor not registered yet.');
        }
        return self::$processor;
    }

    public function getListenerProvider(): ListenerProvider
    {
        if (!self::$listenerProvider) {
            throw new \RuntimeException('ListenerProvider not registered yet.');
        }
        return self::$listenerProvider;
    }

    public function getListener(): NotificationListener
    {
        if (!self::$listener) {
            throw new \RuntimeException('NotificationListener not registered yet.');
        }
        return self::$listener;
    }
}
