<?php

namespace MonkeysLegion\Notifications\Messages;

class DatabaseMessage
{
    /**
     * Create a new database message.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data = []
    ) {
    }
}
