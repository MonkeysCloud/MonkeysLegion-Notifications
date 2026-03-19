<?php

namespace MonkeysLegion\Notifications\Messages;

class DatabaseMessage
{
    /**
     * Create a new database message.
     */
    public function __construct(
        public readonly array $data = []
    ) {
    }
}
