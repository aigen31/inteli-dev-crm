<?php

declare(strict_types=1);

namespace App\Services;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitizer for HTML produced by the WYSIWYG editor and by AI.
 * Never trust stored HTML: sanitize on write and once more before rendering.
 */
class HtmlSanitizer
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'del' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'code' => [],
        'pre' => [],
        'hr' => [],
        'span' => [],
    ];

    /** @var list<string> */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math',
    ];

    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="__sanitizer_root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('__sanitizer_root')
            ?? $document->getElementsByTagName('div')->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->clean($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    public function toPlainText(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $text = preg_replace('/<(br|\/p|\/li|\/h[2-4]|\/blockquote)>/i', "\n", $html) ?? $html;
        $text = strip_tags($text);

        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function clean(DOMNode $node): void
    {
        for ($index = $node->childNodes->length - 1; $index >= 0; $index--) {
            $child = $node->childNodes->item($index);

            if ($child instanceof DOMComment) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            if (! array_key_exists($tag, self::ALLOWED)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $this->cleanAttributes($child, self::ALLOWED[$tag]);
            $this->clean($child);
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function cleanAttributes(DOMElement $element, array $allowed): void
    {
        for ($index = $element->attributes->length - 1; $index >= 0; $index--) {
            $attribute = $element->attributes->item($index);

            if ($attribute === null) {
                continue;
            }

            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if ($name === 'href' && ! $this->isSafeUrl($attribute->nodeValue)) {
                $element->removeAttribute('href');
            }

            if ($name === 'target' && $attribute->nodeValue !== '_blank') {
                $element->removeAttribute('target');
            }
        }

        if ($element->tagName === 'a') {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');

            if ($element->getAttribute('target') === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
    }

    private function isSafeUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }
}
