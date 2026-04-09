# MonkeysLegion Notifications

A multi-channel notification system for the MonkeysLegion framework. Deeply integrated with the MonkeysLegion Queue, Query, and Mail packages while maintaining strict modular isolation.

## 🚀 Overview

MonkeysLegion Notifications allows you to send messages across various delivery channels (like Mail and Database) following a clean, expressive API. It supports asynchronous delivery out-of-the-box by leveraging the `MonkeysLegion-Queue` system.

## ✨ Features

- 📨 **Multi-Channel Delivery** - Send notifications via Mail, Database, and more.
- ⚡ **Queue Integration** - Automatically background heavy notification dispatches (e.g., SMTP) using the `ShouldQueue` interface.
- 🎯 **Notifiable Entities** - Easy integration with your Models/Entities via the `Notifiable` trait.
- 🏷️ **Attribute Triggers** - Trigger notifications automatically using PHP 8 attributes on DTOs or Entity properties.
- 🛡️ **PSR Compliant** - Built with PSR-14 event dispatching and standard isolation principles.

## 📦 Installation

```bash
composer require monkeyscloud/monkeyslegion-notifications
```

## 📖 Basic Usage

### 1. Define a Notification

```php
namespace App\Notifications;

use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Messages\MailMessage;
use MonkeysLegion\Queue\Contracts\ShouldQueue; // Optional: for background sending

class InvoicePaid implements NotificationInterface, ShouldQueue
{
    public function __construct(public float $amount) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invoice Paid')
            ->line("You have paid an invoice of ${$this->amount}")
            ->action('View Invoice', 'https://example.com/invoice/1');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'amount' => $this->amount,
            'message' => "Invoice of ${$this->amount} paid."
        ];
    }
}
```

### 2. Prepare your Notifiable Model

```php
use MonkeysLegion\Notifications\Traits\Notifiable;

class User
{
    use Notifiable;

    public string $email = 'user@example.com';
}
```

### 3. Send the Notification

```php
$user->notify(new InvoicePaid(99.99));
```

### 4. Attribute Triggers (Advanced)

You can automatically trigger notifications by using the `#[Notify]` attribute on your entities or their properties.

```php
use MonkeysLegion\Notifications\Attributes\Notify;
use App\Notifications\LowBalanceWarning;

class Account
{
    use Notifiable;

    #[Notify(LowBalanceWarning::class)]
    public float $balance = 10.00;
}
```

The `AttributeProcessor` scans for these triggers during event lifespan. For more detailed examples and advanced usage (including private getters and global listener mapping), check the [Notification Listener Usage Guide](notification_listener_usage.md).

## 🔌 Custom Channels (Open Channels)

MonkeysLegion Notifications is an **open channels package**. This means you can easily extend it by adding your own delivery channels (e.g., Slack, SMS, Push).

To register a custom channel, use the `extend` method on the `NotificationManager`:

```php
$notificationManager->extend('my-channel', function () {
    return new MyCustomChannel(/* dependencies */);
});
```

The callable must return an instance that implements the `MonkeysLegion\Notifications\Channels\ChannelInterface`.

## 📦 Project Structure

```text
monkeyslegion-notifications/
├── src/
│   ├── Attributes/
│   │   ├── Notify.php              # Attribute-driven triggers
│   │   └── AttributeProcessor.php  # Logic to scan and trigger
│   ├── Channels/
│   │   ├── ChannelInterface.php    # Blueprint for all drivers
│   │   ├── DatabaseChannel.php     # Logic for saving to DB
│   │   └── MailChannel.php         # Logic for sending emails
│   ├── Contracts/
│   │   ├── NotifiableInterface.php # For entities that receive notifications
│   │   └── NotificationInterface.php # For the notification classes themselves
│   ├── Events/
│   │   ├── NotificationSent.php    # PSR-14 event
│   │   └── NotificationFailed.php  # PSR-14 event
│   ├── Exceptions/
│   │   └── CouldNotSendNotification.php
│   ├── Jobs/
│   │   └── SendNotificationJob.php  # Queue wrapper
│   ├── Messages/
│   │   ├── MailMessage.php         # Fluent builder for mail content
│   │   └── DatabaseMessage.php     # Formatter for DB arrays
│   ├── Traits/
│   │   └── Notifiable.php          # The "Glue" for your User/Entity models
│   └── NotificationManager.php     # The "Dispatcher" (Entry point)
├── database/
│   └── migrations/                 # Default sql migrations for DB channel
├── config/
│   ├── notifications.mlc           # MonkeysLegion Config format
│   └── notifications.php           # PHP array config using $_ENV
```

## 📡 Scope & Integration

This package is designed as a **Producer**. It formats messages and submits them to the respective underlying systems:

- **Queue**: If a notification implements `ShouldQueue`, it is wrapped in a `SendNotificationJob` and handed to `MonkeysLegion-Queue`.
    - > [!NOTE] Implement the `ShouldSync` interface to force immediate execution. For full details on the queue system, visit the [MonkeysLegion Queue Documentation](https://monkeyslegion.com/docs/packages/queue).
- **Database**: Records are persisted using `MonkeysLegion-Query`.
- **Mail**: Content is handed over to `MonkeysLegion-Mail`.

## 🚦 TODO

- [ ] Slack & SMS & Push Notifications integration

---
Made with ❤️ by MonkeysLegion
