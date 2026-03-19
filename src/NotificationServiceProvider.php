<?php

namespace MonkeysLegion\Notifications;

use MonkeysLegion\Core\Attributes\Provider;
use MonkeysLegion\Mail\Mailer;
use MonkeysLegion\Mlc\Config;
use MonkeysLegion\Notifications\Channels\MailChannel;
use MonkeysLegion\Notifications\Channels\DatabaseChannel;
use MonkeysLegion\Query\QueryBuilder;

#[Provider]
class NotificationServiceProvider
{
    public function register(
        NotificationManager $manager,
        Mailer $mailer,
        QueryBuilder $queryBuilder,
        Config $config
    ) {
        // Couple MailChannel with the Mailer service
        $manager->extend('mail', function () use ($mailer) {
            return new MailChannel($mailer);
        });

        // Couple DatabaseChannel with QueryBuilder and Config
        $manager->extend('database', function () use ($queryBuilder, $config) {
            return new DatabaseChannel(
                $queryBuilder,
                $config->get('notifications.channels.database.table', 'notifications')
            );
        });
    }
}