<?php

namespace MonkeysLegion\Notifications\Messages;

class MailMessage
{
    public string $level = 'info';
    public string $subject;
    public array $introLines = [];
    public array $outroLines = [];
    public ?string $actionText = null;
    public ?string $actionUrl = null;
    public ?string $view = null;
    public array $viewData = [];

    public array $attachments = [];

    /**
     * Set the level of the notification (info, success, error).
     */
    public function level(string $level): self
    {
        $this->level = $level;
        return $this;
    }

    /**
     * Set the subject of the notification.
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Add a line of text to the notification.
     */
    public function line(string $line): self
    {
        $this->introLines[] = $line;
        return $this;
    }

    /**
     * Add an action button to the notification.
     */
    public function action(string $text, string $url): self
    {
        $this->actionText = $text;
        $this->actionUrl = $url;
        return $this;
    }

    /**
     * Add an outro line of text to the notification.
     */
    public function outroLine(string $line): self
    {
        $this->outroLines[] = $line;
        return $this;
    }

    /**
     * Attach a file to the notification.
     */
    public function attach(string $path, array $options = []): self
    {
        $this->attachments[] = compact('path', 'options');
        return $this;
    }

    /**
     * Set the view to be used for the notification.
     */
    public function view(string $view, array $data = []): self
    {
        $this->view = $view;
        $this->viewData = $data;
        return $this;
    }

    /**
     * Get the data array for the notification.
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'subject' => $this->subject ?? null,
            'introLines' => $this->introLines,
            'outroLines' => $this->outroLines,
            'actionText' => $this->actionText,
            'actionUrl' => $this->actionUrl,
            'view' => $this->view,
            'viewData' => $this->viewData,
            'attachments' => $this->attachments,
        ];
    }
}
