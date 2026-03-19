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

The `AttributeProcessor` scans for these triggers during event lifespan.


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
│   └── notifications.mlc           # MonkeysLegion Config format
```

## 📡 Scope & Integration

This package is designed as a **Producer**. It formats messages and submits them to the respective underlying systems:

- **Queue**: If a notification implements `ShouldQueue`, it is wrapped in a `SendNotificationJob` and handed to `MonkeysLegion-Queue`.
- **Database**: Records are persisted using `MonkeysLegion-Query`.
- **Mail**: Content is handed over to `MonkeysLegion-Mail`.

## 🚦 Roadmap

### Phase 1: Core Foundation (Complete)
- [x] Basic Contracts & Interfaces
- [x] Database Channel implementation
- [x] Queue Integration via `ShouldQueue`
- [x] `Notifiable` Trait & `NotificationManager`

### Phase 2: Built-in Channels (Complete)
- [x] Mail Channel Integration (using MonkeysLegion-Mail)
- [x] `MailMessage` fluent builder

### Phase 3: Advanced Integration (In Progress)
- [x] PSR-14 Event Listeners
- [x] `#[Notify]` attribute processor
- [ ] Notification History UI components (Skeleton integration)

---
Made with ❤️ by MonkeysLegion




Component,Purpose,Status
Notification Discovery,Scan for #[Notify] attributes on DTOs.,In Progress
Polymorphic Storage,Migration for a notifications table that works with any Entity ID.,Needed
"The ""Anonymous"" Notifiable",For routing notifications to raw emails/phone numbers.,Needed
Templating Engine,Integration with your framework's View engine for HTML emails.,Needed