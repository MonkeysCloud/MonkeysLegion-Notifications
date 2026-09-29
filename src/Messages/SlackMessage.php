<?php
declare(strict_types=1);

namespace MonkeysLegion\Notifications\Messages;

/**
 * MonKeysLegion Framework — Notifications Package
 *
 * Fluent builder for Slack incoming webhook payloads.
 *
 * Usage:
 *   $message = (new SlackMessage('#general'))
 *       ->text('Deployment successful!')
 *       ->block(function($b) { $b->text('Version: v1.2.3'); });
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class SlackMessage
{
    /** @var array<string, mixed> */
    private array $payload = [];

    public function __construct(
        private readonly string $channel = '',
    ) {
        if ($channel !== '') {
            $this->payload['channel'] = $channel;
        }
    }

    /**
     * Set the message text (simple format).
     */
    public function text(string $text, bool $markdown = true): self
    {
        $this->payload['text'] = $text;
        if ($markdown) {
            $this->payload['mrkdwn'] = true;
        }
        return $this;
    }

    /**
     * Set the username to post as.
     */
    public function username(string $username): self
    {
        $this->payload['username'] = $username;
        return $this;
    }

    /**
     * Set the bot icon emoji.
     */
    public function icon(string $emoji): self
    {
        $this->payload['icon_emoji'] = $emoji;
        return $this;
    }

    /**
     * Set the bot icon URL.
     */
    public function iconUrl(string $url): self
    {
        $this->payload['icon_url'] = $url;
        return $this;
    }

    /**
     * Add a Block Kit block.
     *
     * @param callable(array<string, mixed>): void $callback
     */
    public function block(callable $callback): self
    {
        $block = [];
        $callback($block);
        $this->payload['blocks'][] = $block;
        return $this;
    }

    /**
     * Add a section block with text.
     */
    public function section(string $text, bool $markdown = true): self
    {
        $this->payload['blocks'][] = [
            'type' => 'section',
            'text' => [
                'type' => $markdown ? 'mrkdwn' : 'plain_text',
                'text' => $text,
            ],
        ];
        return $this;
    }

    /**
     * Add a header block.
     */
    public function header(string $text): self
    {
        $this->payload['blocks'][] = [
            'type' => 'header',
            'text' => ['type' => 'plain_text', 'text' => $text],
        ];
        return $this;
    }

    /**
     * Add a divider block.
     */
    public function divider(): self
    {
        $this->payload['blocks'][] = ['type' => 'divider'];
        return $this;
    }

    /**
     * Add a field to the last section block.
     *
     * @param string $title
     * @param string $value
     * @param bool $markdown
     */
    public function field(string $title, string $value, bool $markdown = true): self
    {
        $lastBlock = &$this->payload['blocks'][count($this->payload['blocks'] ?? []) - 1];

        if (!is_array($lastBlock) || ($lastBlock['type'] ?? '') !== 'section') {
            // Create a new section if the last block isn't a section
            $this->payload['blocks'][] = [
                'type' => 'section',
                'fields' => [],
            ];
            $lastBlock = &$this->payload['blocks'][count($this->payload['blocks']) - 1];
        }

        $lastBlock['fields'][] = [
            'type' => $markdown ? 'mrkdwn' : 'plain_text',
            'text' => "*{$title}*\n{$value}",
        ];

        return $this;
    }

    /**
     * Add a context block with elements.
     *
     * @param list<string> $texts
     */
    public function context(array $texts): self
    {
        $elements = array_map(
            fn($text) => ['type' => 'mrkdwn', 'text' => $text],
            $texts,
        );

        $this->payload['blocks'][] = [
            'type' => 'context',
            'elements' => $elements,
        ];
        return $this;
    }

    /**
     * Set the message color (for attachments).
     */
    public function color(string $color): self
    {
        $this->payload['attachments'][0]['color'] = $color;
        return $this;
    }

    /**
     * Get the payload as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    /**
     * Get the payload as JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
