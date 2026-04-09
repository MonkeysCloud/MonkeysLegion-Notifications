<?php

declare(strict_types=1);

namespace MonkeysLegion\Notifications\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;

#[CommandAttr('make:notification', 'Generate a new Notification class stub')]
final class MakeNotificationCommand extends Command
{
    use Cli;

    /**
     * Handle the command execution.
     *
     * @return int
     */
    protected function handle(): int
    {
        $this->cliLine()
            ->add('📣 ', 'cyan', 'bold')
            ->add('Generating new Notification class...', 'white', 'bold')
            ->print();

        // Get the name from arguments or ask
        $nameInput = $this->argument(0);
        
        ASK_NAME:
        if (empty($nameInput)) {
            $nameInput = $this->ask('Enter notification name (e.g. OrderShipped) : ');
        }
        
        if (empty($nameInput)) {
            $this->cliLine()->error(' ✖ ')->add('Notification name is required', 'red')->print();
            goto ASK_NAME;
        }

        // Clean name and ensure it ends with Notification
        $className = preg_replace('/Notification$/', '', (string)$nameInput) . 'Notification';
        
        if (!preg_match('/^[A-Z][A-Za-z0-9]+$/', $className)) {
            $this->cliLine()->error(' ✖ ')->add('Invalid name: must be CamelCase', 'red', 'bold')->print();
            $nameInput = null; // Clear to force prompt
            goto ASK_NAME;
        }

        $dir = base_path('app/Notifications');
        $file = "{$dir}/{$className}.php";

        // Create directory if it doesn't exist
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            $this->cliLine()
                ->warning(' ℹ ')
                ->add('Notification already exists: ', 'yellow')
                ->add($file, 'white')
                ->print();
            return self::SUCCESS;
        }

        $stub = $this->getStub($className);

        if (file_put_contents($file, $stub) === false) {
            $this->cliLine()->error(' ✖ ')->add("Failed to create file: {$file}", 'red')->print();
            return self::FAILURE;
        }

        $this->cliLine()
            ->success(' ✨ ')
            ->add('Created notification: ', 'green')
            ->add($file, 'white', 'bold')
            ->print();

        return self::SUCCESS;
    }

    /**
     * Get the stub content for the notification class.
     *
     * @param string $name
     * @return string
     */
    private function getStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Notifications;

use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Messages\MailMessage;
use MonkeysLegion\Queue\Contracts\ShouldQueue;
use MonkeysLegion\Queue\Contracts\ShouldSync;

final class {$name} implements NotificationInterface, ShouldQueue
{
    /**
     * Create a new notification instance.
     * All the methods notifiable param must implement NotifiableInterface
     *
     * @return void
     */
    public function __construct()
    {
        // Define your dependencies here
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param object \$notifiable
     * @return array<int, string>
     */
    public function via(object \$notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param object \$notifiable
     * @return array<string, mixed>
     */
    public function toArray(object \$notifiable): array
    {
        return [
            //
        ];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param object \$notifiable
     * @return MailMessage
     */
    public function toMail(object \$notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Notification Subject')
            ->line('The introduction to the notification.')
            ->action('Notification Action', 'https://example.com')
            ->line('Thank you for using our application!');
    }

    /**
     * Get the database representation of the notification.
     *
     * @param object \$notifiable
     * @return array<string, mixed>
     */
    public function toDatabase(object \$notifiable): array
    {
        return [
            // Notification data to be stored in the database
        ];
    }
}
PHP;
    }
}
