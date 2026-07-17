<?php

namespace App\Services;

class CampaignContentSanitizer
{
    /**
     * Preserve a deliberately small formatting vocabulary for campaign copy.
     * Link URLs are restricted to safe web, mail and telephone destinations.
     */
    public function sanitize(?string $content): string
    {
        $content = trim((string) $content);
        $content = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $content) ?? '';
        $content = strip_tags($content, '<p><br><strong><b><em><i><ul><ol><li><h2><h3><blockquote><a>');

        $content = preg_replace_callback('/<a\s+([^>]*)>/i', function (array $match): string {
            if (! preg_match('/href\s*=\s*(["\'])(.*?)\1/i', $match[1], $href)) {
                return '<a>';
            }

            $url = trim($href[2]);
            if (! preg_match('#^(https?://|mailto:|tel:)#i', $url)) {
                return '<a>';
            }

            return '<a href="'.e($url).'">';
        }, $content) ?? '';

        $content = preg_replace_callback('/<\/?([a-z0-9]+)(?:\s+[^>]*)?>/i', function (array $match): string {
            $tag = strtolower($match[1]);
            $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'h2', 'h3', 'blockquote', 'a'];

            if (! in_array($tag, $allowed, true)) {
                return '';
            }

            if ($tag === 'a' && ! str_starts_with($match[0], '</')) {
                return $match[0];
            }

            return str_starts_with($match[0], '</') ? "</{$tag}>" : "<{$tag}>";
        }, $content) ?? '';

        return $content === '' ? '' : nl2br($content, false);
    }
}
