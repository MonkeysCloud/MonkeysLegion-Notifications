<?php

namespace MonkeysLegion\Notifications\Channels;

use MonkeysLegion\Mail\Mailer;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Messages\MailMessage;

class MailChannel implements ChannelInterface
{
    /**
     * Create a new mail channel instance.
     */
    public function __construct(
        protected Mailer $mailer
    ) {
    }

    /**
     * Send the given notification.
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $message = $notification->toMail($notifiable);

        if ($message instanceof MailMessage) {
            $this->mailer->send(
                $notifiable->routeNotificationFor('mail'),
                $message->subject,
                $this->render($message),
                'text/html',
                $message->attachments
            );
            return;
        }

        // If it's already a configured Mailable or something that can be sent directly
        if (method_exists($message, 'send')) {
            $message->setTo($notifiable->routeNotificationFor('mail'))->send();
            return;
        }
    }

    /**
     * Render the mail message to HTML.
     * 
     * @todo Implement full template rendering support.
     */
    protected function render(MailMessage $message): string
    {
        if ($message->view) {
            // For now, we assume the Mailer or a View engine handles this.
            // In a real scenario, we'd use the Template package here.
            return ""; 
        }

        $html = "<h1>{$message->subject}</h1>";
        foreach ($message->introLines as $line) {
            $html .= "<p>{$line}</p>";
        }

        if ($message->actionText && $message->actionUrl) {
            $html .= "<a href='{$message->actionUrl}' style='padding: 10px; background: blue; color: white;'>{$message->actionText}</a>";
        }

        foreach ($message->outroLines as $line) {
            $html .= "<p>{$line}</p>";
        }

        return $html;
    }
}
