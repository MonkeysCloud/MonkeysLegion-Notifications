<?php
declare(strict_types=1);

namespace MonkeysLegion\Notifications\Messages;

/**
 * MonKeysLegion Framework — Notifications Package
 *
 * Fluent builder for Microsoft Teams incoming webhook payloads.
 * Uses Adaptive Cards format.
 *
 * Usage:
 *   $message = (new TeamsMessage())
 *       ->title('Deployment Successful')
 *       ->text('Version v1.2.3 deployed to production.')
 *       ->fact('Environment', 'production')
 *       ->color('good');
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class TeamsMessage
{
    /** @var array<string, mixed> */
    private array $body = [];

    /** @var list<array<string, mixed>> */
    private array $facts = [];

    private string $themeColor = '';

    /**
     * Set the message title.
     */
    public function title(string $title): self
    {
        $this->body['title'] = $title;
        return $this;
    }

    /**
     * Set the message summary text.
     */
    public function text(string $text): self
    {
        $this->body['text'] = $text;
        return $this;
    }

    /**
     * Add a fact (key-value pair) to the message.
     */
    public function fact(string $name, string $value): self
    {
        $this->facts[] = ['name' => $name, 'value' => $value];
        return $this;
    }

    /**
     * Set the theme color (hex without #).
     *
     * @param string $color 'good', 'warning', 'danger', or hex like '0072C6'
     */
    public function color(string $color): self
    {
        $this->themeColor = match ($color) {
            'good'    => '2EB872',
            'warning' => 'FFB900',
            'danger'  => 'E81123',
            default   => ltrim($color, '#'),
        };
        return $this;
    }

    /**
     * Add a section with a title.
     */
    public function section(string $title, string $text = ''): self
    {
        $section = ['title' => $title];
        if ($text !== '') {
            $section['text'] = $text;
        }
        $this->body['sections'][] = $section;
        return $this;
    }

    /**
     * Add an action button.
     */
    public function action(string $label, string $url): self
    {
        $this->body['potentialAction'][] = [
            '@type' => 'OpenUri',
            'name'  => $label,
            'targets' => [['os' => 'default', 'uri' => $url]],
        ];
        return $this;
    }

    /**
     * Get the payload as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            '@type'   => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
        ];

        if (isset($this->body['title'])) {
            $payload['title'] = $this->body['title'];
        }
        if (isset($this->body['text'])) {
            $payload['text'] = $this->body['text'];
        }
        if ($this->themeColor !== '') {
            $payload['themeColor'] = $this->themeColor;
        }

        // Add facts as a section
        if (!empty($this->facts)) {
            $payload['sections'][] = [
                'facts' => $this->facts,
            ];
        }

        // Add custom sections
        foreach ($this->body['sections'] ?? [] as $section) {
            $payload['sections'][] = $section;
        }

        // Add actions
        if (isset($this->body['potentialAction'])) {
            $payload['potentialAction'] = $this->body['potentialAction'];
        }

        return $payload;
    }

    /**
     * Get the payload as JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
