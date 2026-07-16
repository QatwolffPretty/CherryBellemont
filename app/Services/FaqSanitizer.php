<?php

namespace App\Services;

class FaqSanitizer
{
    /**
     * Permit only structural, attribute-free formatting tags. Stripping every
     * attribute prevents event handlers, styles, and unsafe URL schemes.
     */
    public function sanitize(?string $answer): string
    {
        $answer = trim((string) $answer);
        $answer = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $answer) ?? '';
        $answer = strip_tags($answer, '<p><br><strong><b><em><i><ul><ol><li>');

        $answer = preg_replace_callback('/<\/?([a-z0-9]+)(?:\s+[^>]*)?>/i', function (array $match): string {
            $tag = strtolower($match[1]);
            $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li'];

            if (! in_array($tag, $allowed, true)) {
                return '';
            }

            return str_starts_with($match[0], '</') ? "</{$tag}>" : "<{$tag}>";
        }, $answer) ?? '';

        return $answer === '' ? '' : nl2br($answer, false);
    }
}
