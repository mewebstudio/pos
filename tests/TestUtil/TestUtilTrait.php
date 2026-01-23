<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\TestUtil;

trait TestUtilTrait
{
    /**
     * Recursively sort an array and all nested arrays by key.
     */
    private static function recursiveKsort(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::recursiveKsort($value);
            }
        }
        unset($value); // prevent reference side-effects
        ksort($array);
    }
}
