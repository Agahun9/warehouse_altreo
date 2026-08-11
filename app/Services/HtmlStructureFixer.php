<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Naprawia nieprawidlowe zagniezdzanie znacznikow HTML (np. blok <p> wewnatrz
 * naglowka <h2>) powstajace przy edycji w edytorze WYSIWYG, bez zmiany
 * tresci ani stylowania opisu.
 */
class HtmlStructureFixer
{
    /** Elementy blokowe, ktore nie moga wystapic wewnatrz elementow "tylko frazowych". */
    private const BLOCK_TAGS = array(
        'address', 'article', 'aside', 'blockquote', 'details', 'dialog',
        'dd', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer',
        'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li',
        'main', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'ul',
    );

    /** Elementy, ktore zgodnie ze specyfikacja HTML moga zawierac wylacznie tresc frazowa (inline). */
    private const PHRASING_ONLY_TAGS = array(
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'strong', 'b', 'em',
        'i', 'u', 's', 'small', 'mark', 'label', 'sub', 'sup', 'abbr', 'cite', 'q',
    );

    public static function fix(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorState = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__free_rte_root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorState);

        if (!$loaded) {
            return $html;
        }

        $root = $document->getElementById('__free_rte_root__');
        if (!$root instanceof DOMElement) {
            return $html;
        }

        self::fixNode($document, $root);
        self::removeEmptyPhrasingWrappers($root);

        $result = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim((string) $result);
    }

    private static function fixNode(DOMDocument $document, DOMNode $node): void
    {
        if (!($node instanceof DOMElement)) {
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            self::fixNode($document, $child);
        }

        $tagName = strtolower($node->tagName);
        if (in_array($tagName, self::PHRASING_ONLY_TAGS, true) && self::hasBlockChild($node)) {
            self::splitAroundBlockChildren($document, $node);
        }
    }

    private static function hasBlockChild(DOMElement $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), self::BLOCK_TAGS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rozdziela element, ktory nie moze zawierac tresci blokowej, na sekwencje
     * rodzenstwa: kolejne "porcje" tresci frazowej pozostaja opakowane w
     * kopie oryginalnego znacznika (z tymi samymi atrybutami/stylem), a
     * napotkane elementy blokowe trafiaja obok, poza nim.
     */
    private static function splitAroundBlockChildren(DOMDocument $document, DOMElement $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }

        $replacement = array();
        $currentWrap = self::cloneShell($document, $node);
        $wrapHasContent = false;

        foreach (iterator_to_array($node->childNodes) as $child) {
            $isBlock = $child instanceof DOMElement && in_array(strtolower($child->tagName), self::BLOCK_TAGS, true);

            if ($isBlock) {
                if ($wrapHasContent) {
                    $replacement[] = $currentWrap;
                }
                $replacement[] = $child;
                $currentWrap = self::cloneShell($document, $node);
                $wrapHasContent = false;
                continue;
            }

            $currentWrap->appendChild($child);
            if (!($child instanceof DOMText) || trim($child->textContent) !== '') {
                $wrapHasContent = true;
            }
        }

        if ($wrapHasContent) {
            $replacement[] = $currentWrap;
        }

        foreach ($replacement as $fragment) {
            $parent->insertBefore($fragment, $node);
        }
        $parent->removeChild($node);
    }

    /**
     * Usuwa puste znaczniki frazowe (np. osierocony <h2></h2>), ktore libxml
     * potrafi zostawic po samodzielnym domknieciu naglowka przed blokiem
     * podczas parsowania. Nie rusza elementow zawierajacych <br>, obrazek
     * czy nielamiaca spacje – to celowe puste akapity/odstepy autora.
     */
    private static function removeEmptyPhrasingWrappers(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::removeEmptyPhrasingWrappers($child);
            }
        }

        if (!($node instanceof DOMElement)) {
            return;
        }

        $tagName = strtolower($node->tagName);
        if (!in_array($tagName, self::PHRASING_ONLY_TAGS, true)) {
            return;
        }

        if ($node->childNodes->length > 0) {
            return;
        }

        if (trim($node->textContent) !== '') {
            return;
        }

        $parent = $node->parentNode;
        if ($parent !== null) {
            $parent->removeChild($node);
        }
    }

    private static function cloneShell(DOMDocument $document, DOMElement $node): DOMElement
    {
        $clone = $document->createElement($node->tagName);
        foreach ($node->attributes as $attribute) {
            $clone->setAttribute($attribute->name, $attribute->value);
        }

        return $clone;
    }
}
