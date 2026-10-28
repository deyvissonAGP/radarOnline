<?php

namespace App\Radar\Collectors;

use DOMDocument;
use DOMNode;
use DOMXPath;

/** Pequeno ajudante para ler HTML com XPath. */
final class Dom
{
    public static function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return new DOMXPath($doc);
    }

    /** XPath para "elemento cuja classe contém $classe". */
    public static function cls(string $classe): string
    {
        return "contains(concat(' ', normalize-space(@class), ' '), ' {$classe} ')";
    }

    public static function text(DOMXPath $xp, string $query, ?DOMNode $ctx = null): ?string
    {
        $node = $xp->query($query, $ctx)?->item(0);
        if (! $node) {
            return null;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', $node->textContent));

        return $text === '' ? null : $text;
    }

    public static function attr(DOMXPath $xp, string $query, string $attr, ?DOMNode $ctx = null): ?string
    {
        $node = $xp->query($query, $ctx)?->item(0);
        $value = $node?->attributes?->getNamedItem($attr)?->nodeValue;

        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }
}
