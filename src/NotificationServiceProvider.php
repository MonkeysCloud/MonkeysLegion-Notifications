<?php

namespace MonkeysLegion\Notifications;

use MonkeysLegion\Core\Attributes\Provider;
use MonkeysLegion\Mail\Mailer;
use MonkeysLegion\Notifications\Channels\MailChannel;
use MonkeysLegion\Notifications\Channels\DatabaseChannel;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Template\Renderer;

#[Provider]
class NotificationServiceProvider
{
    /**
     * @param array<string, mixed> $config
     */
    public function register(
        NotificationManager $manager,
        Mailer $mailer,
        Renderer $renderer,
        QueryBuilder $queryBuilder,
        array $config
    ): void {
        // Couple MailChannel with the Mailer service
        $manager->extend('mail', function () use ($mailer, $renderer) {
            return new MailChannel($mailer, $renderer);
        });

        // Couple DatabaseChannel with QueryBuilder and Config
        $manager->extend('database', function () use ($queryBuilder, $config) {
            $table = $config['table'] ?? 'notifications';
            return new DatabaseChannel(
                $queryBuilder,
                is_string($table) ? $table : 'notifications'
            );
        });
    }
}
