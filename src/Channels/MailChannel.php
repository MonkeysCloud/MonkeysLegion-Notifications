<?php

namespace MonkeysLegion\Notifications\Channels;

use MonkeysLegion\Mail\Mailer;
use MonkeysLegion\Notifications\Contracts\NotifiableInterface;
use MonkeysLegion\Notifications\Contracts\NotificationInterface;
use MonkeysLegion\Notifications\Messages\MailMessage;
use MonkeysLegion\Template\Renderer;

class MailChannel implements ChannelInterface
{
    /**
     * Create a new mail channel instance.
     */
    public function __construct(
        protected Mailer $mailer,
        private Renderer $renderer
    ) {
    }

    /**
     * Send the given notification.
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $message = $notification->toMail($notifiable);

        if ($message instanceof MailMessage) {
            $recipient = $notifiable->routeNotificationFor('mail');
            if (!is_string($recipient)) {
                return;
            }

            $this->mailer->send(
                $recipient,
                $message->subject,
                $this->render($message),
                'text/html',
                $message->attachments
            );
            return;
        }

        // If it's already a configured Mailable or something that can be sent directly
        if (!is_object($message)) {
            return;
        }

        if (method_exists($message, 'send') && method_exists($message, 'setTo')) {
            $recipient = $notifiable->routeNotificationFor('mail');
            if (!is_string($recipient)) {
                return;
            }

            $message->setTo($recipient);
            $message->send();
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
            $data = $message->toArray()['viewData'];

            return $this->renderer->render(
                $message->view,
                $data
            );
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
