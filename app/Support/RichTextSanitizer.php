<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class RichTextSanitizer
{
    /** @var array<int, string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
        'h2', 'h3', 'h4', 'blockquote', 'a',
    ];

    /** @var array<int, string> */
    private const REMOVE_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'button', 'svg', 'math', 'template',
    ];

    public function clean(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);
        if (! $body instanceof DOMElement) {
            return null;
        }

        $this->sanitizeChildren($body);
        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        $result = trim($result);

        return $result === '' ? null : $result;
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, self::REMOVE_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->sanitizeChildren($node);
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeChildren($node);
            foreach (iterator_to_array($node->attributes) as $attribute) {
                if ($tag !== 'a' || ! in_array(strtolower($attribute->name), ['href', 'target', 'rel'], true)) {
                    $node->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a') {
                $href = trim($node->getAttribute('href'));
                if (! $this->isSafeHref($href)) {
                    $node->removeAttribute('href');
                }
                if ($node->getAttribute('target') === '_blank') {
                    $node->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $node->removeAttribute('target');
                    $node->removeAttribute('rel');
                }
            }
        }
    }

    private function isSafeHref(string $href): bool
    {
        return $href === ''
            || str_starts_with($href, '#')
            || str_starts_with($href, '/')
            || preg_match('/^(https?:|mailto:|tel:)/i', $href) === 1;
    }
}
