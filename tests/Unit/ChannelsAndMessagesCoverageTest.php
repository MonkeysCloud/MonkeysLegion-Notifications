<?php

namespace Tests\Unit;

use MonkeysLegion\Database\SQLite\Connection as SqliteConnection;
use MonkeysLegion\Mail\Mailer;
use MonkeysLegion\Notifications\Channels\DatabaseChannel;
use MonkeysLegion\Notifications\Channels\MailChannel;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Events\NotificationFailed;
use MonkeysLegion\Notifications\Events\NotificationSent;
use MonkeysLegion\Notifications\Exceptions\CouldNotSendNotification;
use MonkeysLegion\Notifications\Messages\DatabaseMessage;
use MonkeysLegion\Notifications\Messages\MailMessage;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Template\Contracts\CompilerInterface;
use MonkeysLegion\Template\Contracts\LoaderInterface;
use MonkeysLegion\Template\Contracts\ParserInterface;
use MonkeysLegion\Template\Renderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseChannel::class)]
#[CoversClass(MailChannel::class)]
#[CoversClass(MailMessage::class)]
#[CoversClass(DatabaseMessage::class)]
#[CoversClass(NotificationFailed::class)]
#[CoversClass(NotificationSent::class)]
#[CoversClass(CouldNotSendNotification::class)]
final class ChannelsAndMessagesCoverageTest extends TestCase
{
    #[Test]
    public function database_channel_persists_notification_data(): void
    {
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
        $channel = new DatabaseChannel($query, 'notifications');

        $notifiable = new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed
            {
                return $channel === 'database' ? 'user-1' : null;
            }
        };

        $notification = new class implements NotificationInterface {
            public function via(NotifiableInterface $notifiable): array { return ['database']; }
            public function toArray(NotifiableInterface $notifiable): array { return ['fallback' => true]; }
            public function toDatabase(NotifiableInterface $notifiable): array { return ['status' => 'ok']; }
            public function toMail(NotifiableInterface $notifiable): mixed { return null; }
        };

        $channel->send($notifiable, $notification);

        $row = $conn->pdo()->query('SELECT notifiable_id, data FROM notifications LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('user-1', $row['notifiable_id']);
        $this->assertSame('{"status":"ok"}', $row['data']);
    }

    #[Test]
    public function mail_channel_sends_mail_message_with_rendered_view(): void
    {
        $base = $this->ensureTempBasePath();
        $viewFile = $base . '/views/mail_welcome.php';
        @mkdir(dirname($viewFile), 0755, true);
        file_put_contents($viewFile, 'Hello <?= $name ?>');

        $parser = $this->createStub(ParserInterface::class);
        $parser->method('parse')->willReturnCallback(fn(string $source): string => $source);
        $parser->method('extractComponentParams')->willReturn([]);
        $parser->method('removePropsDirectives')->willReturnCallback(fn(string $source): string => $source);

        $compiler = $this->createStub(CompilerInterface::class);
        $compiler->method('compile')->willReturnCallback(fn(string $source): string => $source);

        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('getSourcePath')->willReturn($viewFile);

        $renderer = new Renderer($parser, $compiler, $loader, false, $base . '/cache');

        $mailer = $this->getMockBuilder(Mailer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();

        $channel = new MailChannel($mailer, $renderer);

        $notifiable = new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed
            {
                return $channel === 'mail' ? 'user@example.com' : null;
            }
        };

        $notification = new class implements NotificationInterface {
            public function via(NotifiableInterface $notifiable): array { return ['mail']; }
            public function toArray(NotifiableInterface $notifiable): array { return []; }
            public function toDatabase(NotifiableInterface $notifiable): array { return []; }
            public function toMail(NotifiableInterface $notifiable): mixed
            {
                return (new MailMessage())
                    ->subject('Welcome')
                    ->view('mail.welcome', ['name' => 'Ada']);
            }
        };

        $mailer->expects($this->once())
            ->method('send')
            ->with(
                'user@example.com',
                'Welcome',
                $this->stringContains('Hello Ada'),
                'text/html',
                []
            );

        $channel->send($notifiable, $notification);
    }

    #[Test]
    public function mail_channel_supports_sendable_message_object(): void
    {
        $parser = $this->createStub(ParserInterface::class);
        $parser->method('parse')->willReturn('');
        $parser->method('extractComponentParams')->willReturn([]);
        $parser->method('removePropsDirectives')->willReturn('');
        $compiler = $this->createStub(CompilerInterface::class);
        $compiler->method('compile')->willReturn('');
        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('getSourcePath')->willReturn('/tmp/none');
        $renderer = new Renderer($parser, $compiler, $loader, false, sys_get_temp_dir() . '/mln-cache');

        $mailer = $this->getMockBuilder(Mailer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();

        $channel = new MailChannel($mailer, $renderer);
        $notifiable = new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed
            {
                return 'target@example.com';
            }
        };

        $sendable = new class {
            public ?string $to = null;
            public bool $sent = false;
            public function setTo(string $to): self { $this->to = $to; return $this; }
            public function send(): void { $this->sent = true; }
        };

        $notification = new class($sendable) implements NotificationInterface {
            public function __construct(private object $sendable) {}
            public function via(NotifiableInterface $notifiable): array { return ['mail']; }
            public function toArray(NotifiableInterface $notifiable): array { return []; }
            public function toDatabase(NotifiableInterface $notifiable): array { return []; }
            public function toMail(NotifiableInterface $notifiable): mixed { return $this->sendable; }
        };

        $mailer->expects($this->never())->method('send');
        $channel->send($notifiable, $notification);

        $this->assertSame('target@example.com', $sendable->to);
        $this->assertTrue($sendable->sent);
    }

    #[Test]
    public function mail_channel_renders_fallback_html_without_view(): void
    {
        $parser = $this->createStub(ParserInterface::class);
        $parser->method('parse')->willReturn('');
        $parser->method('extractComponentParams')->willReturn([]);
        $parser->method('removePropsDirectives')->willReturn('');
        $compiler = $this->createStub(CompilerInterface::class);
        $compiler->method('compile')->willReturn('');
        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('getSourcePath')->willReturn('/tmp/none');
        $renderer = new Renderer($parser, $compiler, $loader, false, sys_get_temp_dir() . '/mln-cache');

        $mailer = $this->getMockBuilder(Mailer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();

        $channel = new MailChannel($mailer, $renderer);
        $notifiable = new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed { return 'fallback@example.com'; }
        };

        $notification = new class implements NotificationInterface {
            public function via(NotifiableInterface $notifiable): array { return ['mail']; }
            public function toArray(NotifiableInterface $notifiable): array { return []; }
            public function toDatabase(NotifiableInterface $notifiable): array { return []; }
            public function toMail(NotifiableInterface $notifiable): mixed
            {
                return (new MailMessage())
                    ->subject('Fallback Subject')
                    ->line('Intro line')
                    ->action('Open', 'https://example.com/action')
                    ->outroLine('Outro line');
            }
        };

        $mailer->expects($this->once())
            ->method('send')
            ->with(
                'fallback@example.com',
                'Fallback Subject',
                $this->callback(function (string $html): bool {
                    return str_contains($html, '<h1>Fallback Subject</h1>')
                        && str_contains($html, '<p>Intro line</p>')
                        && str_contains($html, "href='https://example.com/action'")
                        && str_contains($html, '>Open<')
                        && str_contains($html, '<p>Outro line</p>');
                }),
                'text/html',
                []
            );

        $channel->send($notifiable, $notification);
    }

    #[Test]
    public function mail_channel_does_nothing_for_non_sendable_non_mail_message(): void
    {
        $parser = $this->createStub(ParserInterface::class);
        $parser->method('parse')->willReturn('');
        $parser->method('extractComponentParams')->willReturn([]);
        $parser->method('removePropsDirectives')->willReturn('');
        $compiler = $this->createStub(CompilerInterface::class);
        $compiler->method('compile')->willReturn('');
        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('getSourcePath')->willReturn('/tmp/none');
        $renderer = new Renderer($parser, $compiler, $loader, false, sys_get_temp_dir() . '/mln-cache');

        $mailer = $this->getMockBuilder(Mailer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();

        $channel = new MailChannel($mailer, $renderer);
        $notifiable = new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed { return 'nobody@example.com'; }
        };

        $notification = new class implements NotificationInterface {
            public function via(NotifiableInterface $notifiable): array { return ['mail']; }
            public function toArray(NotifiableInterface $notifiable): array { return []; }
            public function toDatabase(NotifiableInterface $notifiable): array { return []; }
            public function toMail(NotifiableInterface $notifiable): mixed { return new \stdClass(); }
        };

        $mailer->expects($this->never())->method('send');
        $channel->send($notifiable, $notification);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function message_and_exception_value_objects(): void
    {
        $mail = (new MailMessage())
            ->level('success')
            ->subject('Done')
            ->line('A')
            ->action('Open', 'https://example.com')
            ->outroLine('B')
            ->attach('/tmp/file.txt', ['as' => 'file.txt'])
            ->view('mail.done', ['k' => 'v']);

        $this->assertSame('success', $mail->toArray()['level']);
        $this->assertSame('Done', $mail->toArray()['subject']);
        $this->assertSame('mail.done', $mail->toArray()['view']);

        $dbMessage = new DatabaseMessage(['a' => 1]);
        $this->assertSame(['a' => 1], $dbMessage->data);

        $exception = new \RuntimeException('boom');
        $failed = new NotificationFailed(
            new class implements NotifiableInterface {
                public function routeNotificationFor(string $channel): mixed { return null; }
            },
            new class implements NotificationInterface {
                public function via(NotifiableInterface $notifiable): array { return []; }
                public function toArray(NotifiableInterface $notifiable): array { return []; }
                public function toDatabase(NotifiableInterface $notifiable): array { return []; }
                public function toMail(NotifiableInterface $notifiable): mixed { return null; }
            },
            'mail',
            $exception
        );

        $this->assertSame('mail', $failed->channel);
        $this->assertSame($exception, $failed->exception);

        $sent = new NotificationSent(
            new class implements NotifiableInterface {
                public function routeNotificationFor(string $channel): mixed { return null; }
            },
            new class implements NotificationInterface {
                public function via(NotifiableInterface $notifiable): array { return []; }
                public function toArray(NotifiableInterface $notifiable): array { return []; }
                public function toDatabase(NotifiableInterface $notifiable): array { return []; }
                public function toMail(NotifiableInterface $notifiable): mixed { return null; }
            },
            'database',
            ['ok' => true]
        );
        $this->assertSame('database', $sent->channel);
        $this->assertSame(['ok' => true], $sent->response);

        $e = new CouldNotSendNotification('x');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertSame('x', $e->getMessage());
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
