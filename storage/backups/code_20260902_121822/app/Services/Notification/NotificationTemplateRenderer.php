<?php

namespace App\Services\Notification;

/**
 * Renders notification template subject/body by replacing {{placeholder}}
 * tokens (with or without inner whitespace) from an event data array.
 *
 * Missing variables are replaced with an empty string so templates degrade
 * gracefully when an event does not supply every declared placeholder.
 */
class NotificationTemplateRenderer
{
    public function render(string $text, array $data = []): string
    {
        $replacements = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replacements['{{'.$key.'}}'] = (string) $value;
                $replacements['{{ '.$key.' }}'] = (string) $value;
            }
        }

        $rendered = strtr($text, $replacements);

        // Strip any remaining un-replaced tokens.
        $rendered = preg_replace('/\{\{\s*[\w.]+?\s*\}\}/u', '', $rendered) ?? $rendered;

        return trim($rendered);
    }

    public function subject(?string $subject, array $data = []): string
    {
        return $subject === null ? '' : $this->render($subject, $data);
    }
}
