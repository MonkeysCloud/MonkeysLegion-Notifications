<?php

namespace MonkeysLegion\Notifications\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Notify
{
    public function __construct(
        public readonly string $notification
    ) {
    }
}
