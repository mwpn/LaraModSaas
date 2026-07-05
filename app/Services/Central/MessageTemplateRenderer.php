<?php

declare(strict_types=1);

namespace App\Services\Central;

class MessageTemplateRenderer
{
    public function render(string $template, array $variables = []): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', function (array $matches) use ($variables): string {
            $key = (string) ($matches[1] ?? '');

            if (! array_key_exists($key, $variables)) {
                return $matches[0];
            }

            $value = $variables[$key];

            return is_scalar($value) ? (string) $value : '';
        }, $template) ?? $template;
    }
}
