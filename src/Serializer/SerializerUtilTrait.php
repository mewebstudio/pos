<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

/**
 * Serializer utility methods.
 */
trait SerializerUtilTrait
{
    /**
     * @param string $string
     *
     * @return bool
     */
    private function isHTML(string $string): bool
    {
        if ('' === \trim($string)) {
            return false;
        }

        // Suppress errors for invalid HTML
        $previousLibxmlState = \libxml_use_internal_errors(true);

        // Create a new DOMDocument
        $dom = new \DOMDocument();

        // Attempt to load the string as HTML
        $isValidHTML = $dom->loadHTML($string, LIBXML_NOERROR | LIBXML_NOWARNING);

        // Clear any libxml errors
        \libxml_clear_errors();

        // Restore the previous libxml error handling state
        \libxml_use_internal_errors($previousLibxmlState);

        // Check if the string has recognizable HTML elements
        return $isValidHTML && $dom->getElementsByTagName('html')->length > 0;
    }
}
