<?php

/**
 * Server-side HTML sanitizer for admin-authored page content.
 *
 * Uses DOMDocument to strip dangerous elements/attributes while keeping a
 * rich-but-safe subset (headings, lists, tables, images, links, media).
 * Custom HTML/JS blocks are intentionally NOT sanitized (admin-only,
 * equivalent to BookStack's raw HTML permission) but are clearly labelled.
 */

declare(strict_types=1);

namespace InfoLoop\Services;

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'cite', 'code', 'col', 'colgroup',
        'dd', 'del', 'details', 'dfn', 'div', 'dl', 'dt', 'em', 'figcaption', 'figure',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'kbd', 'li', 'mark',
        'ol', 'p', 'picture', 'pre', 'q', 's', 'samp', 'section', 'small', 'source',
        'span', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th',
        'thead', 'time', 'tr', 'u', 'ul', 'var', 'video', 'audio', 'iframe',
    ];

    /** Tags removed together with their text content. */
    private const DROP_WITH_CONTENT = ['script', 'style', 'noscript', 'template', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base'];

    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'srcset'],
        'source' => ['src', 'type', 'srcset', 'media'],
        'video' => ['src', 'controls', 'loop', 'muted', 'autoplay', 'poster', 'playsinline', 'width', 'height'],
        'audio' => ['src', 'controls', 'loop', 'muted', 'autoplay'],
        'iframe' => ['src', 'width', 'height', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy', 'title'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'col' => ['span'],
        'colgroup' => ['span'],
        'time' => ['datetime'],
        'ol' => ['start', 'reversed', 'type'],
        'li' => ['value'],
        '*' => ['class', 'style', 'id', 'data-*', 'aria-*'],
    ];

    private const ALLOWED_PROTOCOLS = ['http', 'https', 'mailto', 'tel', 'data'];

    /**
     * Sanitizes an HTML fragment and returns safe HTML.
     */
    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return '';
        }
        $this->cleanNode($root);
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }
        return trim($output);
    }

    private function cleanNode(\DOMNode $node): void
    {
        if ($node instanceof \DOMElement) {
            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $this->drop($node);
                return;
            }
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->unwrap($node);
                return;
            }
            $this->cleanAttributes($node, $tag);
        }
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child->parentNode !== null) {
                $this->cleanNode($child);
            }
        }
    }

    private function cleanAttributes(\DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];
        $global = self::ALLOWED_ATTRS['*'];
        $allowedAll = array_merge($allowed, $global);

        $toRemove = [];
        foreach (iterator_to_array($element->attributes ?? []) as $attr) {
            /** @var \DOMAttr $attr */
            $name = strtolower($attr->name);
            if ($this->isAllowedAttr($name, $tag, $allowedAll)) {
                if (in_array($name, ['href', 'src', 'srcset', 'poster', 'action'], true)) {
                    if (!$this->isSafeUrl($attr->value)) {
                        $toRemove[] = $attr->name;
                        continue;
                    }
                }
                if ($name === 'style' && !$this->isSafeStyle($attr->value)) {
                    $toRemove[] = $attr->name;
                    continue;
                }
                if (str_starts_with($name, 'on')) {
                    $toRemove[] = $attr->name;
                }
                continue;
            }
            $toRemove[] = $attr->name;
        }
        foreach ($toRemove as $name) {
            $element->removeAttribute($name);
        }
        if ($element->hasAttribute('target') && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * @param string[] $allowedAll
     */
    private function isAllowedAttr(string $name, string $tag, array $allowedAll): bool
    {
        if (str_starts_with($name, 'on')) {
            return false;
        }
        $tagAllowed = self::ALLOWED_ATTRS[$tag] ?? [];
        if (in_array($name, $tagAllowed, true) || in_array($name, $allowedAll, true)) {
            return true;
        }
        foreach (self::ALLOWED_ATTRS['*'] as $pattern) {
            if (str_ends_with($pattern, '-*') && str_starts_with($name, substr($pattern, 0, -1))) {
                return true;
            }
        }
        return false;
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null) {
            return true; // relative URL
        }
        return in_array(strtolower($scheme), self::ALLOWED_PROTOCOLS, true);
    }

    private function isSafeStyle(string $style): bool
    {
        return !preg_match('/(expression|javascript:|vbscript:|@import|url\s*\(\s*["\']?\s*(javascript|vbscript|data):)/i', $style);
    }

    private function unwrap(\DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private function drop(\DOMElement $element): void
    {
        $element->parentNode?->removeChild($element);
    }
}
