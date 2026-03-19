<?php

namespace MonkeysLegion\Notifications\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class Notify
{
    public function __construct(
        public readonly string $notification,
        public readonly array $channels = [],
        public readonly bool $silent = false
    ) {
    }
}
