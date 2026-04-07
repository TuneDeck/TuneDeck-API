<?php
class Html {
    public static function dom(string $html): DOMXPath {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    public static function firstString(DOMXPath $xp, string $xpath, ?DOMNode $ctx = null): ?string {
        $nodes = $xp->query($xpath, $ctx);
        if (!$nodes || $nodes->length === 0) return null;
        $value = trim($nodes->item(0)->nodeValue ?? '');
        return $value !== '' ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    public static function firstAttr(DOMXPath $xp, string $xpath, ?DOMNode $ctx = null): ?string {
        $nodes = $xp->query($xpath, $ctx);
        if (!$nodes || $nodes->length === 0) return null;
        $value = trim($nodes->item(0)->nodeValue ?? '');
        return $value !== '' ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    public static function attr(?DOMElement $el, string $name): ?string {
        if (!$el || !$el->hasAttribute($name)) return null;
        $value = trim($el->getAttribute($name));
        return $value !== '' ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    public static function text(?DOMNode $node): ?string {
        if (!$node) return null;
        $value = trim($node->textContent ?? '');
        return $value !== '' ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    public static function styleBackgroundUrl(?string $style): ?string {
        if (!$style) return null;
        if (preg_match("~background-image:\s*url\(['\"]?([^'\")]+)~i", $style, $m)) {
            return $m[1];
        }
        return null;
    }

    public static function absoluteUrl(string $baseUrl, ?string $url): ?string {
        if (!$url) return null;
        if (preg_match('~^https?://~i', $url)) return $url;
        if (str_starts_with($url, '//')) return 'https:' . $url;
        if (str_starts_with($url, '/')) return rtrim($baseUrl, '/') . $url;
        return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }
}
