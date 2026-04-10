<?php

namespace Tests\Unit;

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    fwrite(STDERR, "\n\n!!! FOUND IT !!!\n$errstr in $errfile on line $errline\n\n");
    return false; // Let PHPUnit continue its own handling
});


use MonkeysLegion\Events\ListenerProvider;
use MonkeysLegion\Mail\Mailer;
use MonkeysLegion\Notifications\Attributes\AttributeProcessor;
use MonkeysLegion\Notifications\Channels\ChannelInterface;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Events\NotificationFailed;
use MonkeysLegion\Notifications\Jobs\SendNotificationJob;
use MonkeysLegion\Notifications\NotificationListener;
use MonkeysLegion\Notifications\NotificationManager;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Database\SQLite\Connection as SqliteConnection;
use MonkeysLegion\DI\Container;
use MonkeysLegion\Mlc\Config;
use MonkeysLegion\Notifications\Providers\NotificationServiceProvider;
use MonkeysLegion\Queue\Dispatcher\QueueDispatcher;
use MonkeysLegion\Template\Contracts\CompilerInterface;
use MonkeysLegion\Template\Contracts\LoaderInterface;
use MonkeysLegion\Template\Contracts\ParserInterface;
use MonkeysLegion\Template\Renderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(NotificationManager::class)]
#[CoversClass(SendNotificationJob::class)]
#[CoversClass(NotificationListener::class)]
#[CoversClass(NotificationServiceProvider::class)]
#[CoversTrait(\MonkeysLegion\Notifications\Traits\Notifiable::class)]
final class NotificationManagerAndProviderCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        NotificationServiceProvider::$manager = null;
        NotificationServiceProvider::$processor = null;
        NotificationServiceProvider::$listenerProvider = null;
        NotificationServiceProvider::$listener = null;
    }

    #[Test]
    public function manager_send_now_and_array_notifiables_use_channel(): void
    {
        $notifiableA = new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed
            {
                return null;
            }
        };
        $notifiableB = new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed
            {
                return null;
            }
        };
        $notification = $this->createStub(NotificationInterface::class);
        $notification->method('via')->willReturn(['alpha']);
        $channel = $this->createMock(ChannelInterface::class);

        $channel->expects($this->exactly(2))->method('send');

        $manager = new NotificationManager();
        $manager->extend('alpha', fn() => $channel);
        $manager->sendNow([$notifiableA, $notifiableB], $notification);
    }

    #[Test]
    public function manager_skips_empty_channels_and_dispatches_failed_event_then_rethrows(): void
    {
        $notifiable = new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed
            {
                return null;
            }
        };
        $notification = $this->createStub(NotificationInterface::class);
        $notification->method('via')->willReturn(['broken']);

        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('send')->willThrowException(new \RuntimeException('fail'));

        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(NotificationFailed::class));

        $manager = new NotificationManager($events);
        $manager->extend('broken', fn() => $channel);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('fail');
        $manager->send($notifiable, $notification);
    }

    #[Test]
    public function manager_throws_for_unsupported_driver(): void
    {
        $notifiable = new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed
            {
                return null;
            }
        };
        $notification = $this->createStub(NotificationInterface::class);
        $notification->method('via')->willReturn(['missing']);

        $manager = new NotificationManager();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Driver [missing] not supported.');
        $manager->send($notifiable, $notification);
    }

    #[Test]
    public function send_notification_job_handles_success_and_failure_paths(): void
    {
        $manager = $this->createMock(NotificationManager::class);
        $manager->expects($this->once())->method('sendNow');

        $container = new \MonkeysLegion\DI\Container([
            NotificationManager::class => $manager,
        ]);
        \MonkeysLegion\DI\Container::setInstance($container);

        $notifiable = new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed
            {
                return null;
            }
        };
        $notification = $this->createStub(NotificationInterface::class);
        $job = new SendNotificationJob($notifiable, $notification);
        $job->handle();

        $failingManager = $this->createStub(NotificationManager::class);
        $failingManager->method('sendNow')->willThrowException(new \RuntimeException('x'));
        $logger = new class implements \MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface {
            public function smartLog(\Stringable|string $message, array $context = []): void {}
            public function emergency(\Stringable|string $message, array $context = []): void {}
            public function alert(\Stringable|string $message, array $context = []): void {}
            public function critical(\Stringable|string $message, array $context = []): void {}
            public function error(\Stringable|string $message, array $context = []): void {}
            public function warning(\Stringable|string $message, array $context = []): void {}
            public function notice(\Stringable|string $message, array $context = []): void {}
            public function info(\Stringable|string $message, array $context = []): void {}
            public function debug(\Stringable|string $message, array $context = []): void {}
            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $container = new \MonkeysLegion\DI\Container([
            NotificationManager::class => $failingManager,
            \MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface::class => $logger,
        ]);
        \MonkeysLegion\DI\Container::setInstance($container);
        $job->handle();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function notifiable_notify_and_default_route_unknown_channel(): void
    {
        $notification = $this->createStub(NotificationInterface::class);
        $manager = $this->createMock(NotificationManager::class);
        $manager->expects($this->once())->method('send');

        $container = new \MonkeysLegion\DI\Container([NotificationManager::class => $manager]);
        \MonkeysLegion\DI\Container::setInstance($container);

        $user = new class implements NotifiableInterface {
            use \MonkeysLegion\Notifications\Traits\Notifiable;
            public string $email = 'x@example.com';
        };

        $user->notify($notification);
        $this->assertNull($user->routeNotificationFor('sms'));
    }

    #[Test]
    public function listener_executes_callable_target_branch(): void
    {
        $manager = $this->createStub(NotificationManager::class);
        $processor = $this->createStub(AttributeProcessor::class);
        $dispatcher = new ListenerProvider();
        $listener = new NotificationListener($manager, $processor, $dispatcher);

        $called = false;
        $listener->listen(TestListenerEvent::class, function (object $event, NotificationManager $m) use (&$called, $manager): void {
            $called = true;
            TestCase::assertInstanceOf(TestListenerEvent::class, $event);
            TestCase::assertSame($manager, $m);
        });

        $listener->handle(new TestListenerEvent(new class {}));
        $this->assertTrue($called);
    }

    #[Test]
    public function service_provider_registers_extensions_with_default_and_custom_table(): void
    {
        Container::setInstance(new Container());
        $queue = $this->createStub(QueueDispatcher::class);
        $provider = new NotificationServiceProvider();

        $mailer = $this->getMockBuilder(Mailer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $events = $this->createStub(EventDispatcherInterface::class);

        $parser = $this->createStub(ParserInterface::class);
        $parser->method('parse')->willReturnCallback(fn(string $source): string => $source);
        $parser->method('extractComponentParams')->willReturn([]);
        $parser->method('removePropsDirectives')->willReturnCallback(fn(string $source): string => $source);
        $compiler = $this->createStub(CompilerInterface::class);
        $compiler->method('compile')->willReturnCallback(fn(string $source): string => $source);
        $base = $this->ensureTempBasePath();
        $viewFile = $base . '/views/provider_test.php';
        @mkdir(dirname($viewFile), 0755, true);
        file_put_contents($viewFile, 'Provider mail');
        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('getSourcePath')->willReturn($viewFile);
        $renderer = new Renderer($parser, $compiler, $loader, false, $base . '/cache');

        $conn = new SqliteConnection(['memory' => true]);
        $conn->connect();
        $conn->pdo()->exec(
            'CREATE TABLE notifications (
                id TEXT PRIMARY KEY,
                notifiable_type TEXT NOT NULL,
                notifiable_id TEXT NOT NULL,
                data TEXT NOT NULL,
                read_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $query = new QueryBuilder($conn);

        $config = new Config(['table' => 'notifications']);
        $provider->register($mailer, $renderer, $query, $events, $queue,  $config);
        $manager = NotificationServiceProvider::getManager();
        $this->assertInstanceOf(NotificationManager::class, $manager);
        $this->assertInstanceOf(AttributeProcessor::class, $provider->getAttributeProcessor());
        $this->assertInstanceOf(ListenerProvider::class, $provider->getListenerProvider());
        $this->assertInstanceOf(NotificationListener::class, $provider->getListener());

        $notifiable = new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed
            {
                return $channel === 'mail' ? 'provider@example.com' : 'id-1';
            }
        };

        $mailNotification = new class implements NotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return ['mail'];
            }
            public function toArray(NotifiableInterface $notifiable): array
            {
                return [];
            }
            public function toDatabase(NotifiableInterface $notifiable): array
            {
                return [];
            }
            public function toMail(NotifiableInterface $notifiable): mixed
            {
                return (new \MonkeysLegion\Notifications\Messages\MailMessage())
                    ->subject('From Provider')
                    ->view('provider.test');
            }
        };
        $mailer->expects($this->once())->method('send');
        $manager->send($notifiable, $mailNotification);

        $databaseNotification = new class implements NotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return ['database'];
            }
            public function toArray(NotifiableInterface $notifiable): array
            {
                return ['x' => 1];
            }
            public function toDatabase(NotifiableInterface $notifiable): array
            {
                return ['x' => 1];
            }
            public function toMail(NotifiableInterface $notifiable): mixed
            {
                return null;
            }
        };
        $manager->send($notifiable, $databaseNotification);

        $count = (int) $conn->pdo()->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    #[Test]
    public function service_provider_throws_when_manager_not_registred(): void {
        Container::setInstance(new Container());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('NotificationManager not registered yet.');
        NotificationServiceProvider::getManager();
    }

    #[Test]
    public function service_provider_throws_when_attribute_processor_not_registred(): void {
        Container::setInstance(new Container());
        $provider = new NotificationServiceProvider();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('AttributeProcessor not registered yet.');
        $provider->getAttributeProcessor();
    }

    #[Test]
    public function service_provider_throws_when_listener_provider_not_registred(): void {
        Container::setInstance(new Container());
        $provider = new NotificationServiceProvider();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('ListenerProvider not registered yet.');
        $provider->getListenerProvider();
    }

    #[Test]
    public function service_provider_throws_when_listener_not_registred(): void {
        Container::setInstance(new Container());
        $provider = new NotificationServiceProvider();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('NotificationListener not registered yet.');
        $provider->getListener();
    }

    

    private function ensureTempBasePath(): string
    {
        if (!defined('ML_BASE_PATH')) {
            define('ML_BASE_PATH', sys_get_temp_dir() . '/mln-tests-' . getmypid());
        }

        $base = ML_BASE_PATH;
        @mkdir($base, 0755, true);
        @mkdir($base . '/var', 0755, true);
        @mkdir($base . '/config', 0755, true);

        return $base;
    }
}

final class TestListenerEvent
{
    public function __construct(public object $entity) {}
}
