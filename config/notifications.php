<?php

declare(strict_types=1);

return [
    // Controls the notifications table name used when publishing migrations.
    'table' => $_ENV['NOTIFICATIONS_TABLE'] ?? 'notifications',
];
